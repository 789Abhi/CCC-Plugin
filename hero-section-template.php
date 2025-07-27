<?php
/* component Name: Hero Section */
// Example to Fetch component fields data 
$title = get_ccc_field('title');
$description = get_ccc_field('description');
$image = get_ccc_field('images');
$wysiwyg_editor = get_ccc_field('wysiwyg_editor');
$select = get_ccc_field('select');
$mutiple_select = get_ccc_field('mutiple_select');
$checkbox = get_ccc_field('checkbox');
$radio = get_ccc_field('radio');
$color = get_ccc_field('color');
$video = get_ccc_field('video');
?>

<div class="ccc_main">
    <h2>TEXT FIELD</h2>
    <p><?php echo esc_html($title); ?></p>
    
    <h2>TEXT AREA FIELD</h2>
    <p><?php echo esc_html($description); ?></p>
    
    <h2>IMAGE</h2>
    <?php if ($image): ?>
        <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($title); ?>">
    <?php else: ?>
        <p>No image selected.</p>
    <?php endif; ?>
    
    <h2>WYSIWYG EDITOR</h2>
    <div><?php echo wp_kses_post($wysiwyg_editor); ?></div>
    
    <h2>SELECT</h2>
    <p><?php echo esc_html($select); ?></p>
    
    <h2>MULTIPLE SELECT THROUGH ARRAY LOOP</h2>
    <?php if (is_array($mutiple_select) && !empty($mutiple_select)): ?>
        <ul>
            <?php foreach ($mutiple_select as $option): ?>
                <li><?php echo esc_html($option); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No options selected.</p>
    <?php endif; ?>
    
    <h2>MULTIPLE SELECT THROUGH get_ccc_select_display</h2>
    <p><?php echo get_ccc_select_display('mutiple_select'); ?></p>
    
    <h2>CHECKBOX</h2>
    <?php if (is_array($checkbox) && !empty($checkbox)): ?>
        <ul>
            <?php foreach ($checkbox as $option): ?>
                <li><?php echo esc_html($option); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No options selected.</p>
    <?php endif; ?>
    
    <h2>CHECKBOX THROUGH get_ccc_checkbox_values FUNCTION</h2>
    <?php echo get_ccc_checkbox_values('checkbox', null, null, 'options'); ?>
    
    <h2>RADIO</h2>
    <p><?php echo esc_html($radio); ?></p>

    <h2>RADIO THROUGH get_ccc_radio_values FUNCTION</h2>
    <p><?php echo get_ccc_radio_values('radio', null, null, 'options'); ?></p>
    
    <h2>COLOR</h2>
    <?php
    echo "<h2 style='color: " . get_ccc_color_main('color') . ";'>Hello</h2>";

    // ✅ This will work with hover effect
    echo "<h2" . get_ccc_color_with_hover_style('color') . ">Hello KING</h2>";

    // ✅ This will also work with hover effect
    echo "<h2" . get_ccc_color_with_hover_style('color') . ">Hello WORLD</h2>";
    ?>

    <h2 class="text_color">Hello bro</h2>
    <h2 class="text_color_adjusted">Hello - Adjusted</h2>

    <h2>VIDEO FIELD - UNIFIED APPROACH (WORKS FOR ALL TYPES)</h2>
    <?php if ($video): ?>
        <div class="video-container-basic">
            <?php echo get_ccc_video_embed('video'); ?>
        </div>
    <?php else: ?>
        <p>No video selected.</p>
    <?php endif; ?>

    <h2>VIDEO FIELD - CUSTOM STYLED</h2>
    <?php if ($video): ?>
        <div class="video-container-custom">
            <?php echo get_ccc_video_embed('video', null, null, '100%', '400'); ?>
        </div>
    <?php endif; ?>

    <h2>VIDEO FIELD - WITH CUSTOM PLAYER OPTIONS</h2>
    <?php if ($video): ?>
        <div class="video-container-options">
            <?php 
            $custom_options = [
                'controls' => true,
                'autoplay' => false,
                'muted' => false,
                'loop' => false,
                'download' => true,
                'fullscreen' => true,
                'pictureInPicture' => true
            ];
            echo get_ccc_video_embed('video', null, null, '100%', '300', $custom_options); 
            ?>
        </div>
    <?php endif; ?>

    <h2>VIDEO FIELD - AUTOPLAY EXAMPLE (MUTED)</h2>
    <?php if ($video): ?>
        <div class="video-container-autoplay">
            <?php 
            $autoplay_options = [
                'controls' => false,
                'autoplay' => true,
                'muted' => true,
                'loop' => true,
                'download' => false,
                'fullscreen' => true,
                'pictureInPicture' => false
            ];
            echo get_ccc_video_embed('video', null, null, '100%', '250', $autoplay_options); 
            ?>
        </div>
    <?php endif; ?>
