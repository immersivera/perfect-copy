# Clone & Export

Clone & Export is a WordPress plugin designed to simplify the process of duplicating and transferring content between WordPress sites. It offers a streamlined, user-friendly approach to content migration through a JSON-based export/import system that preserves all essential content components.

## Features

- Export WordPress posts and pages to JSON format
- Import content from JSON with one click
- Preserves Gutenberg blocks and Classic Editor content
- Handles media files automatically
- Preserves taxonomies (categories, tags)
- Supports custom fields including ACF fields
- Maintains featured images
- Simple copy-paste mechanism for transferring content

## Requirements

- WordPress 5.8 or higher
- PHP 7.2 or higher
- Write permissions on the uploads directory for media imports

## Installation

1. Download the plugin zip file
2. Navigate to your WordPress admin panel
3. Go to Plugins > Add New
4. Click "Upload Plugin" and select the zip file
5. Click "Install Now"
6. After installation, click "Activate Plugin"

Alternatively, you can extract the plugin directly to your `/wp-content/plugins/` directory via FTP.

## Usage

### Exporting Content

1. Navigate to Tools > Clone & Export in your WordPress admin panel
2. Select the post or page you want to export from the dropdown
3. Click "Generate Export Code"
4. Once the export is generated, click "Copy to Clipboard"
5. Save the JSON code for importing to another site

### Importing Content

1. Navigate to Tools > Clone & Export Import in your WordPress admin panel
2. Paste the JSON code you exported from another site
3. Click "Validate" to verify the JSON format
4. Review the import preview to ensure it's the correct content
5. Click "Import Now"
6. Wait for the import process to complete (this may take several minutes for content with numerous images)
7. Click "View Imported Content" to see your new post/page

## Export/Import Process

During export, the plugin collects:
- Post/page content with Gutenberg blocks or Classic Editor content
- ACF custom fields (if available)
- Featured image
- In-content media references (images, videos, etc.)
- Taxonomies (categories, tags)
- Post meta data

During import, the plugin:
- Creates a new post/page with the correct post type
- Parses and inserts content blocks/editor content
- Downloads and attaches media files from source URLs
- Updates internal links to match destination site URLs
- Recreates and assigns taxonomies
- Restores meta data with adjusted URLs
- Assigns ACF fields (if available)

## Troubleshooting

- **Import Validation Errors**: Ensure your JSON is properly formatted and hasn't been modified after export.
- **Media Import Issues**: Check that the source site is accessible from the destination site. Firewalls or authentication requirements may prevent media downloads.
- **Missing Content**: If certain content elements are missing, check plugin compatibility. Some advanced custom fields or specific page builder content may not be fully supported.

## Limitations

- The plugin currently supports posts and pages only (custom post types are planned for future releases)
- Some third-party page builders may have limited compatibility
- Very complex ACF field structures might need manual adjustment after import

## Support

For support, feature requests, or bug reports, please visit the plugin repository.

## License

Clone & Export is licensed under GPL v2 or later, same as WordPress itself.
