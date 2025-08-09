<?php
/*
Template Name: Test Gallery
*/

get_header(); ?>

<div style="padding: 20px; font-family: monospace; background: #f5f5f5;">
    <h1>Gallery Test Page</h1>
    
    <?php
    // Test the gallery field
    echo "<h2>Testing get_ccc_field('gallery')</h2>";
    $gallery = get_ccc_field('gallery');
    echo "<p><strong>Type:</strong> " . gettype($gallery) . "</p>";
    echo "<p><strong>Value:</strong> " . print_r($gallery, true) . "</p>";
    
    // Test raw data
    echo "<h2>Raw Data</h2>";
    $gallery_raw = get_ccc_field('gallery', 'raw');
    echo "<p><strong>Raw value:</strong> " . esc_html($gallery_raw) . "</p>";
    
    // Display the gallery
    echo "<h2>Gallery Display</h2>";
    if ($gallery && is_array($gallery)) :
        echo "<p>Found " . count($gallery) . " items:</p>";
        foreach ($gallery as $index => $item) :
            echo "<div style='border: 1px solid #ddd; margin: 5px; padding: 5px;'>";
            echo "<strong>Item " . ($index + 1) . ":</strong> ";
            echo "Image: " . (isset($item['image']) ? $item['image'] : 'not set');
            echo "</div>";
        endforeach;
    else :
        echo "<p>No gallery items found or not an array.</p>";
    endif;
    ?>
</div>

<?php get_footer(); ?> 