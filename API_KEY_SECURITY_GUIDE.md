# 🔐 API Key Security Guide

## 🚨 **IMPORTANT: Your API Key is SAFE!**

### **Why Environment Variables Are Secure:**

1. **Server-side storage**: API key is stored on your server, never in user browsers
2. **Backend processing**: All AI requests go through WordPress backend
3. **No frontend exposure**: API key is never sent to or visible by users
4. **WordPress security**: Uses WordPress's built-in security and permissions

### **Security Flow Diagram:**
```
┌─────────────┐    ┌─────────────────┐    ┌─────────────┐
│ User Browser│───▶│ WordPress Backend│───▶│ OpenAI API  │
│             │    │                 │    │             │
│ NO API KEY  │    │ API Key (env)   │    │ Response    │
│ VISIBLE     │    │ (Never leaves   │    │             │
└─────────────┘    │  server)        │    └─────────────┘
                   └─────────────────┘
                            │
                            ▼
                   ┌─────────────────┐
                   │ Process Response│
                   │ Create Component│
                   │ Return to User │
                   └─────────────────┘
```

## ⚙️ **Where to Configure Your API Key**

### **🔒 Option 1: Environment Variables (MOST SECURE)**

#### **For Linux/Mac Servers:**
```bash
# Add to your server's environment
export CCC_OPENAI_API_KEY="sk-your-actual-api-key-here"

# Make it permanent by adding to profile
echo 'export CCC_OPENAI_API_KEY="sk-your-actual-api-key-here"' >> ~/.bashrc
source ~/.bashrc

# Verify it's set
echo $CCC_OPENAI_API_KEY
```

#### **For Windows Servers:**
```cmd
set CCC_OPENAI_API_KEY=sk-your-actual-api-key-here

# Make it permanent (add to system environment variables)
# Control Panel → System → Advanced → Environment Variables
```

#### **For cPanel/Shared Hosting:**
1. Login to cPanel
2. Go to **Advanced** → **Environment Variables**
3. Add new variable:
   - **Variable Name**: `CCC_OPENAI_API_KEY`
   - **Value**: `sk-your-actual-api-key-here`
4. Click **Save**

#### **For .htaccess (if environment variables don't work):**
```apache
# Add to your .htaccess file in WordPress root
SetEnv CCC_OPENAI_API_KEY "sk-your-actual-api-key-here"
```

### **🔐 Option 2: WordPress wp-config.php (SECURE)**

Add this line to your `wp-config.php` file **BEFORE** the "That's all, stop editing!" comment:

```php
// OpenAI API Key for Custom Craft Component
define('CCC_OPENAI_API_KEY', 'sk-your-actual-api-key-here');
```

**File location**: Usually in your WordPress root directory (same level as wp-content folder)

### **⚠️ Option 3: WordPress Database (LESS SECURE)**

As a last resort, you can store it in WordPress options:

```php
// Add this to a temporary PHP file or use WP-CLI
update_option('ccc_openai_api_key', 'sk-your-actual-api-key-here');
```

**Note**: This is less secure than environment variables but still safe from frontend exposure.

## 🛡️ **Security Features Built Into the Plugin**

### **1. API Key Validation**
- Format validation (must start with 'sk-' and be proper length)
- Automatic error logging for invalid keys
- Graceful fallback if key is invalid

### **2. Multi-Level Rate Limiting**
- **Global rate limit**: Configurable per hour (default: 50 requests)
- **IP-based rate limiting**: Additional protection per IP address
- **User-based limits**: WordPress user capability checks
- **Automatic blocking**: Temporary IP blocking for abuse

### **3. Request Logging & Monitoring**
- All API requests are logged with timestamp, IP, and user ID
- Automatic cleanup of old logs (keeps last 1000 entries)
- Security monitoring for suspicious activity

### **4. Input Validation & Sanitization**
- Prompt length limits (max 1000 characters)
- Content sanitization and validation
- Nonce verification for all AJAX requests
- User capability checks (admin only)

### **5. WordPress Security Integration**
- Uses WordPress nonces for CSRF protection
- Leverages WordPress user management
- Integrates with WordPress permissions system
- Follows WordPress security best practices

## 🔍 **How to Verify Your API Key is Secure**

### **Test 1: Check Frontend Exposure**
1. Open your browser's Developer Tools (F12)
2. Go to **Network** tab
3. Try to generate a component with AI
4. Check all requests - you should **NEVER** see your API key

