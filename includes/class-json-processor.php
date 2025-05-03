<?php
/**
 * JSON Processor class for SiteSync Cloner.
 *
 * @package SiteSync_Cloner
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * JSON Processor class.
 */
class SiteSync_Cloner_JSON_Processor {

    /**
     * Encode data to JSON.
     *
     * @param array $data The data to encode.
     * @return string|WP_Error JSON string on success, WP_Error on failure.
     */
    public function encode( $data ) {
        if ( ! is_array( $data ) ) {
            return new WP_Error( 'invalid_data', __( 'Data to encode must be an array.', 'sitesync-cloner' ) );
        }

        // Add export timestamp.
        $data['export_timestamp'] = current_time( 'timestamp' );
        $data['export_site'] = site_url();

        // Encode with pretty print for readability.
        $json = wp_json_encode( $data, JSON_PRETTY_PRINT );

        if ( false === $json ) {
            return new WP_Error( 'json_encode_failed', __( 'Failed to encode data to JSON.', 'sitesync-cloner' ) );
        }

        return $json;
    }

    /**
     * Decode JSON to data.
     *
     * @param string $json The JSON string to decode.
     * @return array|WP_Error Data array on success, WP_Error on failure.
     */
    public function decode( $json ) {
        if ( ! is_string( $json ) ) {
            return new WP_Error( 'invalid_json', __( 'JSON to decode must be a string.', 'sitesync-cloner' ) );
        }

        // Decode JSON.
        $data = json_decode( $json, true );

        if ( null === $data ) {
            return new WP_Error( 'json_decode_failed', __( 'Failed to decode JSON.', 'sitesync-cloner' ) );
        }

        // Validate required fields.
        $required_fields = array( 'post_title', 'post_content', 'post_type' );
        foreach ( $required_fields as $field ) {
            if ( ! isset( $data[ $field ] ) ) {
                return new WP_Error(
                    'missing_required_field',
                    sprintf(
                        /* translators: %s: Field name */
                        __( 'Missing required field: %s', 'sitesync-cloner' ),
                        $field
                    )
                );
            }
        }

        return $data;
    }

    /**
     * Sanitize data for export.
     *
     * @param array $data The data to sanitize.
     * @return array Sanitized data.
     */
    public function sanitize_export_data( $data ) {
        // This method can be expanded with more specific sanitization rules if needed.
        return $this->sanitize_array( $data );
    }

    /**
     * Sanitize data for import.
     *
     * @param array $data The data to sanitize.
     * @return array Sanitized data.
     */
    public function sanitize_import_data( $data ) {
        // This method can be expanded with more specific sanitization rules if needed.
        return $this->sanitize_array( $data );
    }

    /**
     * Recursively sanitize an array.
     *
     * @param array $array The array to sanitize.
     * @return array Sanitized array.
     */
    private function sanitize_array( $array ) {
        foreach ( $array as $key => &$value ) {
            if ( is_array( $value ) ) {
                $value = $this->sanitize_array( $value );
            } elseif ( is_string( $value ) ) {
                // Basic sanitization. Can be expanded with more specific rules based on context.
                $value = sanitize_text_field( $value );
            }
        }

        return $array;
    }
}
