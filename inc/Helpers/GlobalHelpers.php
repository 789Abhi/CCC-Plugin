<?php

// Global helper functions - NO NAMESPACE

defined('ABSPATH') || exit;

// Ensure these functions are only defined once

if (!function_exists('get_ccc_field')) {
    function get_ccc_field($field_name, $post_id = null, $component_id = null, $instance_id = null) {
        global $wpdb, $ccc_current_component, $ccc_current_post_id, $ccc_current_instance_id;
        
        // Use global context if available
        if (!$post_id) {
            $post_id = $ccc_current_post_id ?: get_the_ID();
        }
        
        if (!$component_id && isset($ccc_current_component['id'])) {
            $component_id = $ccc_current_component['id'];
        }
        
        if (!$instance_id && isset($ccc_current_instance_id)) {
            $instance_id = $ccc_current_instance_id;
        }
        
        if (!$post_id) {
            error_log("CCC: No post ID available for get_ccc_field('$field_name')");
            return '';
        }
        
        $fields_table = $wpdb->prefix . 'cc_fields';
        $values_table = $wpdb->prefix . 'cc_field_values';
        
        // Get field type and config from the fields table
        $field_info_query = $wpdb->prepare(
            "SELECT id, type, config FROM $fields_table WHERE name = %s",
            $field_name
        );
        $field_info = $wpdb->get_row($field_info_query);
        if (!$field_info) {
            error_log("CCC: Field '$field_name' not found in database.");
            return '';
        }

        $field_db_id = $field_info->id;
        $field_type = $field_info->type;
        $field_config = json_decode($field_info->config, true);

        // Base query to get the field value
        $query = "
            SELECT fv.value 
            FROM $values_table fv
            WHERE fv.post_id = %d 
            AND fv.field_id = %d
        ";
        
        $params = [$post_id, $field_db_id];
        
        // If instance_id is specified, add it to the query
        if ($instance_id) {
            $query .= " AND fv.instance_id = %s";
            $params[] = $instance_id;
        }
        
        $query .= " ORDER BY fv.id DESC LIMIT 1"; // Get the latest value
        $value = $wpdb->get_var($wpdb->prepare($query, $params));
        
        // Process value based on field type
        if ($field_type === 'repeater') {
            $decoded_value = json_decode($value, true) ?: [];
            // Check if this is the new format with data and state
            if (is_array($decoded_value) && isset($decoded_value['data']) && isset($decoded_value['state'])) {
                return $decoded_value['data']; // Return only the data part for backward compatibility
            } else {
                // Legacy format - return as is
                return $decoded_value;
            }
        } elseif ($field_type === 'image') {
            $return_type = $field_config['return_type'] ?? 'url';
            $decoded_value = json_decode($value, true);
            if ($return_type === 'array' && is_array($decoded_value)) {
                return $decoded_value;
            } elseif ($return_type === 'url' && is_array($decoded_value) && isset($decoded_value['url'])) {
                return $decoded_value['url'];
            }
            return $value ?: '';
        } elseif ($field_type === 'video') {
            $return_type = $field_config['return_type'] ?? 'url';
            $decoded_value = json_decode($value, true);
            if ($return_type === 'array' && is_array($decoded_value)) {
                return $decoded_value;
            } elseif ($return_type === 'url' && is_array($decoded_value) && isset($decoded_value['url'])) {
                return $decoded_value['url'];
            }
            return $value ?: '';
        } elseif ($field_type === 'checkbox') {
            return $value ? explode(',', $value) : [];
        } elseif ($field_type === 'select') {
            $config = $field_config ?: [];
            $multiple = isset($config['multiple']) && $config['multiple'];
            if ($multiple) {
                return $value ? explode(',', $value) : [];
            }
            return $value ?: '';
        } elseif ($field_type === 'radio') {
            return $value ?: '';
        } elseif ($field_type === 'wysiwyg') {
            return wp_kses_post($value);
        } elseif ($field_type === 'color') {
            return $value ?: '';
        }
        
        error_log("CCC: get_ccc_field('$field_name', $post_id, $component_id, '$instance_id') = '" . ($value ?: 'EMPTY') . "'");
        
        return $value ?: '';
    }
}

// Helper functions for new field types

if (!function_exists('get_ccc_select_field')) {
    function get_ccc_select_field($field_name, $post_id = null, $instance_id = null) {
        return get_ccc_field($field_name, $post_id, null, $instance_id);
    }
}

if (!function_exists('get_ccc_checkbox_field')) {
    function get_ccc_checkbox_field($field_name, $post_id = null, $instance_id = null) {
        $value = get_ccc_field($field_name, $post_id, null, $instance_id);
        return is_array($value) ? $value : ($value ? explode(',', $value) : []);
    }
}

if (!function_exists('get_ccc_radio_field')) {
    function get_ccc_radio_field($field_name, $post_id = null, $instance_id = null) {
        return get_ccc_field($field_name, $post_id, null, $instance_id);
    }
}

if (!function_exists('get_ccc_wysiwyg_field')) {
    function get_ccc_wysiwyg_field($field_name, $post_id = null, $instance_id = null) {
        $value = get_ccc_field($field_name, $post_id, null, $instance_id);
        return wp_kses_post($value);
    }
}