### **Test 2: Check Source Code**
1. View page source (Ctrl+U)
2. Search for "sk-" - you should find **NO** API keys
3. Check JavaScript files - no API keys should be visible

### **Test 3: Check Environment Variable**
```bash
# On your server, run:
echo $CCC_OPENAI_API_KEY

# Should show your key (only visible on server)
```

## 🚨 **What Happens if Someone Tries to Access Your API Key**

### **Frontend Attempts:**
- ❌ **Impossible**: API key is never sent to browser
- ❌ **JavaScript access**: No API key in any frontend code
- ❌ **Network inspection**: Only sees WordPress AJAX requests

### **Backend Attempts:**
- ✅ **Protected**: WordPress permissions required
- ✅ **Rate limited**: Automatic blocking of abuse
- ✅ **Logged**: All attempts are recorded
- ✅ **IP blocked**: Temporary blocking of suspicious IPs

## 📋 **Step-by-Step Setup (Recommended)**

### **Step 1: Get Your OpenAI API Key**
1. Go to [OpenAI Platform](https://platform.openai.com/)
2. Sign up or log in
3. Go to **API Keys** section
4. Create new API key
5. Copy the key (starts with `sk-`)

### **Step 2: Configure Environment Variable**
```bash
# On your server, run:
export CCC_OPENAI_API_KEY="sk-your-actual-key-here"

# Make it permanent:
echo 'export CCC_OPENAI_API_KEY="sk-your-actual-key-here"' >> ~/.bashrc
source ~/.bashrc
```

### **Step 3: Test the Configuration**
1. Go to your WordPress admin
2. Navigate to Custom Craft Component
3. Click "Use ChatGPT" button
4. Check AI Status in the modal
5. Should show "AI Status: Ready"

### **Step 4: Test AI Generation**
1. In the AI Generation section, type: *"Create a simple contact form"*
2. Click "Generate with AI"
3. Component should be created successfully

## 🔧 **Troubleshooting**

### **"API key not configured"**
- Check if environment variable is set: `echo $CCC_OPENAI_API_KEY`
- Verify WordPress can access environment variables
- Try wp-config.php method as alternative

### **"Permission denied"**
- Ensure you're logged in as WordPress admin
- Check user capabilities
- Verify nonce is working

### **"Rate limit exceeded"**
- Wait for hourly reset
- Check AI Settings panel for current usage
- Adjust rate limits if needed

## 🎯 **Security Best Practices**

### **Do's:**
✅ Use environment variables when possible
✅ Keep API key private and secure
✅ Monitor usage and logs regularly
✅ Use strong WordPress passwords
✅ Keep WordPress and plugins updated

### **Don'ts:**
❌ Never share your API key publicly
❌ Don't commit API keys to version control
❌ Don't use the same key for multiple projects
❌ Don't ignore security warnings
❌ Don't use weak passwords

## 🔒 **Additional Security Measures You Can Take**

### **1. Server-Level Security**
```bash
# Restrict access to wp-config.php
chmod 600 wp-config.php

# Use HTTPS only
# Enable SSL/TLS on your server
```

### **2. WordPress Security**
```php
// Add to wp-config.php for extra security
define('DISALLOW_FILE_EDIT', true);
define('DISALLOW_FILE_MODS', true);
```

### **3. Firewall Protection**
- Enable server firewall
- Restrict access to WordPress admin
- Use security plugins (Wordfence, Sucuri, etc.)

## 📊 **Security Monitoring**

### **Check Logs Regularly:**
```php
// View AI request logs
$logs = get_option('ccc_ai_request_logs', []);
print_r($logs);
```

### **Monitor Rate Limits:**
- Check AI Settings panel regularly
- Monitor for unusual activity
- Review blocked IPs if any

---

## 🎉 **Summary: Your API Key is SAFE!**

- ✅ **Never exposed** to frontend users
- ✅ **Server-side only** storage
- ✅ **Multiple security layers** built-in
- ✅ **Rate limiting** prevents abuse
- ✅ **Logging** for monitoring
- ✅ **WordPress integration** for permissions

**The environment variable approach is the most secure method and is completely safe from external users accessing your API key.**

---

**Need help?** Check the troubleshooting section or review your server's environment variable configuration. Your security is our top priority! 🛡️
