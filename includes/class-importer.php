<?php
/**
 * Importer class for WP Content Porter.
 *
 * @package WP_Content_Porter
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Importer class.
 */
class WP_Content_Porter_Importer {

    /**
     * Media handler instance.
     *
     * @var WP_Content_Porter_Media_Handler
     */
    private $media_handler;

    /**
     * Initialize the importer class.
     */
    public function __construct() {
        $this->media_handler = new WP_Content_Porter_Media_Handler();
    }

    /**
     * Validate import data.
     *
     * @param array $import_data The import data.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function validate_import_data( $import_data ) {
        // Check required fields.
        $required_fields = array( 'post_title', 'post_content', 'post_type' );
        
        foreach ( $required_fields as $field ) {
            if ( ! isset( $import_data[ $field ] ) ) {
                return new WP_Error(
                    'missing_required_field',
                    sprintf(
                        /* translators: %s: Field name */
                        __( 'Missing required field: %s', 'wp-content-porter' ),
                        $field
                    )
                );
            }
        }

        // Check post type.
        $allowed_post_types = array( 'post', 'page' );
        if ( ! in_array( $import_data['post_type'], $allowed_post_types, true ) ) {
            return new WP_Error(
                'invalid_post_type',
                sprintf(
                    /* translators: %s: Post type */
                    __( 'Invalid post type: %s', 'wp-content-porter' ),
                    $import_data['post_type']
                )
            );
        }

        return true;
    }

    /**
     * Import content.
     *
     * @param array $import_data The import data.
     * @return int|WP_Error Post ID on success, WP_Error on failure.
     */
    public function import_content( $import_data ) {
        // Validate import data.
        $validation = $this->validate_import_data( $import_data );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        // Check if user has permission to create this post type.
        if ( 'post' === $import_data['post_type'] && ! current_user_can( 'publish_posts' ) ) {
            return new WP_Error(
                'permission_denied',
                __( 'You do not have permission to create posts.', 'wp-content-porter' )
            );
        } elseif ( 'page' === $import_data['post_type'] && ! current_user_can( 'publish_pages' ) ) {
            return new WP_Error(
                'permission_denied',
                __( 'You do not have permission to create pages.', 'wp-content-porter' )
            );
        }

        // Start transaction.
        global $wpdb;
        $wpdb->query( 'START TRANSACTION' );

        try {
            // Process media files first.
            $processed_media = $this->process_media_files( $import_data );
            if ( is_wp_error( $processed_media ) ) {
                throw new Exception( $processed_media->get_error_message() );
            }

            // Update content with new media URLs.
            $import_data = $this->update_media_urls( $import_data, $processed_media );

            // Create post.
            $post_id = $this->create_post( $import_data );
            if ( is_wp_error( $post_id ) ) {
                throw new Exception( $post_id->get_error_message() );
            }

            // Import taxonomies.
            $this->import_taxonomies( $post_id, $import_data );

            // Import post meta.
            $this->import_post_meta( $post_id, $import_data );

            // Import ACF fields if available.
            $this->import_acf_fields( $post_id, $import_data );

            // Set featured image if available.
            $this->set_featured_image( $post_id, $import_data, $processed_media );

            // Commit transaction.
            $wpdb->query( 'COMMIT' );

            return $post_id;
        } catch ( Exception $e ) {
            // Rollback transaction.
            $wpdb->query( 'ROLLBACK' );

            return new WP_Error( 'import_failed', $e->getMessage() );
        }
    }

    /**
     * Process media files.
     *
     * @param array $import_data The import data.
     * @return array|WP_Error Processed media data or error.
     */
    private function process_media_files( $import_data ) {
        return $this->media_handler->process_media_files( $import_data );
    }

    /**
     * Update media URLs in content.
     *
     * @param array $import_data The import data.
     * @param array $processed_media The processed media data.
     * @return array Updated import data.
     */
    private function update_media_urls( $import_data, $processed_media ) {
        // Update media URLs in post content.
        if ( ! empty( $processed_media ) && ! empty( $import_data['post_content'] ) ) {
            foreach ( $processed_media as $original_url => $new_data ) {
                if ( isset( $new_data['new_url'] ) ) {
                    $import_data['post_content'] = str_replace( $original_url, $new_data['new_url'], $import_data['post_content'] );
                }
            }
        }

        // Update media URLs in content blocks if available.
        if ( ! empty( $processed_media ) && ! empty( $import_data['content_blocks'] ) ) {
            $updated_blocks = $this->update_blocks_media_urls( $import_data['content_blocks'], $processed_media );
            $import_data['content_blocks'] = $updated_blocks;
            
            // Regenerate post content from updated blocks.
            if ( function_exists( 'serialize_blocks' ) ) {
                $import_data['post_content'] = serialize_blocks( $updated_blocks );
            }
        }

        // Update media URLs in meta data.
        if ( ! empty( $processed_media ) && ! empty( $import_data['meta'] ) ) {
            foreach ( $import_data['meta'] as $meta_key => $meta_value ) {
                if ( is_string( $meta_value ) ) {
                    foreach ( $processed_media as $original_url => $new_data ) {
                        if ( isset( $new_data['new_url'] ) ) {
                            $import_data['meta'][ $meta_key ] = str_replace( $original_url, $new_data['new_url'], $meta_value );
                        }
                    }
                }
            }
        }

        return $import_data;
    }

    /**
     * Update media URLs in blocks.
     *
     * @param array $blocks The blocks array.
     * @param array $processed_media The processed media data.
     * @return array Updated blocks.
     */
    private function update_blocks_media_urls( $blocks, $processed_media ) {
        foreach ( $blocks as &$block ) {
            // Update URL in block attributes.
            if ( ! empty( $block['attrs'] ) ) {
                $block['attrs'] = $this->update_block_attrs_media_urls( $block['attrs'], $processed_media );
            }

            // Update URL in block innerContent.
            if ( ! empty( $block['innerContent'] ) ) {
                foreach ( $block['innerContent'] as &$content ) {
                    if ( is_string( $content ) ) {
                        foreach ( $processed_media as $original_url => $new_data ) {
                            if ( isset( $new_data['new_url'] ) ) {
                                $content = str_replace( $original_url, $new_data['new_url'], $content );
                            }
                        }
                    }
                }
            }

            // Process inner blocks recursively.
            if ( ! empty( $block['innerBlocks'] ) ) {
                $block['innerBlocks'] = $this->update_blocks_media_urls( $block['innerBlocks'], $processed_media );
            }
        }

        return $blocks;
    }

    /**
     * Update media URLs in block attributes.
     *
     * @param array $attrs The block attributes.
     * @param array $processed_media The processed media data.
     * @return array Updated attributes.
     */
    private function update_block_attrs_media_urls( $attrs, $processed_media ) {
        foreach ( $attrs as $key => &$value ) {
            if ( is_string( $value ) ) {
                foreach ( $processed_media as $original_url => $new_data ) {
                    if ( isset( $new_data['new_url'] ) ) {
                        $value = str_replace( $original_url, $new_data['new_url'], $value );
                    }
                }
            } elseif ( is_array( $value ) ) {
                $value = $this->update_block_attrs_media_urls( $value, $processed_media );
            }
        }

        return $attrs;
    }

    /**
     * Create post.
     *
     * @param array $import_data The import data.
     * @return int|WP_Error Post ID on success, WP_Error on failure.
     */
    private function create_post( $import_data ) {
        $post_data = array(
            'post_title'    => $import_data['post_title'],
            'post_content'  => $import_data['post_content'],
            'post_type'     => $import_data['post_type'],
            'post_status'   => 'draft', // Always set as draft regardless of original status
            'post_excerpt'  => isset( $import_data['post_excerpt'] ) ? $import_data['post_excerpt'] : '',
            'comment_status' => isset( $import_data['comment_status'] ) ? $import_data['comment_status'] : 'open',
            'ping_status'   => isset( $import_data['ping_status'] ) ? $import_data['ping_status'] : 'open',
        );

        $post_id = wp_insert_post( $post_data, true );

        return $post_id;
    }

    /**
     * Import taxonomies.
     *
     * @param int   $post_id The post ID.
     * @param array $import_data The import data.
     * @return void
     */
    private function import_taxonomies( $post_id, $import_data ) {
        if ( empty( $import_data['taxonomies'] ) ) {
            return;
        }

        foreach ( $import_data['taxonomies'] as $taxonomy => $terms ) {
            if ( ! taxonomy_exists( $taxonomy ) ) {
                continue;
            }

            $term_ids = array();

            foreach ( $terms as $term_data ) {
                // Check if term exists.
                $existing_term = get_term_by( 'slug', $term_data['slug'], $taxonomy );

                if ( $existing_term ) {
                    $term_ids[] = $existing_term->term_id;
                } else {
                    // Create term if it doesn't exist.
                    $new_term = wp_insert_term(
                        $term_data['name'],
                        $taxonomy,
                        array(
                            'slug' => $term_data['slug'],
                        )
                    );

                    if ( ! is_wp_error( $new_term ) ) {
                        $term_ids[] = $new_term['term_id'];
                    }
                }
            }

            // Set terms for the post.
            if ( ! empty( $term_ids ) ) {
                wp_set_object_terms( $post_id, $term_ids, $taxonomy );
            }
        }
    }

    /**
     * Import post meta.
     *
     * @param int   $post_id The post ID.
     * @param array $import_data The import data.
     * @return void
     */
    private function import_post_meta( $post_id, $import_data ) {
        if ( empty( $import_data['meta'] ) ) {
            return;
        }

        foreach ( $import_data['meta'] as $meta_key => $meta_value ) {
            // Skip ACF fields, they will be handled separately.
            if ( 0 === strpos( $meta_key, '_acf_' ) ) {
                continue;
            }

            // Skip featured image, it will be handled separately.
            if ( '_thumbnail_id' === $meta_key ) {
                continue;
            }

            update_post_meta( $post_id, $meta_key, $meta_value );
        }
    }

    /**
     * Import ACF fields.
     *
     * @param int   $post_id The post ID.
     * @param array $import_data The import data.
     * @return void
     */
    private function import_acf_fields( $post_id, $import_data ) {
        if ( empty( $import_data['acf_fields'] ) || ! function_exists( 'update_field' ) ) {
            return;
        }

        foreach ( $import_data['acf_fields'] as $field_key => $field_value ) {
            update_field( $field_key, $field_value, $post_id );
        }
    }

    /**
     * Set featured image.
     *
     * @param int   $post_id The post ID.
     * @param array $import_data The import data.
     * @param array $processed_media The processed media data.
     * @return void
     */
    private function set_featured_image( $post_id, $import_data, $processed_media ) {
        if ( empty( $import_data['featured_image'] ) ) {
            return;
        }

        $featured_image = $import_data['featured_image'];
        $original_url = $featured_image['url'];

        if ( isset( $processed_media[ $original_url ] ) && isset( $processed_media[ $original_url ]['attachment_id'] ) ) {
            // Set the imported image as the featured image.
            set_post_thumbnail( $post_id, $processed_media[ $original_url ]['attachment_id'] );
        }
    }
}
