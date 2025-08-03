<?php

namespace CustomCraftComponent\Services;

class AIService {
    
    private $api_key;
    private $component_service;
    private $field_service;
    
    public function __init__() {
        $this->api_key = get_option('ccc_openai_api_key', '');
        $this->component_service = new ComponentService();
        $this->field_service = new FieldService();
    }
    
    /**
     * Generate component using ChatGPT API
     */
    public function generate_component_from_chatgpt($prompt) {
        if (empty($this->api_key)) {
            return [
                'success' => false,
                'message' => 'OpenAI API key not configured'
            ];
        }
        
        try {
            // Call ChatGPT API
            $response = $this->call_chatgpt_api($prompt);
            
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
     * Call ChatGPT API
     */
    private function call_chatgpt_api($prompt) {
        $available_fields = ['text', 'textarea', 'image', 'video', 'color', 'select', 'checkbox', 'radio', 'wysiwyg', 'repeater'];
        
        $system_prompt = "You are a WordPress component generator. Create components based on user requests.

Available field types: " . implode(', ', $available_fields) . "

Field type guidelines:
- \"text\": Single line inputs (names, titles, prices, emails, URLs, phone numbers)
- \"textarea\": Multi-line content (descriptions, testimonials, long text)
- \"image\": Image uploads (photos, logos, backgrounds)
- \"video\": Video uploads or video URLs
- \"color\": Color pickers (background colors, theme colors)
- \"select\": Dropdown choices (categories, ratings, options)
- \"checkbox\": True/false options (featured, active, required)
- \"radio\": Single choice from multiple options
- \"wysiwyg\": Rich text editors (formatted content)
- \"repeater\": Repeatable field groups (lists, galleries, features)

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
      \"placeholder\": \"Helpful placeholder text\"
    }
  ]
}

Be creative and think about what would be most useful for the user's request.";

        $api_url = 'https://api.openai.com/v1/chat/completions';
        
        $body = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => 500,
            'temperature' => 0.7
        ];
        
        $response = wp_remote_post($api_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($body),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'API request failed: ' . $response->get_error_message()
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
            'data' => $data['choices'][0]['message']['content']
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
            
            $component_id = $this->component_service->create($component);
            
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
                
                $field_id = $this->field_service->create($field);
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
     * Get API usage info
     */
    public function get_api_info() {
        return [
            'has_api_key' => !empty($this->api_key),
            'api_key_masked' => !empty($this->api_key) ? substr($this->api_key, 0, 8) . '...' : '',
            'estimated_cost' => '~$0.002 per request (GPT-3.5-turbo)'
        ];
    }
} 