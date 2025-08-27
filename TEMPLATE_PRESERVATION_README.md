# Template Preservation Feature

## Overview

This update adds a new feature to safely edit component names and handles while preserving any custom content that users have added to the automatically generated template files.

## What It Does

1. **Warns Users**: When editing a component name that will affect template files, users are shown a warning modal
2. **Preserves Content**: All custom HTML, PHP code, and styling in template files is automatically preserved
3. **Safe Updates**: Template files are updated with new component information while maintaining user customizations

## How It Works

### Backend Changes

#### ComponentService.php
- Added `hasCustomTemplateContent()` method to detect if a template has custom content
- Added `getTemplateContent()` method to retrieve existing template content
- Modified `updateComponent()` to use `updateComponentTemplate()` method
- Added `updateComponentTemplate()` method that preserves custom content
- Added `generateTemplateContentWithPreservedContent()` method to merge old and new content

#### AjaxHandler.php
- Added `checkTemplateContent()` AJAX endpoint to check template content before updates
- New endpoint: `ccc_check_template_content`

#### Component.php
- Added `handleExistsExcluding()` method to check handle uniqueness excluding current component

### Frontend Changes

#### ComponentEditModal.jsx
- Added template content checking before allowing updates
- Shows warning modal when custom content is detected
- Displays existing template content in the warning
- Provides clear information about what will happen
- Allows users to proceed with confidence

## User Experience

### Before (Old Behavior)
- Users could edit component names without warning
- Template files were completely replaced
- Custom content was lost permanently

### After (New Behavior)
1. **User starts editing component name**
2. **System automatically checks** if template has custom content
3. **If custom content exists**: Warning modal appears showing:
   - What will change (file names)
   - Current template content
   - Assurance that content will be preserved
4. **User can choose** to:
   - Cancel the operation
   - Continue with confidence (content will be preserved)
5. **System updates** component and template file safely

## Example Warning Modal

The warning modal shows:
- ⚠️ Warning icon and clear title
- Explanation of what will happen
- Current template file name and new name
- Preview of existing template content
- Reassurance that content will be preserved
- Clear action buttons (Cancel / Continue & Preserve Content)

## Technical Implementation

### Template Content Detection
The system compares the existing template file content with a generated basic template to determine if custom content exists.

### Content Preservation
When updating:
1. Reads existing template file
2. Extracts custom content (everything between PHP tags)
3. Generates new header with updated component info
4. Combines new header with existing custom content
5. Writes new template file
6. Deletes old template file

### Error Handling
- Graceful fallback if content parsing fails
- Proper error logging
- User-friendly error messages

## Testing

Use the `test-template-preservation.php` file to test the functionality:
1. Place it in your plugin directory
2. Access it via browser (requires admin privileges)
3. Verify that custom content detection works
4. Test with components that have custom templates

## Benefits

1. **No More Data Loss**: Users can safely edit component names
2. **Better UX**: Clear warnings and information
3. **Confidence**: Users know their work is safe
4. **Professional**: Handles edge cases gracefully
5. **Maintainable**: Clean, well-documented code

## Future Enhancements

Potential improvements could include:
- Template content backup before updates
- Version history of template changes
- Diff view showing what changed
- Rollback functionality for template changes

## Support

If you encounter issues:
1. Check the error logs for detailed information
2. Verify the template files exist and are readable
3. Ensure proper file permissions on the templates directory
4. Test with the provided test file
