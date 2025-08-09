<?php
/**
 * Relationship Field Example Template
 *
 * This template demonstrates how to use the Relationship field type
 * which allows users to select related posts/pages with filtering options.
 */
CCC\Frontend\TemplateManager::loadHelperFunctions();

// Get the current post ID
$post_id = get_the_ID();

echo '<div class="relationship-examples">';
echo '<h2>Relationship Field Examples</h2>';

// Example 1: Get related posts as post objects
$related_posts = get_ccc_relationship_posts('related_posts', $post_id);
if ($related_posts) {
    echo '<div class="related-posts-section">';
    echo '<h3>Related Posts (as objects)</h3>';
    echo '<ul>';
    foreach ($related_posts as $post) {
        echo '<li>';
        echo '<a href="' . esc_url(get_permalink($post->ID)) . '">' . esc_html($post->post_title) . '</a>';
        echo ' <span class="post-type">(' . esc_html($post->post_type) . ')</span>';
        echo '</li>';
    }
    echo '</ul>';
    echo '</div>';
}

// Example 2: Get just the post IDs
$related_post_ids = get_ccc_relationship_post_ids('related_posts', $post_id);
if ($related_post_ids) {
    echo '<div class="related-post-ids-section">';
    echo '<h3>Related Post IDs</h3>';
    echo '<p>Post IDs: ' . esc_html(implode(', ', $related_post_ids)) . '</p>';
    echo '</div>';
}

// Example 3: Get just the post titles
$related_titles = get_ccc_relationship_post_titles('related_posts', $post_id);
if ($related_titles) {
    echo '<div class="related-titles-section">';
    echo '<h3>Related Post Titles</h3>';
    echo '<ul>';
    foreach ($related_titles as $title) {
        echo '<li>' . esc_html($title) . '</li>';
    }
    echo '</ul>';
    echo '</div>';
}

// Example 4: Using the basic get_ccc_field function
$related_field_value = get_ccc_field('related_posts', $post_id);
if ($related_field_value) {
    echo '<div class="basic-relationship-section">';
    echo '<h3>Basic Relationship Field Value</h3>';
    echo '<p>Raw value: ' . esc_html(is_array($related_field_value) ? implode(', ', $related_field_value) : $related_field_value) . '</p>';
    echo '</div>';
}

// Example 5: Component loop with relationship fields
$components = get_ccc_fields($post_id);
if ($components) {
    echo '<div class="components-section">';
    echo '<h3>Components with Relationship Fields</h3>';
    
    foreach ($components as $component) {
        echo '<div class="component">';
        echo '<h4>' . esc_html($component->name) . '</h4>';
        
        if ($component->fields) {
            foreach ($component->fields as $field) {
                if ($field->type === 'relationship') {
                    $related_posts = get_ccc_relationship_posts($field->name, $post_id, $component->instance_id);
                    if ($related_posts) {
                        echo '<div class="relationship-field">';
                        echo '<h5>' . esc_html($field->label) . '</h5>';
                        echo '<ul>';
                        foreach ($related_posts as $post) {
                            echo '<li>';
                            echo '<a href="' . esc_url(get_permalink($post->ID)) . '">' . esc_html($post->post_title) . '</a>';
                            echo ' <span class="post-type">(' . esc_html($post->post_type) . ')</span>';
                            echo ' <span class="post-status">(' . esc_html($post->post_status) . ')</span>';
                            echo '</li>';
                        }
                        echo '</ul>';
                        echo '</div>';
                    }
                }
            }
        }
        echo '</div>';
    }
    echo '</div>';
}

