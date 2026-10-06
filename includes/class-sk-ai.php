<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SK_AI {

    public static function generate_text( $prompt, $system = '', $max_tokens = 2000 ) {
        $provider = get_option( 'sk_ai_provider', 'openai' );
        switch ( $provider ) {
            case 'gemini': return self::gemini( $prompt, $system, $max_tokens );
            case 'claude': return self::claude( $prompt, $system, $max_tokens );
            case 'openai':
            default:       return self::openai( $prompt, $system, $max_tokens );
        }
    }

    /* ============================================================
     * OPENAI
     * ============================================================ */

    private static function openai( $prompt, $system, $max_tokens ) {
        $key = get_option( 'sk_openai_key' );
        if ( ! $key ) { return new WP_Error( 'no_key', 'OpenAI API key missing.' ); }
        $model = get_option( 'sk_openai_model', 'gpt-4o-mini' );

        $messages = [];
        if ( $system ) { $messages[] = [ 'role' => 'system', 'content' => $system ]; }
        $messages[] = [ 'role' => 'user', 'content' => $prompt ];

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'timeout' => 120,
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode( [
                'model'       => $model,
                'messages'    => $messages,
                'max_tokens'  => $max_tokens,
                'temperature' => 0.7,
            ] ),
        ] );

        if ( is_wp_error( $response ) ) { return $response; }
        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( $code !== 200 ) {
            return new WP_Error( 'api_error', $body['error']['message'] ?? 'OpenAI error (HTTP ' . $code . ')' );
        }
        return $body['choices'][0]['message']['content'] ?? '';
    }

    /* ============================================================
     * GEMINI — Interactions API with multi-model fallback
     * ============================================================ */

    private static function gemini( $prompt, $system, $max_tokens ) {
        $key = get_option( 'sk_gemini_key' );
        if ( ! $key ) { return new WP_Error( 'no_key', 'Gemini API key missing.' ); }

        // Primary model from settings, then fallback chain
        $primary = get_option( 'sk_gemini_model', 'gemini-3.5-flash' );

        $models = array_values( array_unique( [
            $primary,
            'gemini-3.5-flash',
            'gemini-3.6-flash',
            'gemini-3.7-flash',
            'gemini-flash-latest',
            'gemini-3.5-flash-lite',
        ] ) );

        $input_text = $system ? $system . "\n\n" . $prompt : $prompt;
        $last_error = null;

        foreach ( $models as $model ) {
            $response = wp_remote_post( 'https://generativelanguage.googleapis.com/v1beta/interactions', [
                'timeout' => 120,
                'headers' => [
                    'x-goog-api-key' => $key,
                    'Content-Type'   => 'application/json',
                ],
                'body' => wp_json_encode( [
                    'model' => $model,
                    'input' => $input_text,
                    'generation_config' => [
                        'max_output_tokens' => $max_tokens,
                        'temperature'       => 0.7,
                    ],
                ] ),
            ] );

            // Network error — try next model
            if ( is_wp_error( $response ) ) {
                SK_Logger::warn( "Gemini model {$model} network error, trying next.", 'ai' );
                $last_error = $response;
                continue;
            }

            $code = wp_remote_retrieve_response_code( $response );
            $body = json_decode( wp_remote_retrieve_body( $response ), true );

            // 503 / 429 / 500 — transient, try next model
            if ( in_array( $code, [ 429, 500, 502, 503, 504 ], true ) ) {
                $msg = $body['error']['message'] ?? 'HTTP ' . $code;
                SK_Logger::warn( "Gemini model {$model} returned {$code}, trying next. ({$msg})", 'ai' );
                $last_error = new WP_Error( 'api_error', $msg );
                continue;
            }

            // Other HTTP errors — return immediately (auth, bad request, etc.)
            if ( $code !== 200 ) {
                $msg = $body['error']['message'] ?? 'Gemini API error (HTTP ' . $code . ')';
                return new WP_Error( 'api_error', $msg );
            }

            // Extract text from steps[]
            $text = self::extract_text_from_steps( $body );

            if ( ! empty( $text ) ) {
                SK_Logger::info( "Gemini success using model: {$model}", 'ai' );
                return $text;
            }

            // 200 but no text — try next model
            SK_Logger::warn( "Gemini model {$model} returned empty text, trying next.", 'ai' );
            $last_error = new WP_Error( 'no_text', "Model {$model} returned no text." );
        }

        // All models exhausted
        SK_Logger::error( 'All Gemini models failed.', 'ai' );
        return $last_error ?: new WP_Error( 'all_failed', 'All Gemini models failed.' );
    }

    /**
     * Extract text from Interactions API response structure.
     *
     * Response format:
     * {
     *   "steps": [
     *     { "type": "thought", ... },
     *     { "type": "model_output", "content": [ { "type": "text", "text": "..." } ] }
     *   ]
     * }
     */
    private static function extract_text_from_steps( $body ) {
        // Primary: steps[].content[].text
        if ( ! empty( $body['steps'] ) && is_array( $body['steps'] ) ) {
            foreach ( $body['steps'] as $step ) {
                if ( ( $step['type'] ?? '' ) !== 'model_output' ) { continue; }
                if ( empty( $step['content'] ) || ! is_array( $step['content'] ) ) { continue; }

                foreach ( $step['content'] as $block ) {
                    if ( ( $block['type'] ?? '' ) === 'text' && isset( $block['text'] ) ) {
                        return $block['text'];
                    }
                }
            }
        }

        // Fallback: output_text convenience field
        if ( isset( $body['output_text'] ) && is_string( $body['output_text'] ) ) {
            return $body['output_text'];
        }

        // Fallback: old generateContent format (in case API changes back)
        if ( isset( $body['candidates'][0]['content']['parts'][0]['text'] ) ) {
            return $body['candidates'][0]['content']['parts'][0]['text'];
        }

        return '';
    }

    /* ============================================================
     * CLAUDE
     * ============================================================ */

    private static function claude( $prompt, $system, $max_tokens ) {
        $key = get_option( 'sk_claude_key' );
        if ( ! $key ) { return new WP_Error( 'no_key', 'Claude API key missing.' ); }
        $model = get_option( 'sk_claude_model', 'claude-3-5-sonnet-20241022' );

        $payload = [
            'model'      => $model,
            'max_tokens' => $max_tokens,
            'messages'   => [ [ 'role' => 'user', 'content' => $prompt ] ],
        ];
        if ( $system ) { $payload['system'] = $system; }

        $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
            'timeout' => 120,
            'headers' => [
                'x-api-key'         => $key,
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ],
            'body' => wp_json_encode( $payload ),
        ] );

        if ( is_wp_error( $response ) ) { return $response; }
        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( $code !== 200 ) {
            return new WP_Error( 'api_error', $body['error']['message'] ?? 'Claude error (HTTP ' . $code . ')' );
        }
        return $body['content'][0]['text'] ?? '';
    }
}