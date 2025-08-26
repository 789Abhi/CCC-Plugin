<?php

namespace CCC\Services;

use CCC\Models\Field;
use CCC\Models\Component;
use Exception;

class AIService {
    
    private $api_key;
    private $component_service;
    private $field_service;
    private $rate_limit_key;
    private $max_requests_per_hour;
    
    public function __construct() {
        $this->rate_limit_key = 'ccc_ai_rate_limit';
        $this->max_requests_per_hour = $this->getRateLimit();
    }
    
    /**
     * Get API key using proxy key
     */
    private function getApiKey($proxy_key = null) {
        if ($proxy_key === null) {
            // Try to get from session or request
            $proxy_key = $this->getProxyKeyFromRequest();
        }
        
        if (empty($proxy_key)) {
            error_log('CCC AIService: No proxy key provided');
            return '';
        }
        
        // Get API key using proxy key
        $api_key = \CCC\Admin\AdminSettings::getApiKeyWithProxy($proxy_key);
        
        // Validate API key format for security
        if (!empty($api_key) && !$this->isValidApiKey($api_key)) {
            error_log('CCC AIService: Invalid API key format detected');
            return '';
        }
        
        return $api_key;
    }
    
    /**
     * Get proxy key from request (for AJAX calls)
     */
    private function getProxyKeyFromRequest() {
        // Try to get from POST data first
        if (isset($_POST['proxy_key'])) {
            return sanitize_text_field($_POST['proxy_key']);
        }
        
        // Try to get from GET data
        if (isset($_GET['proxy_key'])) {
            return sanitize_text_field($_GET['proxy_key']);
        }
        
        // Try to get from session
        if (isset($_SESSION['ccc_proxy_key'])) {
            return $_SESSION['ccc_proxy_key'];
        }
        
        return null;
    }
    
