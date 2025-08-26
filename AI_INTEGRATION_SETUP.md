# 🤖 AI Integration Setup Guide

## Overview
The Custom Craft Component plugin now includes AI-powered component generation using OpenAI's GPT-4o-mini model. This feature allows users to create components by describing them in natural language, eliminating the need for manual JSON creation.

## 🚀 Features

### **Direct AI Generation**
- **No ChatGPT needed** - Generate components directly within the plugin
- **Natural language input** - Describe what you want in plain English
- **Automatic field creation** - AI intelligently selects appropriate field types
- **Rate limiting** - Configurable usage limits to control costs
- **Token optimization** - Efficient prompts for cost-effective generation

### **Available Field Types**
The AI can generate components using all supported field types:
- **Text** - Single line inputs (names, titles, URLs)
- **Textarea** - Multi-line content (descriptions, testimonials)
- **Image** - Image uploads (photos, logos, backgrounds)
- **Video** - Video uploads or video URLs
- **Oembed** - External content embeds (YouTube, Vimeo)
- **Relationship** - Related posts/pages selection
- **Link** - URL links with target options
- **Email** - Email address inputs
- **Number** - Numeric inputs with validation
- **Range** - Slider inputs with min/max values
- **File** - File uploads
- **Repeater** - Repeatable field groups
- **WYSIWYG** - Rich text editors
- **Color** - Color pickers
- **Select** - Dropdown choices
- **Checkbox** - True/false options
- **Radio** - Single choice from multiple options
- **Toggle** - On/off switches

## ⚙️ Setup Instructions

### **1. Get OpenAI API Key**
1. Go to [OpenAI Platform](https://platform.openai.com/)
2. Sign up or log in to your account
3. Navigate to "API Keys" section
4. Create a new API key
5. Copy the key (it starts with `sk-`)

### **2. Configure API Key**

#### **Option A: Environment Variable (Recommended)**
Add the API key to your server's environment variables:

```bash
# For Linux/Mac servers
export CCC_OPENAI_API_KEY="sk-your-api-key-here"

# For Windows servers
set CCC_OPENAI_API_KEY=sk-your-api-key-here

# For .env files (if using a framework that supports them)
CCC_OPENAI_API_KEY=sk-your-api-key-here
```

#### **Option B: WordPress Options (Alternative)**
If you can't set environment variables, you can store the API key in WordPress options:

```php
// Add this to your wp-config.php or use a plugin like WP-CLI
update_option('ccc_openai_api_key', 'sk-your-api-key-here');
```

### **3. Configure Rate Limiting**
The plugin includes built-in rate limiting to control API usage and costs:

- **Default limit**: 50 requests per hour
- **Configurable range**: 1-1000 requests per hour
- **Cost estimation**: ~$0.00015 per request (GPT-4o-mini)

You can adjust the rate limit through the AI Settings panel in the plugin interface.

## 🎯 Usage

### **Method 1: Direct AI Generation (Recommended)**
1. Open the "AI Component Generator" modal
2. Describe your component in the AI prompt textarea
3. Click "Generate with AI"
4. Component is created automatically with all fields

**Example prompts:**
- "Create a testimonials component with customer name, testimonial content, customer photo, company name, and rating"
- "Build a team member component with name, position, bio, photo, and social media links"
- "Design a portfolio item component with title, description, image, category, and external link"

### **Method 2: ChatGPT Integration (Manual)**
1. Use the existing ChatGPT workflow
2. Describe your component in the ChatGPT prompt
3. Copy the JSON response
4. Paste and validate in the plugin

## 🔧 Configuration

### **Rate Limiting**
- Access AI Settings via the gear icon in the AI Generation section
- Adjust requests per hour (1-1000)
- Monitor current usage and remaining requests
- Reset time is displayed when limit is reached

### **Model Configuration**
- **Default**: GPT-4o-mini (most cost-effective)
- **Features**: Advanced reasoning, better JSON generation
- **Cost**: ~$0.00015 per request
- **Speed**: Fast response times

### **Token Optimization**
The plugin uses optimized prompts to:
- Minimize token usage
- Ensure consistent JSON output
- Provide clear field type guidance
- Generate meaningful labels and placeholders

## 💰 Cost Management

### **Cost Breakdown**
- **GPT-4o-mini**: ~$0.00015 per request
- **Typical component**: 1-2 requests
- **Monthly usage (100 components)**: ~$0.03

### **Cost Control Features**
- **Rate limiting**: Prevent excessive usage
- **Usage monitoring**: Track requests per hour
- **Efficient prompts**: Minimize token consumption
- **Configurable limits**: Set your own usage boundaries

## 🛡️ Security & Privacy

### **API Key Security**
- API keys are never exposed in frontend code
- All requests go through WordPress backend
- Environment variable support for secure storage
- WordPress options fallback for compatibility

### **Data Privacy**
- No component data is sent to OpenAI
- Only the component description prompt is transmitted
- Generated JSON is processed locally
- No user data is stored by OpenAI

## 🚨 Troubleshooting

### **Common Issues**

#### **"API key not configured"**
- Check if environment variable is set correctly
- Verify API key format (starts with `sk-`)
- Ensure WordPress can access environment variables

#### **"Rate limit exceeded"**
- Wait for the hourly reset
- Adjust rate limit in AI Settings
- Monitor usage patterns

#### **"Invalid JSON response"**
- Check API key validity
- Verify OpenAI account has credits
- Ensure network connectivity

#### **"Component creation failed"**
- Check WordPress permissions
- Verify database connectivity
- Review error logs for details

### **Debug Information**
The plugin provides detailed error messages and logging:
- Check browser console for frontend errors
- Review WordPress error logs for backend issues
- Use the AI Settings panel to monitor API status

## 🔄 Updates & Maintenance

### **Automatic Updates**
- Plugin updates include AI feature improvements
- Rate limiting and security enhancements
- New field type support as needed

### **Manual Updates**
- Monitor OpenAI API changes
- Update rate limits based on usage patterns
- Review cost estimates periodically

## 📞 Support

### **Getting Help**
- Check this documentation first
- Review WordPress error logs
- Test with simple prompts
- Verify API key configuration

### **Feature Requests**
- Suggest new field types
- Request prompt improvements
- Propose rate limiting enhancements
- Share usage feedback

## 🎉 Success Stories

### **Example Components Created with AI**
- **Testimonials**: Customer feedback with photos and ratings
- **Team Members**: Professional profiles with social links
- **Portfolio Items**: Project showcases with categories
- **Contact Forms**: Lead generation with validation
- **FAQ Sections**: Organized knowledge bases
- **Gallery Components**: Image collections with descriptions

### **Time Savings**
- **Manual creation**: 10-15 minutes per component
- **AI generation**: 30 seconds to 2 minutes per component
- **Efficiency gain**: 5-10x faster component creation

---

**Ready to get started?** Configure your API key and start generating components with AI today! 🚀