if (!function_exists('get_ccc_color_field')) {
    function get_ccc_color_field($field_name, $post_id = null, $instance_id = null) {
        $value = get_ccc_field($field_name, $post_id, null, $instance_id);
        return $value ?: '';
    }
}

if (!function_exists('get_ccc_video_field')) {
    function get_ccc_video_field($field_name, $post_id = null, $instance_id = null) {
        $value = get_ccc_field($field_name, $post_id, null, $instance_id);
        return $value ?: '';
    }
}

if (!function_exists('get_ccc_video_url')) {
    function get_ccc_video_url($field_name, $post_id = null, $instance_id = null) {
        $value = get_ccc_field($field_name, $post_id, null, $instance_id);
        if (is_array($value) && isset($value['url'])) {
            return $value['url'];
        }
        return $value ?: '';
    }
}

if (!function_exists('get_ccc_video_type')) {
    function get_ccc_video_type($field_name, $post_id = null, $instance_id = null) {
        $value = get_ccc_field($field_name, $post_id, null, $instance_id);
        if (is_array($value) && isset($value['type'])) {
            return $value['type'];
        }
        return 'url';
    }
}

if (!function_exists('get_ccc_video_title')) {
    function get_ccc_video_title($field_name, $post_id = null, $instance_id = null) {
        $value = get_ccc_field($field_name, $post_id, null, $instance_id);
        if (is_array($value) && isset($value['title'])) {
            return $value['title'];
        }
        return '';
    }
}

