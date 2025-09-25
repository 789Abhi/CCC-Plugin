<?php
/**
 * Script to update all field classes with field_type property
 */

$field_files = [
    'TextField.php' => 'text',
    'TextAreaField.php' => 'textarea',
    'ImageField.php' => 'image',
    'VideoField.php' => 'video',
    'OembedField.php' => 'oembed',
    'RelationshipField.php' => 'relationship',
    'LinkField.php' => 'link',
    'EmailField.php' => 'email',
    'NumberField.php' => 'number',
    'RangeField.php' => 'range',
    'FileField.php' => 'file',
    'RepeaterField.php' => 'repeater',
    'WysiwygField.php' => 'wysiwyg',
    'ColorField.php' => 'color',
    'SelectField.php' => 'select',
    'CheckboxField.php' => 'checkbox',
    'RadioField.php' => 'radio',
    'ToggleField.php' => 'toggle',
    'GalleryField.php' => 'gallery',
    'DateField.php' => 'date',
    'UserField.php' => 'user',
    'PasswordField.php' => 'password',
    'TaxonomyTermField.php' => 'taxonomy_term'
];

$fields_dir = 'C:\Users\abish\Local Sites\custom-craft-component-extended\app\public\wp-content\plugins\custom-craft-component\inc\Fields\\';

foreach ($field_files as $filename => $field_type) {
    $file_path = $fields_dir . $filename;
    
    if (!file_exists($file_path)) {
        echo "File not found: $file_path\n";
        continue;
    }
    
    $content = file_get_contents($file_path);
    
    // Check if field_type is already defined
    if (strpos($content, 'protected $field_type') !== false) {
        echo "Field type already defined in $filename\n";
        continue;
    }
    
    // Find the class declaration and add field_type after it
    $pattern = '/class (\w+) extends BaseField \{/';
    $replacement = "class $1 extends BaseField {\n    protected \$field_type = '$field_type';";
    
    $new_content = preg_replace($pattern, $replacement, $content);
    
    if ($new_content !== $content) {
        file_put_contents($file_path, $new_content);
        echo "Updated $filename with field_type = '$field_type'\n";
    } else {
        echo "Could not update $filename\n";
    }
}

echo "Field type update complete!\n";
