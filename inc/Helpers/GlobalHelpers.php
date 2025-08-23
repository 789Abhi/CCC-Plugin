<?php

// Global helper functions - NO NAMESPACE

defined('ABSPATH') || exit;

// Ensure these functions are only defined once

if (!function_exists('get_ccc_field_video')) {
    /**
     * Get and render a video field with automatic type detection and proper controls
     * 
     * @param string $field_name The name of the video field
     * @param array $options Optional rendering options
     * @param int|null $post_id Post ID (optional)
     * @param int|null $component_id Component ID (optional)
     * @param string|null $instance_id Instance ID (optional)
     * @return string HTML output for the video
     */
    function get_ccc_field_video($field_name, $options = [], $post_id = null, $component_id = null, $instance_id = null) {
        // Get the video field value
        $video_value = get_ccc_field($field_name, $post_id, $component_id, $instance_id);
        
        if (empty($video_value)) {
            return '';
        }
        
        // Get field configuration from database to read player options set in the plugin
        global $wpdb;
        $fields_table = $wpdb->prefix . 'cc_fields';
        $field_config = null;
        
        // Try to get field config by name
        $field_query = $wpdb->prepare(
            "SELECT id, name, type, config FROM $fields_table WHERE name = %s",
            $field_name
        );
        $field_result = $wpdb->get_row($field_query);
        
        if ($field_result && $field_result->config) {
            try {
                $field_config = json_decode($field_result->config, true);
                error_log("CCC DEBUG: VideoField '$field_name' - Found field ID: " . $field_result->id . ", Type: " . $field_result->type);
            } catch (Exception $e) {
                error_log("CCC DEBUG: VideoField config decode error: " . $e->getMessage());
            }
        } else {
            error_log("CCC DEBUG: VideoField '$field_name' - Field not found or no config. Query: " . $field_query);
            // Try to find any field with similar name
            $similar_query = $wpdb->prepare(
                "SELECT id, name, type FROM $fields_table WHERE name LIKE %s",
                '%' . $wpdb->esc_like($field_name) . '%'
            );
            $similar_fields = $wpdb->get_results($similar_query);
            if ($similar_fields) {
                error_log("CCC DEBUG: VideoField '$field_name' - Similar fields found: " . json_encode($similar_fields));
            }
        }
        
        // Get player options from field config (set in the plugin)
        $plugin_player_options = [];
        if ($field_config) {
            // Check for player_options in the main config
            if (isset($field_config['player_options'])) {
                $plugin_player_options = $field_config['player_options'];
            }
            // Also check for direct player options at the root level
            elseif (isset($field_config['controls']) || isset($field_config['autoplay']) || isset($field_config['muted'])) {
                                 $plugin_player_options = [
                     'controls' => $field_config['controls'] ?? true,
                     'autoplay' => $field_config['autoplay'] ?? false,
                     'muted' => $field_config['muted'] ?? false,
                     'loop' => $field_config['loop'] ?? false,
                     'download' => $field_config['download'] ?? true
                 ];
            }
        }
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("CCC DEBUG: VideoField '$field_name' - Field config: " . json_encode($field_config));
            error_log("CCC DEBUG: VideoField '$field_name' - Plugin player options: " . json_encode($plugin_player_options));
        }
        
        // Default options
        $default_options = [
            'width' => '100%',
            'height' => '500',
            'controls' => true,
            'autoplay' => false,
            'muted' => false,
            'loop' => false,
            'download' => true,
            'class' => 'ccc-video-player',
            'style' => '',
            'preload' => 'metadata'
        ];
        
        // Merge options in priority order: plugin config > function options > defaults
        // But handle disabled options properly - if plugin explicitly sets an option to false, respect it
        $final_options = $default_options;
        
        // Apply function options first (allows overriding)
        $final_options = array_merge($final_options, $options);
        
        // Apply plugin options last, but respect explicit false values
        if ($plugin_player_options) {
            foreach ($plugin_player_options as $key => $value) {
                // If plugin explicitly sets an option to false, respect it
                if ($value === false) {
                    $final_options[$key] = false;
                } elseif ($value === true) {
                    $final_options[$key] = true;
                } elseif (isset($value)) {
                    $final_options[$key] = $value;
                }
            }
        }
        
        $options = $final_options;
        
                        // Debug logging for final options
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("CCC DEBUG: VideoField '$field_name' - Final merged options: " . json_encode($options));
                }
        
        // Add a helper function to debug field configuration
        if (defined('WP_DEBUG') && WP_DEBUG && !function_exists('ccc_debug_video_field_config')) {
            function ccc_debug_video_field_config($field_name) {
                global $wpdb;
                $fields_table = $wpdb->prefix . 'cc_fields';
                $field_query = $wpdb->prepare(
                    "SELECT id, name, type, config FROM $fields_table WHERE name = %s",
                    $field_name
                );
                $field_result = $wpdb->get_row($field_query);
                
                if ($field_result) {
                    error_log("CCC DEBUG: VideoField '$field_name' - Database record: " . json_encode($field_result));
                    if ($field_result->config) {
                        $config = json_decode($field_result->config, true);
                        error_log("CCC DEBUG: VideoField '$field_name' - Decoded config: " . json_encode($config));
                        
                        // Check for player options specifically
                        if (isset($config['player_options'])) {
                            error_log("CCC DEBUG: VideoField '$field_name' - Player options found: " . json_encode($config['player_options']));
                        } else {
                            error_log("CCC DEBUG: VideoField '$field_name' - No player_options found in config");
                        }
                    }
                } else {
                    error_log("CCC DEBUG: VideoField '$field_name' - Field not found in database");
                }
            }
        }
        
        // Try to decode as JSON first (for structured video data)
        $video_data = null;
        if (is_string($video_value)) {
            $decoded = json_decode($video_value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $video_data = $decoded;
            }
        }
        
        // If no structured data, treat as simple URL
        if (!$video_data) {
            $video_data = [
                'url' => $video_value,
                'type' => 'url',
                'title' => '',
                'description' => ''
            ];
        }
        
        // Auto-detect video type from URL if not already set
        $video_type = $video_data['type'] ?? 'url';
        $video_url = $video_data['url'] ?? '';
        
        if (!$video_url) {
            return '';
        }
        
        // Auto-detect video type from URL
        if ($video_type === 'url' || empty($video_type)) {
            if (strpos($video_url, 'youtube.com') !== false || strpos($video_url, 'youtu.be') !== false) {
                $video_type = 'youtube';
            } elseif (strpos($video_url, 'vimeo.com') !== false) {
                $video_type = 'vimeo';
            } elseif (strpos($video_url, 'dailymotion.com') !== false) {
                $video_type = 'dailymotion';
            } elseif (strpos($video_url, 'facebook.com') !== false && strpos($video_url, '/videos/') !== false) {
                $video_type = 'facebook';
            } elseif (strpos($video_url, 'twitch.tv') !== false) {
                $video_type = 'twitch';
            } elseif (strpos($video_url, 'tiktok.com') !== false) {
                $video_type = 'tiktok';
            } else {
                $video_type = 'file';
            }
        }
        
        // Build CSS styles
        $style_attr = '';
        if ($options['style']) {
            $style_attr = ' style="' . esc_attr($options['style']) . '"';
        }
        
        // Build class attribute
        $class_attr = ' class="' . esc_attr($options['class']) . '"';
        
        // Render based on video type
        switch ($video_type) {
            case 'youtube':
                $youtube_id = extract_youtube_id($video_url);
                if ($youtube_id) {
                    $youtube_params = [];
                                         if ($options['autoplay'] === true) {
                         $youtube_params[] = 'autoplay=1';
                         $youtube_params[] = 'mute=1'; // Autoplay requires muted
                     } elseif ($options['muted'] === true) {
                         $youtube_params[] = 'mute=1';
                     }
                     if ($options['loop'] === true) {
                         $youtube_params[] = 'loop=1&playlist=' . $youtube_id;
                     }
                                         if ($options['controls'] === false) {
                         $youtube_params[] = 'controls=0';
                     }
                    
                    
                    $youtube_url = 'https://www.youtube.com/embed/' . $youtube_id;
                    if (!empty($youtube_params)) {
                        $youtube_url .= '?' . implode('&', $youtube_params);
                    }
                    
                                         return sprintf(
                         '<iframe width="%s" height="%s" src="%s" title="YouTube video" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"%s%s></iframe>',
                         esc_attr($options['width']),
                         esc_attr($options['height']),
                         esc_url($youtube_url),
                         $class_attr,
                         $style_attr
                     );
                }
                break;
                
            case 'vimeo':
                $vimeo_id = extract_vimeo_id($video_url);
                if ($vimeo_id) {
                    $vimeo_params = [];
                                         if ($options['autoplay'] === true) {
                         $vimeo_params[] = 'autoplay=1';
                         $vimeo_params[] = 'muted=1'; // Autoplay requires muted
                     } elseif ($options['muted'] === true) {
                         $vimeo_params[] = 'muted=1';
                     }
                     if ($options['loop'] === true) {
                         $vimeo_params[] = 'loop=1';
                     }
                                         if ($options['controls'] === false) {
                         $vimeo_params[] = 'controls=0';
                     }
                    
                    
                    $vimeo_url = 'https://player.vimeo.com/video/' . $vimeo_id;
                    if (!empty($vimeo_params)) {
                        $vimeo_url .= '?' . implode('&', $vimeo_params);
                    }
                    
                                         return sprintf(
                         '<iframe width="%s" height="%s" src="%s" title="Vimeo video" frameborder="0" allow="autoplay; fullscreen; picture-in-picture"%s%s></iframe>',
                         esc_attr($options['width']),
                         esc_attr($options['height']),
                         esc_url($vimeo_url),
                         $class_attr,
                         $style_attr
                     );
                }
                break;
                
            case 'dailymotion':
                $dailymotion_id = extract_dailymotion_id($video_url);
                if ($dailymotion_id) {
                    $dailymotion_params = [];
                                         if ($options['autoplay'] === true) {
                         $dailymotion_params[] = 'autoplay=1';
                     }
                     if ($options['muted'] === true) {
                         $dailymotion_params[] = 'mute=1';
                     }
                                         if ($options['controls'] === false) {
                         $dailymotion_params[] = 'controls=0';
                     }
                    
                    $dailymotion_url = 'https://www.dailymotion.com/embed/video/' . $dailymotion_id;
                    if (!empty($dailymotion_params)) {
                        $dailymotion_url .= '?' . implode('&', $dailymotion_params);
                    }
                    
                                         return sprintf(
                         '<iframe width="%s" height="%s" src="%s" title="Dailymotion video" frameborder="0" allow="autoplay; fullscreen"%s%s></iframe>',
                         esc_attr($options['width']),
                         esc_attr($options['height']),
                         esc_url($dailymotion_url),
                         $class_attr,
                         $style_attr
                     );
                }
                break;
                
            case 'facebook':
                $facebook_id = extract_facebook_video_id($video_url);
                if ($facebook_id) {
                    $facebook_url = 'https://www.facebook.com/plugins/video.php?href=https://www.facebook.com/video.php?v=' . $facebook_id . '&show_text=false&width=560&height=315&appId';
                    
                                         return sprintf(
                         '<iframe width="%s" height="%s" src="%s" title="Facebook video" frameborder="0" allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share"%s%s></iframe>',
                         esc_attr($options['width']),
                         esc_attr($options['height']),
                         esc_url($facebook_url),
                         $class_attr,
                         $style_attr
                     );
                }
                break;
                
            case 'twitch':
                $twitch_id = extract_twitch_video_id($video_url);
                if ($twitch_id) {
                    $twitch_url = 'https://clips.twitch.tv/embed?clip=' . $twitch_id . '&parent=' . parse_url(home_url(), PHP_URL_HOST);
                    
                                         return sprintf(
                         '<iframe width="%s" height="%s" src="%s" title="Twitch video" frameborder="0" allow="autoplay; fullscreen"%s%s></iframe>',
                         esc_attr($options['width']),
                         esc_attr($options['height']),
                         esc_url($twitch_url),
                         $class_attr,
                         $style_attr
                     );
                }
                break;
                
            case 'tiktok':
                $tiktok_id = extract_tiktok_video_id($video_url);
                if ($tiktok_id) {
                    $tiktok_url = 'https://www.tiktok.com/embed/' . $tiktok_id;
                    
                                         return sprintf(
                         '<iframe width="%s" height="%s" src="%s" title="TikTok video" frameborder="0" allow="autoplay; fullscreen"%s%s></iframe>',
                         esc_attr($options['width']),
                         esc_attr($options['height']),
                         esc_url($tiktok_url),
                         $class_attr,
                         $style_attr
                     );
                }
                break;
                
            case 'file':
            default:
                // For direct video files or other types, use HTML5 video tag
                                 $controls_attr = ($options['controls'] === true) ? ' controls' : '';
                 $autoplay_attr = ($options['autoplay'] === true) ? ' autoplay' : '';
                 $muted_attr = ($options['autoplay'] === true || $options['muted'] === true) ? ' muted' : '';
                 $loop_attr = ($options['loop'] === true) ? ' loop' : '';
                $preload_attr = ' preload="' . esc_attr($options['preload']) . '"';
                
                                 $controls_list = '';
                 if ($options['download'] === false) {
                     $controls_list = ' controlsList="nodownload"';
                 }
                 
                                 
                
                                 $additional_style = $style_attr;
                
                                 $output = sprintf(
                     '<video width="%s" height="%s" data-field-name="%s"%s%s%s%s%s%s%s%s>',
                     esc_attr($options['width']),
                     esc_attr($options['height']),
                     esc_attr($field_name),
                     $controls_attr,
                     $autoplay_attr,
                     $muted_attr,
                     $loop_attr,
                     $preload_attr,
                     $controls_list,
                     $class_attr,
                     $additional_style
                 );
                
                // Add multiple source formats for better compatibility
                $output .= '<source src="' . esc_url($video_url) . '" type="video/mp4">';
                $output .= '<source src="' . esc_url($video_url) . '" type="video/webm">';
                $output .= '<source src="' . esc_url($video_url) . '" type="video/ogg">';
                $output .= 'Your browser does not support the video tag.';
                $output .= '</video>';
                
                
                
                return $output;
        }
        
                 // Fallback: return as iframe if we can't determine the type
         return sprintf(
             '<iframe width="%s" height="%s" src="%s" title="External video" frameborder="0" allow="autoplay; fullscreen; picture-in-picture"%s%s></iframe>',
             esc_attr($options['width']),
             esc_attr($options['height']),
             esc_url($video_url),
             $class_attr,
             $style_attr
         );
    }
}