// Example 6: Advanced usage with custom queries
$related_posts = get_ccc_relationship_posts('featured_posts', $post_id);
if ($related_posts) {
    echo '<div class="featured-posts-section">';
    echo '<h3>Featured Posts Grid</h3>';
    echo '<div class="posts-grid">';
    foreach ($related_posts as $post) {
        echo '<div class="post-card">';
        if (has_post_thumbnail($post->ID)) {
            echo '<div class="post-thumbnail">';
            echo get_the_post_thumbnail($post->ID, 'medium');
            echo '</div>';
        }
        echo '<div class="post-content">';
        echo '<h4><a href="' . esc_url(get_permalink($post->ID)) . '">' . esc_html($post->post_title) . '</a></h4>';
        echo '<p class="post-excerpt">' . esc_html(wp_trim_words($post->post_excerpt ?: $post->post_content, 20)) . '</p>';
        echo '<div class="post-meta">';
        echo '<span class="post-date">' . esc_html(get_the_date('', $post->ID)) . '</span>';
        echo '<span class="post-type">' . esc_html(get_post_type_object($post->post_type)->labels->singular_name) . '</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    echo '</div>';
    echo '</div>';
}

// Example 7: How to configure the relationship field
echo '<div class="configuration-example">';
echo '<h3>How to Configure the Relationship Field</h3>';
echo '<p>When adding a relationship field in the plugin, you can configure:</p>';
echo '<ul>';
echo '<li><strong>Filter by Post Type:</strong> Limit which post types can be selected (e.g., post, page, custom post types)</li>';
echo '<li><strong>Filter by Post Status:</strong> Limit which post statuses can be selected (e.g., publish, draft, pending)</li>';
echo '<li><strong>Filter by Taxonomy:</strong> Limit posts to specific categories, tags, or custom taxonomies</li>';
echo '<li><strong>Filters:</strong> Enable/disable search, post type, and taxonomy filters in the UI</li>';
echo '<li><strong>Max Posts:</strong> Set a maximum number of posts that can be selected (0 for unlimited)</li>';
echo '<li><strong>Return Format:</strong> Choose between "object" (array of post IDs) or "id" (array of IDs)</li>';
echo '</ul>';
echo '</div>';

echo '</div>';
?>

<style>
.relationship-examples {
    max-width: 800px;
    margin: 0 auto;
    padding: 2rem;
}

.relationship-examples h2 {
    color: #1f2937;
    border-bottom: 2px solid #e5e7eb;
    padding-bottom: 0.5rem;
    margin-bottom: 2rem;
}

.relationship-examples h3 {
    color: #374151;
    margin-top: 2rem;
    margin-bottom: 1rem;
}

.relationship-examples h4 {
    color: #4b5563;
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
}

.relationship-examples h5 {
    color: #6b7280;
    margin-top: 1rem;
    margin-bottom: 0.5rem;
}

.relationship-examples ul {
    list-style: none;
    padding: 0;
}

.relationship-examples li {
    padding: 0.5rem 0;
    border-bottom: 1px solid #f3f4f6;
}

.relationship-examples li:last-child {
    border-bottom: none;
}

.relationship-examples a {
    color: #2563eb;
    text-decoration: none;
}

.relationship-examples a:hover {
    text-decoration: underline;
}

.post-type, .post-status {
    background-color: #e5e7eb;
    padding: 0.125rem 0.375rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    color: #6b7280;
    margin-left: 0.5rem;
}

.posts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-top: 1rem;
}

.post-card {
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    overflow: hidden;
    background-color: white;
    transition: box-shadow 0.2s;
}

.post-card:hover {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.post-thumbnail img {
    width: 100%;
    height: 200px;
    object-fit: cover;
}

.post-content {
    padding: 1rem;
}

.post-content h4 {
    margin: 0 0 0.5rem 0;
    font-size: 1.125rem;
}

.post-excerpt {
    color: #6b7280;
    font-size: 0.875rem;
    line-height: 1.5;
    margin-bottom: 1rem;
}

.post-meta {
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
    color: #9ca3af;
}

.configuration-example {
    background-color: #f9fafb;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    padding: 1.5rem;
    margin-top: 2rem;
}

.configuration-example ul {
    margin-top: 1rem;
}

.configuration-example li {
    margin-bottom: 0.5rem;
    padding: 0;
    border: none;
}
</style> 