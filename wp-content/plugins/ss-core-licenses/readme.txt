=== SecureSoft Core & Licenses ===
Contributors: securesoft
Tags: licenses, encryption, woocommerce, digital products
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Core foundation plugin for SecureSoft system - encryption, license management, audit logging, and WooCommerce integration.

== Description ==

SecureSoft Core & Licenses is the foundation plugin for the SecureSoft system, providing:

* AES-256-GCM encryption for license codes
* License management with status tracking
* License pools organized by product
* Comprehensive audit logging
* WooCommerce integration for automatic license assignment
* REST API endpoints for license management
* Admin interface for managing licenses

== Installation ==

1. Upload the plugin to `/wp-content/plugins/ss-core-licenses/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Ensure WooCommerce is installed and activated
4. The plugin will automatically create database tables on activation

== Frequently Asked Questions ==

= Does this plugin require WooCommerce? =

Yes, WooCommerce is required for the plugin to function properly.

= How do I import licenses? =

Go to SecureSoft > Import and upload a CSV or Excel file with license codes.

= How do I rotate encryption keys? =

Go to SecureSoft > Key Management and click "Rotate Keys". Old keys are retained for decrypting existing licenses.

= Can I use this with external license providers? =

Yes, set the Delivery Mode to "External" in product settings to use external providers.

== Changelog ==

= 1.0.0 =
* Initial release
* Core encryption system
* License management
* WooCommerce integration
* Admin interface
* REST API endpoints
* Audit logging

== Upgrade Notice ==

= 1.0.0 =
Initial release of SecureSoft Core & Licenses plugin.

