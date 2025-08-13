# File Field - Custom Craft Component

## Overview

The File Field is a powerful field type that provides comprehensive file upload and management capabilities. It supports various file types including images, videos, PDFs, documents, audio files, and archives. The field features a modern drag-and-drop interface with real-time validation and file previews.

## Features

- **Multi-File Type Support**: Images, videos, documents, audio, and archives
- **Drag & Drop Interface**: Modern, intuitive file upload experience
- **File Validation**: Real-time file type and size validation
- **File Previews**: Visual previews for images, videos, and documents
- **Multiple File Uploads**: Support for single or multiple file uploads
- **File Management**: Download, delete, and organize uploaded files
- **Responsive Design**: Works seamlessly across all device sizes
- **Security**: Built-in file type validation and sanitization

## Configuration Options

### Basic Configuration
- **Allowed Types**: Control which file types can be uploaded
- **Max File Size**: Set maximum file size limit in MB
- **Return Type**: Choose how file data is returned (url, id, array)
- **Multiple Files**: Allow users to upload multiple files at once

### Display Options
- **Show Preview**: Display file previews in the interface
- **Show Download**: Provide download buttons for files
- **Show Delete**: Allow users to remove uploaded files

### Example Configuration
```json
{
  "allowed_types": ["image", "video", "document"],
  "max_file_size": 25,
  "return_type": "id",
  "multiple": true,
  "show_preview": true,
  "show_download": true,
  "show_delete": true
}
```

## Supported File Types

### Images
- **Formats**: JPG, PNG, GIF, WebP, SVG
- **Use Cases**: Product photos, galleries, avatars, banners

### Videos
- **Formats**: MP4, WebM, OGV, MOV, AVI
- **Use Cases**: Product demos, tutorials, presentations, portfolios

### Documents
- **Formats**: PDF, DOC, DOCX, TXT, XLS, XLSX
- **Use Cases**: Specifications, manuals, reports, data sheets

### Audio
- **Formats**: MP3, WAV, OGG, M4A
- **Use Cases**: Podcasts, music, voice notes, sound effects

### Archives
- **Formats**: ZIP, RAR, TAR, GZ
- **Use Cases**: Software packages, backups, collections

## Usage Examples

### 1. Media Gallery
```json
{
  "allowed_types": ["image", "video"],
  "max_file_size": 50,
  "return_type": "array",
  "multiple": true,
  "show_preview": true,
  "show_download": false,
  "show_delete": true
}
```

### 2. Document Library
```json
{
  "allowed_types": ["document"],
  "max_file_size": 100,
  "return_type": "id",
  "multiple": true,
  "show_preview": false,
  "show_download": true,
  "show_delete": true
}
```

### 3. Product Catalog
```json
{
  "allowed_types": ["image", "document"],
  "max_file_size": 20,
  "return_type": "url",
  "multiple": false,
  "show_preview": true,
  "show_download": true,
  "show_delete": false
}
```

### 4. Portfolio Showcase
```json
{
  "allowed_types": ["image", "video", "document"],
  "max_file_size": 100,
  "return_type": "array",
  "multiple": true,
  "show_preview": true,
  "show_download": true,
  "show_delete": true
}
```

## Frontend Implementation

The File Field component (`FileField.jsx`) provides:

- **Drag & Drop Zone**: Visual upload area with drag feedback
- **File Browser**: Traditional file selection interface
- **File Validation**: Real-time type and size checking
- **File Previews**: Thumbnails for images, video players, document icons
- **File Management**: Download and delete functionality
- **Error Handling**: Clear error messages and validation feedback
- **Responsive Layout**: Adapts to different screen sizes

## Backend Implementation

### PHP Classes
- **FileField.php**: Main field class extending BaseField
- **FieldService.php**: Service for creating file fields
- **AjaxHandler.php**: AJAX endpoints for field operations
- **MetaBoxManager.php**: Field value sanitization and saving

### Database Storage
File field values are stored in the `wp_ccc_field_values` table with:
- Field ID reference
- Post ID reference
- Instance ID for component instances
- File data (IDs, URLs, or arrays based on return type)

### File Handling
- **Upload Processing**: Handles file uploads through WordPress media library
- **File Validation**: Ensures file types and sizes meet requirements
- **Security**: Sanitizes and validates all file references
- **Storage**: Integrates with WordPress attachment system

## Installation

1. **Add File Field Type**: The field type is automatically available in the field creation modal
2. **Configure Field**: Set allowed types, file size limits, and display options
3. **Use in Components**: Add the file field to any component configuration
4. **Render on Posts**: The field will automatically render with the configured settings

## Testing

Use the provided test file `test-file-field.php` to verify:
- Field creation and configuration
- File validation and sanitization
- File type and size constraints
- Configuration parsing and storage
- File info retrieval and processing

## Browser Compatibility

- **Modern Browsers**: Full support for all features including drag & drop
- **IE11+**: Basic functionality with fallback upload methods
- **Mobile Devices**: Touch-friendly interface with file browser fallback
- **Screen Readers**: Proper accessibility support and ARIA labels

## Customization

### Styling
The file field uses Tailwind CSS classes and can be customized by:
- Modifying the CSS classes in `FileField.jsx`
- Adding custom CSS for upload area appearance
- Adjusting color schemes and spacing
- Customizing file preview layouts

### Behavior
Customize field behavior by:
- Modifying validation logic in the PHP classes
- Adjusting file type restrictions
- Adding custom file processing hooks
- Implementing custom upload workflows

## Security Considerations

### File Validation
- **Type Checking**: Validates MIME types against allowed file types
- **Size Limits**: Enforces maximum file size restrictions
- **Content Scanning**: Integrates with WordPress security measures
- **Access Control**: Ensures only authorized users can upload files

### Data Sanitization
- **Input Cleaning**: Sanitizes all file references and metadata
- **URL Validation**: Ensures file URLs belong to WordPress attachments
- **ID Validation**: Verifies file IDs exist in the database
- **Array Handling**: Safely processes multiple file selections

## Troubleshooting

### Common Issues
1. **Files not uploading**: Check file size limits and type restrictions
2. **Preview not showing**: Verify file type is supported for previews
3. **Validation errors**: Check allowed file types and size limits
4. **Permission issues**: Ensure user has upload capabilities

### Debug Mode
Enable debug logging by checking:
- Browser console for JavaScript errors
- WordPress error logs for PHP issues
- File upload permissions and settings
- Database connection and storage

## Support

For issues or questions about the File Field:
1. Check the WordPress error logs
2. Verify field configuration in the database
3. Test with the provided test file
4. Review browser console for JavaScript errors
5. Check file upload permissions and server settings

## Future Enhancements

Potential improvements for future versions:
- **Advanced File Processing**: Image resizing, video compression
- **Cloud Storage**: Integration with external storage services
- **File Versioning**: Track file changes and updates
- **Advanced Metadata**: Custom file properties and tags
- **Batch Operations**: Bulk file management and processing
- **File Relationships**: Link files to other content types
- **Search & Filter**: Advanced file discovery and organization

## Integration

The File Field integrates seamlessly with:
- **WordPress Media Library**: Uses existing attachment system
- **Component System**: Works with all component types
- **Repeater Fields**: Supports multiple file instances
- **Conditional Logic**: Can be shown/hidden based on other fields
- **Validation Rules**: Integrates with field validation system
- **Export/Import**: Supports component data migration 