if (!function_exists('get_ccc_video_embed')) {
    function get_ccc_video_embed($field_name, $post_id = null, $instance_id = null, $width = '100%', $height = '400', $custom_options = null) {
        $value = get_ccc_field($field_name, $post_id, null, $instance_id);
        
        if (is_array($value) && isset($value['url']) && isset($value['type'])) {
            $url = $value['url'];
            $type = $value['type'];
        } else {
            $url = $value;
            $type = 'url';
        }
        
        if (empty($url)) {
            return '';
        }
        
        // Auto-detect video type from URL if not already set
        if ($type === 'url' || empty($type)) {
            if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
                $type = 'youtube';
            } elseif (strpos($url, 'vimeo.com') !== false) {
                $type = 'vimeo';
            } elseif (strpos($url, 'dailymotion.com') !== false) {
                $type = 'dailymotion';
            } elseif (strpos($url, 'facebook.com') !== false && strpos($url, '/videos/') !== false) {
                $type = 'facebook';
            } elseif (strpos($url, 'twitch.tv') !== false) {
                $type = 'twitch';
            } elseif (strpos($url, 'tiktok.com') !== false) {
                $type = 'tiktok';
            } else {
                $type = 'file';
            }
        }
        
        // Get field config to access player options
        global $wpdb;
        $fields_table = $wpdb->prefix . 'cc_fields';
        $field = $wpdb->get_row($wpdb->prepare(
            "SELECT config FROM $fields_table WHERE name = %s",
            $field_name
        ));
        
        $field_config = $field ? json_decode($field->config, true) : [];
        $player_options = $custom_options ?: ($field_config['player_options'] ?? [
            'controls' => true,
            'autoplay' => false,
            'muted' => false,
            'loop' => false,
            'download' => true,
            'fullscreen' => true,
            'pictureInPicture' => true
        ]);
        
        // Build video attributes
        $video_attrs = [];
        if ($player_options['controls']) $video_attrs[] = 'controls';
        if ($player_options['autoplay']) {
            $video_attrs[] = 'autoplay';
            // Autoplay requires muted to work in most browsers
            $video_attrs[] = 'muted';
        } elseif ($player_options['muted']) {
            $video_attrs[] = 'muted';
        }
        if ($player_options['loop']) $video_attrs[] = 'loop';
        if (!$player_options['download']) $video_attrs[] = 'controlslist="nodownload"';
        if (!$player_options['pictureInPicture']) $video_attrs[] = 'disablepictureinpicture';
        
        $video_attrs_str = implode(' ', $video_attrs);
        
        switch ($type) {
            case 'youtube':
                $video_id = '';
                if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $url, $matches)) {
                    $video_id = $matches[1];
                }
                if ($video_id) {
                    $youtube_params = [];
                    if ($player_options['autoplay']) $youtube_params[] = 'autoplay=1';
                    if ($player_options['muted']) $youtube_params[] = 'mute=1';
                    if ($player_options['loop']) $youtube_params[] = 'loop=1&playlist=' . $video_id;
                    if (!$player_options['controls']) $youtube_params[] = 'controls=0';
                    if (!$player_options['fullscreen']) $youtube_params[] = 'fs=0';
                    
                    $youtube_url = 'https://www.youtube.com/embed/' . $video_id;
                    if (!empty($youtube_params)) {
                        $youtube_url .= '?' . implode('&', $youtube_params);
                    }
                    
                    return sprintf(
                        '<iframe width="%s" height="%s" src="%s" title="YouTube video" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen style="border: none; display: block;"></iframe>',
                        esc_attr($width),
                        esc_attr($height),
                        esc_url($youtube_url)
                    );
                }
                break;
                
            case 'vimeo':
                $video_id = '';
                if (preg_match('/vimeo\.com\/(?:video\/)?(\d+)/', $url, $matches)) {
                    $video_id = $matches[1];
                }
                if ($video_id) {
                    $vimeo_params = [];
                    if ($player_options['autoplay']) {
                        $vimeo_params[] = 'autoplay=1';
                        $vimeo_params[] = 'muted=1'; // Autoplay requires muted
                    } elseif ($player_options['muted']) {
                        $vimeo_params[] = 'muted=1';
                    }
                    if ($player_options['loop']) $vimeo_params[] = 'loop=1';
                    if (!$player_options['controls']) $vimeo_params[] = 'controls=0';
                    if (!$player_options['fullscreen']) $vimeo_params[] = 'fullscreen=0';
                    
                    $vimeo_url = 'https://player.vimeo.com/video/' . $video_id;
                    if (!empty($vimeo_params)) {
                        $vimeo_url .= '?' . implode('&', $vimeo_params);
                    }
                    
                    return sprintf(
                        '<iframe width="%s" height="%s" src="%s" title="Vimeo video" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen style="border: none; display: block;"></iframe>',
                        esc_attr($width),
                        esc_attr($height),
                        esc_url($vimeo_url)
                    );
                }
                break;
                
            case 'dailymotion':
                $video_id = '';
                if (preg_match('/dailymotion\.com\/video\/([a-zA-Z0-9]+)/', $url, $matches)) {
                    $video_id = $matches[1];
                }
                if ($video_id) {
                    $dailymotion_params = [];
                    if ($player_options['autoplay']) $dailymotion_params[] = 'autoplay=1';
                    if ($player_options['muted']) $dailymotion_params[] = 'mute=1';
                    if (!$player_options['controls']) $dailymotion_params[] = 'controls=0';
                    
                    $dailymotion_url = 'https://www.dailymotion.com/embed/video/' . $video_id;
                    if (!empty($dailymotion_params)) {
                        $dailymotion_url .= '?' . implode('&', $dailymotion_params);
                    }
                    
                    return sprintf(
                        '<iframe width="%s" height="%s" src="%s" title="Dailymotion video" frameborder="0" allow="autoplay; fullscreen" allowfullscreen style="border: none; display: block;"></iframe>',
                        esc_attr($width),
                        esc_attr($height),
                        esc_url($dailymotion_url)
                    );
                }
                break;
                
            case 'facebook':
                $video_id = '';
                if (preg_match('/facebook\.com\/[^\/]+\/videos\/(\d+)/', $url, $matches)) {
                    $video_id = $matches[1];
                }
                if ($video_id) {
                    $facebook_url = 'https://www.facebook.com/plugins/video.php?href=https://www.facebook.com/video.php?v=' . $video_id . '&show_text=false&width=560&height=315&appId';
                    
                    return sprintf(
                        '<iframe width="%s" height="%s" src="%s" title="Facebook video" frameborder="0" allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share" allowfullscreen style="border: none; display: block;"></iframe>',
                        esc_attr($width),
                        esc_attr($height),
                        esc_url($facebook_url)
                    );
                }
                break;
                
            case 'twitch':
                $video_id = '';
                if (preg_match('/twitch\.tv\/videos\/(\d+)/', $url, $matches)) {
                    $video_id = $matches[1];
                }
                if ($video_id) {
                    $twitch_url = 'https://clips.twitch.tv/embed?clip=' . $video_id . '&parent=' . $_SERVER['HTTP_HOST'];
                    
                    return sprintf(
                        '<iframe width="%s" height="%s" src="%s" title="Twitch video" frameborder="0" allow="autoplay; fullscreen" allowfullscreen style="border: none; display: block;"></iframe>',
                        esc_attr($width),
                        esc_attr($height),
                        esc_url($twitch_url)
                    );
                }
                break;
                
            case 'tiktok':
                $video_id = '';
                if (preg_match('/tiktok\.com\/@[^\/]+\/video\/(\d+)/', $url, $matches)) {
                    $video_id = $matches[1];
                }
                if ($video_id) {
                    $tiktok_url = 'https://www.tiktok.com/embed/' . $video_id;
                    
                    return sprintf(
                        '<iframe width="%s" height="%s" src="%s" title="TikTok video" frameborder="0" allow="autoplay; fullscreen" allowfullscreen style="border: none; display: block;"></iframe>',
                        esc_attr($width),
                        esc_attr($height),
                        esc_url($tiktok_url)
                    );
                }
                break;
                
            case 'file':
            default:
                return sprintf(
                    '<video width="%s" height="%s" %s style="border: none; display: block;"><source src="%s" type="video/mp4"><source src="%s" type="video/webm"><source src="%s" type="video/ogg">Your browser does not support the video tag.</video>',
                    esc_attr($width),
                    esc_attr($height),
                    $video_attrs_str,
                    esc_url($url),
                    esc_url($url),
                    esc_url($url)
                );
        }
        
        return '';
    }
}