if (!function_exists('extract_youtube_id')) {
    /**
     * Extract YouTube video ID from URL
     */
    function extract_youtube_id($url) {
        $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/';
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
        return null;
    }
}

if (!function_exists('extract_vimeo_id')) {
    /**
     * Extract Vimeo video ID from URL
     */
    function extract_vimeo_id($url) {
        $pattern = '/vimeo\.com\/(?:video\/)?(\d+)/';
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
        return null;
    }
}

if (!function_exists('extract_dailymotion_id')) {
    /**
     * Extract Dailymotion video ID from URL
     */
    function extract_dailymotion_id($url) {
        $pattern = '/dailymotion\.com\/video\/([a-zA-Z0-9]+)/';
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
        return null;
    }
}

if (!function_exists('extract_facebook_video_id')) {
    /**
     * Extract Facebook video ID from URL
     */
    function extract_facebook_video_id($url) {
        $pattern = '/facebook\.com\/[^\/]+\/videos\/(\d+)/';
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
        return null;
    }
}

if (!function_exists('extract_twitch_video_id')) {
    /**
     * Extract Twitch video ID from URL
     */
    function extract_twitch_video_id($url) {
        $pattern = '/twitch\.tv\/videos\/(\d+)/';
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
        return null;
    }
}

if (!function_exists('extract_tiktok_video_id')) {
    /**
     * Extract TikTok video ID from URL
     */
    function extract_tiktok_video_id($url) {
        $pattern = '/tiktok\.com\/@[^\/]+\/video\/(\d+)/';
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
        return null;
    }
}

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
            // Handle null values safely
            if ($value === null) {
                return [];
            }
            $decoded_value = json_decode($value, true) ?: [];
            // Check if this is the new format with data and state
            if (is_array($decoded_value) && isset($decoded_value['data']) && isset($decoded_value['state'])) {
                $items = $decoded_value['data']; // Get the data part for backward compatibility
            } else {
                // Legacy format - use as is
                $items = $decoded_value;
            }
            
            // Filter out hidden items
            if (is_array($items)) {
                $visible_items = array_filter($items, function($item) {
                    return !isset($item['_hidden']) || !$item['_hidden'];
                });
                
                // Remove the _hidden property from visible items
                $clean_items = array_map(function($item) {
                    unset($item['_hidden']);
                    return $item;
                }, $visible_items);
                
                return array_values($clean_items); // Re-index array
            }
            
            return [];
        } elseif ($field_type === 'image') {
            $return_type = $field_config['return_type'] ?? 'url';
            // Handle null values safely
            if ($value === null) {
                return '';
            }
            $decoded_value = json_decode($value, true);
            if ($return_type === 'array' && is_array($decoded_value)) {
                return $decoded_value;
            } elseif ($return_type === 'url' && is_array($decoded_value) && isset($decoded_value['url'])) {
                return $decoded_value['url'];
            }
            return $value ?: '';
        } elseif ($field_type === 'video') {
            $return_type = $field_config['return_type'] ?? 'url';
            // Handle null values safely
            if ($value === null) {
                return '';
            }
            $decoded_value = json_decode($value, true);
            if ($return_type === 'array' && is_array($decoded_value)) {
                return $decoded_value;
            } elseif ($return_type === 'url' && is_array($decoded_value) && isset($decoded_value['url'])) {
                return $decoded_value['url'];
            }
            return $value ?: '';
        } elseif ($field_type === 'file') {
            $return_type = $field_config['return_type'] ?? 'url';
            // Handle null values safely
            if ($value === null) {
                return '';
            }
            $decoded_value = json_decode($value, true);
            
            // Handle multiple files
            if (is_array($decoded_value) && isset($decoded_value[0])) {
                if ($return_type === 'array') {
                    return $decoded_value; // Return full array
                } else {
                    // Return just the first file's URL
                    return isset($decoded_value[0]['url']) ? $decoded_value[0]['url'] : '';
                }
            }
            
            // Handle single file
            if (is_array($decoded_value) && isset($decoded_value['url'])) {
                if ($return_type === 'array') {
                    return $decoded_value; // Return full object
                } else {
                    return $decoded_value['url']; // Return just the URL
                }
            }
            
            return $value ?: '';
        } elseif ($field_type === 'checkbox') {
            // Handle null values safely
            if ($value === null) {
                return [];
            }
            return $value ? explode(',', $value) : [];
        } elseif ($field_type === 'select') {
            $config = $field_config ?: [];
            $multiple = isset($config['multiple']) && $config['multiple'];
            
            // Handle null values safely
            if ($value === null) {
                return $multiple ? [] : '';
            }
            
            if ($multiple) {
                // Multiple select field - handle various input formats
                if (is_array($value)) {
                    // Already an array, return as is
                    return $value;
                } elseif (is_string($value)) {
                    // Check if it's JSON encoded
                    if (strpos($value, '[') === 0) {
                        $decoded = json_decode($value, true);
                        if (is_array($decoded)) {
                            return $decoded;
                        }
                    }
                    // Fallback to comma-separated string
                    return $value ? explode(',', $value) : [];
                } else {
                    return [];
                }
            } else {
                // Single select field - ensure it's a string
                if (is_array($value)) {
                    return count($value) > 0 ? strval($value[0]) : '';
                } else {
                    return strval($value);
                }
            }
        } elseif ($field_type === 'radio') {
            // Handle null values safely
            if ($value === null) {
                return '';
            }
            return $value ?: '';
        } elseif ($field_type === 'wysiwyg') {
            // Handle null values safely
            if ($value === null) {
                return '';
            }
            return wp_kses_post($value);
        } elseif ($field_type === 'oembed') {
            if (empty($value)) {
                return '';
            }
            // Return the iframe code directly since it's already HTML
            return $value;
        } elseif ($field_type === 'relationship') {
            if (empty($value)) {
                return [];
            }
            // Return array of post IDs
            $post_ids = is_array($value) ? $value : explode(',', $value);
            return array_map('intval', array_filter($post_ids));
        } elseif ($field_type === 'link') {
            if (empty($value)) {
                return '';
            }
            // For link fields, try to extract URL from JSON data and store link data globally
            $link_data = json_decode($value, true);
            if ($link_data && is_array($link_data) && isset($link_data['url'])) {
                // Store link data globally for target handling
                global $ccc_current_link_data;
                $ccc_current_link_data = $link_data;
                return $link_data['url'];
            }
            // If not valid JSON or no URL found, return the original value
            return $value;
        } elseif ($field_type === 'color') {
            // Handle null values safely
            if ($value === null) {
                return '';
            }
            return $value ?: '';
        } elseif ($field_type === 'toggle') {
            // Handle null values safely
            if ($value === null) {
                return false;
            }
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
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

if (!function_exists('get_ccc_toggle_field')) {
    function get_ccc_toggle_field($field_name, $post_id = null, $instance_id = null) {
        $value = get_ccc_field($field_name, $post_id, null, $instance_id);
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
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

if (!function_exists('get_ccc_file_field')) {
    /**
     * Get CCC file field value with options for return type
     * 
     * @param string $field_name The file field name
     * @param string $return_type 'url' for just the URL, 'array' for full file data
     * @param int $post_id Optional post ID (defaults to current post)
     * @param int $component_id Optional component ID
     * @param string $instance_id Optional instance ID for repeaters
     * @return string|array File URL or full file data array
     */
    function get_ccc_file_field($field_name, $return_type = 'url', $post_id = null, $component_id = null, $instance_id = null) {
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
            error_log("CCC: No post ID available for get_ccc_file_field('$field_name')");
            return '';
        }
        
        $fields_table = $wpdb->prefix . 'cc_fields';
        $values_table = $wpdb->prefix . 'cc_field_values';
        
        // Get field config from the fields table
        $field_info_query = $wpdb->prepare(
            "SELECT id, config FROM $fields_table WHERE name = %s",
            $field_name
        );
        $field_info = $wpdb->get_row($field_info_query);
        if (!$field_info) {
            error_log("CCC: File field '$field_name' not found in database.");
            return '';
        }

        $field_db_id = $field_info->id;
        $field_config = json_decode($field_info->config, true);
        
        // Override return type if specified in function call
        if ($return_type !== 'url' && $return_type !== 'array') {
            $return_type = $field_config['return_type'] ?? 'url';
        }

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
        
        if (empty($value)) {
            return '';
        }
        
        // Handle null values safely
        if ($value === null) {
            return '';
        }
        
        $decoded_value = json_decode($value, true);
        
        // Handle multiple files
        if (is_array($decoded_value) && isset($decoded_value[0])) {
            if ($return_type === 'array') {
                return $decoded_value; // Return full array
            } else {
                // Return just the first file's URL
                return isset($decoded_value[0]['url']) ? $decoded_value[0]['url'] : '';
            }
        }
        
        // Handle single file
        if (is_array($decoded_value) && isset($decoded_value['url'])) {
            if ($return_type === 'array') {
                return $decoded_value; // Return full object
            } else {
                return $decoded_value['url']; // Return just the URL
            }
        }
        
        return $value ?: '';
    }
}

// Link field target helper functions

if (!function_exists('get_ccc_field_target')) {
    /**
     * Get CCC link field URL and target attributes in one call
     * 
     * @param string $field_name The link field name
     * @param int $post_id Optional post ID (defaults to current post)
     * @param string $instance_id Optional instance ID for repeaters
     * @return array Array with 'url' and 'target' keys
     */
    function get_ccc_field_target($field_name, $post_id = null, $instance_id = null) {
        // Get the link data to trigger the global storage
        $link_url = get_ccc_field($field_name, $post_id, null, $instance_id);
        
        global $ccc_current_link_data;
        
        $target = '';
        if ($ccc_current_link_data && is_array($ccc_current_link_data) && $ccc_current_link_data['target'] === '_blank') {
            $target = ' target="_blank" rel="noopener noreferrer"';
        }
        
        return [
            'url' => $link_url,
            'target' => $target
        ];
    }
}

if (!function_exists('get_ccc_link_with_target')) {
    /**
     * Get CCC link field URL and target attributes in one call
     * 
     * @param string $field_name The link field name
     * @param int $post_id Optional post ID (defaults to current post)
     * @param string $instance_id Optional instance ID for repeaters
     * @return array Array with 'url' and 'target' keys
     */
    function get_ccc_link_with_target($field_name, $post_id = null, $instance_id = null) {
        // Get the link data to trigger the global storage
        $link_url = get_ccc_field($field_name, $post_id, null, $instance_id);
        
        global $ccc_current_link_data;
        
        $target = '';
        if ($ccc_current_link_data && is_array($ccc_current_link_data) && $ccc_current_link_data['target'] === '_blank') {
            $target = ' target="_blank" rel="noopener noreferrer"';
        }
        
        return [
            'url' => $link_url,
            'target' => $target
        ];
    }
}

if (!function_exists('get_ccc_field_target_value')) {
    /**
     * Get CCC link field target value (e.g., '_blank', '_self')
     * 
     * @param string $field_name The link field name
     * @param int $post_id Optional post ID (defaults to current post)
     * @param string $instance_id Optional instance ID for repeaters
     * @return string Target value (e.g., '_blank', '_self', '_parent', '_top')
     */
    function get_ccc_field_target_value($field_name, $post_id = null, $instance_id = null) {
        // First get the link data to trigger the global storage
        $link_url = get_ccc_field($field_name, $post_id, null, $instance_id);
        
        global $ccc_current_link_data;
        
        if ($ccc_current_link_data && is_array($ccc_current_link_data)) {
            return $ccc_current_link_data['target'] ?? '_self';
        }
        
        return '_self';
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
             'download' => true
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
                    
                    
                    $youtube_url = 'https://www.youtube.com/embed/' . $video_id;
                    if (!empty($youtube_params)) {
                        $youtube_url .= '?' . implode('&', $youtube_params);
                    }
                    
                    return sprintf(
                                                 '<iframe width="%s" height="%s" src="%s" title="YouTube video" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" style="border: none; display: block;"></iframe>',
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
                    
                    
                    $vimeo_url = 'https://player.vimeo.com/video/' . $video_id;
                    if (!empty($vimeo_params)) {
                        $vimeo_url .= '?' . implode('&', $vimeo_params);
                    }
                    
                    return sprintf(
                                                 '<iframe width="%s" height="%s" src="%s" title="Vimeo video" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" style="border: none; display: block;"></iframe>',
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
                                                 '<iframe width="%s" height="%s" src="%s" title="Dailymotion video" frameborder="0" allow="autoplay; fullscreen" style="border: none; display: block;"></iframe>',
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
                                                 '<iframe width="%s" height="%s" src="%s" title="Facebook video" frameborder="0" allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share" style="border: none; display: block;"></iframe>',
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
                                                 '<iframe width="%s" height="%s" src="%s" title="Twitch video" frameborder="0" allow="autoplay; fullscreen" style="border: none; display: block;"></iframe>',
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
                                                 '<iframe width="%s" height="%s" src="%s" title="TikTok video" frameborder="0" allow="autoplay; fullscreen" style="border: none; display: block;"></iframe>',
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
        
        // Enhanced debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("CCC Select Debug - Field: $field_name, Raw values: " . print_r($values, true) . ", Format: $format, Type: " . gettype($values));
            if (is_array($values)) {
                error_log("CCC Select Debug - Array count: " . count($values) . ", Keys: " . implode(', ', array_keys($values)));
            }
        }
        
        // Handle null/empty values
        if ($values === null || $values === '') {
            switch ($format) {
                case 'array':
                    return [];
                case 'string':
                    return '';
                case 'list':
                    return '<ul></ul>';
                case 'options':
                    return '';
                default:
                    return $format === 'array' ? [] : '';
            }
        }
        
        // Handle both single and multiple select fields
        if (is_array($values)) {
            // Multiple select field
            if (empty($values)) {
                switch ($format) {
                    case 'array':
                        return [];
                    case 'string':
                        return '';
                    case 'list':
                        return '<ul></ul>';
                    case 'options':
                        return '';
                    default:
                        return [];
                }
            }
            
            // Filter out empty values
            $values = array_filter($values, function($value) {
                return $value !== null && $value !== '';
            });
            
            switch ($format) {
                case 'array':
                    $result = array_values($values); // Re-index array
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("CCC Select Debug - Returning array format: " . print_r($result, true) . ", Type: " . gettype($result));
                    }
                    return $result;
                case 'string':
                    return implode(', ', $values);
                case 'list':
                    return '<ul><li>' . implode('</li><li>', array_map('esc_html', $values)) . '</li></ul>';
                case 'options':
                    return implode(' | ', array_map('esc_html', $values));
                default:
                    return array_values($values);
            }
        } else {
            // Single select field
            if (empty($values)) {
                switch ($format) {
                    case 'array':
                        return [];
                    case 'string':
                        return '';
                    case 'list':
                        return '<ul></ul>';
                    case 'options':
                        return '';
                    default:
                        return $format === 'array' ? [] : '';
                }
            }
            
            switch ($format) {
                case 'array':
                    $result = [$values];
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("CCC Select Debug - Single select returning array format: " . print_r($result, true) . ", Type: " . gettype($result));
                    }
                    return $result;
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

if (!function_exists('get_ccc_oembed_field')) {
    function get_ccc_oembed_field($field_name, $post_id = null, $instance_id = null, $width = '100%', $height = '400px') {
        $value = get_ccc_field($field_name, $post_id, null, $instance_id);
        if (empty($value)) {
            return '';
        }
        
        // If it's already iframe code, process it with custom dimensions
        if (strpos($value, '<iframe') !== false) {
            $processed_code = $value;
            
            // Replace width attribute
            $processed_code = preg_replace('/width=["\'][^"\']*["\']/', 'width="' . $width . '"', $processed_code);
            if (!preg_match('/width=/', $processed_code)) {
                $processed_code = str_replace('<iframe', '<iframe width="' . $width . '"', $processed_code);
            }
            
            // Replace height attribute
            $processed_code = preg_replace('/height=["\'][^"\']*["\']/', 'height="' . $height . '"', $processed_code);
            if (!preg_match('/height=/', $processed_code)) {
                $processed_code = str_replace('<iframe', '<iframe height="' . $height . '"', $processed_code);
            }
            
            return $processed_code;
        }
        
        // Fallback: return the original value
        return $value;
    }
}

if (!function_exists('get_ccc_oembed_url')) {
    function get_ccc_oembed_url($field_name, $post_id = null, $instance_id = null) {
        $value = get_ccc_field($field_name, $post_id, null, $instance_id);
        if (empty($value)) {
            return '';
        }
        
        // Extract URL from iframe code if present
        if (strpos($value, '<iframe') !== false) {
            preg_match('/src=["\']([^"\']+)["\']/', $value, $matches);
            return isset($matches[1]) ? esc_url($matches[1]) : '';
        }
        
        return esc_url($value);
    }
}

// Test function to debug video field configuration
if (!function_exists('ccc_test_video_field')) {
    function ccc_test_video_field($field_name) {
        echo "<h3>Testing Video Field: $field_name</h3>";
        
        // Debug the field configuration
        ccc_debug_video_field_config($field_name);
        
        // Test the get_ccc_field_video function
        echo "<h4>Video Output:</h4>";
        echo get_ccc_field_video($field_name);
        
        // Test with custom options
        echo "<h4>Video with Custom Options (autoplay=true, muted=true):</h4>";
        echo get_ccc_field_video($field_name, [
            'autoplay' => true,
            'muted' => true,
            'width' => '600px',
            'height' => '400px'
        ]);
        
        
        
        // Show raw field value
        echo "<h4>Raw Field Value:</h4>";
        $raw_value = get_ccc_field($field_name);
        echo "<pre>" . esc_html(print_r($raw_value, true)) . "</pre>";
    }
}

if (!function_exists('get_ccc_video_title')) {
    /**
     * Get video title from video field
     */
    function get_ccc_video_title($field_name, $post_id = null, $instance_id = null) {
        $value = get_ccc_field($field_name, $post_id, null, $instance_id);
        if (is_array($value) && isset($value['title'])) {
            return $value['title'];
        }
        return '';
    }
}

if (!function_exists('get_ccc_link_field')) {
    /**
     * Get link field value with options to extract different parts
     */
    function get_ccc_link_field($field_name, $format = 'url', $post_id = null, $instance_id = null) {
        $value = get_ccc_field($field_name, $post_id, null, $instance_id);
        
        if (empty($value)) {
            return '';
        }
        
        // If it's already a string (URL), return as is
        if (is_string($value) && !json_decode($value)) {
            return $value;
        }
        
        // Try to decode JSON
        $link_data = json_decode($value, true);
        if (!$link_data || !is_array($link_data)) {
            return $value; // Return original if not valid JSON
        }
        
        // Return different parts based on format
        switch ($format) {
            case 'url':
                return $link_data['url'] ?? '';
            case 'title':
                return $link_data['title'] ?? '';
            case 'target':
                return $link_data['target'] ?? '_self';
            case 'type':
                return $link_data['type'] ?? 'external';
            case 'post_id':
                return $link_data['post_id'] ?? '';
            case 'array':
                return $link_data;
            default:
                return $link_data['url'] ?? '';
        }
    }
}

if (!function_exists('get_ccc_link_html')) {
    /**
     * Get link field as complete HTML link with proper target attribute
     */
    function get_ccc_link_html($field_name, $link_text = null, $post_id = null, $instance_id = null, $additional_attributes = []) {
        $value = get_ccc_field($field_name, $post_id, null, $instance_id);
        
        if (empty($value)) {
            return '';
        }
        
        // Try to decode JSON
        $link_data = json_decode($value, true);
        if (!$link_data || !is_array($link_data)) {
            // If not valid JSON, treat as simple URL
            $url = $value;
            $link_text = $link_text ?: $url;
            $target = '_self';
        } else {
            // Extract data from JSON
            $url = $link_data['url'] ?? '';
            $link_text = $link_text ?: ($link_data['title'] ?: $url);
            $target = $link_data['target'] ?? '_self';
        }
        
        if (empty($url)) {
            return '';
        }
        
        // Build HTML attributes
        $attributes = array_merge([
            'href' => esc_url($url),
            'target' => esc_attr($target)
        ], $additional_attributes);
        
        // Add rel="noopener noreferrer" for external links that open in new tab
        if ($target === '_blank') {
            $attributes['rel'] = 'noopener noreferrer';
        }
        
        // Convert attributes array to HTML string
        $attr_string = '';
        foreach ($attributes as $key => $value) {
            $attr_string .= ' ' . esc_attr($key) . '="' . esc_attr($value) . '"';
        }
        
        return '<a' . $attr_string . '>' . esc_html($link_text) . '</a>';
    }
}

if (!function_exists('get_ccc_link_data')) {
    /**
     * Get raw link field data as array (includes url, title, target, type, post_id)
     */
    function get_ccc_link_data($field_name, $post_id = null, $instance_id = null) {
        $value = get_ccc_field($field_name, $post_id, null, $instance_id);
        
        if (empty($value)) {
            return [];
        }
        
        // Try to decode JSON
        $link_data = json_decode($value, true);
        if (!$link_data || !is_array($link_data)) {
            // If not valid JSON, treat as simple URL
            return [
                'type' => 'external',
                'url' => $value,
                'title' => $value,
                'target' => '_self',
                'post_id' => ''
            ];
        }
        
        return $link_data;
    }
}

// New color field helper functions for specific color values
if (!function_exists('get_ccc_field_color')) {
    /**
     * Get the main color value from a color field
     * 
     * @param string $field_name The name of the color field
     * @param int|null $post_id Optional post ID (defaults to current post)
     * @param string|null $instance_id Optional instance ID for repeaters
     * @return string The main color value (hex code)
     */
    function get_ccc_field_color($field_name, $post_id = null, $instance_id = null) {
        $colors = get_ccc_color_enhanced($field_name, $post_id, $instance_id);
        return $colors['main'] ?: '';
    }
}

if (!function_exists('get_ccc_field_hover_color')) {
    /**
     * Get the hover color value from a color field
     * 
     * @param string $field_name The name of the color field
     * @param int|null $post_id Optional post ID (defaults to current post)
     * @param string|null $instance_id Optional instance ID for repeaters
     * @return string The hover color value (hex code)
     */
    function get_ccc_field_hover_color($field_name, $post_id = null, $instance_id = null) {
        $colors = get_ccc_color_enhanced($field_name, $post_id, $instance_id);
        return $colors['hover'] ?: '';
    }
}

if (!function_exists('get_ccc_field_adjusted_color')) {
    /**
     * Get the adjusted color value from a color field
     * 
     * @param string $field_name The name of the color field
     * @param int|null $post_id Optional post ID (defaults to current post)
     * @param string|null $instance_id Optional instance ID for repeaters
     * @return string The adjusted color value (hex code)
     */
    function get_ccc_field_adjusted_color($field_name, $post_id = null, $instance_id = null) {
        $colors = get_ccc_color_enhanced($field_name, $post_id, $instance_id);
        return $colors['adjusted'] ?: '';
    }
}

// Relationship field helper functions
if (!function_exists('get_ccc_relationship_posts')) {
    function get_ccc_relationship_posts($field_name, $post_id = null, $component_id = null, $instance_id = null) {
        $post_ids = get_ccc_field($field_name, $post_id, $component_id, $instance_id);
        if (empty($post_ids)) {
            return [];
        }
        
        $posts = [];
        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            if ($post) {
                $posts[] = $post;
            }
        }
        return $posts;
    }
}

if (!function_exists('get_ccc_relationship_post_ids')) {
    function get_ccc_relationship_post_ids($field_name, $post_id = null, $component_id = null, $instance_id = null) {
        return get_ccc_field($field_name, $post_id, $component_id, $instance_id);
    }
}

if (!function_exists('get_ccc_relationship_post_titles')) {
    function get_ccc_relationship_post_titles($field_name, $post_id = null, $component_id = null, $instance_id = null) {
        $posts = get_ccc_relationship_posts($field_name, $post_id, $component_id, $instance_id);
        return array_map(function($post) {
            return $post->post_title;
        }, $posts);
    }
}

// Additional select field helper functions
if (!function_exists('get_ccc_select_array')) {
    function get_ccc_select_array($field_name, $post_id = null, $instance_id = null) {
        return get_ccc_select_values($field_name, $post_id, $instance_id, 'array');
    }
}

if (!function_exists('get_ccc_select_string')) {
    function get_ccc_select_string($field_name, $post_id = null, $instance_id = null) {
        return get_ccc_select_values($field_name, $post_id, $instance_id, 'string');
    }
}

if (!function_exists('get_ccc_select_list')) {
    function get_ccc_select_list($field_name, $post_id = null, $instance_id = null) {
        return get_ccc_select_values($field_name, $post_id, $instance_id, 'list');
    }
}

if (!function_exists('get_ccc_select_options')) {
    function get_ccc_select_options($field_name, $post_id = null, $instance_id = null) {
        return get_ccc_select_values($field_name, $post_id, $instance_id, 'options');
    }
}

if (!function_exists('get_ccc_select_first')) {
    function get_ccc_select_first($field_name, $post_id = null, $instance_id = null) {
        $values = get_ccc_select_values($field_name, $post_id, $instance_id, 'array');
        return is_array($values) && !empty($values) ? $values[0] : '';
    }
}

if (!function_exists('get_ccc_select_count')) {
    function get_ccc_select_count($field_name, $post_id = null, $instance_id = null) {
        $values = get_ccc_select_values($field_name, $post_id, $instance_id, 'array');
        return is_array($values) ? count($values) : 0;
    }
}

// Debug function for select field values
if (!function_exists('debug_ccc_select_values')) {
    function debug_ccc_select_values($field_name, $post_id = null, $instance_id = null) {
        $values = get_ccc_field($field_name, $post_id, null, $instance_id);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("CCC Select Debug - Field: $field_name, Raw values: " . print_r($values, true));
        }
        
        $select_array = get_ccc_select_values($field_name, $post_id, $instance_id, 'array');
        
        return [
            'raw_values' => $values,
            'raw_type' => gettype($values),
            'is_array' => is_array($values),
            'array_count' => is_array($values) ? count($values) : 0,
            'select_array' => $select_array,
            'select_array_type' => gettype($select_array),
            'select_array_is_array' => is_array($select_array),
            'select_string' => get_ccc_select_values($field_name, $post_id, $instance_id, 'string'),
            'select_list' => get_ccc_select_values($field_name, $post_id, $instance_id, 'list')
        ];
    }
}
