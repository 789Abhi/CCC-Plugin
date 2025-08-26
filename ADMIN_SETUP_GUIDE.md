# Admin Setup Guide for Custom Craft Component Plugin

## 🔒 Security Notice
This plugin does NOT create any default admin accounts automatically. This is a security feature to prevent unauthorized access.

## 📋 Initial Setup Steps

### 1. Install and Activate Plugin
- Install the plugin normally
- Activate it through WordPress admin
- The required database tables will be created automatically

### 2. Create Your First Admin Account
- Go to the plugin's "Account Settings" tab
- Click "Register" to create your first account
- Use a strong password and valid email
- This account will have 'user' role initially

### 3. Set Up Admin Email (Optional)
If you want to receive license request notifications:
- Go to WordPress Admin → Settings → General
- Set your admin email address
- Or add this to your wp-config.php:
```php
define('CCC_ADMIN_EMAIL', 'your-email@domain.com');
```

### 4. Promote to Admin Role (If Needed)
To give admin privileges to a user:
- Access your database directly
- Update the user's role in `wp_ccc_users` table:
```sql
UPDATE wp_ccc_users SET role = 'admin' WHERE email = 'your-email@domain.com';
```

## 🚀 User Registration Flow

1. **New users** register through the plugin interface
2. **All users start with 'user' role** (no admin privileges)
3. **Admin roles must be assigned manually** for security
4. **License requests** are processed through the plugin interface

## 🔐 Security Features

- ✅ **No hardcoded credentials** in the code
- ✅ **No automatic admin creation**
- ✅ **User isolation** - each installation is separate
- ✅ **Manual role assignment** required
- ✅ **Clean installation** for production use

## 📧 Support

If you need help setting up admin accounts or have questions about security, please refer to the plugin documentation or contact support.

---

**Note**: This plugin is designed with security in mind. No sensitive information is stored in the code, and all user management is handled through the WordPress database with proper isolation between installations.
