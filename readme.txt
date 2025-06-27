=== Smart WP Export ===
Contributors: smartartsbt  
Donate link: http://smartart.hu  
Tags: export, database, shortcode, custom, backup, csv  
Requires at least: 5.0  
Tested up to: 6.8  
Requires PHP: 7.2  
Stable tag: 1.0.1  
License: GPLv2 or later  
License URI: http://www.gnu.org/licenses/gpl-2.0.html  

Export any WordPress database table or post type with pagination, filtering, and CSV download — directly from your admin or frontend.

== Description ==

**Smart WP Export** is a powerful and flexible plugin for exporting WordPress data from any database table or post type. With built-in filtering, pagination, and CSV export features, this tool is ideal for developers, site administrators, and anyone needing quick access to structured data.

= Key Features =

- Export any database table or custom post type
- Date range filters
- Column selection for export
- Pagination for large datasets
- CSV download capability
- REST API support
- Admin menu integration
- Shortcode for frontend export view
- Secure access with capability checks and nonces

= Shortcode =

Use the shortcode `[smart_export_viewer]` to embed the export interface on any page or post. Useful for providing controlled export access to logged-in users.

= REST API =

The plugin registers a secure REST endpoint:  
`/wp-json/smart-export/v1/table`

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/smart-wp-export` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to **Tools > Database Export** to begin exporting.

== Frequently Asked Questions ==

= Can I filter by date when exporting? =  
Yes. When available, the plugin detects a date column (like `post_date`, `created_at`, etc.) and allows filtering records by date range.

= Does this support custom tables created by other plugins? =  
Absolutely. You can choose from all available tables in your database.

= Is this plugin safe to use on production sites? =  
Yes. All actions are permission-checked (`manage_options`), and nonces are used for AJAX and REST requests.

== Screenshots ==

1. Admin interface showing available database tables.
2. Filter and pagination controls.
3. Export results with column selection.
4. CSV export button in action.
5. Shortcode output on the frontend.

== Changelog ==

= 1.0.0 =
* Initial release with:
  - Table and post type export
  - REST API integration
  - Pagination and filtering
  - CSV export
  - Shortcode support

== Upgrade Notice ==

= 1.0.0 =
Initial stable release of Smart WP Export.

= 1.0.1 =
Update sanitize issues Smart WP Export.

== License ==

This plugin is licensed under the GPLv2 or later. See http://www.gnu.org/licenses/gpl-2.0.html.