    /**
     * Validate API key format for security
     */
    private function isValidApiKey($api_key) {
        // OpenAI API keys start with 'sk-' and are typically 51 characters long
        if (empty($api_key) || strlen($api_key) < 20 || strlen($api_key) > 100) {
            return false;
        }
        
        // Check if it starts with 'sk-' (OpenAI format)
        if (!preg_match('/^sk-[a-zA-Z0-9]{20,}$/', $api_key)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get rate limit from admin settings
     */
    private function getRateLimit() {
        $config = \CCC\Admin\AdminSettings::getConfig();
        return $config['rate_limit'] ?? 50;
    }
    
    /**
     * Get AI model from admin settings
     */
    private function getModel() {
        $config = \CCC\Admin\AdminSettings::getConfig();
        return $config['model'] ?? 'gpt-4o-mini';
    }
    
    /**
     * Check rate limiting with IP-based protection
     */
    private function checkRateLimit() {
        $current_hour = date('Y-m-d H:00:00');
        $user_ip = $this->getUserIP();
        
        // Check global rate limit
        $rate_limit_data = get_option($this->rate_limit_key, []);
        
        // Clean old entries
        $rate_limit_data = array_filter($rate_limit_data, function($timestamp) {
            return $timestamp > (time() - 3600); // Keep only last hour
        });
        
        // Check if global limit exceeded
        if (count($rate_limit_data) >= $this->max_requests_per_hour) {
            return [
                'allowed' => false,
                'message' => 'Global rate limit exceeded. Maximum ' . $this->max_requests_per_hour . ' requests per hour.',
                'reset_time' => strtotime($current_hour) + 3600
            ];
        }
        
        // Check IP-based rate limiting (additional security)
        $ip_rate_limit_key = 'ccc_ai_ip_rate_limit_' . md5($user_ip);
        $ip_rate_limit_data = get_option($ip_rate_limit_key, []);
        
        // Clean old IP entries
        $ip_rate_limit_data = array_filter($ip_rate_limit_data, function($timestamp) {
            return $timestamp > (time() - 3600);
        });
        
        // IP-based limit (more restrictive)
        $max_ip_requests = min(10, $this->max_requests_per_hour / 5); // 1/5 of global limit or max 10
        
        if (count($ip_rate_limit_data) >= $max_ip_requests) {
            return [
                'allowed' => false,
                'message' => 'IP rate limit exceeded. Please try again later.',
                'reset_time' => strtotime($current_hour) + 3600
            ];
        }
        
        // Add current request to both global and IP limits
        $rate_limit_data[] = time();
        $ip_rate_limit_data[] = time();
        
        update_option($this->rate_limit_key, $rate_limit_data);
        update_option($ip_rate_limit_key, $ip_rate_limit_data);
        
        // Log the request for security monitoring
        $this->logApiRequest($user_ip);
        
        return ['allowed' => true];
    }
    
    /**
     * Get user IP address for rate limiting
     */
    private function getUserIP() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return 'unknown';
    }
    
    /**
     * Log API requests for security monitoring
     */
    private function logApiRequest($user_ip) {
        $log_data = [
            'timestamp' => current_time('mysql'),
            'ip' => $user_ip,
            'user_id' => get_current_user_id(),
            'action' => 'ai_component_generation'
        ];
        
        // Store in WordPress options (you can also use a custom table)
        $logs = get_option('ccc_ai_request_logs', []);
        $logs[] = $log_data;
        
        // Keep only last 1000 logs
        if (count($logs) > 1000) {
            $logs = array_slice($logs, -1000);
        }
        
        update_option('ccc_ai_request_logs', $logs);
    }
    
    /**
     * Generate component using ChatGPT API (requires proxy key)
     */
    public function generate_component_from_chatgpt($prompt, $proxy_key = null) {
        // Check rate limiting
        $rate_check = $this->checkRateLimit();
        if (!$rate_check['allowed']) {
            return [
                'success' => false,
                'message' => $rate_check['message'],
                'rate_limited' => true,
                'reset_time' => $rate_check['reset_time']
            ];
        }
        
        // Get API key using proxy key
        $api_key = $this->getApiKey($proxy_key);
        if (empty($api_key)) {
            return [
                'success' => false,
                'message' => 'Invalid proxy key or API key not configured'
            ];
        }
        
        try {
            // Call ChatGPT API
            $response = $this->call_chatgpt_api($prompt, $api_key);
            
            if (!$response['success']) {
                return $response;
            }
            
            // Parse the JSON response
            $component_data = json_decode($response['data'], true);
            
            if (!$component_data || !isset($component_data['component']) || !isset($component_data['fields'])) {
                return [
                    'success' => false,
                    'message' => 'Invalid JSON response from ChatGPT'
                ];
            }
            
            // Create component in database
            $component_result = $this->create_component_from_ai($component_data);
            
            return $component_result;
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Call ChatGPT API with GPT-4o-mini
     */
    private function call_chatgpt_api($prompt, $api_key) {
        $available_fields = [
            'text', 'textarea', 'image', 'video', 'oembed', 'relationship', 
            'link', 'email', 'number', 'range', 'file', 'repeater', 
            'wysiwyg', 'color', 'select', 'checkbox', 'radio', 'toggle'
        ];
        
        $system_prompt = "You are a WordPress component generator. Create components based on user requests.

Available field types: " . implode(', ', $available_fields) . "

Field type guidelines:
- \"text\": Single line inputs (names, titles, prices, URLs, phone numbers)
- \"textarea\": Multi-line content (descriptions, testimonials, long text)
- \"image\": Image uploads (photos, logos, backgrounds)
- \"video\": Video uploads or video URLs
- \"oembed\": External content embeds (YouTube, Vimeo, social media)
- \"relationship\": Related posts/pages selection
- \"link\": URL links with target options
- \"email\": Email address inputs
- \"number\": Numeric inputs with validation
- \"range\": Slider inputs with min/max values
- \"file\": File uploads
- \"repeater\": Repeatable field groups (lists, galleries, features)
- \"wysiwyg\": Rich text editors (formatted content)
- \"color\": Color pickers (background colors, theme colors)
- \"select\": Dropdown choices (categories, ratings, options)
- \"checkbox\": True/false options (featured, active, required)
- \"radio\": Single choice from multiple options
- \"toggle\": On/off switches

Generate a creative, useful component based on the user's request. Think about what fields would be most helpful for that type of component.

Return ONLY valid JSON in this exact format:
{
  \"component\": {
    \"name\": \"Creative Component Name\",
    \"handle\": \"creative_component_handle\",
    \"description\": \"Brief description of what this component does\"
  },
  \"fields\": [
    {
      \"label\": \"Field Label\",
      \"name\": \"field_name\",
      \"type\": \"field_type\",
      \"required\": true/false,
      \"placeholder\": \"Helpful placeholder text\",
      \"config\": {}
    }
  ]
}

Be creative and think about what would be most useful for the user's request. Use appropriate field types and provide meaningful labels and placeholders.";

        $api_url = 'https://api.openai.com/v1/chat/completions';
        
        $body = [
            'model' => $this->getModel(), // Using GPT-4o-mini for better performance
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => 800, // Increased for better responses
            'temperature' => 0.7,
            'top_p' => 0.9,
            'frequency_penalty' => 0.1,
            'presence_penalty' => 0.1
        ];
        
        $response = wp_remote_post($api_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'OpenAI-Beta' => 'assistants=v1'
            ],
            'body' => json_encode($body),
            'timeout' => 60 // Increased timeout for GPT-4
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'API request failed: ' . $response->get_error_message()
            ];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            $error_data = json_decode($body, true);
            
            $error_message = 'API request failed with status ' . $response_code;
            if (isset($error_data['error']['message'])) {
                $error_message .= ': ' . $error_data['error']['message'];
            }
            
            return [
                'success' => false,
                'message' => $error_message
            ];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['choices'][0]['message']['content'])) {
            return [
                'success' => false,
                'message' => 'Invalid response from ChatGPT API'
            ];
        }
        
        return [
            'success' => true,
            'data' => $data['choices'][0]['message']['content'],
            'usage' => $data['usage'] ?? null
        ];
    }
    
    /**
     * Create component from AI-generated data
     */
    private function create_component_from_ai($component_data) {
        try {
            // Create component
            $component = [
                'name' => sanitize_text_field($component_data['component']['name']),
                'handle' => sanitize_title($component_data['component']['handle']),
                'description' => sanitize_textarea_field($component_data['component']['description']),
                'status' => 'active'
            ];
            
            $component_id = $this->component_service->createComponent($component['name'], $component['handle']);
            
            if (!$component_id) {
                return [
                    'success' => false,
                    'message' => 'Failed to create component'
                ];
            }
            
            // Create fields
            $fields_created = 0;
            foreach ($component_data['fields'] as $field_data) {
                $field = [
                    'component_id' => $component_id,
                    'label' => sanitize_text_field($field_data['label']),
                    'name' => sanitize_title($field_data['name']),
                    'type' => sanitize_text_field($field_data['type']),
                    'required' => !empty($field_data['required']),
                    'placeholder' => sanitize_text_field($field_data['placeholder'] ?? ''),
                    'config' => isset($field_data['config']) ? json_encode($field_data['config']) : '{}',
                    'order' => $fields_created + 1
                ];
                
                $field_id = $this->field_service->createField(
                    $field['component_id'],
                    $field['label'],
                    $field['name'],
                    $field['type'],
                    $field['config']
                );
                
                if ($field_id) {
                    $fields_created++;
                }
            }
            
            return [
                'success' => true,
                'message' => "Component '{$component['name']}' created successfully with {$fields_created} fields",
                'component_id' => $component_id,
                'fields_created' => $fields_created,
                'component' => $component,
                'fields' => $component_data['fields']
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error creating component: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Process manual ChatGPT response
     */
    public function process_manual_chatgpt_response($json_response) {
        try {
            $component_data = json_decode($json_response, true);
            
            if (!$component_data || !isset($component_data['component']) || !isset($component_data['fields'])) {
                return [
                    'success' => false,
                    'message' => 'Invalid JSON format. Please provide valid component JSON.'
                ];
            }
            
            return $this->create_component_from_ai($component_data);
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error processing JSON: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get API usage info and rate limit status
     */
    public function get_api_info() {
        $rate_limit_data = get_option($this->rate_limit_key, []);
        $current_hour = date('Y-m-d H:00:00');
        
        // Clean old entries
        $rate_limit_data = array_filter($rate_limit_data, function($timestamp) {
            return $timestamp > (time() - 3600);
        });
        
        $requests_this_hour = count($rate_limit_data);
        $remaining_requests = max(0, $this->max_requests_per_hour - $requests_this_hour);
        
        return [
            'has_api_key' => !empty($this->api_key),
            'api_key_masked' => !empty($this->api_key) ? substr($this->api_key, 0, 8) . '...' : '',
            'rate_limit' => [
                'max_per_hour' => $this->max_requests_per_hour,
                'used_this_hour' => $requests_this_hour,
                'remaining' => $remaining_requests,
                'reset_time' => strtotime($current_hour) + 3600
            ],
            'estimated_cost' => '~$0.00015 per request (GPT-4o-mini)',
            'model' => $this->getModel()
        ];
    }
    
    /**
     * Update rate limit settings
     */
    public function update_rate_limit($max_requests_per_hour) {
        $this->max_requests_per_hour = intval($max_requests_per_hour);
        update_option('ccc_ai_rate_limit_max', $this->max_requests_per_hour);
        return true;
    }
    
    /**
     * Get current rate limit settings
     */
    public function get_rate_limit_settings() {
        return [
            'max_requests_per_hour' => $this->max_requests_per_hour,
            'current_usage' => count(get_option($this->rate_limit_key, []))
        ];
    }
} 