</div>

<style>
    .ccc_main {
        padding: 20px;
        font-family: Arial, sans-serif;
        max-width: 1200px;
        margin: 0 auto;
    }

    .ccc_main h2 {
        color: #333;
        margin: 2rem 0 1rem 0;
        padding: 0.5rem;
        background: #f8f9fa;
        border-left: 4px solid #007cba;
    }

    .ccc_main p {
        line-height: 1.6;
        margin: 0.5rem 0;
    }

    .ccc_main ul {
        margin: 0.5rem 0;
        padding-left: 1.5rem;
    }

    .ccc_main li {
        margin: 0.25rem 0;
    }

    .ccc_main img {
        width: 200px;
        height: 200px;
        object-fit: cover;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    /* Basic video container - no hover effects */
    .video-container-basic {
        margin: 1rem 0;
        border: 1px solid #ddd;
        border-radius: 8px;
        overflow: hidden;
    }

    .video-container-basic iframe,
    .video-container-basic video {
        display: block;
        width: 100%;
        height: auto;
        border: none;
    }

    /* Custom styled video container */
    .video-container-custom {
        margin: 2rem 0;
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        background: #f8f9fa;
    }

    .video-container-custom iframe,
    .video-container-custom video {
        display: block;
        width: 100%;
        height: auto;
        border: none;
    }

    /* Video container with options */
    .video-container-options {
        margin: 2rem 0;
        border: 2px solid #28a745;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 4px rgba(40, 167, 69, 0.2);
    }

    .video-container-options iframe,
    .video-container-options video {
        display: block;
        width: 100%;
        height: auto;
        border: none;
    }

    /* Autoplay video container */
    .video-container-autoplay {
        margin: 2rem 0;
        border: 2px solid #dc3545;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 4px rgba(220, 53, 69, 0.2);
        position: relative;
    }

    .video-container-autoplay::before {
        content: "Autoplay Video (Muted)";
        position: absolute;
        top: 10px;
        left: 10px;
        background: rgba(220, 53, 69, 0.9);
        color: white;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        z-index: 10;
    }

    .video-container-autoplay iframe,
    .video-container-autoplay video {
        display: block;
        width: 100%;
        height: auto;
        border: none;
    }

    /* Remove any hover effects from video elements */
    .ccc_main video:hover,
    .ccc_main iframe:hover {
        /* No hover effects */
    }

    /* Ensure video elements have no unwanted styling */
    .ccc_main video,
    .ccc_main iframe {
        outline: none !important;
        box-shadow: none !important;
        transition: none !important;
    }

    .ccc_main video:focus,
    .ccc_main iframe:focus {
        outline: none !important;
        box-shadow: none !important;
    }

    <?php echo get_ccc_color_css_variables_root('color'); ?>

    .text_color {
        color: var(--ccc-color-main);
        transition: color 0.3s ease;
        cursor: pointer;
    }

    .text_color:hover {
        color: var(--ccc-color-hover, var(--ccc-color-main));
    }

    .text_color_adjusted {
        color: var(--ccc-color-adjusted, var(--ccc-color-main));
    }

    /* Responsive design */
    @media (max-width: 768px) {
        .ccc_main {
            padding: 1rem;
        }
        
        .ccc_main h2 {
            font-size: 1.2rem;
        }
        
        .ccc_main img {
            width: 150px;
            height: 150px;
        }
    }
</style>

<script>
// JavaScript to show which CSS variables are available
document.addEventListener('DOMContentLoaded', function() {
    const root = document.documentElement;
    const computedStyle = getComputedStyle(root);
    
    console.log('Available CSS Variables:');
    console.log('--ccc-color-main:', computedStyle.getPropertyValue('--ccc-color-main'));
    console.log('--ccc-color-adjusted:', computedStyle.getPropertyValue('--ccc-color-adjusted'));
    console.log('--ccc-color-hover:', computedStyle.getPropertyValue('--ccc-color-hover'));
});
</script> 