if (!function_exists('get_ccc_date_field')) {
    function get_ccc_date_field($field_name, $post_id = null, $instance_id = null) {
        return get_ccc_field($field_name, $post_id, null, $instance_id);
    }
}

if (!function_exists('get_ccc_date_display')) {
    function get_ccc_date_display($field_name, $post_id = null, $instance_id = null, $format = null, $empty_text = 'No date selected') {
        $value = get_ccc_field($field_name, $post_id, null, $instance_id);
        
        if (empty($value)) {
            return $empty_text;
        }
        
        // Parse the date value
        $date_data = _ccc_parse_date_value($value);
        
        if (!$date_data) {
            return $empty_text;
        }
        
        // Get field config for format
        global $wpdb;
        $fields_table = $wpdb->prefix . 'cc_fields';
        $field = $wpdb->get_row($wpdb->prepare(
            "SELECT config FROM $fields_table WHERE name = %s",
            $field_name
        ));
        
        $field_config = $field ? json_decode($field->config, true) : [];
        $date_format = $format ?: ($field_config['custom_date_format'] ?: $field_config['date_format'] ?: 'Y-m-d');
        
        return _ccc_format_date_display($date_data, $date_format);
    }
}

if (!function_exists('get_ccc_date_start')) {
    function get_ccc_date_start($field_name, $post_id = null, $instance_id = null, $format = null) {
        $value = get_ccc_field($field_name, $post_id, null, $instance_id);
        
        if (empty($value)) {
            return '';
        }
        
        $date_data = _ccc_parse_date_value($value);
        
        if (!$date_data || empty($date_data['start_date'])) {
            return '';
        }
        
        // Get field config for format
        global $wpdb;
        $fields_table = $wpdb->prefix . 'cc_fields';
        $field = $wpdb->get_row($wpdb->prepare(
            "SELECT config FROM $fields_table WHERE name = %s",
            $field_name
        ));
        
        $field_config = $field ? json_decode($field->config, true) : [];
        $date_format = $format ?: ($field_config['custom_date_format'] ?: $field_config['date_format'] ?: 'Y-m-d');
        
        return _ccc_format_single_date($date_data['start_date'], $date_data['start_time'], $date_format);
    }
}

if (!function_exists('get_ccc_date_end')) {
    function get_ccc_date_end($field_name, $post_id = null, $instance_id = null, $format = null) {
        $value = get_ccc_field($field_name, $post_id, null, $instance_id);
        
        if (empty($value)) {
            return '';
        }
        
        $date_data = _ccc_parse_date_value($value);
        
        if (!$date_data || empty($date_data['end_date'])) {
            return '';
        }
        
        // Get field config for format
        global $wpdb;
        $fields_table = $wpdb->prefix . 'cc_fields';
        $field = $wpdb->get_row($wpdb->prepare(
            "SELECT config FROM $fields_table WHERE name = %s",
            $field_name
        ));
        
        $field_config = $field ? json_decode($field->config, true) : [];
        $field_config = json_decode($field_config, true) ?: [];
        
        // Base query to get the field value
        $query = "
            SELECT fv.value 
            FROM $values_table fv
            WHERE fv.post_id = %d 
            AND fv.field_id = %d
        ";
        
        $params = [$post_id, $field_db_id];
        
        // If instance_id is specified, add it to the query
        if ($instance_id) {
            $query .= " AND fv.instance_id = %s";
            $params[] = $instance_id;
        }
        
        $query .= " ORDER BY fv.id DESC LIMIT 1"; // Get the latest value
        $value = $wpdb->get_var($wpdb->prepare($query, $params));
        
        // Process repeater value
        $decoded_value = json_decode($value, true) ?: [];
        // Check if this is the new format with data and state
        if (is_array($decoded_value) && isset($decoded_value['data']) && isset($decoded_value['state'])) {
            return $decoded_value; // Return the complete structure with data and state
        } else {
            // Legacy format - return as is
            return $decoded_value;
        }
    }
}

// Existing helper functions (keeping them for backward compatibility)

