# Product Requirements Document (PRD): WP Content Porter Plugin

## Overview

WP Content Porter is a WordPress plugin designed to simplify the process of duplicating and transferring content between WordPress sites. It offers a streamlined, user-friendly approach to content migration through a JSON-based export/import system that preserves all essential content components.

## Target Audience

- WordPress content creators and bloggers
- Developers who manage multiple WordPress environments (dev/staging/production)
- Non-technical WordPress users who need to duplicate content across sites

## Problem Statement

Transferring WordPress content between sites is currently a complex, multi-step process that requires technical knowledge. Existing solutions are either too complicated for average users or require direct server access. Content creators need a simple, reliable way to move their work between development and production environments.

## Solution

WP Content Porter provides a straightforward copy-paste mechanism to transfer posts and pages between WordPress sites while preserving content structure, media, custom fields, and taxonomies.

## MVP Feature Requirements

### 1. Content Export Functionality
- **1.1** Create an admin page under the WordPress Tools menu for export functionality
- **1.2** Provide a dropdown/search interface to select posts or pages for export
- **1.3** Generate comprehensive JSON string containing:
  - Post/page content with Gutenberg blocks or Classic Editor content
  - ACF custom fields
  - Featured image reference
  - In-content media references (images, videos, etc.)
  - Taxonomies (categories, tags)
  - Post meta data
- **1.4** Provide a copy-to-clipboard button for the generated JSON
- **1.5** Display export success confirmation

### 2. Content Import Functionality
- **2.1** Create an admin page under the WordPress Tools menu for import functionality
- **2.2** Provide text area for pasting JSON export string
- **2.3** Include a validation button to verify JSON format and content before import
- **2.4** Display preview of content to be imported (title, type, media count)
- **2.5** Process import operation:
  - Create new post/page with correct post type
  - Parse and insert content blocks/editor content
  - Download and attach media files from source URLs
  - Update internal links to match destination site URLs
  - Recreate and assign taxonomies
  - Restore meta data with adjusted URLs
  - Assign ACF fields
- **2.6** Show progress indicator during import, especially for media downloads
- **2.7** Display success/error messages after import completion

### 3. Media Handling
- **3.1** Extract media URLs from content and meta fields
- **3.2** Download media files from source site
- **3.3** Add media to WordPress media library with appropriate attribution
- **3.4** Update content references to point to new media URLs
- **3.5** Handle errors when media is inaccessible

### 4. Error Handling & Validation
- **4.1** Validate JSON structure before import
- **4.2** Check for required fields and content integrity
- **4.3** Provide clear error messages for failed imports
- **4.4** Allow retry of failed media downloads
- **4.5** Create import logs for troubleshooting

## Non-functional Requirements

### 1. Performance
- **1.1** Export process should complete within 5 seconds for standard posts
- **1.2** Import process should handle posts with up to 20 media items efficiently
- **1.3** Plugin should not significantly impact site performance

### 2. Compatibility
- **2.1** Support WordPress 5.8 and higher
- **2.2** Compatible with Gutenberg blocks
- **2.3** Compatible with Classic Editor
- **2.4** Support for ACF fields (free version)
- **2.5** Work with standard WordPress themes and common page builders

### 3. Security
- **3.1** Sanitize all input and output
- **3.2** Implement WordPress nonces for form submissions
- **3.3** Respect WordPress capability checks for post editing
- **3.4** Validate media source URLs before downloading

### 4. User Experience
- **4.1** Intuitive UI that requires minimal documentation
- **4.2** Clear feedback at each step of the process
- **4.3** Progress indicators for operations that take time
- **4.4** Helpful error messages that suggest solutions

## Technical Specifications

### File Structure
```
wp-content-porter/
├── wp-content-porter.php       # Main plugin file
├── includes/
│   ├── class-admin.php         # Admin interface
│   ├── class-exporter.php      # Export functionality
│   ├── class-importer.php      # Import functionality
│   ├── class-media-handler.php # Media processing
│   └── class-json-processor.php # JSON processing
├── assets/
│   ├── css/                    # Stylesheets
│   ├── js/                     # JavaScript files
│   └── images/                 # Plugin images
└── languages/                  # Internationalization
```

### Database
- No custom tables required for MVP
- Will use WordPress options API for plugin settings
- Temporary import data stored in transients

## User Flows

### Export Flow
1. User navigates to Tools > WP Content Porter > Export
2. User selects post/page from dropdown
3. User clicks "Generate Export Code"
4. System displays JSON in text area
5. User clicks "Copy to Clipboard"
6. User receives confirmation of copy

### Import Flow
1. User navigates to Tools > WP Content Porter > Import
2. User pastes JSON into text area
3. User clicks "Validate"
4. System displays preview of content to be imported
5. User clicks "Import Now"
6. System shows progress as content and media are processed
7. System displays success message with link to new post/page

## Future Enhancements (Post-MVP)
- Batch export/import of multiple posts
- Export to file / Import from file
- Direct site-to-site transfer via API
- Support for additional page builders (Elementor, Divi)
- Support for WooCommerce products
- Custom post type support
- User interface for mapping categories/taxonomies between sites

## Success Metrics
- Time saved compared to traditional migration methods
- Percentage of successful imports without errors
- User feedback on ease of use
- Number of active installations

This PRD provides a comprehensive guide for implementing the WP Content Porter plugin MVP. Development should focus on creating a stable, user-friendly core experience before adding additional features.