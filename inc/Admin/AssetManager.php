<?php
namespace CCC\Admin;

defined('ABSPATH') || exit;

class AssetManager {
  public function init() {
      add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
  }

  public function enqueueAssets($hook) {
      error_log("CCC AssetManager: enqueueAssets called with hook: $hook");
      
      // For debugging, let's log ALL hooks to see what we're actually getting
      if (strpos($hook, 'custom-craft') !== false) {
          error_log("CCC AssetManager: Found custom-craft hook: $hook");
      }
      
      // Check if this is one of our plugin pages
      $is_plugin_page = false;
      
      // Check for main page
      if ($hook === 'toplevel_page_custom-craft-component') {
          $is_plugin_page = true;
          error_log("CCC AssetManager: Main plugin page detected");
      }
      
      // Check for submenu pages - WordPress sanitizes the slug, so we need to check for both variations
      if (strpos($hook, 'custom-craft-component_page_') === 0 || strpos($hook, 'custom-components_page_') === 0) {
          $is_plugin_page = true;
          error_log("CCC AssetManager: Submenu page detected: $hook");
      }
      
      // Check for post edit pages
      if (in_array($hook, ['post.php', 'post-new.php'])) {
          $is_plugin_page = true;
          error_log("CCC AssetManager: Post edit page detected - will load metabox assets");
      }

      if (!$is_plugin_page) {
          error_log("CCC AssetManager: Not a plugin page, enqueuing frontend assets");
          $this->enqueueFrontendAssets();
          return;
      }

      error_log("CCC AssetManager: Plugin page detected, enqueuing admin assets");
      $this->enqueueAdminAssets($hook);
  }

  private function enqueueAdminAssets($hook) {
      error_log("CCC AssetManager: enqueueAdminAssets called with hook: $hook");
      
      // Ensure React dependencies are loaded
      wp_enqueue_script('react', 'https://unpkg.com/react@17/umd/react.production.min.js', [], '17.0.2', true);
      wp_enqueue_script('react-dom', 'https://unpkg.com/react-dom@17/umd/react-dom.production.min.js', ['react'], '17.0.2', true);

      // Enqueue jQuery UI for sortable functionality
      wp_enqueue_script('jquery-ui-sortable');
      wp_enqueue_style('wp-jquery-ui-dialog');

      // Enqueue WordPress Color Picker
      wp_enqueue_style('wp-color-picker');
      wp_enqueue_script('wp-color-picker');

      $build_dir = plugin_dir_path(__FILE__) . '../../build/assets/';
      $build_url = plugin_dir_url(__FILE__) . '../../build/assets/';

      error_log("CCC AssetManager: Build directory: $build_dir");
      error_log("CCC AssetManager: Build URL: $build_url");

      $js_file = $this->findBuildFile($build_dir, '*.js');
      $css_file = $this->findBuildFile($build_dir, '*.css');

      if ($js_file) {
          error_log("CCC AssetManager: Enqueuing React script: $build_url$js_file");
          wp_enqueue_script('ccc-react', $build_url . $js_file, ['wp-api', 'react', 'react-dom'], '1.2.8', true);
          $this->localizeScript($hook);
      } else {
          error_log("CCC AssetManager: No JS file found in $build_dir");
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
          error_log("CCC AssetManager: Build directory not found: $dir");
          return '';
      }

      $files = glob($dir . $pattern);
      $file = $files ? basename($files[0]) : '';
      error_log("CCC AssetManager: Found build file: $file in $dir");
      return $file;
  }

  private function localizeScript($hook) {
      $current_page = $hook;
      
      // Map WordPress hooks to page slugs - handle both sanitized and unsanitized hook names
      if ($hook === 'toplevel_page_custom-craft-component') {
          $current_page = 'custom-craft-component';
      } elseif ($hook === 'custom-craft-component_page_custom-craft-posttypes' || $hook === 'custom-components_page_custom-craft-posttypes') {
          $current_page = 'custom-craft-posttypes';
      } elseif ($hook === 'custom-craft-component_page_custom-craft-taxonomies' || $hook === 'custom-components_page_custom-craft-taxonomies') {
          $current_page = 'custom-craft-taxonomies';
      } elseif ($hook === 'custom-craft-component_page_custom-craft-importexport' || $hook === 'custom-components_page_custom-craft-importexport') {
          $current_page = 'custom-craft-importexport';
      } elseif ($hook === 'custom-craft-component_page_custom-craft-settings' || $hook === 'custom-components_page_custom-craft-settings') {
          $current_page = 'custom-craft-settings';
      }

      // Debug logging
      error_log("CCC AssetManager: Hook: $hook, Current Page: $current_page");

      wp_localize_script('ccc-react', 'cccData', [
          'currentPage' => $current_page,
          'baseUrl' => plugin_dir_url(__FILE__) . '../../build/assets/',
          'ajaxUrl' => admin_url('admin-ajax.php'),
          'nonce' => wp_create_nonce('ccc_nonce'),
          'postId' => isset($_GET['post']) ? intval($_GET['post']) : 0,
      ]);
  }
}
