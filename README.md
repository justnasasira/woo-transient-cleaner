# WooCommerce Transient Cleaner

A WordPress plugin that automatically cleans WooCommerce transients to prevent database overload and site crashes.

## Description

WooCommerce Transient Cleaner is a lightweight plugin that helps maintain your WooCommerce site's performance by automatically cleaning expired transients and WooCommerce transients. This prevents the common issue of database overload that can cause site crashes.

### Features

- Automatically cleans expired transients and WooCommerce transients
- Configurable cleanup interval (1-30 days)
- Manual cleanup trigger
- Status monitoring (last run and next scheduled run)
- Optional logging of cleanup activities
- Clean and intuitive admin interface
- Lightweight and optimized for performance

## Installation

1. Download the plugin files
2. Upload the `woo-transient-cleaner` folder to the `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to Tools > WC Transient Cleaner to configure the plugin

## Usage

### Automatic Cleanup

The plugin will automatically clean transients based on your configured interval. By default, it runs every 3 days.

### Manual Cleanup

You can manually trigger the cleanup process at any time:

1. Go to Tools > WC Transient Cleaner
2. Click the "Clean Transients Now" button

### Configuration

1. Go to Tools > WC Transient Cleaner
2. Set your desired cleanup interval (1-30 days)
3. Optionally enable logging
4. Click "Save Changes"

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- WooCommerce 5.0 or higher

## Security

- All cleanup operations require administrator privileges
- AJAX requests are protected with nonces
- Input is properly sanitized and validated

## Support

If you encounter any issues or have questions, please create an issue in the GitHub repository.

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed by Justus Nasasira 
