=== BD Courier Order Ratio Checker ===
Contributors: synthiasoft,rashedulhaquerumi
Donate link: https://bdcourier.com/donate
Tags: order ratio, courier, woocommerce, bd courier, tracking
Requires at least: 6.0
Tested up to: 6.7.2
Stable tag: 3.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

This plugin lets users fetch and display customer order ratios from BD Courier using their API.

== Description ==

BD Courier Order Ratio Checker is a powerful fraud prevention and order management plugin for WooCommerce stores. It integrates with the BD Courier API to automatically check customer order success ratios and help prevent fraudulent orders.

= Key Features =

* **Order Ratio Checking**: Automatically check customer order success ratios from BD Courier API
* **Fraud Prevention**: Block orders from customers with low success ratios
* **Duplicate Order Detection**: Prevent duplicate orders using browser fingerprinting
* **Incomplete Order Tracking**: Track and manage abandoned checkouts
* **Blocked Entities Management**: Block IP addresses, phone numbers, emails, and browser fingerprints
* **VPN/Proxy Detection**: Detect and optionally block VPN, proxy, Tor, and hosting/datacenter IPs
* **Order History**: Centralized history table for better performance
* **Modern Admin Dashboard**: React-based admin interface with intuitive design
* **Database Management**: Built-in database structure checker and updater
* **Data Migration**: Migrate historical data from order meta to dedicated tables

= Requirements =

* WordPress 5.8 or higher
* WooCommerce 5.0 or higher
* PHP 7.4 or higher
* MySQL 5.6 or higher (or MariaDB equivalent)
* BD Courier API account and token

= Features by Plan =

* **Free Plan**: Basic order ratio checking
* **Paid Plan**: Extended features including incomplete order tracking, fraud blocker, duplicate order detection, VPN/proxy detection, and hosting/datacenter IP blocking

== Installation ==

1. Upload the `bd-courier-order-ratio-checker` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **BD Courier → Settings** and enter your API token
4. Configure your settings including minimum success ratio, auto-blocking, and error messages

== Frequently Asked Questions ==

= Do I need a BD Courier API account? =

Yes, you need an active BD Courier account with an API token to use this plugin.

= What is the minimum success ratio? =

The default minimum success ratio is 70%, but you can customize this in the settings. Orders from customers with a success ratio below this threshold will be blocked.

= Can I block specific IP addresses or phone numbers? =

Yes, you can manually block IP addresses, phone numbers, emails, and browser fingerprints from the Blocked Entities page.

= Does the plugin work with WooCommerce HPOS? =

Yes, the plugin is fully compatible with WooCommerce High-Performance Order Storage (HPOS).

= How do I migrate historical data? =

Go to **BD Courier → Settings → Database & Migration** and use the migration wizard to move historical courier data from order meta to the dedicated history table.

= What if I see database structure issues? =

Go to **BD Courier → Settings → Database & Migration**, click "Check Database" to see what's missing, then click "Update Database" to fix any issues.

== Screenshots ==

1. Settings page with API configuration
2. Order ratio display in WooCommerce orders list
3. Blocked entities management page
4. Incomplete orders tracking page
5. Database management and migration wizard

== Changelog ==

= 3.1.0 =
* Updated plugin version to 3.1.0
* Removed all console.log statements from source files
* Changed cache busting to use plugin version instead of filemtime for better cache control
* Fixed incomplete orders page script loading issues
* Improved CartFlows checkout compatibility
* Enhanced error handling and user experience
* Updated WordPress compatibility to 6.7.2
* Improved database migration wizard
* Enhanced blocked entities management
* Fixed authorization checks for extended features

= 2.0.1 =
* Premium Api Added
* Refresh Issue Solved
* Design Improved
* Orderlist Slow Issue Solved

= 1.8 =
* Security Issue Fixed

= 1.7 =
* Initial release of the plugin.
* Added support for WooCommerce integration.
* Implemented AJAX-based search form and results display.

== Upgrade Notice ==

= 1.8 =
* Security Issue Fixed

= 1.7 =
Upgrade to the latest version to ensure compatibility with WooCommerce and WordPress 5.8.

== Support ==

For support, feature requests, or bug reports, please visit:
Plugin URI: https://rasedulhaque.com/bd-courier-order-ratio-checker

== Credits ==

Built with React and TypeScript for the admin interface. Uses SweetAlert2 for beautiful modals. Integrates with BD Courier API for order data.
