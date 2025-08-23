<?php

namespace CCC\Fields;

use CCC\Fields\BaseField;
use Exception;

class FileField extends BaseField
{
    public function __construct($label, $name, $component_id, $required = false, $placeholder = '', $config = '')
    {
        parent::__construct($label, $name, $component_id, $required, $placeholder, $config);
    }

    public function render($post_id, $instance_id, $value = '')
    {
        $field_name = $this->getName();
        $field_config = $this->getConfig();
        $field_value = $value;
        $field_required = $this->isRequired() ? 'true' : 'false';
        
        // Ensure WordPress media scripts are loaded
        wp_enqueue_media();
        wp_enqueue_script('jquery');
        
        // Parse config to get file field specific options
        $config = [];
        if (!empty($field_config)) {
            try {
                $config = is_string($field_config) ? json_decode($field_config, true) : $field_config;
            } catch (Exception $e) {
                $config = [];
            }
        }
        
        // Ensure all config values are properly set
        $config = array_merge([
            'allowed_types' => ['image', 'video', 'document', 'audio', 'archive'],
            'max_file_size' => null, // No default restriction
            'return_type' => 'url', // url, id, array
            'show_preview' => true,
            'show_download' => true,
            'show_delete' => true
        ], $config);
        
        // Process the field value to get file information
        $processed_value = $this->processFieldValue($field_value, $config);
        
        // Output the hidden input for the value and div for React component
        echo '<div class="w-full mb-4">';
        echo '<input type="hidden" name="' . esc_attr($field_name) . '" value="' . esc_attr($field_value) . '" />';
        echo '<div id="ccc-file-field-' . esc_attr($instance_id) . '" 
                   data-field-name="' . esc_attr($field_name) . '"
                   data-field-config="' . esc_attr(json_encode($config)) . '"
                   data-field-value="' . esc_attr(json_encode($processed_value)) . '"
                   data-field-required="' . esc_attr($field_required) . '">
             </div>';
        echo '</div>';
    }

    public function save()
    {
        // Saving is handled by FieldValue model
        return true;
    }

    public function sanitize($value)
    {
        if (empty($value)) {
            return '';
        }
        
        // Always treat as single file
        return $this->sanitizeSingleFile($value);
    }
    
    private function sanitizeSingleFile($file)
    {
        if (empty($file)) {
            return '';
        }
        
        // If it's already a file ID, validate it exists
        if (is_numeric($file)) {
            $attachment = get_post($file);
            if ($attachment) {
                return $file;
            }
            return '';
        }
        
        // If it's a URL, validate it's a valid attachment URL
        if (is_string($file) && filter_var($file, FILTER_VALIDATE_URL)) {
            // Check if this URL belongs to a WordPress attachment
            $attachment_id = attachment_url_to_postid($file);
            if ($attachment_id) {
                return $attachment_id;
            }
            return '';
        }
        
        // If it's an object/array with id property (from React component)
        if (is_array($file) && isset($file['id'])) {
            $file_id = $file['id'];
            if (is_numeric($file_id)) {
                $attachment = get_post($file_id);
                if ($attachment) {
                    return $file_id;
                }
            }
            return '';
        }
        
        // If it's an object with id property (from React component)
        if (is_object($file) && isset($file->id)) {
            $file_id = $file->id;
            if (is_numeric($file_id)) {
                $attachment = get_post($file_id);
                if ($attachment) {
                    return $file_id;
                }
            }
            return '';
        }
        
        return '';
    }
    
    public function getFileInfo($file_id)
    {
        if (empty($file_id)) {
            return null;
        }
        
        $attachment = get_post($file_id);
        if (!$attachment) {
            return null;
        }
        
        $file_path = get_attached_file($file_id);
        $file_url = wp_get_attachment_url($file_id);
        $file_type = get_post_mime_type($file_id);
        $file_size = file_exists($file_path) ? filesize($file_path) : 0;
        
        return [
            'id' => $file_id,
            'title' => $attachment->post_title,
            'filename' => basename($file_path),
            'url' => $file_url,
            'type' => $file_type,
            'size' => $file_size,
            'size_formatted' => size_format($file_size),
            'date' => $attachment->post_date,
            'is_image' => wp_attachment_is_image($file_id),
            'is_video' => strpos($file_type, 'video/') === 0,
            'is_audio' => strpos($file_type, 'audio/') === 0,
            'is_document' => in_array($file_type, [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/plain'
            ]),
            'thumbnail' => wp_get_attachment_image_src($file_id, 'thumbnail')[0] ?? null,
            'medium' => wp_get_attachment_image_src($file_id, 'medium')[0] ?? null,
            'large' => wp_get_attachment_image_src($file_id, 'large')[0] ?? null
        ];
    }
    
