<?php
/**
 * Media Handler class for SiteSync Cloner.
 *
 * @package SiteSync_Cloner
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Media Handler class.
 */
class SiteSync_Cloner_Media_Handler {

    /**
     * Extract media references from export data.
     *
     * @param array $export_data The export data.
     * @return array Media references.
     */
    public function extract_media_references( $export_data ) {
        $media_references = array();
        
        // Extract from post content.
        if ( ! empty( $export_data['post_content'] ) ) {
            $this->extract_urls_from_content( $export_data['post_content'], $media_references );
        }
        
        // Extract from content blocks if available.
        if ( ! empty( $export_data['content_blocks'] ) ) {
            $this->extract_urls_from_blocks( $export_data['content_blocks'], $media_references );
        }
        
        // Extract from featured image.
        if ( ! empty( $export_data['featured_image'] ) && ! empty( $export_data['featured_image']['url'] ) ) {
            $url = $export_data['featured_image']['url'];
            if ( $this->is_local_media_url( $url ) ) {
                $media_references[ $url ] = array(
                    'url'  => $url,
                    'type' => 'featured_image',
                );
            }
        }
        
        // Extract from meta.
        if ( ! empty( $export_data['meta'] ) ) {
            $this->extract_urls_from_meta( $export_data['meta'], $media_references );
        }
        
        // Extract from ACF fields if available.
        if ( ! empty( $export_data['acf_fields'] ) ) {
            $this->extract_urls_from_acf( $export_data['acf_fields'], $media_references );
        }
        
        return $media_references;
    }

    /**
     * Count media in content.
     *
     * @param array $content_data The content data.
     * @return int Media count.
     */
    public function count_media_in_content( $content_data ) {
        $media_references = $this->extract_media_references( $content_data );
        return count( $media_references );
    }

    /**
     * Extract URLs from content.
     *
     * @param string $content The content.
     * @param array  $media_references The media references array to populate.
     * @return void
     */
    private function extract_urls_from_content( $content, &$media_references ) {
        // Match all image src and background-image URLs.
        $pattern = '/(src|background-image|url)\s*=\s*[\'"]?\s*([^\'"\s>)]+\.(jpg|jpeg|png|gif|webp|svg))/i';
        preg_match_all( $pattern, $content, $matches );
        
        if ( ! empty( $matches[2] ) ) {
            foreach ( $matches[2] as $url ) {
                if ( $this->is_local_media_url( $url ) ) {
                    $media_references[ $url ] = array(
                        'url'  => $url,
                        'type' => 'content',
                    );
                }
            }
        }
        
        // Match all shortcodes with image IDs.
        $pattern = '/\[gallery.*ids\s*=\s*[\'"]([0-9,]+)[\'"]/i';
        preg_match_all( $pattern, $content, $matches );
        
        if ( ! empty( $matches[1] ) ) {
            foreach ( $matches[1] as $id_list ) {
                $ids = explode( ',', $id_list );
                foreach ( $ids as $id ) {
                    $url = wp_get_attachment_url( $id );
                    if ( $url && $this->is_local_media_url( $url ) ) {
                        $media_references[ $url ] = array(
                            'url'  => $url,
                            'type' => 'gallery',
                        );
                    }
                }
            }
        }
    }

    /**
     * Extract URLs from blocks.
     *
     * @param array $blocks The blocks.
     * @param array $media_references The media references array to populate.
     * @return void
     */
    private function extract_urls_from_blocks( $blocks, &$media_references ) {
        foreach ( $blocks as $block ) {
            // Extract URLs from block attributes.
            if ( ! empty( $block['attrs'] ) ) {
                $this->extract_urls_from_block_attrs( $block['attrs'], $media_references );
            }
            
            // Extract URLs from innerContent.
            if ( ! empty( $block['innerContent'] ) ) {
                foreach ( $block['innerContent'] as $content ) {
                    if ( is_string( $content ) ) {
                        $this->extract_urls_from_content( $content, $media_references );
                    }
                }
            }
            
            // Process inner blocks recursively.
            if ( ! empty( $block['innerBlocks'] ) ) {
                $this->extract_urls_from_blocks( $block['innerBlocks'], $media_references );
            }
        }
    }

