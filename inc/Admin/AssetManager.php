<?php
namespace CCC\Admin;

defined('ABSPATH') || exit;

class AssetManager {
    public function init() {
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function enqueueAssets($hook) {
        $plugin_pages = [
            'toplevel_page_custom-craft-component',
            'custom-craft-component_page_custom-craft-posttypes',
            'custom-craft-component_page_custom-craft-taxonomies',
            'custom-craft-component_page_custom-craft-importexport',
            'custom-craft-component_page_custom-craft-settings',
            'post.php',
            'post-new.php'
        ];

        if (!in_array($hook, $plugin_pages)) {
            $this->enqueueFrontendAssets();
            return;
        }

        $this->enqueueAdminAssets($hook);
    }

    private function enqueueAdminAssets($hook) {
        // Ensure React dependencies are loaded
        wp_enqueue_script('react', 'https://unpkg.com/react@17/umd/react.production.min.js', [], '17.0.2', true);
        wp_enqueue_script('react-dom', 'https://unpkg.com/react-dom@17/umd/react-dom.production.min.js', ['react'], '17.0.2', true);

        $build_dir = plugin_dir_path(__FILE__) . '../../build/assets/';
        $build_url = plugin_dir_url(__FILE__) . '../../build/assets/';

        $js_file = $this->findBuildFile($build_dir, '*.js');
        $css_file = $this->findBuildFile($build_dir, '*.css');

        if ($js_file) {
            wp_enqueue_script('ccc-react', $build_url . $js_file, ['wp-api', 'react', 'react-dom'], '1.2.8', true);
            $this->localizeScript($hook);
        }

        if ($css_file) {
            wp_enqueue_style('ccc-style', $build_url . $css_file, [], '1.2.8');
        }

        wp_enqueue_script('react-beautiful-dnd', 'https://unpkg.com/react-beautiful-dnd@13.1.1/dist/react-beautiful-dnd.min.js', ['react', 'react-dom'], '13.1.1', true);
    }

    private function enqueueFrontendAssets() {
        if (is_singular()) {
            wp_enqueue_script('jquery');
            wp_enqueue_script('ccc-frontend', '', ['jquery'], '1.2.8', true);
            
            $this->addFrontendScript();
            $this->addFrontendStyles();
            
            wp_localize_script('ccc-frontend', 'cccData', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ccc_nonce'),
            ]);
        }
    }

    private function addFrontendScript() {
        $js_code = "
            console.log('hello');
            jQuery(document).ready(function ($) {
                $('#ccc-component-form').on('submit', function (e) {
                    e.preventDefault();
                    var formData = $(this).serializeArray();
                    var data = {
                        action: 'ccc_save_field_values',
                        nonce: cccData.nonce,
                        post_id: $('input[name=\"post_id\"]').val(),
                        ccc_field_values: {}
                    };
                    $.each(formData, function (index, field) {
                        if (field.name.startsWith('ccc_field_values[')) {
                            var fieldId = field.name.match(/\\[(\\d+)\\]/)[1];
                            data.ccc_field_values[fieldId] = field.value;
                        }
                    });
                    $.ajax({
                        url: cccData.ajaxUrl,
                        type: 'POST',
                        data: data,
                        success: function (response) {
                            if (response.success) {
                                alert(response.data.message || 'Field values saved successfully.');
                            } else {
                                alert(response.data.message || 'Failed to save field values.');
                            }
                        },
                        error: function () {
                            alert('Error connecting to server. Please try again.');
                        }
                    });
                });
            });
        ";
        wp_add_inline_script('ccc-frontend', $js_code);
    }

    private function addFrontendStyles() {
        wp_enqueue_style('ccc-frontend-style', '', [], '1.2.8');
        $css_code = "
            .ccc-component {
                margin-bottom: 20px;
                padding: 15px;
                border: 1px solid #e5e7eb;
                border-radius: 5px;
                background-color: #f9fafb;
            }
            .ccc-field {
                margin-bottom: 15px;
            }
            .ccc-field label {
                display: block;
                font-weight: 500;
                margin-bottom: 5px;
                color: #374151;
            }
            .ccc-input {
                width: 100%;
                padding: 8px;
                border: 1px solid #d1d5db;
                border-radius: 4px;
                font-size: 14px;
            }
            .ccc-textarea {
                width: 100%;
                padding: 8px;
                border: 1px solid #d1d5db;
                border-radius: 4px;
                font-size: 14px;
                resize: vertical;
            }
            .ccc-submit-button {
                background-color: #10b981;
                color: white;
                padding: 10px 20px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
            }
            .ccc-submit-button:hover {
                background-color: #059669;
            }
        ";
        wp_add_inline_style('ccc-frontend-style', $css_code);
    }

    private function findBuildFile($dir, $pattern) {
        if (!is_dir($dir)) {
            return '';
        }

        $files = glob($dir . $pattern);
        return $files ? basename($files[0]) : '';
    }

    private function localizeScript($hook) {
        $current_page = $hook;
        if (strpos($hook, 'custom-craft-component_page_') !== false) {
            $current_page = str_replace('custom-craft-component_page_', '', $hook);
        } elseif ($hook === 'toplevel_page_custom-craft-component') {
            $current_page = 'custom-craft-component';
        }

        wp_localize_script('ccc-react', 'cccData', [
            'currentPage' => $current_page,
            'baseUrl' => plugin_dir_url(__FILE__) . '../../build/assets/',
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ccc_nonce'),
            'postId' => isset($_GET['post']) ? intval($_GET['post']) : 0,
        ]);
    }
}
