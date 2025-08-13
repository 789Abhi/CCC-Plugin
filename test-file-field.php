<?php
/**
 * Test file for File Field Configuration
 * This demonstrates the new file field options
 */

// Include WordPress
require_once('../../../wp-load.php');

// Test the FileField class
require_once('inc/Fields/FileField.php');

use CCC\Fields\FileField;

echo "<h1>File Field Configuration Test</h1>";

// Test 1: Basic file field
echo "<h2>Test 1: Basic File Field</h2>";
$basicField = new FileField('Basic File', 'basic_file', 1, false, 'Upload a file');
echo "<p>Field created: " . $basicField->getName() . "</p>";

// Test 2: File field with configuration
echo "<h2>Test 2: File Field with Configuration</h2>";
$config = [
    'allowed_types' => ['image', 'video', 'document'],
    'max_file_size' => 25,
    'return_type' => 'id',
    'multiple' => true,
    'show_preview' => true,
    'show_download' => true,
    'show_delete' => true
];
$configField = new FileField('Media Files', 'media_files', 1, true, 'Upload media files', json_encode($config));
echo "<p>Field created: " . $configField->getName() . "</p>";
echo "<p>Config: " . $configField->getConfig() . "</p>";

// Test 3: Test sanitization
echo "<h2>Test 3: Sanitization Test</h2>";
$testValues = [
    '123', // Valid file ID
    '999999', // Invalid file ID
    'https://example.com/file.pdf', // Invalid URL
    'https://' . get_site_url() . '/wp-content/uploads/2024/01/test.jpg', // Valid attachment URL
    ['123', '456'], // Valid file IDs array
    ['invalid', '789'], // Mixed valid/invalid
    'not_a_file' // Invalid value
];

foreach ($testValues as $value) {
    $sanitized = $configField->sanitize($value);
    echo "<p>Input: " . (is_array($value) ? json_encode($value) : $value) . " → Sanitized: " . (is_array($sanitized) ? json_encode($sanitized) : $sanitized) . "</p>";
}

// Test 4: Test file info retrieval
echo "<h2>Test 4: File Info Retrieval Test</h2>";
// Create a test attachment (this would normally be done through WordPress upload)
$testAttachmentId = 1; // Assuming there's at least one attachment
$fileInfo = $configField->getFileInfo($testAttachmentId);
if ($fileInfo) {
    echo "<p>File Info for ID {$testAttachmentId}:</p>";
    echo "<ul>";
    echo "<li><strong>Title:</strong> " . $fileInfo['title'] . "</li>";
    echo "<li><strong>Filename:</strong> " . $fileInfo['filename'] . "</li>";
    echo "<li><strong>Type:</strong> " . $fileInfo['type'] . "</li>";
    echo "<li><strong>Size:</strong> " . $fileInfo['size_formatted'] . "</li>";
    echo "<li><strong>Is Image:</strong> " . ($fileInfo['is_image'] ? 'Yes' : 'No') . "</li>";
    echo "<li><strong>Is Video:</strong> " . ($fileInfo['is_video'] ? 'Yes' : 'No') . "</li>";
    echo "<li><strong>Is Document:</strong> " . ($fileInfo['is_document'] ? 'Yes' : 'No') . "</li>";
    echo "</ul>";
} else {
    echo "<p>No file info available for ID {$testAttachmentId}</p>";
}

echo "<h2>Configuration Options Summary</h2>";
echo "<ul>";
echo "<li><strong>Allowed Types:</strong> Control which file types can be uploaded (image, video, document, audio, archive)</li>";
echo "<li><strong>Max File Size:</strong> Set maximum file size limit in MB</li>";
echo "<li><strong>Return Type:</strong> Choose how file data is returned (url, id, array)</li>";
echo "<li><strong>Multiple Files:</strong> Allow users to upload multiple files at once</li>";
echo "<li><strong>Show Preview:</strong> Display file previews in the interface</li>";
echo "<li><strong>Show Download:</strong> Provide download buttons for files</li>";
echo "<li><strong>Show Delete:</strong> Allow users to remove uploaded files</li>";
echo "</ul>";

echo "<h2>Frontend Features</h2>";
echo "<ul>";
echo "<li>Drag and drop file upload interface</li>";
echo "<li>File type validation and size checking</li>";
echo "<li>Visual file previews (images, videos, documents)</li>";
echo "<li>File management (download, delete)</li>";
echo "<li>Support for multiple file uploads</li>";
echo "<li>Real-time validation and error handling</li>";
echo "<li>Responsive design for all screen sizes</li>";
echo "</ul>";

echo "<h2>Supported File Types</h2>";
echo "<ul>";
echo "<li><strong>Images:</strong> JPG, PNG, GIF, WebP, SVG</li>";
echo "<li><strong>Videos:</strong> MP4, WebM, OGV, MOV, AVI</li>";
echo "<li><strong>Documents:</strong> PDF, DOC, DOCX, TXT, XLS, XLSX</li>";
echo "<li><strong>Audio:</strong> MP3, WAV, OGG, M4A</li>";
echo "<li><strong>Archives:</strong> ZIP, RAR, TAR, GZ</li>";
echo "</ul>";

echo "<h2>Usage Examples</h2>";
echo "<p>This file field is perfect for:</p>";
echo "<ul>";
echo "<li><strong>Media Galleries:</strong> Upload multiple images and videos</li>";
echo "<li><strong>Document Libraries:</strong> Store PDFs, Word docs, and spreadsheets</li>";
echo "<li><strong>Product Catalogs:</strong> Attach product images and specifications</li>";
echo "<li><strong>Portfolio Showcases:</strong> Display work samples and projects</li>";
echo "<li><strong>Resource Centers:</strong> Provide downloadable materials</li>";
echo "</ul>";

echo "<p>File field test complete!</p>";
?> 