if (!function_exists('get_ccc_component_fields')) {
    function _ccc_process_field_value_recursive($value, $field_type, $field_config) {
        if ($field_type === 'repeater') {
            $decoded_value = json_decode($value, true) ?: [];
            
            // Check if this is the new format with data and state
            if (is_array($decoded_value) && isset($decoded_value['data']) && isset($decoded_value['state'])) {
                $repeater_data = $decoded_value['data'];
                $repeater_state = $decoded_value['state'];
                
                $processed_items = [];
                $nested_field_definitions = $field_config['nested_fields'] ?? [];

                foreach ($repeater_data as $item) {
                    $processed_item = [];
                    foreach ($nested_field_definitions as $nested_field_def) {
                        $nested_field_name = $nested_field_def['name'];
                        $nested_field_type = $nested_field_def['type'];
                        $nested_field_config = $nested_field_def['config'] ?? [];

                        if (isset($item[$nested_field_name])) {
                            $processed_item[$nested_field_name] = _ccc_process_field_value_recursive(
                                $item[$nested_field_name],
                                $nested_field_type,
                                $nested_field_config
                            );
                        }
                    }
                    $processed_items[] = $processed_item;
                }
                
                // Return only the processed data for backward compatibility
                return $processed_items;
            } else {
                // Legacy format - process as before
                $processed_items = [];
                $nested_field_definitions = $field_config['nested_fields'] ?? [];

                foreach ($decoded_value as $item) {
                    $processed_item = [];
                    foreach ($nested_field_definitions as $nested_field_def) {
                        $nested_field_name = $nested_field_def['name'];
                        $nested_field_type = $nested_field_def['type'];
                        $nested_field_config = $nested_field_def['config'] ?? [];

                        if (isset($item[$nested_field_name])) {
                            $processed_item[$nested_field_name] = _ccc_process_field_value_recursive(
                                $item[$nested_field_name],
                                $nested_field_type,
                                $nested_field_config
                            );
                        }
                    }
                    $processed_items[] = $processed_item;
                }
                return $processed_items;
            }
        } elseif ($field_type === 'image') {
            $return_type = $field_config['return_type'] ?? 'url';
            $decoded_value = json_decode($value, true);
            if ($return_type === 'array' && is_array($decoded_value)) {
                return $decoded_value;
            } elseif ($return_type === 'url' && is_array($decoded_value) && isset($decoded_value['url'])) {
                return $decoded_value['url'];
            }
            return $value ?: '';
        } elseif ($field_type === 'checkbox') {
            return $value ? explode(',', $value) : [];
        } elseif ($field_type === 'select') {
            $multiple = isset($field_config['multiple']) && $field_config['multiple'];
            if ($multiple) {
                return $value ? explode(',', $value) : [];
            }
            return $value ?: '';
        } elseif ($field_type === 'radio') {
            return $value ?: '';
        } elseif ($field_type === 'wysiwyg') {
            return wp_kses_post($value);
        } elseif ($field_type === 'color') {
            return $value ?: '';
        }

        return $value ?: '';
    }

    function get_ccc_component_fields($component_id, $post_id = null, $instance_id = null) {
        global $wpdb, $ccc_current_post_id, $ccc_current_instance_id;
        
        if (!$post_id) {
            $post_id = $ccc_current_post_id ?: get_the_ID();
        }
        
        if (!$instance_id && isset($ccc_current_instance_id)) {
            $instance_id = $ccc_current_instance_id;
        }
        
        if (!$post_id || !$component_id) {
            error_log("CCC: Invalid parameters for get_ccc_component_fields($component_id, $post_id, '$instance_id')");
            return [];
        }
        
        $fields_table = $wpdb->prefix . 'cc_fields';
        $values_table = $wpdb->prefix . 'cc_field_values';
        
        $query = "
            SELECT f.name, f.label, f.type, f.config, COALESCE(fv.value, '') as value
            FROM $fields_table f
            LEFT JOIN $values_table fv ON f.id = fv.field_id AND fv.post_id = %d
        ";
        
        $params = [$post_id];
        
        if ($instance_id) {
            $query .= " AND fv.instance_id = %s";
            $params[] = $instance_id;
        }
        
        $query .= " WHERE f.component_id = %d ORDER BY f.field_order, f.created_at";
        $params[] = $component_id;
        
        $results = $wpdb->get_results($wpdb->prepare($query, $params), ARRAY_A);
        
        $fields = [];
        foreach ($results as $result) {
            $field_config = json_decode($result['config'], true) ?: [];
            $fields[$result['name']] = _ccc_process_field_value_recursive(
                $result['value'],
                $result['type'],
                $field_config
            );
        }
        
        error_log("CCC: get_ccc_component_fields($component_id, $post_id, '$instance_id') returned " . count($fields) . " fields: " . implode(', ', array_keys($fields)));
        
        return $fields;
    }
}

if (!function_exists('get_ccc_post_components')) {
    function get_ccc_post_components($post_id = null) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }
        
        $components = get_post_meta($post_id, '_ccc_components', true);
        if (!is_array($components)) {
            $components = [];
        }
        
        // Sort by order
        usort($components, function($a, $b) {
            return ($a['order'] ?? 0) - ($b['order'] ?? 0);
        });
        
        return $components;
    }
}

// Debug function to check if values exist in database
if (!function_exists('ccc_debug_field_values')) {
    function ccc_debug_field_values($post_id = null, $instance_id = null) {
        global $wpdb;
        
        if (!$post_id) {
            $post_id = get_the_ID();
        }
        
        $values_table = $wpdb->prefix . 'cc_field_values';
        $fields_table = $wpdb->prefix . 'cc_fields';
        
        $query = "
            SELECT f.name, f.label, f.type, fv.value, f.component_id, fv.instance_id
            FROM $values_table fv
            INNER JOIN $fields_table f ON f.id = fv.field_id
            WHERE fv.post_id = %d
        ";
        
        $params = [$post_id];
        
        if ($instance_id) {
            $query .= " AND fv.instance_id = %s";
            $params[] = $instance_id;
        }
        
        $query .= " ORDER BY fv.instance_id, f.component_id";
        
        $results = $wpdb->get_results($wpdb->prepare($query, $params), ARRAY_A);
        
        error_log("CCC DEBUG: Field values for post $post_id" . ($instance_id ? " instance $instance_id" : "") . ":");
        foreach ($results as $result) {
            error_log("  - Field: {$result['name']} = '{$result['value']}' (Component: {$result['component_id']}, Instance: {$result['instance_id']})");
        }
        
        return $results;
    }
}

