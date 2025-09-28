# MasterStudy LMS - Tranzila Gateway Plugin

## Overview
This plugin integrates Tranzila payment gateway with MasterStudy LMS, enabling secure credit/debit card payments through Tranzila's iframe integration.

## Version 7.0 Features
- ✅ Full iframe integration with Tranzila's new API
- ✅ Automatic order status updates
- ✅ IPN (Instant Payment Notification) support
- ✅ Transaction logging and tracking
- ✅ Multi-language support
- ✅ Customizable payment form appearance
- ✅ Debug mode for troubleshooting
- ✅ Mobile-responsive payment page

## Requirements
- WordPress 6.0+
- PHP 7.4+
- MasterStudy LMS Pro installed and activated
- Tranzila merchant account

## Installation

### 1. File Structure
Create the following directory structure in your WordPress plugins folder:

```
wp-content/plugins/mslms-tranzila-gateway/
├── mslms-tranzila-gateway.php (main plugin file)
├── includes/
│   ├── Admin.php
│   └── Checkout.php
├── templates/
│   └── payment-page.php
├── assets/
│   ├── checkout.js
│   └── admin.js (optional)
└── README.md
```

### 2. Upload Files
1. Copy all provided PHP files to their respective directories
2. Ensure file permissions are set correctly (644 for files, 755 for directories)

### 3. Activate Plugin
1. Go to WordPress Admin → Plugins
2. Find "MasterStudy LMS – Tranzila Gateway"
3. Click "Activate"

## Configuration

### 1. Basic Settings
Navigate to **Settings → Tranzila** or **Tranzila** in the admin menu:

#### Required Settings:
- **Enable Gateway**: Check to activate
- **Terminal Name**: Your Tranzila terminal name (provided by Tranzila)
- **Mode**: Select "Production" for live payments or "Sandbox" for testing

#### Payment Settings:
- **Language**: Payment form language (Hebrew, English, Russian, Arabic)
- **Currency**: Select your transaction currency
  - 1 = ILS (₪)
  - 2 = USD ($)
  - 978 = EUR (€)
  - 826 = GBP (£)
  - 392 = JPY (¥)
- **Credit Type**: 
  - Regular = Standard payment
  - Credit = Credit payment
  - Installments = Payment in installments
- **Max Installments**: If using installments, set maximum number (1-36)

#### Appearance Settings:
- **Background Color**: Payment form background
- **Text Color**: Payment form text color
- **Button Color**: Submit button color

### 2. Tranzila Terminal Configuration
In your Tranzila merchant account:

1. **Set IPN URL**:
   ```
   https://yoursite.com/wp-json/mslms-tranzila/v1/ipn
   ```

2. **Configure Success URL**:
   ```
   https://yoursite.com/tranzila-return/
   ```

3. **Configure Fail URL**:
   ```
   https://yoursite.com/tranzila-fail/
   ```

### 3. MasterStudy LMS Settings
1. Go to **STM LMS → Settings → Payment Methods**
2. Enable "Tranzila" payment method
3. Set description (optional)

## Testing

### Test Mode Setup
1. Set **Mode** to "Sandbox" in plugin settings
2. Use Tranzila test terminal credentials
3. Test card numbers:
   - Success: 4111111111111111
   - Failure: 4111111111111112

### Test Transaction Flow
1. Add a course to cart
2. Proceed to checkout
3. Select "Credit/Debit Card (Tranzila)"
4. Complete payment on secure form
5. Verify order status updates

## Troubleshooting

### Common Issues

#### 1. Payment Page Not Loading
- Check terminal name is correct
- Verify plugin is activated
- Clear WordPress cache
- Check browser console for errors

#### 2. Orders Not Updating
- Verify IPN URL is accessible
- Check debug logs if enabled
- Ensure proper file permissions
- Test with debug mode enabled

#### 3. Redirect Issues After Payment
- Clear permalinks (Settings → Permalinks → Save)
- Check .htaccess file permissions
- Verify checkout page exists in MasterStudy settings

### Debug Mode
Enable debug mode to log transactions:
1. Check "Debug Mode" in settings
2. View logs in: `/wp-content/debug.log`
3. Search for `[Tranzila]` entries

## Hooks and Filters

### Available Filters

```php
// Customize iframe parameters
add_filter('mslms_tranzila_iframe_params', function($params, $order_id) {
    // Add custom parameters
    $params['custom_field'] = 'value';
    return $params;
}, 10, 2);

// Modify success redirect URL
add_filter('mslms_tranzila_success_url', function($url, $order_id) {
    // Custom redirect logic
    return $url;
}, 10, 2);
```

### Available Actions

```php
// After successful payment
add_action('mslms_tranzila_payment_complete', function($order_id, $transaction_id) {
    // Custom logic after payment
}, 10, 2);

// After failed payment
add_action('mslms_tranzila_payment_failed', function($order_id, $reason) {
    // Handle failed payment
}, 10, 2);
```

## Security

### PCI Compliance
- Payment data is handled entirely by Tranzila's secure iframe
- No credit card information touches your server
- SSL certificate required for production use

### Best Practices
1. Always use HTTPS in production
2. Keep WordPress and plugins updated
3. Use strong passwords for admin accounts
4. Regularly review transaction logs
5. Enable debug mode only for troubleshooting

## API Endpoints

### REST API Routes
- `/wp-json/mslms-tranzila/v1/ipn` - IPN handler
- `/wp-json/mslms-tranzila/v1/status` - Status check
- `/wp-json/mslms-tranzila/v1/validate` - Transaction validation

## Database Tables

### Transaction Log Table
Table: `{prefix}_mslms_tranzila_transactions`

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| order_id | bigint | MasterStudy order ID |
| transaction_id | varchar(100) | Tranzila transaction ID |
| status | varchar(50) | Transaction status |
| response_code | varchar(10) | Tranzila response code |
| amount | decimal(10,2) | Transaction amount |
| currency | varchar(10) | Currency code |
| created_at | datetime | Transaction date |
| response_data | longtext | Full response JSON |

## Support

### Resources
- [Tranzila Documentation](https://docs.tranzila.com/)
- [MasterStudy LMS Documentation](https://docs.stylemixthemes.com/masterstudy-lms)

### Getting Help
1. Enable debug mode and check logs
2. Review common issues above
3. Check browser console for JavaScript errors
4. Verify all settings are correct

## Changelog

### Version 7.0
- Complete rewrite with modern architecture
- Added transaction logging database
- Improved error handling and debugging
- Enhanced mobile responsiveness
- Added customizable appearance settings
- Fixed redirect issues after payment
- Added multi-language support
- Improved IPN handling
- Added transaction validation

### Version 6.1
- Fixed 404 redirect after payment
- Basic iframe integration

## License
GPL v2 or later

## Credits
Enhanced integration for MasterStudy LMS and Tranzila payment gateway.