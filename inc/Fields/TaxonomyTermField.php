<?php

namespace CCC\Fields;

use CCC\Fields\BaseField;

class TaxonomyTermField extends BaseField
{
    protected $type = 'taxonomy_term';

    public function render($field_name, $field_value, $field_config = [])
    {
        $config = array_merge([
            'taxonomy' => 'category',
            'multiple' => false,
            'allow_empty' => true,
            'placeholder' => 'Select terms...',
            'searchable' => true,
            'hierarchical' => false,
            'show_count' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        ], $field_config);

        // Enqueue WordPress scripts for taxonomy selection
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-autocomplete');
        wp_enqueue_script('jquery-ui-sortable');

        $field_id = 'field_' . uniqid();
        
        // Get taxonomy terms
        $terms = get_terms([
            'taxonomy' => $config['taxonomy'],
            'hide_empty' => false,
            'orderby' => $config['orderby'],
            'order' => $config['order']
        ]);

        // Process field value
        $selected_terms = $this->processFieldValue($field_value, $config['taxonomy']);
        
        // Convert to JSON for React component
        $processed_value = json_encode($selected_terms);

        return sprintf(
            '<div class="ccc-field ccc-taxonomy-term-field" data-field-name="%s" data-field-value=\'%s\' data-field-config=\'%s\' data-field-id="%s"></div>',
            esc_attr($field_name),
            esc_attr($processed_value),
            esc_attr(json_encode($config)),
            esc_attr($field_id)
        );
    }

    public function sanitize($value)
    {
        if (empty($value)) {
            return '';
        }

        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (!is_array($value)) {
            return '';
        }

        $sanitized_terms = [];
        foreach ($value as $term) {
            if (isset($term['term_id']) && is_numeric($term['term_id'])) {
                $sanitized_terms[] = [
                    'term_id' => intval($term['term_id']),
                    'name' => sanitize_text_field($term['name'] ?? ''),
                    'slug' => sanitize_text_field($term['slug'] ?? ''),
                    'taxonomy' => sanitize_text_field($term['taxonomy'] ?? '')
                ];
            }
        }

        return json_encode($sanitized_terms);
    }

    private function processFieldValue($field_value, $taxonomy)
    {
        if (empty($field_value)) {
            return [];
        }

        if (is_string($field_value)) {
            $field_value = json_decode($field_value, true);
        }

        if (!is_array($field_value)) {
            return [];
        }

        $processed_terms = [];
        foreach ($field_value as $term) {
            if (isset($term['term_id']) && is_numeric($term['term_id'])) {
                $term_obj = get_term($term['term_id'], $taxonomy);
                if ($term_obj && !is_wp_error($term_obj)) {
                    $processed_terms[] = [
                        'term_id' => $term_obj->term_id,
                        'name' => $term_obj->name,
                        'slug' => $term_obj->slug,
                        'taxonomy' => $term_obj->taxonomy,
                        'description' => $term_obj->description,
                        'count' => $term_obj->count,
                        'parent' => $term_obj->parent,
                        'term_taxonomy_id' => $term_obj->term_taxonomy_id
                    ];
                }
            }
        }

        return $processed_terms;
    }

    public function getValue($field_value)
    {
        if (empty($field_value)) {
            return [];
        }

        if (is_string($field_value)) {
            $field_value = json_decode($field_value, true);
        }

        if (!is_array($field_value)) {
            return [];
        }

        $terms = [];
        foreach ($field_value as $term_data) {
            if (isset($term_data['term_id'])) {
                $term = get_term($term_data['term_id']);
                if ($term && !is_wp_error($term)) {
                    $terms[] = $term;
                }
            }
        }

        return $terms;
    }
} 