if (!function_exists('get_ccc_select_values')) {
    function get_ccc_select_values($field_name, $post_id = null, $instance_id = null, $format = 'array') {
        $values = get_ccc_field($field_name, $post_id, null, $instance_id);
        
        // Handle both single and multiple select fields
        if (is_array($values)) {
            // Multiple select field
            if (empty($values)) {
                return $format === 'array' ? [] : '';
            }
            
            switch ($format) {
                case 'array':
                    return $values;
                case 'string':
                    return implode(', ', $values);
                case 'list':
                    return '<ul><li>' . implode('</li><li>', array_map('esc_html', $values)) . '</li></ul>';
                case 'options':
                    return implode(' | ', array_map('esc_html', $values));
                default:
                    return $values;
            }
        } else {
            // Single select field
            if (empty($values)) {
                return $format === 'array' ? [] : '';
            }
            
            switch ($format) {
                case 'array':
                    return [$values];
                case 'string':
                    return $values;
                case 'list':
                    return '<ul><li>' . esc_html($values) . '</li></ul>';
                case 'options':
                    return esc_html($values);
                default:
                    return $values;
            }
        }
    }
}

if (!function_exists('get_ccc_select_display')) {
    function get_ccc_select_display($field_name, $post_id = null, $instance_id = null, $empty_text = 'No option selected') {
        $values = get_ccc_field($field_name, $post_id, null, $instance_id);
        
        // Handle both single and multiple select fields
        if (is_array($values)) {
            // Multiple select field
            if (empty($values)) {
                return esc_html($empty_text);
            }
            return esc_html(implode(', ', $values));
        } else {
            // Single select field
            if (empty($values)) {
                return esc_html($empty_text);
            }
            return esc_html($values);
        }
    }
}

if (!function_exists('get_ccc_checkbox_display')) {
    function get_ccc_checkbox_display($field_name, $post_id = null, $instance_id = null, $empty_text = 'No options selected') {
        $values = get_ccc_checkbox_field($field_name, $post_id, $instance_id);
        
        if (empty($values)) {
            return esc_html($empty_text);
        }
        return esc_html(implode(', ', $values));
    }
}

if (!function_exists('get_ccc_checkbox_values')) {
    function get_ccc_checkbox_values($field_name, $post_id = null, $instance_id = null, $format = 'array') {
        $values = get_ccc_checkbox_field($field_name, $post_id, $instance_id);
        
        if (empty($values)) {
            return $format === 'array' ? [] : '';
        }
        
        switch ($format) {
            case 'array':
                return $values;
            case 'string':
                return implode(', ', $values);
            case 'list':
                return '<ul><li>' . implode('</li><li>', array_map('esc_html', $values)) . '</li></ul>';
            case 'options':
                return implode(' | ', array_map('esc_html', $values));
            default:
                return $values;
        }
    }
}

if (!function_exists('get_ccc_radio_display')) {
    function get_ccc_radio_display($field_name, $post_id = null, $instance_id = null, $empty_text = 'No option selected') {
        $value = get_ccc_radio_field($field_name, $post_id, $instance_id);
        
        if (empty($value)) {
            return esc_html($empty_text);
        }
        return esc_html($value);
    }
}

if (!function_exists('get_ccc_radio_values')) {
    function get_ccc_radio_values($field_name, $post_id = null, $instance_id = null, $format = 'string') {
        $value = get_ccc_radio_field($field_name, $post_id, $instance_id);
        
        if (empty($value)) {
            return $format === 'array' ? [] : '';
        }
        
        switch ($format) {
            case 'array':
                return [$value];
            case 'string':
                return $value;
            case 'list':
                return '<ul><li>' . esc_html($value) . '</li></ul>';
            case 'options':
                return esc_html($value);
            default:
                return $value;
        }
    }
}

if (!function_exists('get_ccc_color_display')) {
    function get_ccc_color_display($field_name, $post_id = null, $instance_id = null, $empty_text = 'No color selected') {
        $value = get_ccc_color_field($field_name, $post_id, $instance_id);
        
        if (empty($value)) {
            return esc_html($empty_text);
        }
        return esc_html($value);
    }
}

if (!function_exists('get_ccc_color_values')) {
    function get_ccc_color_values($field_name, $post_id = null, $instance_id = null, $format = 'string') {
        $value = get_ccc_color_field($field_name, $post_id, $instance_id);
        
        if (empty($value)) {
            return $format === 'array' ? [] : '';
        }
        
        switch ($format) {
            case 'array':
                return [$value];
            case 'string':
                return $value;
            case 'list':
                return '<ul><li>' . esc_html($value) . '</li></ul>';
            case 'options':
                return esc_html($value);
            case 'css':
                return 'color: ' . esc_attr($value) . ';';
            case 'background':
                return 'background-color: ' . esc_attr($value) . ';';
            default:
                return $value;
        }
    }
}

