<?php

namespace CCC\Fields;

class DateField extends BaseField {
    
    protected $date_type = 'date'; // date, datetime, time, time_range, date_range
    protected $date_format = 'Y-m-d'; // Default date format
    protected $time_format = 'H:i'; // Default time format
    protected $min_date = '';
    protected $max_date = '';
    protected $show_timezone = false;
    protected $timezone = '';
    
    public function __construct($label, $name, $component_id, $required = false, $placeholder = '', $config = '') {
        parent::__construct($label, $name, $component_id, $required, $placeholder, $config);
        
        // Parse field configuration
        $field_config = is_string($config) ? json_decode($config, true) : $config;
        
        $this->date_type = isset($field_config['date_type']) ? $field_config['date_type'] : 'date';
        $this->date_format = isset($field_config['date_format']) ? $field_config['date_format'] : 'Y-m-d';
        $this->time_format = isset($field_config['time_format']) ? $field_config['time_format'] : 'H:i';
        $this->min_date = isset($field_config['min_date']) ? $field_config['min_date'] : '';
        $this->max_date = isset($field_config['max_date']) ? $field_config['max_date'] : '';
        $this->show_timezone = isset($field_config['show_timezone']) ? $field_config['show_timezone'] : false;
        $this->timezone = isset($field_config['timezone']) ? $field_config['timezone'] : wp_timezone_string();
    }
    
    public function render($post_id, $instance_id, $value = '') {
        $field_id = 'ccc-date-' . uniqid();
        $field_name = $this->name;
        $field_value = $value ?: $this->getValue();
        $field_config = json_encode([
            'date_type' => $this->date_type,
            'date_format' => $this->date_format,
            'time_format' => $this->time_format,
            'min_date' => $this->min_date,
            'max_date' => $this->max_date,
            'show_timezone' => $this->show_timezone,
            'timezone' => $this->timezone,
            'required' => $this->required,
            'placeholder' => $this->placeholder
        ]);
        
        ob_start();
        ?>
        <div class="ccc-field ccc-date-field" data-field-name="<?php echo esc_attr($field_name); ?>">
            <input type="hidden" 
                   name="<?php echo esc_attr($field_name); ?>" 
                   id="<?php echo esc_attr($field_id); ?>" 
                   value="<?php echo esc_attr($field_value); ?>" />
            <div id="<?php echo esc_attr($field_id); ?>-container" 
                 data-field-config="<?php echo esc_attr($field_config); ?>"
                 data-field-value="<?php echo esc_attr($field_value); ?>">
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (window.CCCDateField && typeof window.CCCDateField.init === 'function') {
                window.CCCDateField.init('<?php echo esc_js($field_id); ?>-container');
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    public function save() {
        return true;
    }
    
    public function sanitize($value) {
        error_log("CCC DateField: sanitize called with value: " . (is_array($value) ? 'Array' : $value));
        
        if (empty($value)) {
            return '';
        }
        
        // Handle different date types
        switch ($this->date_type) {
            case 'date':
                return $this->sanitizeDate($value);
            case 'datetime':
                return $this->sanitizeDateTime($value);
            case 'time':
                return $this->sanitizeTime($value);
            case 'time_range':
                return $this->sanitizeTimeRange($value);
            case 'date_range':
                return $this->sanitizeDateRange($value);
            default:
                return sanitize_text_field($value);
        }
    }
    
    private function sanitizeDate($value) {
        // Handle array format (from frontend datetime object)
        if (is_array($value)) {
            if (isset($value['date'])) {
                $value = $value['date'];
            } else {
                return '';
            }
        }
        
        // Try to parse the date and return in the configured format
        $date = \DateTime::createFromFormat($this->date_format, $value);
        if ($date) {
            // Return in the same format as configured
            return $date->format($this->date_format);
        }
        
        // Fallback: try to parse with various common formats and convert to configured format
        $common_formats = ['Y-m-d', 'm/d/Y', 'd/m/Y', 'Y/m/d', 'F j, Y', 'j F Y'];
        foreach ($common_formats as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date) {
                return $date->format($this->date_format);
            }
        }
        
        // Final fallback to standard parsing
        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return date($this->date_format, $timestamp);
        }
        
        return sanitize_text_field($value);
    }
    
    private function sanitizeDateTime($value) {
        // Handle array format (from frontend datetime object)
        if (is_array($value)) {
            if (isset($value['date']) && isset($value['time'])) {
                $datetime = $value['date'] . ' ' . $value['time'];
                $timestamp = strtotime($datetime);
                if ($timestamp !== false) {
                    // Return in configured date format with time
                    return date($this->date_format . ' ' . $this->time_format, $timestamp);
                }
            }
            // If array doesn't have expected structure, return empty
            return '';
        }
        
        // Handle JSON format for datetime with timezone
        if (is_string($value) && strpos($value, '{') === 0) {
            $data = json_decode($value, true);
            if (isset($data['date']) && isset($data['time'])) {
                $datetime = $data['date'] . ' ' . $data['time'];
                $timestamp = strtotime($datetime);
                if ($timestamp !== false) {
                    // Return in configured date format with time
                    return date($this->date_format . ' ' . $this->time_format, $timestamp);
                }
            }
        }
        
        // Handle simple datetime string
        if (is_string($value)) {
            $timestamp = strtotime($value);
            if ($timestamp !== false) {
                // Return in configured date format with time
                return date($this->date_format . ' ' . $this->time_format, $timestamp);
            }
        }
        
        return sanitize_text_field($value);
    }
    
    private function sanitizeTime($value) {
        // Handle time format validation
        $time = \DateTime::createFromFormat($this->time_format, $value);
        if ($time) {
            return $time->format('H:i:s');
        }
        
        // Fallback to standard parsing
        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return date('H:i:s', $timestamp);
        }
        
