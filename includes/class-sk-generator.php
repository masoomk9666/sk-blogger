<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SK_Generator {

    public static function generate_post( $topic, $keywords = '', $override = [] ) {
        $length_map = [ 'short' => 600, 'medium' => 1200, 'long' => 2000 ];
        $length     = $length_map[ get_option( 'sk_post_length', 'medium' ) ] ?? 1200;
        $tone       = get_option( 'sk_tone', 'professional' );
        $language   = get_option( 'sk_language', 'en' );

        // STRICT system prompt — forces JSON-only output
        $system = "You are a JSON API. You MUST respond with ONLY a single valid JSON object. "
                . "Your response MUST start with the character { and end with the character }. "
                . "You MUST NOT include any text, explanation, or commentary before or after the JSON. "
                . "You MUST NOT use markdown code fences (no ```json, no ```). "
                . "You MUST NOT wrap the JSON in quotes.\n\n"
                . "Tone: {$tone}. Language: {$language}.\n\n"
                . "The JSON object MUST contain EXACTLY these keys:\n"
                . "- title: string (blog post title)\n"
                . "- content: string (HTML content using <h2>, <h3>, <p>, <ul>, <li> tags)\n"
                . "- excerpt: string (short summary, max 200 chars)\n"
                . "- meta_description: string (SEO meta description, max 155 chars)\n"
                . "- tags: array of strings (3-6 relevant tags)\n\n"
                . "Example structure:\n"
                . "{\"title\":\"...\",\"content\":\"<h2>...</h2><p>...</p>\",\"excerpt\":\"...\",\"meta_description\":\"...\",\"tags\":[\"tag1\",\"tag2\"]}";

        $prompt = "Write a comprehensive, original, SEO-optimized blog post of approximately {$length} words.\n\n"
                . "Topic: {$topic}\n"
                . ( $keywords ? "Target keywords: {$keywords}\n" : '' )
                . "\nRequirements:\n"
                . "- Include an engaging introduction\n"
                . "- Use multiple <h2> sections with <h3> subsections\n"
                . "- Include bullet lists where appropriate\n"
                . "- End with a strong conclusion\n"
                . "- Content must be valid HTML\n\n"
                . "CRITICAL: Return ONLY the raw JSON object. Start with { and end with }. "
                . "No markdown, no code fences, no explanations.";

        // 3x tokens to avoid truncation
        $max_tokens = (int) ( $length * 3 );

        $raw = SK_AI::generate_text( $prompt, $system, $max_tokens );

        if ( is_wp_error( $raw ) ) {
            SK_Logger::error( 'AI generation failed: ' . $raw->get_error_message(), 'generator', [ 'topic' => $topic ] );
            return $raw;
        }

        $data = self::parse_json( $raw );

        // Fallback: if JSON fails, try extracting title/content manually
        if ( ! $data || empty( $data['content'] ) ) {
            SK_Logger::warn( 'JSON parse failed, using fallback extraction.', 'generator', [
                'raw_preview' => substr( $raw, 0, 500 ),
                'raw_length'  => strlen( $raw ),
            ] );

            $fallback = self::fallback_extract( $raw, $topic );
            if ( $fallback ) {
                $data = $fallback;
                SK_Logger::info( 'Fallback extraction succeeded.', 'generator' );
            } else {
                SK_Logger::error( 'Both JSON parse and fallback extraction failed.', 'generator' );
                return new WP_Error( 'bad_json', 'AI returned invalid JSON and fallback extraction failed.' );
            }
        }

        $status    = $override['status']   ?? get_option( 'sk_default_status', 'draft' );
        $author    = $override['author']   ?? get_option( 'sk_default_author', 1 );
        $category  = $override['category'] ?? get_option( 'sk_default_category', 1 );

        $post_id = wp_insert_post( [
            'post_title'    => wp_strip_all_tags( $data['title'] ?? $topic ),
            'post_content'  => wp_kses_post( $data['content'] ),
            'post_excerpt'  => sanitize_text_field( $data['excerpt'] ?? '' ),
            'post_status'   => $status,
            'post_author'   => (int) $author,
            'post_category' => [ (int) $category ],
            'post_type'     => 'post',
        ], true );

        if ( is_wp_error( $post_id ) ) {
            SK_Logger::error( 'Post insert failed: ' . $post_id->get_error_message(), 'generator' );
            return $post_id;
        }

        if ( ! empty( $data['tags'] ) && is_array( $data['tags'] ) ) {
            wp_set_post_tags( $post_id, array_map( 'sanitize_text_field', $data['tags'] ) );
        }

        // Featured image
        if ( get_option( 'sk_include_images' ) ) {
    $image_provider = get_option( 'sk_image_provider', 'gemini' );
    $has_provider   = ( $image_provider === 'pollinations' )
                   || ( $image_provider === 'openai' && get_option( 'sk_openai_key' ) )
                   || ( $image_provider === 'gemini' && get_option( 'sk_gemini_key' ) );

    if ( $has_provider ) {
        // Better prompt for featured image
        $img_prompt = "Professional blog featured image about: {$topic}. "
                    . "Modern, clean, photorealistic style. "
                    . "Wide 16:9 composition. "
                    . "No text, no watermarks, no logos, no people faces. "
                    . "High quality, suitable as a blog header image.";

        $att = SK_Image::generate_and_sideload( $img_prompt, $post_id, $topic );

        if ( ! is_wp_error( $att ) ) {
            set_post_thumbnail( $post_id, $att );
            SK_Logger::info( "Featured image set for post #{$post_id}", 'generator' );
        } else {
            SK_Logger::warn( 'Image failed: ' . $att->get_error_message(), 'generator' );
        }
    }
}

        SK_SEO::apply(
            $post_id,
            $data['meta_title'] ?? $data['title'] ?? $topic,
            $data['meta_description'] ?? $data['excerpt'] ?? '',
            $keywords
        );

        SK_Logger::info( "Post #{$post_id} generated for topic: {$topic}", 'generator' );
        return $post_id;
    }

    /**
     * Aggressive JSON parser with multiple fallback strategies.
     */
    private static function parse_json( $raw ) {
        // DEBUG: log raw response (first 3000 chars)
        SK_Logger::info( 'RAW RESPONSE: ' . substr( $raw, 0, 3000 ), 'debug' );

        // Step 1: Strip BOM and zero-width characters
        $raw = preg_replace( '/[\x{FEFF}\x{200B}-\x{200D}\x{FFFD}]/u', '', $raw );
        $raw = trim( $raw );

        // Step 2: Remove markdown code fences (various formats)
        $raw = preg_replace( '/^```(?:json|JSON)?\s*/im', '', $raw );
        $raw = preg_replace( '/```\s*$/im', '', $raw );
        $raw = trim( $raw );

        // Step 3: Direct decode
        $data = json_decode( $raw, true );
        if ( json_last_error() === JSON_ERROR_NONE && is_array( $data ) ) {
            return $data;
        }

        // Step 4: Find first { and last } — greedy match
        $first = strpos( $raw, '{' );
        $last  = strrpos( $raw, '}' );
        if ( $first !== false && $last !== false && $last > $first ) {
            $candidate = substr( $raw, $first, $last - $first + 1 );
            $data = json_decode( $candidate, true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $data ) ) {
                return $data;
            }
        }

        // Step 5: Balanced brace extraction (handles nested objects + strings with braces)
        if ( $first !== false ) {
            $depth     = 0;
            $in_string = false;
            $escape    = false;
            $end       = $first;
            $len       = strlen( $raw );

            for ( $i = $first; $i < $len; $i++ ) {
                $c = $raw[$i];

                if ( $escape ) { $escape = false; continue; }
                if ( $c === '\\' ) { $escape = true; continue; }
                if ( $c === '"' ) { $in_string = ! $in_string; continue; }
                if ( $in_string ) { continue; }

                if ( $c === '{' ) {
                    $depth++;
                } elseif ( $c === '}' ) {
                    $depth--;
                    if ( $depth === 0 ) {
                        $end = $i;
                        break;
                    }
                }
            }

            $candidate = substr( $raw, $first, $end - $first + 1 );
            $data = json_decode( $candidate, true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $data ) ) {
                return $data;
            }

            // Step 6: Repair common issues — trailing commas
            $repaired = preg_replace( '/,\s*([}\]])/', '$1', $candidate );

            // Remove control characters inside JSON (except \n \r \t escaped)
            $repaired = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $repaired );

            $data = json_decode( $repaired, true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $data ) ) {
                return $data;
            }

            // All failed — log details
            SK_Logger::error( 'JSON parse failed: ' . json_last_error_msg(), 'generator', [
                'preview'       => substr( $candidate, 0, 800 ),
                'raw_length'    => strlen( $raw ),
                'candidate_len' => strlen( $candidate ),
            ] );
        } else {
            SK_Logger::error( 'JSON parse failed: no { found in response.', 'generator', [
                'preview' => substr( $raw, 0, 800 ),
            ] );
        }

        return null;
    }

    /**
     * Fallback extraction: If JSON parse fails, try to manually extract
     * title and content from raw text. Never let a post completely fail.
     */
    private static function fallback_extract( $raw, $topic ) {
        $raw = trim( $raw );
        if ( empty( $raw ) ) { return null; }

        $title   = $topic;
        $content = '';

        // Try to find title field
        if ( preg_match( '/["\']title["\']\s*:\s*["\'](.+?)["\']/is', $raw, $m ) ) {
            $title = trim( $m[1] );
        } elseif ( preg_match( '/^#\s*(.+)$/m', $raw, $m ) ) {
            $title = trim( $m[1] );
        }

        // Try to find content field
        if ( preg_match( '/["\']content["\']\s*:\s*["\'](.+)["\']/is', $raw, $m ) ) {
            $content = $m[1];
        }

        // If we found content, unescape it
        if ( ! empty( $content ) ) {
            // Unescape common JSON escapes
            $content = str_replace( [ '\\n', '\\r', '\\t', '\\"', "\\'", '\\/' ], [ "\n", "\r", "\t", '"', "'", '/' ], $content );
            $content = wp_kses_post( $content );
        } else {
            // No JSON structure — treat entire raw as content
            // Remove any JSON-looking artifacts
            $cleaned = preg_replace( '/^\s*\{.*?["\']content["\']\s*:\s*["\']/is', '', $raw );
            $cleaned = preg_replace( '/["\']\s*,\s*["\']\w+["\']\s*:.*$/s', '', $cleaned );
            $cleaned = trim( $cleaned, " \t\n\r\0\x0B\"'" );

            if ( ! empty( $cleaned ) && strlen( $cleaned ) > 100 ) {
                // Check if it's HTML
                if ( strip_tags( $cleaned ) !== $cleaned ) {
                    $content = wp_kses_post( $cleaned );
                } else {
                    // Convert plain text to HTML paragraphs
                    $paragraphs = preg_split( '/\n\s*\n/', $cleaned );
                    $content = '';
                    foreach ( $paragraphs as $p ) {
                        $p = trim( $p );
                        if ( ! empty( $p ) ) {
                            $content .= '<p>' . esc_html( $p ) . '</p>' . "\n";
                        }
                    }
                }
            }
        }

        if ( empty( $content ) ) { return null; }

        return [
            'title'            => $title,
            'content'          => $content,
            'excerpt'          => wp_trim_words( wp_strip_all_tags( $content ), 30 ),
            'meta_description' => wp_trim_words( wp_strip_all_tags( $content ), 25 ),
            'tags'             => [],
        ];
    }
}