if (!function_exists('get_ccc_color_contrast')) {
    function get_ccc_color_contrast($field_name, $post_id = null, $instance_id = null) {
        $hexColor = get_ccc_color_field($field_name, $post_id, $instance_id);
        
        if (empty($hexColor)) {
            return '#000000'; // Default to black
        }
        
        // Remove the # if present
        $hex = str_replace('#', '', $hexColor);
        
        // Convert to RGB
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        // Calculate luminance
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        
        // Return black or white based on luminance
        return $luminance > 0.5 ? '#000000' : '#ffffff';
    }
}

if (!function_exists('get_ccc_color_enhanced')) {
    function get_ccc_color_enhanced($field_name, $post_id = null, $instance_id = null) {
        $value = get_ccc_field($field_name, $post_id, null, $instance_id);
        
        // Handle enhanced color data structure
        if (is_string($value) && $value !== '' && $value[0] === '{') {
            $decoded_color = json_decode($value, true);
            if (is_array($decoded_color)) {
                return [
                    'main' => $decoded_color['main'] ?? '',
                    'adjusted' => $decoded_color['adjusted'] ?? $decoded_color['main'] ?? '',
                    'hover' => $decoded_color['hover'] ?? ''
                ];
            }
        }
        
        // Fallback to simple color
        return [
            'main' => $value ?: '',
            'adjusted' => $value ?: '',
            'hover' => ''
        ];
    }
}

if (!function_exists('get_ccc_color_main')) {
    function get_ccc_color_main($field_name, $post_id = null, $instance_id = null) {
        $colors = get_ccc_color_enhanced($field_name, $post_id, $instance_id);
        return $colors['main'];
    }
}

if (!function_exists('get_ccc_color_adjusted')) {
    function get_ccc_color_adjusted($field_name, $post_id = null, $instance_id = null) {
        $colors = get_ccc_color_enhanced($field_name, $post_id, $instance_id);
        return $colors['adjusted'];
    }
}

if (!function_exists('get_ccc_color_hover')) {
    function get_ccc_color_hover($field_name, $post_id = null, $instance_id = null) {
        $colors = get_ccc_color_enhanced($field_name, $post_id, $instance_id);
        return $colors['hover'];
    }
}

if (!function_exists('get_ccc_color_css')) {
    function get_ccc_color_css($field_name, $post_id = null, $instance_id = null, $type = 'main') {
        $colors = get_ccc_color_enhanced($field_name, $post_id, $instance_id);
        $color = $colors[$type] ?? $colors['main'];
        
        if (empty($color)) {
            return '';
        }
        
        return "color: " . esc_attr($color) . ";";
    }
}

if (!function_exists('get_ccc_color_background_css')) {
    function get_ccc_color_background_css($field_name, $post_id = null, $instance_id = null, $type = 'main') {
        $colors = get_ccc_color_enhanced($field_name, $post_id, $instance_id);
        $color = $colors[$type] ?? $colors['main'];
        
        if (empty($color)) {
            return '';
        }
        
        return "background-color: " . esc_attr($color) . ";";
    }
}

if (!function_exists('get_ccc_color_with_hover')) {
    function get_ccc_color_with_hover($field_name, $post_id = null, $instance_id = null, $property = 'color') {
        $colors = get_ccc_color_enhanced($field_name, $post_id, $instance_id);
        $main_color = $colors['main'];
        $hover_color = $colors['hover'];
        
        if (empty($main_color)) {
            return '';
        }
        
        $css = $property . ": " . esc_attr($main_color) . ";";
        
        // If hover color is set, add hover CSS
        if (!empty($hover_color)) {
            $css .= " transition: " . $property . " 0.3s ease;";
            $css .= " cursor: pointer;";
        }
        
        return $css;
    }
}

if (!function_exists('get_ccc_color_hover_css')) {
    function get_ccc_color_hover_css($field_name, $post_id = null, $instance_id = null, $property = 'color') {
        $colors = get_ccc_color_enhanced($field_name, $post_id, $instance_id);
        $hover_color = $colors['hover'];
        
        if (empty($hover_color)) {
            return '';
        }
        
        return $property . ": " . esc_attr($hover_color) . " !important;";
    }
}

if (!function_exists('get_ccc_color_auto_css')) {
    function get_ccc_color_auto_css($field_name, $post_id = null, $instance_id = null, $property = 'color') {
        $colors = get_ccc_color_enhanced($field_name, $post_id, $instance_id);
        $main_color = $colors['main'];
        $hover_color = $colors['hover'];
        
        if (empty($main_color)) {
            return '';
        }
        
        $css = $property . ": " . esc_attr($main_color) . ";";
        
        // If hover color is set, add hover CSS
        if (!empty($hover_color)) {
            $css .= " transition: " . $property . " 0.3s ease;";
            $css .= " cursor: pointer;";
        }
        
        return $css;
    }
}

