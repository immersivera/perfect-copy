<?php
/**
 * Exporter class for SiteSync Cloner.
 *
 * @package SiteSync_Cloner
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Exporter class.
 */
class SiteSync_Cloner_Exporter {

    /**
     * Export multiple posts or pages.
     *
     * @param array $post_ids Array of post IDs to export.
     * @return array Array containing export data for each post and batch metadata.
     */
    public function export_batch( $post_ids ) {
        if ( ! is_array( $post_ids ) || empty( $post_ids ) ) {
            return new WP_Error( 'invalid_post_ids', __( 'No valid post IDs provided for export.', 'sitesync-cloner' ) );
        }
        
        $batch_data = array(
            'batch_id' => uniqid( 'batch_' ),
            'export_date' => current_time( 'mysql' ),
            'export_version' => SITESYNC_CLONER_VERSION,
            'count' => count( $post_ids ),
            'items' => array(),
        );
        
        foreach ( $post_ids as $post_id ) {
            $export_result = $this->export_post( $post_id );
            
            if ( ! is_wp_error( $export_result ) ) {
                // Add to batch items
                $batch_data['items'][] = $export_result;
            } else {
                // Log the error but continue with other posts
                $batch_data['errors'][] = array(
                    'post_id' => $post_id,
                    'message' => $export_result->get_error_message(),
                );
            }
        }
        
        return $batch_data;
    }
    
    /**
     * Export a post or page.
     *
     * @param int $post_id The post ID to export.
     * @return array|WP_Error Export data array or error.
     */
    public function export_post( $post_id ) {
        // Get post.
        $post = get_post( $post_id );

        if ( ! $post ) {
            return new WP_Error( 'post_not_found', __( 'Post not found.', 'sitesync-cloner' ) );
        }

        // Check user capability.
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return new WP_Error( 'permission_denied', __( 'You do not have permission to export this post.', 'sitesync-cloner' ) );
        }

        // Prepare export data.
        $export_data = array(
            'post_title'    => $post->post_title,
            'post_content'  => $post->post_content,
            'post_type'     => $post->post_type,
            'post_status'   => $post->post_status,
            'post_excerpt'  => $post->post_excerpt,
            'post_date'     => $post->post_date,
            'comment_status' => $post->comment_status,
            'ping_status'   => $post->ping_status,
            'meta'          => $this->get_post_meta( $post_id ),
            'taxonomies'    => $this->get_post_taxonomies( $post_id ),
            'featured_image' => $this->get_featured_image( $post_id ),
            'export_version' => SITESYNC_CLONER_VERSION,
            'content_blocks' => $this->get_content_blocks( $post ),
            'acf_fields'    => $this->get_acf_fields( $post_id ),
        );

        // Add media references.
        $export_data['media_references'] = $this->extract_media_references( $export_data );

        return $export_data;
    }

    /**
     * Get post meta for export.
     *
     * @param int $post_id The post ID.
     * @return array Post meta data.
     */
    private function get_post_meta( $post_id ) {
        $meta = get_post_meta( $post_id );
        $exclude_meta = array(
            '_edit_lock',
            '_edit_last',
            '_wp_trash_meta_status',
            '_wp_trash_meta_time',
            '_wp_desired_post_slug',
            '_wp_old_slug',
            '_wpas_done_all',
            '_encloseme',
            '_wp_page_template',
        );

        foreach ( $exclude_meta as $key ) {
            if ( isset( $meta[ $key ] ) ) {
                unset( $meta[ $key ] );
            }
        }

        // Handle serialized data.
        foreach ( $meta as $key => $value ) {
            if ( is_array( $value ) && count( $value ) === 1 ) {
                $meta[ $key ] = maybe_unserialize( $value[0] );
            }
        }

        return $meta;
    }

    /**
     * Get post taxonomies for export.
     *
     * @param int $post_id The post ID.
     * @return array Post taxonomies data.
     */
    private function get_post_taxonomies( $post_id ) {
        $taxonomies = get_object_taxonomies( get_post_type( $post_id ) );
        $tax_data = array();

        foreach ( $taxonomies as $taxonomy ) {
            $terms = get_the_terms( $post_id, $taxonomy );

            if ( ! is_wp_error( $terms ) && $terms ) {
                $tax_data[ $taxonomy ] = array();

                foreach ( $terms as $term ) {
                    $tax_data[ $taxonomy ][] = array(
                        'name' => $term->name,
                        'slug' => $term->slug,
                    );
                }
            }
        }

        return $tax_data;
    }

    /**
     * Get featured image for export.
     *
     * @param int $post_id The post ID.
     * @return array|null Featured image data or null if none.
     */
    private function get_featured_image( $post_id ) {
        $thumbnail_id = get_post_thumbnail_id( $post_id );

        if ( ! $thumbnail_id ) {
            return null;
        }

        $attachment = get_post( $thumbnail_id );

        if ( ! $attachment ) {
            return null;
        }

        $image_url = wp_get_attachment_url( $thumbnail_id );
        $image_meta = wp_get_attachment_metadata( $thumbnail_id );

        return array(
            'id'    => $thumbnail_id,
            'title' => $attachment->post_title,
            'alt'   => get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ),
            'url'   => $image_url,
            'meta'  => $image_meta,
        );
    }

    /**
     * Get content blocks from a post.
     *
     * @param WP_Post $post The post object.
     * @return array|null Content blocks or null if not using blocks.
     */
    private function get_content_blocks( $post ) {
        if ( ! function_exists( 'has_blocks' ) || ! has_blocks( $post->post_content ) ) {
            return null;
        }

        $blocks = parse_blocks( $post->post_content );

        return $blocks;
    }

    /**
     * Get ACF fields for export.
     *
     * @param int $post_id The post ID.
     * @return array|null ACF fields or null if ACF not active.
     */
    private function get_acf_fields( $post_id ) {
        if ( ! function_exists( 'get_fields' ) ) {
            return null;
        }

        $fields = get_fields( $post_id );

        return $fields ? $fields : null;
    }

    /**
     * Extract media references from content and meta.
     *
     * @param array $export_data The export data.
     * @return array Media references.
     */
    private function extract_media_references( $export_data ) {
        $media_handler = new SiteSync_Cloner_Media_Handler();
        
        return $media_handler->extract_media_references( $export_data );
    }
}
