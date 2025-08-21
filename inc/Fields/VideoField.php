<?php

namespace CCC\Fields;

use CCC\Fields\BaseField;
use Exception;

class VideoField extends BaseField
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
        
        // Parse config to get video field specific options
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
            'allowed_sources' => ['file', 'url'], // 'file', 'url', 'youtube', 'vimeo', etc.
            'max_file_size' => 100, // MB
            'return_type' => 'array', // 'url', 'id', 'array'
            'multiple' => false,
            'show_preview' => true,
            'show_download' => true,
            'show_delete' => true,
            'player_options' => [
                'controls' => true,
                'autoplay' => false,
                'muted' => false,
                'loop' => false,
                'download' => true
            ]
        ], $config);
        
        // Process the field value to get video information
        $processed_value = $this->processFieldValue($field_value, $config);
        
        // Output the hidden input for the value and div for React component
        echo '<div class="w-full mb-4">';
        echo '<input type="hidden" name="' . esc_attr($field_name) . '" value="' . esc_attr($field_value) . '" />';
        echo '<div id="ccc-video-field-' . esc_attr($instance_id) . '" 
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
        
        // If it's an array (multiple videos), sanitize each one
        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $video) {
                $sanitized[] = $this->sanitizeSingleVideo($video);
            }
            return array_filter($sanitized); // Remove empty values
        }
        
        // Single video
        return $this->sanitizeSingleVideo($value);
    }
    
    private function sanitizeSingleVideo($video)
    {
        if (empty($video)) {
            return '';
        }
        
        // If it's already a video ID, validate it exists
        if (is_numeric($video)) {
            $attachment = get_post($video);
            if ($attachment && strpos($attachment->post_mime_type, 'video/') === 0) {
                return $video;
            }
            return '';
        }
        
        // If it's a URL, validate it's a valid video URL or attachment URL
        if (is_string($video) && filter_var($video, FILTER_VALIDATE_URL)) {
            // Check if this URL belongs to a WordPress attachment
            $attachment_id = attachment_url_to_postid($video);
            if ($attachment_id) {
                $attachment = get_post($attachment_id);
                if ($attachment && strpos($attachment->post_mime_type, 'video/') === 0) {
                    return $attachment_id;
                }
            }
            
            // If it's an external video URL, validate it's a supported platform
            if ($this->isValidExternalVideoUrl($video)) {
                return $video;
            }
            
            return '';
        }
        
        // If it's an object/array with id property (from React component)
        if (is_array($video) && isset($video['id'])) {
            $video_id = $video['id'];
            if (is_numeric($video_id)) {
                $attachment = get_post($video_id);
                if ($attachment && strpos($attachment->post_mime_type, 'video/') === 0) {
                    return $video_id;
                }
            }
            return '';
        }
        
        // If it's an object with id property (from React component)
        if (is_object($video) && isset($video->id)) {
            $video_id = $video->id;
            if (is_numeric($video_id)) {
                $attachment = get_post($video_id);
                if ($attachment && strpos($attachment->post_mime_type, 'video/') === 0) {
                    return $video_id;
                }
            }
            return '';
        }
        
        return '';
    }
    
    private function isValidExternalVideoUrl($url)
    {
        $supported_platforms = [
            'youtube.com',
            'youtu.be',
            'vimeo.com',
            'dailymotion.com',
            'facebook.com',
            'twitch.tv',
            'tiktok.com'
        ];
        
        foreach ($supported_platforms as $platform) {
            if (strpos($url, $platform) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    public function getVideoInfo($video_id)
    {
        if (empty($video_id)) {
            return null;
        }
        
        $attachment = get_post($video_id);
        if (!$attachment) {
            return null;
        }
        
        $file_path = get_attached_file($video_id);
        $file_url = wp_get_attachment_url($video_id);
        $file_type = get_post_mime_type($video_id);
        $file_size = file_exists($file_path) ? filesize($file_path) : 0;
        
        // Get video metadata
        $video_metadata = wp_get_attachment_metadata($video_id);
        $duration = isset($video_metadata['length']) ? $video_metadata['length'] : 0;
        $width = isset($video_metadata['width']) ? $video_metadata['width'] : 0;
        $height = isset($video_metadata['height']) ? $video_metadata['height'] : 0;
        
        return [
            'id' => $video_id,
            'title' => $attachment->post_title,
            'filename' => basename($file_path),
            'url' => $file_url,
            'type' => $file_type,
            'size' => $file_size,
            'size_formatted' => size_format($file_size),
            'date' => $attachment->post_date,
            'duration' => $duration,
            'width' => $width,
            'height' => $height,
            'thumbnail' => wp_get_attachment_image_src($video_id, 'thumbnail')[0] ?? null,
            'medium' => wp_get_attachment_image_src($video_id, 'medium')[0] ?? null,
            'large' => wp_get_attachment_image_src($video_id, 'large')[0] ?? null
        ];
    }
    
    private function processFieldValue($value, $config)
    {
        if (empty($value)) {
            return '';
        }
        
        error_log("CCC DEBUG: VideoField processFieldValue called with value: " . json_encode($value));
        
        // If it's a JSON string, decode it
        if (is_string($value)) {
            try {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $value = $decoded;
                    error_log("CCC DEBUG: VideoField decoded JSON value: " . json_encode($value));
                }
            } catch (Exception $e) {
                error_log("CCC DEBUG: VideoField JSON decode error: " . $e->getMessage());
                // Keep original value if JSON decode fails
            }
        }
        
        // If it's an array (multiple videos), process each one
        if (is_array($value)) {
            error_log("CCC DEBUG: VideoField processing array of videos, count: " . count($value));
            $processed_videos = [];
            foreach ($value as $video) {
                $video_info = $this->getVideoInfoFromValue($video);
                if ($video_info) {
                    $processed_videos[] = $video_info;
                }
            }
            error_log("CCC DEBUG: VideoField processed videos result: " . json_encode($processed_videos));
            return $processed_videos;
        }
        
        // Single video
        error_log("CCC DEBUG: VideoField processing single video");
        $result = $this->getVideoInfoFromValue($value);
        error_log("CCC DEBUG: VideoField single video result: " . json_encode($result));
        return $result;
    }
    
    private function getVideoInfoFromValue($video_value)
    {
        if (empty($video_value)) {
            return null;
        }
        
        error_log("CCC DEBUG: VideoField getVideoInfoFromValue called with: " . json_encode($video_value));
        
        $video_id = null;
        
        // Extract video ID from various formats
        if (is_numeric($video_value)) {
            $video_id = $video_value;
            error_log("CCC DEBUG: VideoField numeric video_value: " . $video_id);
        } elseif (is_array($video_value) && isset($video_value['id'])) {
            $video_id = $video_value['id'];
            error_log("CCC DEBUG: VideoField array video_value with id: " . $video_id);
        } elseif (is_object($video_value) && isset($video_value->id)) {
            $video_id = $video_value->id;
            error_log("CCC DEBUG: VideoField object video_value with id: " . $video_id);
        } elseif (is_string($video_value) && filter_var($video_value, FILTER_VALIDATE_URL)) {
            $video_id = attachment_url_to_postid($video_value);
            error_log("CCC DEBUG: VideoField URL converted to ID: " . $video_id);
        }
        
        // If we have a numeric video ID, get the video info from WordPress
        if ($video_id && is_numeric($video_id)) {
            error_log("CCC DEBUG: VideoField getting video info for ID: " . $video_id);
            return $this->getVideoInfo($video_id);
        }
        
        // If it's a temporary uploaded video (has temp_id), return the video data as is
        if (is_array($video_value) && isset($video_value['temp_id']) && isset($video_value['is_temp']) && $video_value['is_temp'] === true) {
            error_log("CCC DEBUG: VideoField temporary video detected: " . json_encode($video_value));
            return $video_value;
        }
        
        // If it's an object with temp_id property
        if (is_object($video_value) && isset($video_value->temp_id) && isset($video_value->is_temp) && $video_value->is_temp === true) {
            error_log("CCC DEBUG: VideoField temporary video object detected: " . json_encode($video_value));
            return [
                'temp_id' => $video_value->temp_id,
                'name' => $video_value->name ?? '',
                'type' => $video_value->type ?? '',
                'size' => $video_value->size ?? 0,
                'is_temp' => true
            ];
        }
        
        // If it's a media library video with id, url, type, name structure
        if (is_array($video_value) && isset($video_value['id']) && isset($video_value['url']) && isset($video_value['type'])) {
            error_log("CCC DEBUG: VideoField media library video array detected: " . json_encode($video_value));
            // This is a media library video, get the full info
            if (is_numeric($video_value['id'])) {
                error_log("CCC DEBUG: VideoField getting video info for media library video ID: " . $video_value['id']);
                return $this->getVideoInfo($video_value['id']);
            }
        }
        
        // If it's an object with id, url, type, name structure
        if (is_object($video_value) && isset($video_value->id) && isset($video_value->url) && isset($video_value->type)) {
            error_log("CCC DEBUG: VideoField media library video object detected: " . json_encode($video_value));
            // This is a media library video, get the full info
            if (is_numeric($video_value->id)) {
                error_log("CCC DEBUG: VideoField getting video info for media library video object ID: " . $video_value->id);
                return $this->getVideoInfo($video_value->id);
            }
        }
        
        // If it's an external video URL, create a structured object
        if (is_string($video_value) && filter_var($video_value, FILTER_VALIDATE_URL)) {
            $video_type = $this->detectVideoType($video_value);
            error_log("CCC DEBUG: VideoField external video URL detected: " . $video_value . " (type: " . $video_type . ")");
            return [
                'url' => $video_value,
                'type' => $video_type,
                'title' => $this->getVideoTitleFromUrl($video_value, $video_type),
                'description' => $this->getVideoDescriptionFromUrl($video_value, $video_type),
                'is_external' => true
            ];
        }
        
        error_log("CCC DEBUG: VideoField returning original value: " . json_encode($video_value));
        // If we can't get video info, return the original value for the React component to handle
        return $video_value;
    }
    
    private function detectVideoType($url)
    {
        if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
            return 'youtube';
        } elseif (strpos($url, 'vimeo.com') !== false) {
            return 'vimeo';
        } elseif (strpos($url, 'dailymotion.com') !== false) {
            return 'dailymotion';
        } elseif (strpos($url, 'facebook.com') !== false && strpos($url, '/videos/') !== false) {
            return 'facebook';
        } elseif (strpos($url, 'twitch.tv') !== false) {
            return 'twitch';
        } elseif (strpos($url, 'tiktok.com') !== false) {
            return 'tiktok';
        } else {
            return 'url';
        }
    }
    
    private function getVideoTitleFromUrl($url, $type)
    {
        switch ($type) {
            case 'youtube':
                $video_id = $this->extractYoutubeId($url);
                return $video_id ? "YouTube Video ($video_id)" : "YouTube Video";
            case 'vimeo':
                $video_id = $this->extractVimeoId($url);
                return $video_id ? "Vimeo Video ($video_id)" : "Vimeo Video";
            case 'dailymotion':
                $video_id = $this->extractDailymotionId($url);
                return $video_id ? "Dailymotion Video ($video_id)" : "Dailymotion Video";
            case 'facebook':
                $video_id = $this->extractFacebookVideoId($url);
                return $video_id ? "Facebook Video ($video_id)" : "Facebook Video";
            case 'twitch':
                $video_id = $this->extractTwitchVideoId($url);
                return $video_id ? "Twitch Video ($video_id)" : "Twitch Video";
            case 'tiktok':
                $video_id = $this->extractTiktokVideoId($url);
                return $video_id ? "TikTok Video ($video_id)" : "TikTok Video";
            default:
                return "External Video";
        }
    }
    
    private function getVideoDescriptionFromUrl($url, $type)
    {
        switch ($type) {
            case 'youtube':
                $video_id = $this->extractYoutubeId($url);
                return $video_id ? "YouTube: https://www.youtube.com/watch?v=$video_id" : "YouTube Video";
            case 'vimeo':
                $video_id = $this->extractVimeoId($url);
                return $video_id ? "Vimeo: https://vimeo.com/$video_id" : "Vimeo Video";
            case 'dailymotion':
                $video_id = $this->extractDailymotionId($url);
                return $video_id ? "Dailymotion: $url" : "Dailymotion Video";
            case 'facebook':
                $video_id = $this->extractFacebookVideoId($url);
                return $video_id ? "Facebook: $url" : "Facebook Video";
            case 'twitch':
                $video_id = $this->extractTwitchVideoId($url);
                return $video_id ? "Twitch: $url" : "Twitch Video";
            case 'tiktok':
                $video_id = $this->extractTiktokVideoId($url);
                return $video_id ? "TikTok: $url" : "TikTok Video";
            default:
                return "External: $url";
        }
    }
    
    private function extractYoutubeId($url)
    {
        $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/';
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    private function extractVimeoId($url)
    {
        $pattern = '/vimeo\.com\/(?:video\/)?(\d+)/';
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    private function extractDailymotionId($url)
    {
        $pattern = '/dailymotion\.com\/video\/([a-zA-Z0-9]+)/';
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    private function extractFacebookVideoId($url)
    {
        $pattern = '/facebook\.com\/[^\/]+\/videos\/(\d+)/';
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    private function extractTwitchVideoId($url)
    {
        $pattern = '/twitch\.tv\/videos\/(\d+)/';
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    private function extractTiktokVideoId($url)
    {
        $pattern = '/tiktok\.com\/@[^\/]+\/video\/(\d+)/';
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