if (!function_exists('get_ccc_color_auto_style')) {
    function get_ccc_color_auto_style($field_name, $post_id = null, $instance_id = null, $property = 'color') {
        $css = get_ccc_color_auto_css($field_name, $post_id, $instance_id, $property);
        
        if (empty($css)) {
            return '';
        }
        
        return ' style="' . esc_attr($css) . '"';
    }
}

if (!function_exists('get_ccc_color_with_hover_css')) {
    function get_ccc_color_with_hover_css($field_name, $post_id = null, $instance_id = null, $property = 'color', $selector = null) {
        $colors = get_ccc_color_enhanced($field_name, $post_id, $instance_id);
        $main_color = $colors['main'];
        $hover_color = $colors['hover'];
        
        if (empty($main_color)) {
            return '';
        }
        
        // Generate unique class name if no selector provided
        if (!$selector) {
            $selector = 'ccc-color-' . sanitize_title($field_name) . '-' . uniqid();
        }
        
        $css = '.' . $selector . ' {';
        $css .= $property . ': ' . esc_attr($main_color) . ';';
        
        // If hover color is set, add hover CSS
        if (!empty($hover_color)) {
            $css .= ' transition: ' . $property . ' 0.3s ease;';
            $css .= ' cursor: pointer;';
        }
        $css .= '}';
        
        // Add hover rule if hover color exists
        if (!empty($hover_color)) {
            $css .= '.' . $selector . ':hover {';
            $css .= $property . ': ' . esc_attr($hover_color) . ' !important;';
            $css .= '}';
        }
        
        return $css;
    }
}

if (!function_exists('get_ccc_color_with_hover_style')) {
    function get_ccc_color_with_hover_style($field_name, $post_id = null, $instance_id = null, $property = 'color') {
        $colors = get_ccc_color_enhanced($field_name, $post_id, $instance_id);
        $main_color = $colors['main'];
        $hover_color = $colors['hover'];
        
        if (empty($main_color)) {
            return '';
        }
        
        // Generate unique class name
        $class_name = 'ccc-color-' . sanitize_title($field_name) . '-' . uniqid();
        
        // Generate CSS
        $css = get_ccc_color_with_hover_css($field_name, $post_id, $instance_id, $property, $class_name);
        
        // Output CSS in style tag
        echo '<style>' . $css . '</style>';
        
        // Return class name
        return ' class="' . esc_attr($class_name) . '"';
    }
}

if (!function_exists('get_ccc_color_hover_style')) {
    function get_ccc_color_hover_style($field_name, $post_id = null, $instance_id = null, $property = 'color') {
        $css = get_ccc_color_hover_css($field_name, $post_id, $instance_id, $property);
        
        if (empty($css)) {
            return '';
        }
        
        return ' style="' . esc_attr($css) . '"';
    }
}

if (!function_exists('get_ccc_color_css_variables')) {
    function get_ccc_color_css_variables($field_name, $post_id = null, $instance_id = null) {
        $colors = get_ccc_color_enhanced($field_name, $post_id, $instance_id);
        $main_color = $colors['main'];
        $hover_color = $colors['hover'];
        $adjusted_color = $colors['adjusted'];
        
        if (empty($main_color)) {
            return '';
        }
        
        $css = '--ccc-color-main: ' . esc_attr($main_color) . ';';
        
        if (!empty($adjusted_color) && $adjusted_color !== $main_color) {
            $css .= ' --ccc-color-adjusted: ' . esc_attr($adjusted_color) . ';';
        }
        
        if (!empty($hover_color)) {
            $css .= ' --ccc-color-hover: ' . esc_attr($hover_color) . ';';
        }
        
        return $css;
    }
}

if (!function_exists('get_ccc_color_css_variables_style')) {
    function get_ccc_color_css_variables_style($field_name, $post_id = null, $instance_id = null) {
        $variables = get_ccc_color_css_variables($field_name, $post_id, $instance_id);
        
        if (empty($variables)) {
            return '';
        }
        
        return ' style="' . esc_attr($variables) . '"';
    }
}

if (!function_exists('get_ccc_color_css_variables_root')) {
    function get_ccc_color_css_variables_root($field_name, $post_id = null, $instance_id = null) {
        $variables = get_ccc_color_css_variables($field_name, $post_id, $instance_id);
        
        if (empty($variables)) {
            return '';
        }
        
        return ':root { ' . $variables . ' }';
    }
}

if (!function_exists('get_ccc_color_adjusted_css')) {
    function get_ccc_color_adjusted_css($field_name, $post_id = null, $instance_id = null, $property = 'color') {
        $colors = get_ccc_color_enhanced($field_name, $post_id, $instance_id);
        $adjusted_color = $colors['adjusted'];
        
        if (empty($adjusted_color)) {
            return '';
        }
        
        return $property . ": " . esc_attr($adjusted_color) . ";";
    }
}

if (!function_exists('get_ccc_color_adjusted_style')) {
    function get_ccc_color_adjusted_style($field_name, $post_id = null, $instance_id = null, $property = 'color') {
        $css = get_ccc_color_adjusted_css($field_name, $post_id, $instance_id, $property);
        
        if (empty($css)) {
            return '';
        }
        
        return ' style="' . esc_attr($css) . '"';
    }
}