    /**
     * Extract URLs from block attributes.
     *
     * @param array $attrs The block attributes.
     * @param array $media_references The media references array to populate.
     * @return void
     */
    private function extract_urls_from_block_attrs( $attrs, &$media_references ) {
        foreach ( $attrs as $key => $value ) {
            if ( is_string( $value ) && $this->is_media_attribute( $key ) ) {
                if ( $this->is_local_media_url( $value ) ) {
                    $media_references[ $value ] = array(
                        'url'  => $value,
                        'type' => 'block',
                    );
                }
            } elseif ( $key === 'id' && is_numeric( $value ) && isset( $attrs['url'] ) ) {
                // This might be a media block.
                $url = $attrs['url'];
                if ( $this->is_local_media_url( $url ) ) {
                    $media_references[ $url ] = array(
                        'url'  => $url,
                        'type' => 'block',
                        'id'   => $value,
                    );
                }
            } elseif ( is_array( $value ) ) {
                // Handle nested attributes.
                $this->extract_urls_from_block_attrs( $value, $media_references );
            }
        }
    }

    /**
     * Check if attribute is a media attribute.
     *
     * @param string $key The attribute key.
     * @return bool True if media attribute, false otherwise.
     */
    private function is_media_attribute( $key ) {
        $media_attrs = array( 'src', 'url', 'backgroundImage', 'mediaUrl', 'href', 'thumbnail' );
        return in_array( $key, $media_attrs, true );
    }

    /**
     * Extract URLs from meta.
     *
     * @param array $meta The meta data.
     * @param array $media_references The media references array to populate.
     * @return void
     */
    private function extract_urls_from_meta( $meta, &$media_references ) {
        foreach ( $meta as $key => $value ) {
            if ( is_string( $value ) && $this->contains_media_url( $value ) ) {
                $this->extract_urls_from_content( $value, $media_references );
            } elseif ( is_array( $value ) ) {
                $this->extract_urls_from_meta( $value, $media_references );
            }
        }
    }

    /**
     * Extract URLs from ACF fields.
     *
     * @param array $fields The ACF fields.
     * @param array $media_references The media references array to populate.
     * @return void
     */
    private function extract_urls_from_acf( $fields, &$media_references ) {
        foreach ( $fields as $key => $value ) {
            if ( is_string( $value ) && $this->contains_media_url( $value ) ) {
                $this->extract_urls_from_content( $value, $media_references );
            } elseif ( is_array( $value ) ) {
                $this->extract_urls_from_acf( $value, $media_references );
            }
        }
    }

    /**
     * Check if string contains a media URL.
     *
     * @param string $str The string to check.
     * @return bool True if contains media URL, false otherwise.
     */
    private function contains_media_url( $str ) {
        $pattern = '/\.(jpg|jpeg|png|gif|webp|svg)/i';
        return preg_match( $pattern, $str );
    }

    /**
     * Check if URL is a local media URL.
     *
     * @param string $url The URL to check.
     * @return bool True if local media URL, false otherwise.
     */
    private function is_local_media_url( $url ) {
        // Check if URL is relative.
        if ( 0 === strpos( $url, '/' ) && '/' !== $url[1] ) {
            return true;
        }
        
        // Check if URL is from the same domain.
        $site_url = site_url();
        $site_domain = parse_url( $site_url, PHP_URL_HOST );
        
        $url_domain = parse_url( $url, PHP_URL_HOST );
        
        return $url_domain === $site_domain;
    }

    /**
     * Process media files for import.
     *
     * @param array $import_data The import data.
     * @return array|WP_Error Processed media data or error.
     */
    public function process_media_files( $import_data ) {
        $media_references = $this->extract_media_references( $import_data );
        $processed_media = array();
        
        if ( empty( $media_references ) ) {
            return $processed_media;
        }
        
        foreach ( $media_references as $url => $ref_data ) {
            $result = $this->download_and_import_media( $url );
            
            if ( is_wp_error( $result ) ) {
                // Log error but continue with other media.
                $processed_media[ $url ] = array(
                    'error' => $result->get_error_message(),
                );
            } else {
                $processed_media[ $url ] = $result;
            }
        }
        
        return $processed_media;
    }

    /**
     * Download and import media file.
     *
     * @param string $url The media URL.
     * @return array|WP_Error Media data on success, WP_Error on failure.
     */
    private function download_and_import_media( $url ) {
        // Get file name from URL.
        $file_name = basename( parse_url( $url, PHP_URL_PATH ) );
        
        // Create temporary file.
        $temp_file = download_url( $url );
        
        if ( is_wp_error( $temp_file ) ) {
            return $temp_file;
        }
        
        // Prepare file data.
        $file_data = array(
            'name'     => $file_name,
            'tmp_name' => $temp_file,
        );
        
        // Use media_handle_sideload to add media to the library.
        $attachment_id = media_handle_sideload( $file_data, 0 );
        
        if ( is_wp_error( $attachment_id ) ) {
            @unlink( $temp_file );
            return $attachment_id;
        }
        
        // Get new media URL.
        $new_url = wp_get_attachment_url( $attachment_id );
        
        return array(
            'attachment_id' => $attachment_id,
            'new_url'       => $new_url,
        );
    }
}