    private function processFieldValue($value, $config)
    {
        if (empty($value)) {
            return '';
        }
        
        error_log("CCC DEBUG: FileField processFieldValue called with value: " . json_encode($value));
        
        // If it's a JSON string, decode it
        if (is_string($value)) {
            try {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $value = $decoded;
                    error_log("CCC DEBUG: FileField decoded JSON value: " . json_encode($value));
                }
            } catch (Exception $e) {
                error_log("CCC DEBUG: FileField JSON decode error: " . $e->getMessage());
                // Keep original value if JSON decode fails
            }
        }
        
        // Always treat as single file
        error_log("CCC DEBUG: FileField processing single file");
        $result = $this->getFileInfoFromValue($value);
        error_log("CCC DEBUG: FileField single file result: " . json_encode($result));
        return $result;
    }
    
    private function getFileInfoFromValue($file_value)
    {
        if (empty($file_value)) {
            return null;
        }
        
        error_log("CCC DEBUG: FileField getFileInfoFromValue called with: " . json_encode($file_value));
        
        $file_id = null;
        
        // Extract file ID from various formats
        if (is_numeric($file_value)) {
            $file_id = $file_value;
            error_log("CCC DEBUG: FileField numeric file_value: " . $file_id);
        } elseif (is_array($file_value) && isset($file_value['id'])) {
            $file_id = $file_value['id'];
            error_log("CCC DEBUG: FileField array file_value with id: " . $file_id);
        } elseif (is_object($file_value) && isset($file_value->id)) {
            $file_id = $file_value->id;
            error_log("CCC DEBUG: FileField object file_value with id: " . $file_id);
        } elseif (is_string($file_value) && filter_var($file_value, FILTER_VALIDATE_URL)) {
            $file_id = attachment_url_to_postid($file_value);
            error_log("CCC DEBUG: FileField URL converted to ID: " . $file_id);
        }
        
        // If we have a numeric file ID, get the file info from WordPress
        if ($file_id && is_numeric($file_id)) {
            error_log("CCC DEBUG: FileField getting file info for ID: " . $file_id);
            return $this->getFileInfo($file_id);
        }
        
        // If it's a temporary uploaded file (has temp_id), return the file data as is
        if (is_array($file_value) && isset($file_value['temp_id']) && isset($file_value['is_temp']) && $file_value['is_temp'] === true) {
            error_log("CCC DEBUG: FileField temporary file detected: " . json_encode($file_value));
            return $file_value;
        }
        
        // If it's an object with temp_id property
        if (is_object($file_value) && isset($file_value->temp_id) && isset($file_value->is_temp) && $file_value->is_temp === true) {
            error_log("CCC DEBUG: FileField temporary file object detected: " . json_encode($file_value));
            return [
                'temp_id' => $file_value->temp_id,
                'name' => $file_value->name ?? '',
                'type' => $file_value->type ?? '',
                'size' => $file_value->size ?? 0,
                'is_temp' => true
            ];
        }
        
        // If it's a media library file with id, url, type, name structure
        if (is_array($file_value) && isset($file_value['id']) && isset($file_value['url']) && isset($file_value['type'])) {
            error_log("CCC DEBUG: FileField media library file array detected: " . json_encode($file_value));
            // This is a media library file, get the full info
            if (is_numeric($file_value['id'])) {
                error_log("CCC DEBUG: FileField getting file info for media library file ID: " . $file_value['id']);
                return $this->getFileInfo($file_value['id']);
            }
        }
        
        // If it's an object with id, url, type, name structure
        if (is_object($file_value) && isset($file_value->id) && isset($file_value->url) && isset($file_value->type)) {
            error_log("CCC DEBUG: FileField media library file object detected: " . json_encode($file_value));
            // This is a media library file, get the full info
            if (is_numeric($file_value->id)) {
                error_log("CCC DEBUG: FileField getting file info for media library file object ID: " . $file_value->id);
                return $this->getFileInfo($file_value->id);
            }
        }
        
        error_log("CCC DEBUG: FileField returning original value: " . json_encode($file_value));
        // If we can't get file info, return the original value for the React component to handle
        return $file_value;
    }
} 