# 🌍 Centralized Tracking System Setup Guide

## **🎯 Overview**
This system allows you to track **ALL** Custom Craft Component plugin installations worldwide from your central super admin dashboard. Every user registration, plugin installation, and activity will be reported to your main website.

## **🏗️ System Architecture**

### **Components:**
1. **Central License Server** (Your main website)
2. **Client Plugin** (Each installation worldwide)
3. **Real-time Reporting** (Automatic data collection)
4. **Global Dashboard** (View all installations)

### **Data Flow:**
```
Plugin Installation → Reports to → Central Server → Your Dashboard
User Registration → Reports to → Central Server → Your Dashboard
Health Checks → Reports to → Central Server → Your Dashboard
```

## **🚀 Setup Instructions**

### **Step 1: Central License Server is Built-In (No Separate Plugin Needed!)**

**🎉 GREAT NEWS!** The centralized tracking system is **already built into your plugin**. You don't need to install any separate plugins!

#### **What's Already Included:**
- ✅ **Central License Server** - Built into your main plugin
- ✅ **Database tables** - Created automatically
- ✅ **REST API endpoints** - Ready to receive reports
- ✅ **Admin dashboard** - Accessible via "CCC Worldwide" menu
- ✅ **Role-based access** - Only admin/super_admin can see data

#### **What This Creates:**
- **Database tables** for worldwide tracking
- **REST API endpoints** to receive reports
- **Admin dashboard** to view all installations
- **Real-time statistics** from all domains

### **Step 2: Configure Your Plugin (Built-In Configuration)**

#### **Automatic Setup:**
Your plugin automatically:
- ✅ **Reports installation** when activated
- ✅ **Reports user registrations** in real-time
- ✅ **Sends health checks** daily
- ✅ **Reports deactivation** when removed

#### **Configuration (Optional):**
1. **Go to**: WordPress Admin → Custom Craft Settings → Master Password
2. **Set master password**: For local admin access
3. **Configure central server**: Set your main website URL (if different)
4. **Test connection**: Ensure communication works
5. **Enable reporting**: Turn on central server reporting (enabled by default)

### **Step 3: Verify System is Working**

#### **Check Your Dashboard:**
1. **Go to**: Your WordPress admin
2. **Look for**: "CCC Worldwide" menu item (admin/super_admin only)
3. **View dashboard**: Should show installations and users from all domains
4. **Check statistics**: Real-time counts from all installations

#### **Test with Other Installations:**
1. **Install plugin** on another website
2. **Register a user**: Should appear in your central dashboard
3. **Check logs**: WordPress error logs for reporting status
4. **Verify data**: Appears in your "CCC Worldwide" dashboard

## **📊 What You'll See in Your Dashboard**

### **Global Statistics:**
- **Total Active Installations**: All websites using your plugin
- **Total Users**: All users across all installations
- **Pending Licenses**: License requests from all domains
- **Active Licenses**: Approved licenses worldwide

### **Recent Installations:**
- **Domain**: Website URL
- **Site Name**: WordPress site name
- **Admin Email**: Site administrator email
- **Plugin Version**: Version of plugin installed
- **Last Activity**: When they last reported
- **Users Count**: How many users on that site
- **Components Count**: How many components created

### **Recent Users:**
- **Domain**: Which website they're from
- **Email**: User's email address
- **Phone**: User's phone number
- **Role**: User role (user/admin/super_admin)
- **Registration Date**: When they signed up

## **🔧 Configuration Options**

### **Central Server Settings:**
- **License Server URL**: Your main website URL
- **Central Reporting**: Enable/disable reporting
- **Connection Testing**: Test communication

### **Client Plugin Settings:**
- **Installation Tracking**: Local tracking toggle
- **User Registration**: Report new users
- **Health Checks**: Daily status reports
- **Deactivation**: Report when plugin removed

## **📡 API Endpoints**

### **Report Endpoint:**
```
POST /wp-json/ccc-central/v1/report
```

### **Test Endpoint:**
```
POST /wp-json/ccc-central/v1/test
```

### **Data Sent:**
- **User registrations** with full details
- **Plugin installations** with site info
- **Health checks** with usage statistics
- **Deactivation notices** when removed

## **🔒 Security Features**

### **Data Protection:**
- **Sanitized inputs** for all received data
- **Nonce verification** for admin actions
- **Role-based access** to dashboard
- **Secure REST API** endpoints

### **Privacy Compliance:**
- **No sensitive data** stored unnecessarily
- **User consent** for data collection
- **Data retention** policies
- **GDPR compliance** considerations

## **🚨 Troubleshooting**

### **Common Issues:**

#### **1. No Data Appearing:**
- **Check plugin activation**: Ensure central server is active
- **Verify URL**: Check license server URL is correct
- **Test connection**: Use test button in settings
- **Check logs**: WordPress error logs for issues

#### **2. Connection Failed:**
- **Verify domain**: Ensure main website is accessible
- **Check SSL**: HTTPS vs HTTP mismatch
- **Firewall issues**: Server blocking connections
- **Plugin conflicts**: Other plugins interfering

#### **3. Missing Users/Installations:**
- **Check reporting**: Ensure central reporting is enabled
- **Verify hooks**: User registration hooks working
- **Check permissions**: WordPress user capabilities
- **Database issues**: Tables created correctly

### **Debug Steps:**
1. **Enable WordPress debug**: `WP_DEBUG` in wp-config.php
2. **Check error logs**: Server and WordPress logs
3. **Test endpoints**: Use Postman or similar tool
4. **Verify database**: Check tables exist and have data

## **📈 Monitoring and Maintenance**

### **Daily Checks:**
- **New installations**: Monitor growth
- **User registrations**: Track adoption
- **Health reports**: Ensure all sites active
- **Error logs**: Check for issues

### **Weekly Tasks:**
- **Statistics review**: Analyze trends
- **License management**: Process requests
- **User support**: Address issues
- **System updates**: Keep plugins current

### **Monthly Tasks:**
- **Performance review**: Check system health
- **Security audit**: Review access logs
- **Backup verification**: Ensure data safety
- **Feature planning**: Plan improvements

## **🔮 Future Enhancements**

### **Planned Features:**
- **Real-time notifications** for new installations
- **Advanced analytics** and reporting
- **Bulk license management** tools
- **User communication** system
- **Payment integration** for licenses
- **Export/import** functionality

### **Custom Development:**
- **API integrations** with other systems
- **Custom dashboards** for specific needs
- **Automated workflows** for license approval
- **Advanced user management** features

## **📞 Support and Help**

### **Getting Help:**
1. **Check this guide** first
2. **Review error logs** for specific issues
3. **Test connections** step by step
4. **Contact support** with detailed information

### **Required Information:**
- **WordPress version** on both sites
- **Plugin version** installed
- **Error messages** from logs
- **Steps to reproduce** the issue
- **Screenshots** of problems

---

## **✅ Quick Setup Checklist**

- [ ] **Plugin activated** (central server is built-in)
- [ ] **Database tables** created automatically
- [ ] **"CCC Worldwide" menu** visible (admin/super_admin only)
- [ ] **Central server URL** configured (if different domain)
- [ ] **Connection test** successful
- [ ] **User registration** reporting working
- [ ] **Dashboard showing** data from multiple installations

**🎯 Result**: You now have **complete visibility** into all plugin installations worldwide, with real-time data collection and a centralized management system!
