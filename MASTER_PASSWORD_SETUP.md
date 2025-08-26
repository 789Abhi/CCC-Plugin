# 🔐 Master Password System Setup Guide

## **Overview**
The Master Password System provides secure administrative access to the Custom Craft Component plugin. It allows you to:
- **Promote users** to Admin/Super Admin roles
- **Create new admin accounts** directly
- **Manage all plugin installations** and licenses
- **Access system-wide administration** functions

## **🚀 Quick Setup**

### **Step 1: Set Master Password**
1. Go to **WordPress Admin → Custom Craft Settings → Master Password**
2. Enter your desired master password
3. Click **"Update Master Password"**
4. ✅ Your master password is now set and encrypted

### **Step 2: Access Admin Panel**
1. Go to **WordPress Admin → Custom Craft Settings → Admin Login**
2. Enter your master password
3. Click **"Access Admin Panel"**
4. ✅ You now have full administrative access

## **🔑 Default Master Password**
- **Initial Password**: `admin123456`
- **Change this immediately** after first login for security
- **Use a strong password** (12+ characters, mixed case, numbers, symbols)

## **📋 Admin Panel Features**

### **User Management**
- **View all users** in the system
- **Promote users** from User → Admin → Super Admin
- **Create new admin users** directly
- **Monitor user status** and registration dates

### **System Overview**
- **Total installations** count
- **Free vs. licensed** installations
- **Pending license requests**
- **Active licenses** count

### **Security Features**
- **24-hour session expiry**
- **Secure token authentication**
- **Nonce verification** for all actions
- **Role-based access control**

## **🔒 Security Best Practices**

### **Password Management**
- ✅ Use a **strong, unique password**
- ✅ **Never share** the master password
- ✅ **Change regularly** (every 3-6 months)
- ✅ **Store securely** (password manager recommended)

### **Access Control**
- ✅ **Limit access** to trusted administrators only
- ✅ **Monitor admin sessions** regularly
- ✅ **Log out** when not actively using
- ✅ **Use HTTPS** for all admin access

### **User Role Management**
- ✅ **Promote users carefully** - only trusted individuals
- ✅ **Monitor admin users** regularly
- ✅ **Remove admin access** when no longer needed
- ✅ **Use least privilege** principle

## **🚨 Troubleshooting**

### **Can't Access Admin Panel?**
1. **Check master password** - ensure it's correct
2. **Clear browser cache** and cookies
3. **Try logging out** and back in
4. **Check WordPress permissions** - need `manage_options` capability

### **Session Expired?**
- Admin sessions expire after **24 hours**
- Simply **re-enter master password** to continue
- This is a security feature, not a bug

### **Forgot Master Password?**
1. **Access WordPress database** directly
2. **Delete the option** `ccc_master_password`
3. **Reactivate plugin** to reset to default
4. **Set new password** immediately

## **📱 URL Structure**
- **Master Password Settings**: `/wp-admin/admin.php?page=custom-craft-settings-master-password`
- **Admin Login**: `/wp-admin/admin.php?page=custom-craft-settings-admin-login`

## **🔧 Advanced Configuration**

### **Custom Session Duration**
To change the 24-hour session expiry, modify the `AdminLoginManager.php` file:
```php
// Change this line in setAdminAuthenticated() method
$expiry = time() + (24 * 60 * 60); // 24 hours
// To:
$expiry = time() + (48 * 60 * 60); // 48 hours
```

### **Custom Master Password Option**
To change the option name, modify both classes:
```php
// In AdminLoginManager.php and MasterPasswordSettings.php
private $master_password_option = 'your_custom_option_name';
```

## **📞 Support**
If you need help with the Master Password System:
1. **Check this guide** first
2. **Review WordPress error logs**
3. **Contact plugin support** with specific error messages
4. **Provide system details** (WordPress version, plugin version, etc.)

---

**⚠️ Security Reminder**: The master password provides full administrative access to your plugin. Keep it secure and only share with trusted administrators.