        return sanitize_text_field($value);
    }
    
    private function sanitizeTimeRange($value) {
        // Handle array format (from frontend time range object)
        if (is_array($value)) {
            if (isset($value['from']) && isset($value['to'])) {
                return json_encode([
                    'from' => $this->sanitizeTime($value['from']),
                    'to' => $this->sanitizeTime($value['to'])
                ]);
            }
            return '';
        }
        
        // Handle JSON format for time range
        if (is_string($value) && strpos($value, '{') === 0) {
            $data = json_decode($value, true);
            if (isset($data['from']) && isset($data['to'])) {
                return json_encode([
                    'from' => $this->sanitizeTime($data['from']),
                    'to' => $this->sanitizeTime($data['to'])
                ]);
            }
        }
        
        return sanitize_text_field($value);
    }
    
    private function sanitizeDateRange($value) {
        error_log("CCC DateField: sanitizeDateRange called with value: " . (is_array($value) ? 'Array' : $value));
        
        // Handle array format (from frontend date range object)
        if (is_array($value)) {
            if (isset($value['from']) && isset($value['to'])) {
                return json_encode([
                    'from' => $this->sanitizeDate($value['from']),
                    'to' => $this->sanitizeDate($value['to'])
                ]);
            }
        }
        
        // Handle JSON format for date range
        if (is_string($value) && strpos($value, '{') === 0) {
            $data = json_decode($value, true);
            if (isset($data['from']) && isset($data['to'])) {
                return json_encode([
                    'from' => $this->sanitizeDate($data['from']),
                    'to' => $this->sanitizeDate($data['to'])
                ]);
            }
        }
        
        return sanitize_text_field($value);
    }
    
    public function getFieldConfig() {
        return [
            'date_type' => [
                'type' => 'select',
                'label' => 'Date Type',
                'description' => 'Select the type of date/time picker to display.',
                'options' => [
                    'date' => 'Date Picker',
                    'datetime' => 'Date & Time Picker',
                    'time' => 'Time Picker',
                    'time_range' => 'Time Range (From - To)',
                    'date_range' => 'Date Range (From - To)'
                ],
                'default' => 'date'
            ],
            'date_format' => [
                'type' => 'select',
                'label' => 'Date Format',
                'description' => 'Select the display format for dates.',
                'options' => [
                    'Y-m-d' => 'YYYY-MM-DD',
                    'm/d/Y' => 'MM/DD/YYYY',
                    'd/m/Y' => 'DD/MM/YYYY',
                    'Y/m/d' => 'YYYY/MM/DD',
                    'F j, Y' => 'Month Day, Year',
                    'j F Y' => 'Day Month Year'
                ],
                'default' => 'Y-m-d'
            ],
            'time_format' => [
                'type' => 'select',
                'label' => 'Time Format',
                'description' => 'Select the display format for times.',
                'options' => [
                    'H:i' => '24 Hour (HH:MM)',
                    'g:i A' => '12 Hour (H:MM AM/PM)',
                    'H:i:s' => '24 Hour with Seconds',
                    'g:i:s A' => '12 Hour with Seconds'
                ],
                'default' => 'H:i'
            ],
            'min_date' => [
                'type' => 'text',
                'label' => 'Minimum Date',
                'description' => 'Set the minimum selectable date (YYYY-MM-DD format). Leave empty for no limit.',
                'default' => ''
            ],
            'max_date' => [
                'type' => 'text',
                'label' => 'Maximum Date',
                'description' => 'Set the maximum selectable date (YYYY-MM-DD format). Leave empty for no limit.',
                'default' => ''
            ],
            'show_timezone' => [
                'type' => 'checkbox',
                'label' => 'Show Timezone',
                'description' => 'Display timezone selection for datetime fields.',
                'default' => false
            ],
        ];
    }
    
    /**
     * Get WordPress timezone options
     */
    private function getWordPressTimezones() {
        $timezones = [];
        
        // Add Africa timezones
        $timezones['Africa/Abidjan'] = 'Africa - Abidjan';
        $timezones['Africa/Accra'] = 'Africa - Accra';
        $timezones['Africa/Addis_Ababa'] = 'Africa - Addis Ababa';
        $timezones['Africa/Algiers'] = 'Africa - Algiers';
        $timezones['Africa/Asmara'] = 'Africa - Asmara';
        $timezones['Africa/Bamako'] = 'Africa - Bamako';
        $timezones['Africa/Bangui'] = 'Africa - Bangui';
        $timezones['Africa/Banjul'] = 'Africa - Banjul';
        $timezones['Africa/Bissau'] = 'Africa - Bissau';
        $timezones['Africa/Blantyre'] = 'Africa - Blantyre';
        $timezones['Africa/Brazzaville'] = 'Africa - Brazzaville';
        $timezones['Africa/Bujumbura'] = 'Africa - Bujumbura';
        $timezones['Africa/Cairo'] = 'Africa - Cairo';
        $timezones['Africa/Casablanca'] = 'Africa - Casablanca';
        $timezones['Africa/Ceuta'] = 'Africa - Ceuta';
        $timezones['Africa/Conakry'] = 'Africa - Conakry';
        $timezones['Africa/Dakar'] = 'Africa - Dakar';
        $timezones['Africa/Dar_es_Salaam'] = 'Africa - Dar es Salaam';
        $timezones['Africa/Djibouti'] = 'Africa - Djibouti';
        $timezones['Africa/Douala'] = 'Africa - Douala';
        $timezones['Africa/El_Aaiun'] = 'Africa - El Aaiun';
        $timezones['Africa/Freetown'] = 'Africa - Freetown';
        $timezones['Africa/Gaborone'] = 'Africa - Gaborone';
        $timezones['Africa/Harare'] = 'Africa - Harare';
        $timezones['Africa/Johannesburg'] = 'Africa - Johannesburg';
        $timezones['Africa/Juba'] = 'Africa - Juba';
        $timezones['Africa/Kampala'] = 'Africa - Kampala';
        $timezones['Africa/Khartoum'] = 'Africa - Khartoum';
        $timezones['Africa/Kigali'] = 'Africa - Kigali';
        $timezones['Africa/Kinshasa'] = 'Africa - Kinshasa';
        $timezones['Africa/Lagos'] = 'Africa - Lagos';
        $timezones['Africa/Libreville'] = 'Africa - Libreville';
        $timezones['Africa/Lome'] = 'Africa - Lome';
        $timezones['Africa/Luanda'] = 'Africa - Luanda';
        $timezones['Africa/Lubumbashi'] = 'Africa - Lubumbashi';
        $timezones['Africa/Lusaka'] = 'Africa - Lusaka';
        $timezones['Africa/Malabo'] = 'Africa - Malabo';
        $timezones['Africa/Maputo'] = 'Africa - Maputo';
        $timezones['Africa/Maseru'] = 'Africa - Maseru';
        $timezones['Africa/Mbabane'] = 'Africa - Mbabane';
        $timezones['Africa/Mogadishu'] = 'Africa - Mogadishu';
        $timezones['Africa/Monrovia'] = 'Africa - Monrovia';
        $timezones['Africa/Nairobi'] = 'Africa - Nairobi';
        $timezones['Africa/Ndjamena'] = 'Africa - Ndjamena';
        $timezones['Africa/Niamey'] = 'Africa - Niamey';
        $timezones['Africa/Nouakchott'] = 'Africa - Nouakchott';
        $timezones['Africa/Ouagadougou'] = 'Africa - Ouagadougou';
        $timezones['Africa/Porto-Novo'] = 'Africa - Porto-Novo';
        $timezones['Africa/Sao_Tome'] = 'Africa - Sao Tome';
        $timezones['Africa/Tripoli'] = 'Africa - Tripoli';
        $timezones['Africa/Tunis'] = 'Africa - Tunis';
        $timezones['Africa/Windhoek'] = 'Africa - Windhoek';
        
        // Add America timezones
        $timezones['America/Adak'] = 'America - Adak';
        $timezones['America/Anchorage'] = 'America - Anchorage';
        $timezones['America/Anguilla'] = 'America - Anguilla';
        $timezones['America/Antigua'] = 'America - Antigua';
        $timezones['America/Araguaina'] = 'America - Araguaina';
        $timezones['America/Argentina/Buenos_Aires'] = 'America - Argentina - Buenos Aires';
        $timezones['America/Argentina/Catamarca'] = 'America - Argentina - Catamarca';
        $timezones['America/Argentina/Cordoba'] = 'America - Argentina - Cordoba';
        $timezones['America/Argentina/Jujuy'] = 'America - Argentina - Jujuy';
        $timezones['America/Argentina/La_Rioja'] = 'America - Argentina - La Rioja';
        $timezones['America/Argentina/Mendoza'] = 'America - Argentina - Mendoza';
        $timezones['America/Argentina/Rio_Gallegos'] = 'America - Argentina - Rio Gallegos';
        $timezones['America/Argentina/Salta'] = 'America - Argentina - Salta';
        $timezones['America/Argentina/San_Juan'] = 'America - Argentina - San Juan';
        $timezones['America/Argentina/San_Luis'] = 'America - Argentina - San Luis';
        $timezones['America/Argentina/Tucuman'] = 'America - Argentina - Tucuman';
        $timezones['America/Argentina/Ushuaia'] = 'America - Argentina - Ushuaia';
        $timezones['America/Aruba'] = 'America - Aruba';
        $timezones['America/Asuncion'] = 'America - Asuncion';
        $timezones['America/Atikokan'] = 'America - Atikokan';
        $timezones['America/Bahia'] = 'America - Bahia';
        $timezones['America/Bahia_Banderas'] = 'America - Bahia Banderas';
        $timezones['America/Barbados'] = 'America - Barbados';
        $timezones['America/Belem'] = 'America - Belem';
        $timezones['America/Belize'] = 'America - Belize';
        $timezones['America/Blanc-Sablon'] = 'America - Blanc-Sablon';
        $timezones['America/Boa_Vista'] = 'America - Boa Vista';
        $timezones['America/Bogota'] = 'America - Bogota';
        $timezones['America/Boise'] = 'America - Boise';
        $timezones['America/Cambridge_Bay'] = 'America - Cambridge Bay';
        $timezones['America/Campo_Grande'] = 'America - Campo Grande';
        $timezones['America/Cancun'] = 'America - Cancun';
        $timezones['America/Caracas'] = 'America - Caracas';
        $timezones['America/Cayenne'] = 'America - Cayenne';
        $timezones['America/Cayman'] = 'America - Cayman';
        $timezones['America/Chicago'] = 'America - Chicago';
        $timezones['America/Chihuahua'] = 'America - Chihuahua';
        $timezones['America/Ciudad_Juarez'] = 'America - Ciudad Juarez';
        $timezones['America/Costa_Rica'] = 'America - Costa Rica';
        $timezones['America/Creston'] = 'America - Creston';
        $timezones['America/Cuiaba'] = 'America - Cuiaba';
        $timezones['America/Curacao'] = 'America - Curacao';
        $timezones['America/Danmarkshavn'] = 'America - Danmarkshavn';
        $timezones['America/Dawson'] = 'America - Dawson';
        $timezones['America/Dawson_Creek'] = 'America - Dawson Creek';
        $timezones['America/Denver'] = 'America - Denver';
        $timezones['America/Detroit'] = 'America - Detroit';
        $timezones['America/Dominica'] = 'America - Dominica';
        $timezones['America/Edmonton'] = 'America - Edmonton';
        $timezones['America/Eirunepe'] = 'America - Eirunepe';
        $timezones['America/El_Salvador'] = 'America - El Salvador';
        $timezones['America/Fortaleza'] = 'America - Fortaleza';
        $timezones['America/Fort_Nelson'] = 'America - Fort Nelson';
        $timezones['America/Glace_Bay'] = 'America - Glace Bay';
        $timezones['America/Goose_Bay'] = 'America - Goose Bay';
        $timezones['America/Grand_Turk'] = 'America - Grand Turk';
        $timezones['America/Grenada'] = 'America - Grenada';
        $timezones['America/Guadeloupe'] = 'America - Guadeloupe';
        $timezones['America/Guatemala'] = 'America - Guatemala';
        $timezones['America/Guayaquil'] = 'America - Guayaquil';
        $timezones['America/Guyana'] = 'America - Guyana';
        $timezones['America/Halifax'] = 'America - Halifax';
        $timezones['America/Havana'] = 'America - Havana';
        $timezones['America/Hermosillo'] = 'America - Hermosillo';
        $timezones['America/Indiana/Indianapolis'] = 'America - Indiana - Indianapolis';
        $timezones['America/Indiana/Knox'] = 'America - Indiana - Knox';
        $timezones['America/Indiana/Marengo'] = 'America - Indiana - Marengo';
        $timezones['America/Indiana/Petersburg'] = 'America - Indiana - Petersburg';
        $timezones['America/Indiana/Tell_City'] = 'America - Indiana - Tell City';
        $timezones['America/Indiana/Vevay'] = 'America - Indiana - Vevay';
        $timezones['America/Indiana/Vincennes'] = 'America - Indiana - Vincennes';
        $timezones['America/Indiana/Winamac'] = 'America - Indiana - Winamac';
        $timezones['America/Inuvik'] = 'America - Inuvik';
        $timezones['America/Iqaluit'] = 'America - Iqaluit';
        $timezones['America/Jamaica'] = 'America - Jamaica';
        $timezones['America/Juneau'] = 'America - Juneau';
        $timezones['America/Kentucky/Louisville'] = 'America - Kentucky - Louisville';
        $timezones['America/Kentucky/Monticello'] = 'America - Kentucky - Monticello';
        $timezones['America/Kralendijk'] = 'America - Kralendijk';
        $timezones['America/La_Paz'] = 'America - La Paz';
        $timezones['America/Lima'] = 'America - Lima';
        $timezones['America/Los_Angeles'] = 'America - Los Angeles';
        $timezones['America/Lower_Princes'] = 'America - Lower Princes';
        $timezones['America/Maceio'] = 'America - Maceio';
        $timezones['America/Managua'] = 'America - Managua';
        $timezones['America/Manaus'] = 'America - Manaus';
        $timezones['America/Marigot'] = 'America - Marigot';
        $timezones['America/Martinique'] = 'America - Martinique';
        $timezones['America/Matamoros'] = 'America - Matamoros';
        $timezones['America/Mazatlan'] = 'America - Mazatlan';
        $timezones['America/Menominee'] = 'America - Menominee';
        $timezones['America/Merida'] = 'America - Merida';
        $timezones['America/Metlakatla'] = 'America - Metlakatla';
        $timezones['America/Mexico_City'] = 'America - Mexico City';
        $timezones['America/Miquelon'] = 'America - Miquelon';
        $timezones['America/Moncton'] = 'America - Moncton';
        $timezones['America/Monterrey'] = 'America - Monterrey';
        $timezones['America/Montevideo'] = 'America - Montevideo';
        $timezones['America/Montserrat'] = 'America - Montserrat';
        $timezones['America/Nassau'] = 'America - Nassau';
        $timezones['America/New_York'] = 'America - New York';
        $timezones['America/Nome'] = 'America - Nome';
        $timezones['America/Noronha'] = 'America - Noronha';
        $timezones['America/North_Dakota/Beulah'] = 'America - North Dakota - Beulah';
        $timezones['America/North_Dakota/Center'] = 'America - North Dakota - Center';
        $timezones['America/North_Dakota/New_Salem'] = 'America - North Dakota - New Salem';
        $timezones['America/Nuuk'] = 'America - Nuuk';
        $timezones['America/Ojinaga'] = 'America - Ojinaga';
        $timezones['America/Panama'] = 'America - Panama';
        $timezones['America/Paramaribo'] = 'America - Paramaribo';
        $timezones['America/Phoenix'] = 'America - Phoenix';
        $timezones['America/Port-au-Prince'] = 'America - Port-au-Prince';
        $timezones['America/Port_of_Spain'] = 'America - Port of Spain';
        $timezones['America/Porto_Velho'] = 'America - Porto Velho';
        $timezones['America/Puerto_Rico'] = 'America - Puerto Rico';
        $timezones['America/Punta_Arenas'] = 'America - Punta Arenas';
        $timezones['America/Rankin_Inlet'] = 'America - Rankin Inlet';
        $timezones['America/Recife'] = 'America - Recife';
        $timezones['America/Regina'] = 'America - Regina';
        $timezones['America/Resolute'] = 'America - Resolute';
        $timezones['America/Rio_Branco'] = 'America - Rio Branco';
        $timezones['America/Santarem'] = 'America - Santarem';
        $timezones['America/Santiago'] = 'America - Santiago';
        $timezones['America/Santo_Domingo'] = 'America - Santo Domingo';
        $timezones['America/Sao_Paulo'] = 'America - Sao Paulo';
        $timezones['America/Scoresbysund'] = 'America - Scoresbysund';
        $timezones['America/Sitka'] = 'America - Sitka';
        $timezones['America/St_Barthelemy'] = 'America - St Barthelemy';
        $timezones['America/St_Johns'] = 'America - St Johns';
        $timezones['America/St_Kitts'] = 'America - St Kitts';
        $timezones['America/St_Lucia'] = 'America - St Lucia';
        $timezones['America/St_Thomas'] = 'America - St Thomas';
        $timezones['America/St_Vincent'] = 'America - St Vincent';
        $timezones['America/Swift_Current'] = 'America - Swift Current';
        $timezones['America/Tegucigalpa'] = 'America - Tegucigalpa';
        $timezones['America/Thule'] = 'America - Thule';
        $timezones['America/Tijuana'] = 'America - Tijuana';
        $timezones['America/Toronto'] = 'America - Toronto';
        $timezones['America/Tortola'] = 'America - Tortola';
        $timezones['America/Vancouver'] = 'America - Vancouver';
        $timezones['America/Whitehorse'] = 'America - Whitehorse';
        $timezones['America/Winnipeg'] = 'America - Winnipeg';
        $timezones['America/Yakutat'] = 'America - Yakutat';
        
        // Add Antarctica timezones
        $timezones['Antarctica/Casey'] = 'Antarctica - Casey';
        $timezones['Antarctica/Davis'] = 'Antarctica - Davis';
        $timezones['Antarctica/DumontDUrville'] = 'Antarctica - DumontDUrville';
        $timezones['Antarctica/Macquarie'] = 'Antarctica - Macquarie';
        $timezones['Antarctica/Mawson'] = 'Antarctica - Mawson';
        $timezones['Antarctica/McMurdo'] = 'Antarctica - McMurdo';
        $timezones['Antarctica/Palmer'] = 'Antarctica - Palmer';
        $timezones['Antarctica/Rothera'] = 'Antarctica - Rothera';
        $timezones['Antarctica/Syowa'] = 'Antarctica - Syowa';
        $timezones['Antarctica/Troll'] = 'Antarctica - Troll';
        $timezones['Antarctica/Vostok'] = 'Antarctica - Vostok';
        
        // Add Arctic timezones
        $timezones['Arctic/Longyearbyen'] = 'Arctic - Longyearbyen';
        
        // Add Asia timezones
        $timezones['Asia/Aden'] = 'Asia - Aden';
        $timezones['Asia/Almaty'] = 'Asia - Almaty';
        $timezones['Asia/Amman'] = 'Asia - Amman';
        $timezones['Asia/Anadyr'] = 'Asia - Anadyr';
        $timezones['Asia/Aqtau'] = 'Asia - Aqtau';
        $timezones['Asia/Aqtobe'] = 'Asia - Aqtobe';
        $timezones['Asia/Ashgabat'] = 'Asia - Ashgabat';
        $timezones['Asia/Atyrau'] = 'Asia - Atyrau';
        $timezones['Asia/Baghdad'] = 'Asia - Baghdad';
        $timezones['Asia/Bahrain'] = 'Asia - Bahrain';
        $timezones['Asia/Baku'] = 'Asia - Baku';
        $timezones['Asia/Bangkok'] = 'Asia - Bangkok';
        $timezones['Asia/Barnaul'] = 'Asia - Barnaul';
        $timezones['Asia/Beirut'] = 'Asia - Beirut';
        $timezones['Asia/Bishkek'] = 'Asia - Bishkek';
        $timezones['Asia/Brunei'] = 'Asia - Brunei';
        $timezones['Asia/Chita'] = 'Asia - Chita';
        $timezones['Asia/Choibalsan'] = 'Asia - Choibalsan';
        $timezones['Asia/Colombo'] = 'Asia - Colombo';
        $timezones['Asia/Damascus'] = 'Asia - Damascus';
        $timezones['Asia/Dhaka'] = 'Asia - Dhaka';
        $timezones['Asia/Dili'] = 'Asia - Dili';
        $timezones['Asia/Dubai'] = 'Asia - Dubai';
        $timezones['Asia/Dushanbe'] = 'Asia - Dushanbe';
        $timezones['Asia/Famagusta'] = 'Asia - Famagusta';
        $timezones['Asia/Gaza'] = 'Asia - Gaza';
        $timezones['Asia/Hebron'] = 'Asia - Hebron';
        $timezones['Asia/Ho_Chi_Minh'] = 'Asia - Ho Chi Minh';
        $timezones['Asia/Hong_Kong'] = 'Asia - Hong Kong';
        $timezones['Asia/Hovd'] = 'Asia - Hovd';
        $timezones['Asia/Irkutsk'] = 'Asia - Irkutsk';
        $timezones['Asia/Jakarta'] = 'Asia - Jakarta';
        $timezones['Asia/Jayapura'] = 'Asia - Jayapura';
        $timezones['Asia/Jerusalem'] = 'Asia - Jerusalem';
        $timezones['Asia/Kabul'] = 'Asia - Kabul';
        $timezones['Asia/Kamchatka'] = 'Asia - Kamchatka';
        $timezones['Asia/Karachi'] = 'Asia - Karachi';
        $timezones['Asia/Kathmandu'] = 'Asia - Kathmandu';
        $timezones['Asia/Khandyga'] = 'Asia - Khandyga';
        $timezones['Asia/Kolkata'] = 'Asia - Kolkata';
        $timezones['Asia/Krasnoyarsk'] = 'Asia - Krasnoyarsk';
        $timezones['Asia/Kuala_Lumpur'] = 'Asia - Kuala Lumpur';
        $timezones['Asia/Kuching'] = 'Asia - Kuching';
        $timezones['Asia/Kuwait'] = 'Asia - Kuwait';
        $timezones['Asia/Macau'] = 'Asia - Macau';
        $timezones['Asia/Magadan'] = 'Asia - Magadan';
        $timezones['Asia/Makassar'] = 'Asia - Makassar';
        $timezones['Asia/Manila'] = 'Asia - Manila';
        $timezones['Asia/Muscat'] = 'Asia - Muscat';
        $timezones['Asia/Nicosia'] = 'Asia - Nicosia';
        $timezones['Asia/Novokuznetsk'] = 'Asia - Novokuznetsk';
        $timezones['Asia/Novosibirsk'] = 'Asia - Novosibirsk';
        $timezones['Asia/Omsk'] = 'Asia - Omsk';
        $timezones['Asia/Oral'] = 'Asia - Oral';
        $timezones['Asia/Phnom_Penh'] = 'Asia - Phnom Penh';
        $timezones['Asia/Pontianak'] = 'Asia - Pontianak';
        $timezones['Asia/Pyongyang'] = 'Asia - Pyongyang';
        $timezones['Asia/Qatar'] = 'Asia - Qatar';
        $timezones['Asia/Qostanay'] = 'Asia - Qostanay';
        $timezones['Asia/Qyzylorda'] = 'Asia - Qyzylorda';
        $timezones['Asia/Riyadh'] = 'Asia - Riyadh';
        $timezones['Asia/Sakhalin'] = 'Asia - Sakhalin';
        $timezones['Asia/Samarkand'] = 'Asia - Samarkand';
        $timezones['Asia/Seoul'] = 'Asia - Seoul';
        $timezones['Asia/Shanghai'] = 'Asia - Shanghai';
        $timezones['Asia/Singapore'] = 'Asia - Singapore';
        $timezones['Asia/Srednekolymsk'] = 'Asia - Srednekolymsk';
        $timezones['Asia/Taipei'] = 'Asia - Taipei';
        $timezones['Asia/Tashkent'] = 'Asia - Tashkent';
        $timezones['Asia/Tbilisi'] = 'Asia - Tbilisi';
        $timezones['Asia/Tehran'] = 'Asia - Tehran';
        $timezones['Asia/Thimphu'] = 'Asia - Thimphu';
        $timezones['Asia/Tokyo'] = 'Asia - Tokyo';
        $timezones['Asia/Tomsk'] = 'Asia - Tomsk';
        $timezones['Asia/Ulaanbaatar'] = 'Asia - Ulaanbaatar';
        $timezones['Asia/Urumqi'] = 'Asia - Urumqi';
        $timezones['Asia/Ust-Nera'] = 'Asia - Ust-Nera';
        $timezones['Asia/Vientiane'] = 'Asia - Vientiane';
        $timezones['Asia/Vladivostok'] = 'Asia - Vladivostok';
        $timezones['Asia/Yakutsk'] = 'Asia - Yakutsk';
        $timezones['Asia/Yangon'] = 'Asia - Yangon';
        $timezones['Asia/Yekaterinburg'] = 'Asia - Yekaterinburg';
        $timezones['Asia/Yerevan'] = 'Asia - Yerevan';
        
        // Add Atlantic timezones
        $timezones['Atlantic/Azores'] = 'Atlantic - Azores';
        $timezones['Atlantic/Bermuda'] = 'Atlantic - Bermuda';
        $timezones['Atlantic/Canary'] = 'Atlantic - Canary';
        $timezones['Atlantic/Cape_Verde'] = 'Atlantic - Cape Verde';
        $timezones['Atlantic/Faroe'] = 'Atlantic - Faroe';
        $timezones['Atlantic/Madeira'] = 'Atlantic - Madeira';
        $timezones['Atlantic/Reykjavik'] = 'Atlantic - Reykjavik';
        $timezones['Atlantic/South_Georgia'] = 'Atlantic - South Georgia';
        $timezones['Atlantic/Stanley'] = 'Atlantic - Stanley';
        $timezones['Atlantic/St_Helena'] = 'Atlantic - St Helena';
        
        // Add Australia timezones
        $timezones['Australia/Adelaide'] = 'Australia - Adelaide';
        $timezones['Australia/Brisbane'] = 'Australia - Brisbane';
        $timezones['Australia/Broken_Hill'] = 'Australia - Broken Hill';
        $timezones['Australia/Darwin'] = 'Australia - Darwin';
        $timezones['Australia/Eucla'] = 'Australia - Eucla';
        $timezones['Australia/Hobart'] = 'Australia - Hobart';
        $timezones['Australia/Lindeman'] = 'Australia - Lindeman';
        $timezones['Australia/Lord_Howe'] = 'Australia - Lord Howe';
        $timezones['Australia/Melbourne'] = 'Australia - Melbourne';
        $timezones['Australia/Perth'] = 'Australia - Perth';
        $timezones['Australia/Sydney'] = 'Australia - Sydney';
        
        // Add Europe timezones
        $timezones['Europe/Amsterdam'] = 'Europe - Amsterdam';
        $timezones['Europe/Andorra'] = 'Europe - Andorra';
        $timezones['Europe/Astrakhan'] = 'Europe - Astrakhan';
        $timezones['Europe/Athens'] = 'Europe - Athens';
        $timezones['Europe/Belgrade'] = 'Europe - Belgrade';
        $timezones['Europe/Berlin'] = 'Europe - Berlin';
        $timezones['Europe/Bratislava'] = 'Europe - Bratislava';
        $timezones['Europe/Brussels'] = 'Europe - Brussels';
        $timezones['Europe/Bucharest'] = 'Europe - Bucharest';
        $timezones['Europe/Budapest'] = 'Europe - Budapest';
        $timezones['Europe/Busingen'] = 'Europe - Busingen';
        $timezones['Europe/Chisinau'] = 'Europe - Chisinau';
        $timezones['Europe/Copenhagen'] = 'Europe - Copenhagen';
        $timezones['Europe/Dublin'] = 'Europe - Dublin';
        $timezones['Europe/Gibraltar'] = 'Europe - Gibraltar';
        $timezones['Europe/Guernsey'] = 'Europe - Guernsey';
        $timezones['Europe/Helsinki'] = 'Europe - Helsinki';
        $timezones['Europe/Isle_of_Man'] = 'Europe - Isle of Man';
        $timezones['Europe/Istanbul'] = 'Europe - Istanbul';
        $timezones['Europe/Jersey'] = 'Europe - Jersey';
        $timezones['Europe/Kaliningrad'] = 'Europe - Kaliningrad';
        $timezones['Europe/Kirov'] = 'Europe - Kirov';
        $timezones['Europe/Kyiv'] = 'Europe - Kyiv';
        $timezones['Europe/Lisbon'] = 'Europe - Lisbon';
        $timezones['Europe/Ljubljana'] = 'Europe - Ljubljana';
        $timezones['Europe/London'] = 'Europe - London';
        $timezones['Europe/Luxembourg'] = 'Europe - Luxembourg';
        $timezones['Europe/Madrid'] = 'Europe - Madrid';
        $timezones['Europe/Malta'] = 'Europe - Malta';
        $timezones['Europe/Mariehamn'] = 'Europe - Mariehamn';
        $timezones['Europe/Minsk'] = 'Europe - Minsk';
        $timezones['Europe/Monaco'] = 'Europe - Monaco';
        $timezones['Europe/Moscow'] = 'Europe - Moscow';
        $timezones['Europe/Oslo'] = 'Europe - Oslo';
        $timezones['Europe/Paris'] = 'Europe - Paris';
        $timezones['Europe/Podgorica'] = 'Europe - Podgorica';
        $timezones['Europe/Prague'] = 'Europe - Prague';
        $timezones['Europe/Riga'] = 'Europe - Riga';
        $timezones['Europe/Rome'] = 'Europe - Rome';
        $timezones['Europe/Samara'] = 'Europe - Samara';
        $timezones['Europe/San_Marino'] = 'Europe - San Marino';
        $timezones['Europe/Sarajevo'] = 'Europe - Sarajevo';
        $timezones['Europe/Saratov'] = 'Europe - Saratov';
        $timezones['Europe/Simferopol'] = 'Europe - Simferopol';
        $timezones['Europe/Skopje'] = 'Europe - Skopje';
        $timezones['Europe/Sofia'] = 'Europe - Sofia';
        $timezones['Europe/Stockholm'] = 'Europe - Stockholm';
        $timezones['Europe/Tallinn'] = 'Europe - Tallinn';
        $timezones['Europe/Tirane'] = 'Europe - Tirane';
        $timezones['Europe/Ulyanovsk'] = 'Europe - Ulyanovsk';
        $timezones['Europe/Vaduz'] = 'Europe - Vaduz';
        $timezones['Europe/Vatican'] = 'Europe - Vatican';
        $timezones['Europe/Vienna'] = 'Europe - Vienna';
        $timezones['Europe/Vilnius'] = 'Europe - Vilnius';
        $timezones['Europe/Volgograd'] = 'Europe - Volgograd';
        $timezones['Europe/Warsaw'] = 'Europe - Warsaw';
        $timezones['Europe/Zagreb'] = 'Europe - Zagreb';
        $timezones['Europe/Zurich'] = 'Europe - Zurich';
        
        // Add Indian timezones
        $timezones['Indian/Antananarivo'] = 'Indian - Antananarivo';
        $timezones['Indian/Chagos'] = 'Indian - Chagos';
        $timezones['Indian/Christmas'] = 'Indian - Christmas';
        $timezones['Indian/Cocos'] = 'Indian - Cocos';
        $timezones['Indian/Comoro'] = 'Indian - Comoro';
        $timezones['Indian/Kerguelen'] = 'Indian - Kerguelen';
        $timezones['Indian/Mahe'] = 'Indian - Mahe';
        $timezones['Indian/Maldives'] = 'Indian - Maldives';
        $timezones['Indian/Mauritius'] = 'Indian - Mauritius';
        $timezones['Indian/Mayotte'] = 'Indian - Mayotte';
        $timezones['Indian/Reunion'] = 'Indian - Reunion';
        
        // Add Pacific timezones
        $timezones['Pacific/Apia'] = 'Pacific - Apia';
        $timezones['Pacific/Auckland'] = 'Pacific - Auckland';
        $timezones['Pacific/Bougainville'] = 'Pacific - Bougainville';
        $timezones['Pacific/Chatham'] = 'Pacific - Chatham';
        $timezones['Pacific/Chuuk'] = 'Pacific - Chuuk';
        $timezones['Pacific/Easter'] = 'Pacific - Easter';
        $timezones['Pacific/Efate'] = 'Pacific - Efate';
        $timezones['Pacific/Fakaofo'] = 'Pacific - Fakaofo';
        $timezones['Pacific/Fiji'] = 'Pacific - Fiji';
        $timezones['Pacific/Funafuti'] = 'Pacific - Funafuti';
        $timezones['Pacific/Galapagos'] = 'Pacific - Galapagos';
        $timezones['Pacific/Gambier'] = 'Pacific - Gambier';
        $timezones['Pacific/Guadalcanal'] = 'Pacific - Guadalcanal';
        $timezones['Pacific/Guam'] = 'Pacific - Guam';
        $timezones['Pacific/Honolulu'] = 'Pacific - Honolulu';
        $timezones['Pacific/Kanton'] = 'Pacific - Kanton';
        $timezones['Pacific/Kiritimati'] = 'Pacific - Kiritimati';
        $timezones['Pacific/Kosrae'] = 'Pacific - Kosrae';
        $timezones['Pacific/Kwajalein'] = 'Pacific - Kwajalein';
        $timezones['Pacific/Majuro'] = 'Pacific - Majuro';
        $timezones['Pacific/Marquesas'] = 'Pacific - Marquesas';
        $timezones['Pacific/Midway'] = 'Pacific - Midway';
        $timezones['Pacific/Nauru'] = 'Pacific - Nauru';
        $timezones['Pacific/Niue'] = 'Pacific - Niue';
        $timezones['Pacific/Norfolk'] = 'Pacific - Norfolk';
        $timezones['Pacific/Noumea'] = 'Pacific - Noumea';
        $timezones['Pacific/Pago_Pago'] = 'Pacific - Pago Pago';
        $timezones['Pacific/Palau'] = 'Pacific - Palau';
        $timezones['Pacific/Pitcairn'] = 'Pacific - Pitcairn';
        $timezones['Pacific/Pohnpei'] = 'Pacific - Pohnpei';
        $timezones['Pacific/Port_Moresby'] = 'Pacific - Port Moresby';
        $timezones['Pacific/Rarotonga'] = 'Pacific - Rarotonga';
        $timezones['Pacific/Saipan'] = 'Pacific - Saipan';
        $timezones['Pacific/Tahiti'] = 'Pacific - Tahiti';
        $timezones['Pacific/Tarawa'] = 'Pacific - Tarawa';
        $timezones['Pacific/Tongatapu'] = 'Pacific - Tongatapu';
        $timezones['Pacific/Wake'] = 'Pacific - Wake';
        $timezones['Pacific/Wallis'] = 'Pacific - Wallis';
        
        // Add UTC
        $timezones['UTC'] = 'UTC';
        
        // Add Manual Offsets
        $timezones['UTC-12'] = 'UTC-12';
        $timezones['UTC-11.5'] = 'UTC-11:30';
        $timezones['UTC-11'] = 'UTC-11';
        $timezones['UTC-10.5'] = 'UTC-10:30';
        $timezones['UTC-10'] = 'UTC-10';
        $timezones['UTC-9.5'] = 'UTC-9:30';
        $timezones['UTC-9'] = 'UTC-9';
        $timezones['UTC-8.5'] = 'UTC-8:30';
        $timezones['UTC-8'] = 'UTC-8';
        $timezones['UTC-7.5'] = 'UTC-7:30';
        $timezones['UTC-7'] = 'UTC-7';
        $timezones['UTC-6.5'] = 'UTC-6:30';
        $timezones['UTC-6'] = 'UTC-6';
        $timezones['UTC-5.5'] = 'UTC-5:30';
        $timezones['UTC-5'] = 'UTC-5';
        $timezones['UTC-4.5'] = 'UTC-4:30';
        $timezones['UTC-4'] = 'UTC-4';
        $timezones['UTC-3.5'] = 'UTC-3:30';
        $timezones['UTC-3'] = 'UTC-3';
        $timezones['UTC-2.5'] = 'UTC-2:30';
        $timezones['UTC-2'] = 'UTC-2';
        $timezones['UTC-1.5'] = 'UTC-1:30';
        $timezones['UTC-1'] = 'UTC-1';
        $timezones['UTC-0.5'] = 'UTC-0:30';
        $timezones['UTC+0'] = 'UTC+0';
        $timezones['UTC+0.5'] = 'UTC+0:30';
        $timezones['UTC+1'] = 'UTC+1';
        $timezones['UTC+1.5'] = 'UTC+1:30';
        $timezones['UTC+2'] = 'UTC+2';
        $timezones['UTC+2.5'] = 'UTC+2:30';
        $timezones['UTC+3'] = 'UTC+3';
        $timezones['UTC+3.5'] = 'UTC+3:30';
        $timezones['UTC+4'] = 'UTC+4';
        $timezones['UTC+4.5'] = 'UTC+4:30';
        $timezones['UTC+5'] = 'UTC+5';
        $timezones['UTC+5.5'] = 'UTC+5:30';
        $timezones['UTC+5.75'] = 'UTC+5:45';
        $timezones['UTC+6'] = 'UTC+6';
        $timezones['UTC+6.5'] = 'UTC+6:30';
        $timezones['UTC+7'] = 'UTC+7';
        $timezones['UTC+7.5'] = 'UTC+7:30';
        $timezones['UTC+8'] = 'UTC+8';
        $timezones['UTC+8.5'] = 'UTC+8:30';
        $timezones['UTC+8.75'] = 'UTC+8:45';
        $timezones['UTC+9'] = 'UTC+9';
        $timezones['UTC+9.5'] = 'UTC+9:30';
        $timezones['UTC+10'] = 'UTC+10';
        $timezones['UTC+10.5'] = 'UTC+10:30';
        $timezones['UTC+11'] = 'UTC+11';
        $timezones['UTC+11.5'] = 'UTC+11:30';
        $timezones['UTC+12'] = 'UTC+12';
        $timezones['UTC+12.75'] = 'UTC+12:45';
        $timezones['UTC+13'] = 'UTC+13';
        $timezones['UTC+13.75'] = 'UTC+13:45';
        $timezones['UTC+14'] = 'UTC+14';
        
        return $timezones;
    }
    
    
    public function getValue($post_id = null, $instance_id = null) {
        $post_id = $post_id ?: get_the_ID();
        $instance_id = $instance_id ?: '';
        
        $field_value = \CCC\Models\FieldValue::getValue($this->id, $post_id, $instance_id);
        
        if (empty($field_value)) {
            return '';
        }
        
        // Format the value based on the field type
        return $this->formatValue($field_value);
    }
    
    private function formatValue($value) {
        switch ($this->date_type) {
            case 'date':
                return $this->formatDate($value);
            case 'datetime':
                return $this->formatDateTime($value);
            case 'time':
                return $this->formatTime($value);
            case 'time_range':
                return $this->formatTimeRange($value);
            default:
                return $value;
        }
    }
    
    private function formatDate($value) {
        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return date($this->date_format, $timestamp);
        }
        return $value;
    }
    
    private function formatDateTime($value) {
        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return [
                'date' => date('Y-m-d', $timestamp),
                'time' => date($this->time_format, $timestamp),
                'timestamp' => $timestamp,
                'formatted' => date($this->date_format . ' ' . $this->time_format, $timestamp)
            ];
        }
        return $value;
    }
    
    private function formatTime($value) {
        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return date($this->time_format, $timestamp);
        }
        return $value;
    }
    
    private function formatTimeRange($value) {
        if (is_string($value) && strpos($value, '{') === 0) {
            $data = json_decode($value, true);
            if (isset($data['from']) && isset($data['to'])) {
                return [
                    'from' => $this->formatTime($data['from']),
                    'to' => $this->formatTime($data['to']),
                    'duration' => $this->calculateDuration($data['from'], $data['to'])
                ];
            }
        }
        return $value;
    }
    
    private function calculateDuration($from, $to) {
        $from_time = strtotime($from);
        $to_time = strtotime($to);
        
        if ($from_time !== false && $to_time !== false) {
            $diff = $to_time - $from_time;
            $hours = floor($diff / 3600);
            $minutes = floor(($diff % 3600) / 60);
            return sprintf('%02d:%02d', $hours, $minutes);
        }
        
        return '';
    }
    
    public function validate($value) {
        $errors = [];
        
        if ($this->required && empty($value)) {
            $errors[] = sprintf('Field "%s" is required.', $this->label);
        }
        
        if (!empty($value)) {
            // Validate based on date type
            switch ($this->date_type) {
                case 'date':
                    if (!$this->isValidDate($value)) {
                        $errors[] = sprintf('Field "%s" must contain a valid date.', $this->label);
                    }
                    break;
                case 'datetime':
                    if (!$this->isValidDateTime($value)) {
                        $errors[] = sprintf('Field "%s" must contain a valid date and time.', $this->label);
                    }
                    break;
                case 'time':
                    if (!$this->isValidTime($value)) {
                        $errors[] = sprintf('Field "%s" must contain a valid time.', $this->label);
                    }
                    break;
                case 'time_range':
                    if (!$this->isValidTimeRange($value)) {
                        $errors[] = sprintf('Field "%s" must contain a valid time range.', $this->label);
                    }
                    break;
            }
        }
        
        return empty($errors) ? true : $errors;
    }
    
    private function isValidDate($value) {
        $date = \DateTime::createFromFormat($this->date_format, $value);
        return $date && $date->format($this->date_format) === $value;
    }
    
    private function isValidDateTime($value) {
        if (is_string($value) && strpos($value, '{') === 0) {
            $data = json_decode($value, true);
            return isset($data['date']) && isset($data['time']);
        }
        return strtotime($value) !== false;
    }
    
    private function isValidTime($value) {
        $time = \DateTime::createFromFormat($this->time_format, $value);
        return $time && $time->format($this->time_format) === $value;
    }
    
    private function isValidTimeRange($value) {
        if (is_string($value) && strpos($value, '{') === 0) {
            $data = json_decode($value, true);
            return isset($data['from']) && isset($data['to']) && 
                   $this->isValidTime($data['from']) && $this->isValidTime($data['to']);
        }
        return false;
    }
    
    /**
     * Get date field value (date only)
     * 
     * Usage examples:
     * $date_field = new DateField('Event Date', 'event_date', 'component_1');
     * $date_value = $date_field->get_ccc_field_date($post_id); // Returns formatted date string
     * 
     * Available methods:
     * - get_ccc_field_date(): For date fields only
     * - get_ccc_field_datetime(): For datetime fields only  
     * - get_ccc_field_time(): For time fields only
     * - get_ccc_field_time_range(): For time range fields only
     */
    public function get_ccc_field_date($post_id = null, $instance_id = null) {
        if ($this->date_type !== 'date') {
            return null;
        }
        
        $value = $this->getValue($post_id, $instance_id);
        if (empty($value)) {
            return null;
        }
        
        // Handle array format from frontend
        if (is_array($value)) {
            $value = isset($value['date']) ? $value['date'] : '';
        }
        
        // Convert to specified date format
        if (!empty($value)) {
            // Try to parse with the configured format first
            $date = \DateTime::createFromFormat($this->date_format, $value);
            if ($date) {
                return $date->format($this->date_format);
            }
            
            // Fallback: try common formats for backward compatibility
            $common_formats = ['Y-m-d', 'm/d/Y', 'd/m/Y', 'Y/m/d', 'F j, Y', 'j F Y'];
            foreach ($common_formats as $format) {
                $date = \DateTime::createFromFormat($format, $value);
                if ($date) {
                    return $date->format($this->date_format);
                }
            }
        }
        
        return $value;
    }
    
    /**
     * Get datetime field value (date + time)
     */
    public function get_ccc_field_datetime($post_id = null, $instance_id = null) {
        if ($this->date_type !== 'datetime') {
            return null;
        }
        
        $value = $this->getValue($post_id, $instance_id);
        if (empty($value)) {
            return null;
        }
        
        // Handle array format from frontend
        if (is_array($value)) {
            if (isset($value['date']) && isset($value['time'])) {
                $datetime = $value['date'] . ' ' . $value['time'];
            } else {
                return null;
            }
        } else {
            $datetime = $value;
        }
        
        // Convert to specified datetime format
        if (!empty($datetime)) {
            // Try to parse with the configured format first
            $date = \DateTime::createFromFormat($this->date_format . ' ' . $this->time_format, $datetime);
            if ($date) {
                return $date->format($this->date_format . ' ' . $this->time_format);
            }
            
            // Fallback: try common formats for backward compatibility
            $common_formats = [
                'Y-m-d H:i:s',
                'Y-m-d H:i',
                'm/d/Y H:i:s',
                'd/m/Y H:i:s',
                'Y/m/d H:i:s',
                'F j, Y H:i:s',
                'j F Y H:i:s'
            ];
            foreach ($common_formats as $format) {
                $date = \DateTime::createFromFormat($format, $datetime);
                if ($date) {
                    return $date->format($this->date_format . ' ' . $this->time_format);
                }
            }
        }
        
        return $datetime;
    }
    
    /**
     * Get time field value (time only)
     */
    public function get_ccc_field_time($post_id = null, $instance_id = null) {
        if ($this->date_type !== 'time') {
            return null;
        }
        
        $value = $this->getValue($post_id, $instance_id);
        if (empty($value)) {
            return null;
        }
        
        // Handle array format from frontend
        if (is_array($value)) {
            $value = isset($value['time']) ? $value['time'] : '';
        }
        
        // Convert to specified time format
        if (!empty($value)) {
            $time = \DateTime::createFromFormat('H:i:s', $value);
            if ($time) {
                return $time->format($this->time_format);
            }
        }
        
        return $value;
    }
    
    /**
     * Get time range field value (from time to time)
     */
    public function get_ccc_field_time_range($post_id = null, $instance_id = null) {
        if ($this->date_type !== 'time_range') {
            return null;
        }
        
        $value = $this->getValue($post_id, $instance_id);
        if (empty($value)) {
            return null;
        }
        
        // Handle array format from frontend
        if (is_array($value)) {
            $from = isset($value['from']) ? $value['from'] : '';
            $to = isset($value['to']) ? $value['to'] : '';
        } else {
            // Handle JSON string format
            $data = json_decode($value, true);
            $from = isset($data['from']) ? $data['from'] : '';
            $to = isset($data['to']) ? $data['to'] : '';
        }
        
        // Convert to specified time format
        if (!empty($from) && !empty($to)) {
            $from_time = \DateTime::createFromFormat('H:i:s', $from);
            $to_time = \DateTime::createFromFormat('H:i:s', $to);
            
            if ($from_time && $to_time) {
                return [
                    'from' => $from_time->format($this->time_format),
                    'to' => $to_time->format($this->time_format),
                    'duration' => $this->calculateDuration($from_time, $to_time)
                ];
            }
        }
        
        return [
            'from' => $from,
            'to' => $to,
            'duration' => ''
        ];
    }
    
    /**
     * Get date range field value (from date + to date)
     */
    public function get_ccc_field_date_range($post_id = null, $instance_id = null) {
        if ($this->date_type !== 'date_range') {
            return null;
        }
        
        $value = $this->getValue($post_id, $instance_id);
        if (empty($value)) {
            return null;
        }
        
        // Handle array format from frontend
        if (is_array($value)) {
            $from = isset($value['from']) ? $value['from'] : '';
            $to = isset($value['to']) ? $value['to'] : '';
        } else {
            // Handle JSON string format
            $data = json_decode($value, true);
            $from = isset($data['from']) ? $data['from'] : '';
            $to = isset($data['to']) ? $data['to'] : '';
        }
        
        // Convert to specified date format
        if (!empty($from) && !empty($to)) {
            // Try to parse with the configured format first
            $from_date = \DateTime::createFromFormat($this->date_format, $from);
            $to_date = \DateTime::createFromFormat($this->date_format, $to);
            
            if ($from_date && $to_date) {
                $duration_days = $from_date->diff($to_date)->days + 1; // +1 to include both start and end days
                return [
                    'from' => $from_date->format($this->date_format),
                    'to' => $to_date->format($this->date_format),
                    'duration_days' => $duration_days
                ];
            }
            
            // Fallback: try common formats for backward compatibility
            $common_formats = ['Y-m-d', 'm/d/Y', 'd/m/Y', 'Y/m/d', 'F j, Y', 'j F Y'];
            foreach ($common_formats as $format) {
                $from_date = \DateTime::createFromFormat($format, $from);
                $to_date = \DateTime::createFromFormat($format, $to);
                if ($from_date && $to_date) {
                    $duration_days = $from_date->diff($to_date)->days + 1;
                    return [
                        'from' => $from_date->format($this->date_format),
                        'to' => $to_date->format($this->date_format),
                        'duration_days' => $duration_days
                    ];
                }
            }
        }
        
        return [
            'from' => $from,
            'to' => $to,
            'duration_days' => 0
        ];
    }
    
}
