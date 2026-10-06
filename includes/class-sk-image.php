<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SK_Image {

    /**
     * Main entry point: generate image and attach to post.
     */
    public static function generate_and_sideload( $prompt, $post_id, $alt = '' ) {
        $provider = get_option( 'sk_image_provider', 'gemini' );

        $image_data = null;

        switch ( $provider ) {
            case 'gemini':
                $image_data = self::gemini_image( $prompt );
                break;
            case 'pollinations':
                $image_data = self::pollinations_image( $prompt );
                break;
            case 'openai':
            default:
                $image_data = self::openai_image( $prompt );
                break;
        }

        // If provider failed, try Pollinations as free fallback
        if ( is_wp_error( $image_data ) || empty( $image_data ) ) {
            $error_msg = is_wp_error( $image_data ) ? $image_data->get_error_message() : 'Empty image data';
            SK_Logger::warn( "Image provider '{$provider}' failed: {$error_msg}", 'image' );

            // Fallback to Pollinations (free, always works)
            if ( $provider !== 'pollinations' ) {
                SK_Logger::info( 'Trying Pollinations as fallback...', 'image' );
                $image_data = self::pollinations_image( $prompt );
            }

            if ( is_wp_error( $image_data ) || empty( $image_data ) ) {
                return new WP_Error( 'no_image', 'All image providers failed.' );
            }
        }

        // Sideload to media library
        return self::sideload( $image_data, $post_id, $alt ?: $prompt );
    }

    /**
     * Gemini Image Generation (Nano Banana)
     * Endpoint: /v1beta/interactions
     * Models: gemini-3.1-flash-image, gemini-2.5-flash-image
     */
    private static function gemini_image( $prompt ) {
        $key = get_option( 'sk_gemini_key' );
        if ( ! $key ) {
            return new WP_Error( 'no_key', 'Gemini API key missing.' );
        }

        // Try models in order of preference
        $models = [
            'gemini-3.1-flash-image',
            'gemini-2.5-flash-image',
            'gemini-3-pro-image-preview',
        ];

        $last_error = null;

        foreach ( $models as $model ) {
            $payload = [
                'model' => $model,
                'input' => $prompt,
            ];

            $response = wp_remote_post(
                'https://generativelanguage.googleapis.com/v1beta/interactions',
                [
                    'timeout' => 120,
                    'headers' => [
                        'x-goog-api-key' => $key,
                        'Content-Type'   => 'application/json',
                    ],
                    'body' => wp_json_encode( $payload ),
                ]
            );

            if ( is_wp_error( $response ) ) {
                $last_error = $response;
                continue;
            }

            $code = wp_remote_retrieve_response_code( $response );
            $body = json_decode( wp_remote_retrieve_body( $response ), true );

            // 503/429 — try next model
            if ( in_array( $code, [ 429, 500, 502, 503, 504 ], true ) ) {
                $msg = $body['error']['message'] ?? 'HTTP ' . $code;
                SK_Logger::warn( "Gemini image model {$model} returned {$code}, trying next.", 'image' );
                $last_error = new WP_Error( 'api_error', $msg );
                continue;
            }

            if ( $code !== 200 ) {
                $msg = $body['error']['message'] ?? 'Gemini image error (HTTP ' . $code . ')';
                $last_error = new WP_Error( 'api_error', $msg );
                continue;
            }

            // Extract base64 image from response steps
            $image_base64 = self::extract_image_from_response( $body );

            if ( ! empty( $image_base64 ) ) {
                $decoded = base64_decode( $image_base64, true );
                if ( $decoded !== false && strlen( $decoded ) > 1000 ) {
                    SK_Logger::info( "Gemini image generated via {$model}", 'image' );
                    return $decoded;
                }
            }

            $last_error = new WP_Error( 'no_image', "Model {$model} returned no image data." );
        }

        return $last_error ?: new WP_Error( 'all_failed', 'All Gemini image models failed.' );
    }

    /**
     * Extract base64 image from Interactions API response.
     *
     * Response format:
     * {
     *   "steps": [
     *     {
     *       "type": "model_output",
     *       "content": [
     *         { "type": "image", "data": "BASE64...", "mime_type": "image/png" }
     *       ]
     *     }
     *   ]
     * }
     */
    private static function extract_image_from_response( $body ) {
        if ( empty( $body['steps'] ) || ! is_array( $body['steps'] ) ) {
            return '';
        }

        foreach ( $body['steps'] as $step ) {
            if ( ( $step['type'] ?? '' ) !== 'model_output' ) { continue; }
            if ( empty( $step['content'] ) || ! is_array( $step['content'] ) ) { continue; }

            foreach ( $step['content'] as $block ) {
                if ( ( $block['type'] ?? '' ) === 'image' && ! empty( $block['data'] ) ) {
                    return $block['data'];
                }
            }
        }

        return '';
    }

    /**
     * OpenAI DALL-E 3 image generation.
     */
    private static function openai_image( $prompt ) {
        $key = get_option( 'sk_openai_key' );
        if ( ! $key ) { return new WP_Error( 'no_key', 'OpenAI key missing for image.' ); }

        $response = wp_remote_post( 'https://api.openai.com/v1/images/generations', [
            'timeout' => 120,
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode( [
                'model'   => 'dall-e-3',
                'prompt'  => $prompt,
                'n'       => 1,
                'size'    => '1024x1024',
                'quality' => 'standard',
            ] ),
        ] );

        if ( is_wp_error( $response ) ) { return $response; }
        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( $code !== 200 ) {
            return new WP_Error( 'api_error', $body['error']['message'] ?? 'OpenAI image error (HTTP ' . $code . ')' );
        }
        $url = $body['data'][0]['url'] ?? '';
        if ( ! $url ) { return new WP_Error( 'no_url', 'No image URL returned.' ); }

        $img = wp_remote_get( $url, [ 'timeout' => 120 ] );
        if ( is_wp_error( $img ) ) { return $img; }
        return wp_remote_retrieve_body( $img );
    }

    /**
     * Pollinations AI — free, no API key needed.
     */
    private static function pollinations_image( $prompt ) {
        $url = 'https://image.pollinations.ai/prompt/'
             . rawurlencode( $prompt )
             . '?width=1024&height=1024&nologo=true&enhance=true';

        $img = wp_remote_get( $url, [ 'timeout' => 120 ] );

        if ( is_wp_error( $img ) ) { return $img; }

        $code = wp_remote_retrieve_response_code( $img );
        if ( $code !== 200 ) {
            return new WP_Error( 'api_error', 'Pollinations error: HTTP ' . $code );
        }

        $body = wp_remote_retrieve_body( $img );
        if ( strlen( $body ) < 1000 ) {
            return new WP_Error( 'no_image', 'Pollinations returned empty image.' );
        }

        return $body;
    }

    /**
     * Save binary image data to WordPress media library.
     */
    private static function sideload( $image_data, $post_id, $alt = '' ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Detect mime type
        $finfo     = finfo_open( FILEINFO_MIME_TYPE );
        $mime_type = finfo_buffer( $finfo, $image_data );
        finfo_close( $finfo );

        $ext_map = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
        ];
        $ext = $ext_map[ $mime_type ] ?? 'png';

        $tmp = wp_tempnam( 'sk-image' );
        if ( ! file_put_contents( $tmp, $image_data ) ) {
            @unlink( $tmp );
            return new WP_Error( 'file_error', 'Could not write temp file.' );
        }

        $file_array = [
            'name'     => sanitize_file_name( 'sk-image-' . $post_id . '-' . wp_generate_password( 6, false ) . '.' . $ext ),
            'tmp_name' => $tmp,
        ];

        $attachment_id = media_handle_sideload( $file_array, $post_id, $alt ?: '' );

        if ( is_wp_error( $attachment_id ) ) {
            @unlink( $tmp );
            SK_Logger::error( 'Sideload failed: ' . $attachment_id->get_error_message(), 'image' );
            return $attachment_id;
        }

        // Set alt text
        if ( $alt ) {
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
        }

        SK_Logger::info( "Image attached to post #{$post_id} (attachment #{$attachment_id})", 'image' );
        return $attachment_id;
    }
}