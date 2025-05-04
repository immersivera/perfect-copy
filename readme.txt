=== Perfect Copy ===
Contributors: perfectcopyteam
Donate link: https://example.com/donate
Tags: perfect copy, content migration, export, import, content cloning, post duplication, page duplication, media duplication, post duplicator, page duplicator, media duplicator
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Perfect Copy simplifies content migration between WordPress sites through a JSON-based export/import system.

== Description ==

Perfect Copy is designed to simplify the process of duplicating and transferring content between WordPress sites. It offers a streamlined, user-friendly approach to content migration through a JSON-based export/import system that preserves all essential content components.

= Features =

* Export WordPress posts and pages to JSON format
* Import content from JSON with one click
* Preserves Gutenberg blocks and Classic Editor content
* Handles media files automatically
* Preserves taxonomies (categories, tags)
* Supports custom fields including ACF fields
* Maintains featured images
* Simple copy-paste mechanism for transferring content

= Requirements =

* WordPress 5.0 or higher
* PHP 7.0 or higher
* Write permissions on the uploads directory for media imports

== Installation ==

1. Upload the `perfectcopy` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to the PerfectCopy menu in your WordPress admin panel

Alternatively:

1. Download the plugin zip file
2. Navigate to your WordPress admin panel
3. Go to Plugins > Add New
4. Click "Upload Plugin" and select the zip file
5. Click "Install Now"
6. After installation, click "Activate Plugin"

== Frequently Asked Questions ==

= Does this plugin modify my database structure? =

No, Perfect Copy does not create any custom database tables. It uses the WordPress options API for storing settings and transients for temporary import data.

= Can I transfer media files between sites? =

Yes, Perfect Copy handles media files attached to your content automatically during the import process.

= Is this compatible with Gutenberg? =

Yes, Perfect Copy fully supports Gutenberg blocks and preserves their structure when transferring content.

= Will this work with my custom fields? =

Yes, Perfect Copy preserves custom fields, including those created with Advanced Custom Fields (ACF).

== Screenshots ==

1. Export interface
2. Import interface
3. Batch processing screen

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release of Perfect Copy.
