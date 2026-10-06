<?php
// if ( ! defined( 'ABSPATH' ) ) { exit; }

// class SK_Generator {

//     public static function generate_post( $topic, $keywords = '', $override = [] ) {
//         $length_map = [ 'short' => 600, 'medium' => 1200, 'long' => 2000 ];
//         $length     = $length_map[ get_option( 'sk_post_length', 'medium' ) ] ?? 1200;
//         $tone       = get_option( 'sk_tone', 'professional' );
//         $language   = get_option( 'sk_language', 'en' );

//         // STRICT system prompt — forces JSON-only output
//         $system = "You are a JSON API. You MUST respond with ONLY a single valid JSON object. "
//                 . "Your response MUST start with the character { and end with the character }. "
//                 . "You MUST NOT include any text, explanation, or commentary before or after the JSON. "
//                 . "You MUST NOT use markdown code fences (no ```json, no ```). "
//                 . "You MUST NOT wrap the JSON in quotes.\n\n"
//                 . "Tone: {$tone}. Language: {$language}.\n\n"
//                 . "The JSON object MUST contain EXACTLY these keys:\n"
//                 . "- title: string (blog post title)\n"
//                 . "- content: string (HTML content using <h2>, <h3>, <p>, <ul>, <li> tags)\n"
//                 . "- excerpt: string (short summary, max 200 chars)\n"
//                 . "- meta_description: string (SEO meta description, max 155 chars)\n"
//                 . "- tags: array of strings (3-6 relevant tags)\n\n"
//                 . "Example structure:\n"
//                 . "{\"title\":\"...\",\"content\":\"<h2>...</h2><p>...</p>\",\"excerpt\":\"...\",\"meta_description\":\"...\",\"tags\":[\"tag1\",\"tag2\"]}";

//         $prompt = "Write a comprehensive, original, SEO-optimized blog post of approximately {$length} words.\n\n"
//                 . "Topic: {$topic}\n"
//                 . ( $keywords ? "Target keywords: {$keywords}\n" : '' )
//                 . "\nRequirements:\n"
//                 . "- Include an engaging introduction\n"
//                 . "- Use multiple <h2> sections with <h3> subsections\n"
//                 . "- Include bullet lists where appropriate\n"
//                 . "- End with a strong conclusion\n"
//                 . "- Content must be valid HTML\n\n"
//                 . "CRITICAL: Return ONLY the raw JSON object. Start with { and end with }. "
//                 . "No markdown, no code fences, no explanations.";

//         // 3x tokens to avoid truncation
//         $max_tokens = (int) ( $length * 3 );

//         $raw = SK_AI::generate_text( $prompt, $system, $max_tokens );

//         if ( is_wp_error( $raw ) ) {
//             SK_Logger::error( 'AI generation failed: ' . $raw->get_error_message(), 'generator', [ 'topic' => $topic ] );
//             return $raw;
//         }

//         $data = self::parse_json( $raw );

//         // Fallback: if JSON fails, try extracting title/content manually
//         if ( ! $data || empty( $data['content'] ) ) {
//             SK_Logger::warn( 'JSON parse failed, using fallback extraction.', 'generator', [
//                 'raw_preview' => substr( $raw, 0, 500 ),
//                 'raw_length'  => strlen( $raw ),
//             ] );

//             $fallback = self::fallback_extract( $raw, $topic );
//             if ( $fallback ) {
//                 $data = $fallback;
//                 SK_Logger::info( 'Fallback extraction succeeded.', 'generator' );
//             } else {
//                 SK_Logger::error( 'Both JSON parse and fallback extraction failed.', 'generator' );
//                 return new WP_Error( 'bad_json', 'AI returned invalid JSON and fallback extraction failed.' );
//             }
//         }

//         $status    = $override['status']   ?? get_option( 'sk_default_status', 'draft' );
//         $author    = $override['author']   ?? get_option( 'sk_default_author', 1 );
//         $category  = $override['category'] ?? get_option( 'sk_default_category', 1 );

//         $post_id = wp_insert_post( [
//             'post_title'    => wp_strip_all_tags( $data['title'] ?? $topic ),
//             'post_content'  => wp_kses_post( $data['content'] ),
//             'post_excerpt'  => sanitize_text_field( $data['excerpt'] ?? '' ),
//             'post_status'   => $status,
//             'post_author'   => (int) $author,
//             'post_category' => [ (int) $category ],
//             'post_type'     => 'post',
//         ], true );

//         if ( is_wp_error( $post_id ) ) {
//             SK_Logger::error( 'Post insert failed: ' . $post_id->get_error_message(), 'generator' );
//             return $post_id;
//         }

//         if ( ! empty( $data['tags'] ) && is_array( $data['tags'] ) ) {
//             wp_set_post_tags( $post_id, array_map( 'sanitize_text_field', $data['tags'] ) );
//         }

//         // Featured image
//         if ( get_option( 'sk_include_images' ) ) {
//     $image_provider = get_option( 'sk_image_provider', 'gemini' );
//     $has_provider   = ( $image_provider === 'pollinations' )
//                    || ( $image_provider === 'openai' && get_option( 'sk_openai_key' ) )
//                    || ( $image_provider === 'gemini' && get_option( 'sk_gemini_key' ) );

//     if ( $has_provider ) {
//         // Better prompt for featured image
//         $img_prompt = "Professional blog featured image about: {$topic}. "
//                     . "Modern, clean, photorealistic style. "
//                     . "Wide 16:9 composition. "
//                     . "No text, no watermarks, no logos, no people faces. "
//                     . "High quality, suitable as a blog header image.";

//         $att = SK_Image::generate_and_sideload( $img_prompt, $post_id, $topic );

//         if ( ! is_wp_error( $att ) ) {
//             set_post_thumbnail( $post_id, $att );
//             SK_Logger::info( "Featured image set for post #{$post_id}", 'generator' );
//         } else {
//             SK_Logger::warn( 'Image failed: ' . $att->get_error_message(), 'generator' );
//         }
//     }
// }

//         SK_SEO::apply(
//             $post_id,
//             $data['meta_title'] ?? $data['title'] ?? $topic,
//             $data['meta_description'] ?? $data['excerpt'] ?? '',
//             $keywords
//         );

//         SK_Logger::info( "Post #{$post_id} generated for topic: {$topic}", 'generator' );
//         return $post_id;
//     }

//     /**
//      * Aggressive JSON parser with multiple fallback strategies.
//      */
//     private static function parse_json( $raw ) {
//         // DEBUG: log raw response (first 3000 chars)
//         SK_Logger::info( 'RAW RESPONSE: ' . substr( $raw, 0, 3000 ), 'debug' );

//         // Step 1: Strip BOM and zero-width characters
//         $raw = preg_replace( '/[\x{FEFF}\x{200B}-\x{200D}\x{FFFD}]/u', '', $raw );
//         $raw = trim( $raw );

//         // Step 2: Remove markdown code fences (various formats)
//         $raw = preg_replace( '/^```(?:json|JSON)?\s*/im', '', $raw );
//         $raw = preg_replace( '/```\s*$/im', '', $raw );
//         $raw = trim( $raw );

//         // Step 3: Direct decode
//         $data = json_decode( $raw, true );
//         if ( json_last_error() === JSON_ERROR_NONE && is_array( $data ) ) {
//             return $data;
//         }

//         // Step 4: Find first { and last } — greedy match
//         $first = strpos( $raw, '{' );
//         $last  = strrpos( $raw, '}' );
//         if ( $first !== false && $last !== false && $last > $first ) {
//             $candidate = substr( $raw, $first, $last - $first + 1 );
//             $data = json_decode( $candidate, true );
//             if ( json_last_error() === JSON_ERROR_NONE && is_array( $data ) ) {
//                 return $data;
//             }
//         }

//         // Step 5: Balanced brace extraction (handles nested objects + strings with braces)
//         if ( $first !== false ) {
//             $depth     = 0;
//             $in_string = false;
//             $escape    = false;
//             $end       = $first;
//             $len       = strlen( $raw );

//             for ( $i = $first; $i < $len; $i++ ) {
//                 $c = $raw[$i];

//                 if ( $escape ) { $escape = false; continue; }
//                 if ( $c === '\\' ) { $escape = true; continue; }
//                 if ( $c === '"' ) { $in_string = ! $in_string; continue; }
//                 if ( $in_string ) { continue; }

//                 if ( $c === '{' ) {
//                     $depth++;
//                 } elseif ( $c === '}' ) {
//                     $depth--;
//                     if ( $depth === 0 ) {
//                         $end = $i;
//                         break;
//                     }
//                 }
//             }

//             $candidate = substr( $raw, $first, $end - $first + 1 );
//             $data = json_decode( $candidate, true );
//             if ( json_last_error() === JSON_ERROR_NONE && is_array( $data ) ) {
//                 return $data;
//             }

//             // Step 6: Repair common issues — trailing commas
//             $repaired = preg_replace( '/,\s*([}\]])/', '$1', $candidate );

//             // Remove control characters inside JSON (except \n \r \t escaped)
//             $repaired = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $repaired );

//             $data = json_decode( $repaired, true );
//             if ( json_last_error() === JSON_ERROR_NONE && is_array( $data ) ) {
//                 return $data;
//             }

//             // All failed — log details
//             SK_Logger::error( 'JSON parse failed: ' . json_last_error_msg(), 'generator', [
//                 'preview'       => substr( $candidate, 0, 800 ),
//                 'raw_length'    => strlen( $raw ),
//                 'candidate_len' => strlen( $candidate ),
//             ] );
//         } else {
//             SK_Logger::error( 'JSON parse failed: no { found in response.', 'generator', [
//                 'preview' => substr( $raw, 0, 800 ),
//             ] );
//         }

//         return null;
//     }

//     /**
//      * Fallback extraction: If JSON parse fails, try to manually extract
//      * title and content from raw text. Never let a post completely fail.
//      */
//     private static function fallback_extract( $raw, $topic ) {
//         $raw = trim( $raw );
//         if ( empty( $raw ) ) { return null; }

//         $title   = $topic;
//         $content = '';

//         // Try to find title field
//         if ( preg_match( '/["\']title["\']\s*:\s*["\'](.+?)["\']/is', $raw, $m ) ) {
//             $title = trim( $m[1] );
//         } elseif ( preg_match( '/^#\s*(.+)$/m', $raw, $m ) ) {
//             $title = trim( $m[1] );
//         }

//         // Try to find content field
//         if ( preg_match( '/["\']content["\']\s*:\s*["\'](.+)["\']/is', $raw, $m ) ) {
//             $content = $m[1];
//         }

//         // If we found content, unescape it
//         if ( ! empty( $content ) ) {
//             // Unescape common JSON escapes
//             $content = str_replace( [ '\\n', '\\r', '\\t', '\\"', "\\'", '\\/' ], [ "\n", "\r", "\t", '"', "'", '/' ], $content );
//             $content = wp_kses_post( $content );
//         } else {
//             // No JSON structure — treat entire raw as content
//             // Remove any JSON-looking artifacts
//             $cleaned = preg_replace( '/^\s*\{.*?["\']content["\']\s*:\s*["\']/is', '', $raw );
//             $cleaned = preg_replace( '/["\']\s*,\s*["\']\w+["\']\s*:.*$/s', '', $cleaned );
//             $cleaned = trim( $cleaned, " \t\n\r\0\x0B\"'" );

//             if ( ! empty( $cleaned ) && strlen( $cleaned ) > 100 ) {
//                 // Check if it's HTML
//                 if ( strip_tags( $cleaned ) !== $cleaned ) {
//                     $content = wp_kses_post( $cleaned );
//                 } else {
//                     // Convert plain text to HTML paragraphs
//                     $paragraphs = preg_split( '/\n\s*\n/', $cleaned );
//                     $content = '';
//                     foreach ( $paragraphs as $p ) {
//                         $p = trim( $p );
//                         if ( ! empty( $p ) ) {
//                             $content .= '<p>' . esc_html( $p ) . '</p>' . "\n";
//                         }
//                     }
//                 }
//             }
//         }

//         if ( empty( $content ) ) { return null; }

//         return [
//             'title'            => $title,
//             'content'          => $content,
//             'excerpt'          => wp_trim_words( wp_strip_all_tags( $content ), 30 ),
//             'meta_description' => wp_trim_words( wp_strip_all_tags( $content ), 25 ),
//             'tags'             => [],
//         ];
//     }
//     /**
//  * Add internal and external links to post content.
//  */
// private static function inject_links( $content, $topic, $keywords = '', $post_id = 0 ) {
//     // Only if enabled
//     if ( ! get_option( 'sk_internal_links_enabled', 1 ) 
//       && ! get_option( 'sk_external_links_enabled', 1 ) ) {
//         return $content;
//     }

//     $primary_kw = SK_SEO::get_primary_keyword( $keywords );
//     $insertions = [];

//     // Internal links (from existing posts)
//     if ( get_option( 'sk_internal_links_enabled', 1 ) ) {
//         $internal = self::find_internal_link_targets( $topic, $keywords, $post_id );
//         foreach ( $internal as $link ) {
//             $insertions[] = [
//                 'url'   => $link['url'],
//                 'text'  => $link['anchor'],
//                 'type'  => 'internal',
//                 'title' => $link['title'],
//             ];
//         }
//     }

//     // External links (authority sources)
//     if ( get_option( 'sk_external_links_enabled', 1 ) ) {
//         $external = self::find_external_link_targets( $topic, $keywords );
//         foreach ( $external as $link ) {
//             $insertions[] = [
//                 'url'   => $link['url'],
//                 'text'  => $link['anchor'],
//                 'type'  => 'external',
//             ];
//         }
//     }

//     if ( empty( $insertions ) ) {
//         return $content;
//     }

//     // Inject links into content at natural points
//     return self::insert_links_into_content( $content, $insertions );
// }

// /**
//  * Find internal link targets from existing posts.
//  */
// private static function find_internal_link_targets( $topic, $keywords, $exclude_post_id = 0, $count = 3 ) {
//     $targets = [];

//     // Search by keywords
//     $kw_array = SK_SEO::get_all_keywords( $keywords );

//     // Query posts with matching content
//     $args = [
//         'post_type'      => 'post',
//         'post_status'    => 'publish',
//         'posts_per_page' => 20,
//         'post__not_in'   => $exclude_post_id ? [ $exclude_post_id ] : [],
//         'orderby'        => 'relevance',
//         's'              => $topic,
//     ];

//     $query = new WP_Query( $args );

//     if ( $query->have_posts() ) {
//         while ( $query->have_posts() && count( $targets ) < $count ) {
//             $query->the_post();
//             $pid = get_the_ID();

//             // Create an anchor from the post title
//             $title = get_the_title( $pid );
//             $anchor = self::create_anchor_text( $title, $kw_array );

//             $targets[] = [
//                 'url'   => get_permalink( $pid ),
//                 'anchor'=> $anchor,
//                 'title' => $title,
//                 'id'    => $pid,
//             ];
//         }
//         wp_reset_postdata();
//     }

//     // Fallback: use category or tag links
//     if ( empty( $targets ) ) {
//         $categories = get_categories( [ 'number' => 3, 'hide_empty' => true ] );
//         foreach ( $categories as $cat ) {
//             if ( count( $targets ) >= $count ) { break; }
//             $targets[] = [
//                 'url'   => get_category_link( $cat->term_id ),
//                 'anchor'=> $cat->name,
//                 'title' => $cat->name,
//                 'id'    => 0,
//             ];
//         }
//     }

//     return $targets;
// }

// /**
//  * Generate external authority links based on keywords.
//  */
// private static function find_external_link_targets( $topic, $keywords, $count = 2 ) {
//     // Authority sites by topic keyword matching
//     $authority_map = [
//         'ai'              => [ [ 'https://ai.google', 'Google AI' ], [ 'https://openai.com', 'OpenAI' ] ],
//         'machine learning'=> [ [ 'https://www.tensorflow.org', 'TensorFlow' ], [ 'https://pytorch.org', 'PyTorch' ] ],
//         'engineering'     => [ [ 'https://www.asme.org', 'ASME' ], [ 'https://www.ieee.org', 'IEEE' ] ],
//         'seo'             => [ [ 'https://developers.google.com/search', 'Google Search Central' ], [ 'https://moz.com', 'Moz' ] ],
//         'wordpress'       => [ [ 'https://wordpress.org', 'WordPress.org' ], [ 'https://developer.wordpress.org', 'WordPress Developer' ] ],
//         'marketing'       => [ [ 'https://blog.hubspot.com', 'HubSpot' ], [ 'https://neilpatel.com', 'Neil Patel' ] ],
//         'health'          => [ [ 'https://www.who.int', 'WHO' ], [ 'https://www.nih.gov', 'NIH' ] ],
//         'finance'         => [ [ 'https://www.investopedia.com', 'Investopedia' ], [ 'https://www.forbes.com', 'Forbes' ] ],
//     ];

//     $targets = [];
//     $combined = strtolower( $topic . ' ' . $keywords );

//     foreach ( $authority_map as $key => $sites ) {
//         if ( strpos( $combined, $key ) !== false ) {
//             foreach ( $sites as $site ) {
//                 if ( count( $targets ) >= $count ) { break 2; }
//                 $targets[] = [
//                     'url'   => $site[0],
//                     'anchor'=> $site[1],
//                 ];
//             }
//         }
//     }

//     return $targets;
// }

// /**
//  * Create natural anchor text from a post title.
//  */
// private static function create_anchor_text( $title, $keywords = [] ) {
//     // If title is short enough, use it directly
//     if ( mb_strlen( $title ) <= 40 ) {
//         return $title;
//     }

//     // Try to find a keyword within the title
//     foreach ( (array) $keywords as $kw ) {
//         if ( ! empty( $kw ) && mb_stripos( $title, $kw ) !== false ) {
//             return $kw;
//         }
//     }

//     // Fallback: first 40 chars of title
//     return mb_substr( $title, 0, 40 ) . '...';
// }

// /**
//  * Insert links naturally into content by replacing keywords/anchors.
//  */
// private static function insert_links_into_content( $content, $insertions ) {
//     $inserted = 0;
//     $used_anchors = [];

//     // Shuffle for variety
//     shuffle( $insertions );

//     foreach ( $insertions as $link ) {
//         $anchor = $link['text'];

//         // Skip if anchor already used
//         if ( in_array( strtolower( $anchor ), $used_anchors, true ) ) {
//             continue;
//         }

//         // Find anchor in content, but not inside existing <a> tags
//         $pattern = '/(?<!<a[^>]*>)(?<!\w)' . preg_quote( $anchor, '/' ) . '(?!\w)(?![^<]*<\/a>)/iu';

//         $replacement = '<a href="' . esc_url( $link['url'] ) . '"';
//         if ( $link['type'] === 'external' ) {
//             $replacement .= ' target="_blank" rel="noopener nofollow"';
//         } else {
//             $replacement .= ' rel="internal"';
//         }
//         $replacement .= '>' . esc_html( $anchor ) . '</a>';

//         $new_content = preg_replace( $pattern, $replacement, $content, 1, $count );

//         if ( $count > 0 ) {
//             $content = $new_content;
//             $used_anchors[] = strtolower( $anchor );
//             $inserted++;
//         }
//     }

//     // If no natural match, inject links into random paragraphs
//     if ( $inserted === 0 && ! empty( $insertions ) ) {
//         $content = self::inject_links_into_paragraphs( $content, $insertions );
//     }

//     return $content;
// }

// /**
//  * Fallback: append links at end of random paragraphs.
//  */
// private static function inject_links_into_paragraphs( $content, $insertions ) {
//     // Split content by paragraphs
//     $parts = preg_split( '/(<\/p>)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE );

//     if ( count( $parts ) < 3 ) { return $content; }

//     $total_paras = 0;
//     for ( $i = 0; $i < count( $parts ); $i += 2 ) { $total_paras++; }

//     if ( $total_paras < 3 ) { return $content; }

//     // Pick insertion points (spread through article)
//     $insert_positions = [];
//     $step = max( 1, (int) ( $total_paras / ( count( $insertions ) + 1 ) ) );
//     for ( $i = $step; $i < $total_paras; $i += $step ) {
//         $insert_positions[] = $i * 2; // multiply by 2 because of delimiter
//     }

//     $link_index = 0;
//     foreach ( $insert_positions as $pos ) {
//         if ( ! isset( $parts[$pos] ) || ! isset( $insertions[$link_index] ) ) { break; }

//         $link = $insertions[$link_index];
//         $extra = ' ' . esc_html( 'Learn more about this on ' ) 
//                . '<a href="' . esc_url( $link['url'] ) . '"'
//                . ( $link['type'] === 'external' ? ' target="_blank" rel="noopener nofollow"' : '' )
//                . '>' . esc_html( $link['text'] ) . '</a>.';

//         // Insert before the closing </p>
//         $parts[$pos - 1] = $parts[$pos - 1] . $extra;
//         $link_index++;
//     }

//     return implode( '', $parts );
// }
// }

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
                . "- meta_description: string (SEO meta description, 145-160 chars)\n"
                . "- tags: array of strings (3-6 relevant tags)\n\n"
                . "Example structure:\n"
                . "{\"title\":\"...\",\"content\":\"<h2>...</h2><p>...</p>\",\"excerpt\":\"...\",\"meta_description\":\"...\",\"tags\":[\"tag1\",\"tag2\"]}";

        $prompt = "Write a comprehensive, original, SEO-optimized blog post of approximately {$length} words.\n\n"
                . "Topic: {$topic}\n"
                . ( $keywords ? "Target keywords: {$keywords}\n" : '' )
                . "\nRequirements:\n"
                . "- Include an engaging introduction (first paragraph MUST contain the primary keyword)\n"
                . "- Use multiple <h2> sections with <h3> subsections\n"
                . "- At least ONE <h2> heading MUST contain the primary keyword\n"
                . "- Include bullet lists where appropriate\n"
                . "- End with a strong conclusion\n"
                . "- Content must be valid HTML (use <h2>, <h3>, <p>, <ul>, <li>, <strong> tags)\n"
                . "- DO NOT include <h1> tags (post title is already H1)\n\n"
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

        // ============================================================
        // SEO ENHANCEMENTS — Apply BEFORE post insert
        // ============================================================

        $post_title = wp_strip_all_tags( $data['title'] ?? $topic );

        // 1. Optimize content for keywords (first para, H2, H1→H2 fix)
        $content = self::optimize_content_for_keywords( $data['content'], $topic, $keywords, $data );

        // 2. Inject internal + external links
        $content = self::inject_links( $content, $topic, $keywords, 0 );

        // 3. Build SEO-friendly slug
        $slug = self::build_seo_slug( $post_title, $keywords );

        // ============================================================

        $status    = $override['status']   ?? get_option( 'sk_default_status', 'draft' );
        $author    = $override['author']   ?? get_option( 'sk_default_author', 1 );
        $category  = $override['category'] ?? get_option( 'sk_default_category', 1 );

        $post_id = wp_insert_post( [
            'post_title'    => $post_title,
            'post_content'  => wp_kses_post( $content ),
            'post_excerpt'  => sanitize_text_field( $data['excerpt'] ?? '' ),
            'post_status'   => $status,
            'post_author'   => (int) $author,
            'post_category' => [ (int) $category ],
            'post_type'     => 'post',
            'post_name'     => $slug,   // SEO slug
        ], true );

        if ( is_wp_error( $post_id ) ) {
            SK_Logger::error( 'Post insert failed: ' . $post_id->get_error_message(), 'generator' );
            return $post_id;
        }

        if ( ! empty( $data['tags'] ) && is_array( $data['tags'] ) ) {
            wp_set_post_tags( $post_id, array_map( 'sanitize_text_field', $data['tags'] ) );
        }

        // ============================================================
        // Featured image with keyword-rich alt text
        // ============================================================
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

                // Keyword-rich alt text
                $alt_text = self::build_image_alt( $topic, $keywords, $data );

                $att = SK_Image::generate_and_sideload( $img_prompt, $post_id, $alt_text );

                if ( ! is_wp_error( $att ) ) {
                    set_post_thumbnail( $post_id, $att );
                    SK_Logger::info( "Featured image set for post #{$post_id}", 'generator' );
                } else {
                    SK_Logger::warn( 'Image failed: ' . $att->get_error_message(), 'generator' );
                }
            }
        }

        // ============================================================
        // SEO meta (title 50-60, description 145-160) — handled in SK_SEO
        // ============================================================
        SK_SEO::apply(
            $post_id,
            $data['meta_title'] ?? $post_title,
            $data['meta_description'] ?? $data['excerpt'] ?? '',
            $keywords
        );

        SK_Logger::info( "Post #{$post_id} generated for topic: {$topic}", 'generator' );
        return $post_id;
    }

    /* ================================================================
     * SEO HELPER METHODS
     * ================================================================ */

    /**
     * Ensure primary keyword appears in first paragraph and at least one H2.
     * Also converts any <h1> in AI content to <h2>.
     */
    private static function optimize_content_for_keywords( $content, $topic, $keywords, $data = [] ) {
        $content = trim( $content );

        // 1. Convert any H1 in content to H2 (post title is the H1)
        $content = preg_replace( '/<h1(\s[^>]*)?>(.*?)<\/h1>/is', '<h2$1>$2</h2>', $content );

        $primary_kw = SK_SEO::get_primary_keyword( $keywords );
        if ( empty( $primary_kw ) ) {
            return $content;
        }

        // 2. Ensure primary keyword in first paragraph
        if ( preg_match( '/<p>(.*?)<\/p>/is', $content, $m ) ) {
            $first_para = $m[1];
            if ( mb_stripos( $first_para, $primary_kw ) === false ) {
                // Prepend keyword context to first paragraph
                $kw_label = esc_html( ucwords( $primary_kw ) );
                $new_first = '<p><strong>' . $kw_label . '</strong> — ' . $first_para . '</p>';
                $pos = strpos( $content, $m[0] );
                if ( $pos !== false ) {
                    $content = substr_replace( $content, $new_first, $pos, strlen( $m[0] ) );
                }
            }
        }

        // 3. Ensure at least one H2 contains the primary keyword
        $has_kw_in_h2 = false;
        if ( preg_match_all( '/<h2(\s[^>]*)?>(.*?)<\/h2>/is', $content, $h2_matches ) ) {
            foreach ( $h2_matches[2] as $h2_text ) {
                if ( mb_stripos( $h2_text, $primary_kw ) !== false ) {
                    $has_kw_in_h2 = true;
                    break;
                }
            }

            if ( ! $has_kw_in_h2 && ! empty( $h2_matches[0][0] ) ) {
                // Modify the FIRST h2 to include keyword
                $original = $h2_matches[0][0];
                $inner    = $h2_matches[2][0];
                $attrs    = $h2_matches[1][0];
                $new_h2   = '<h2' . $attrs . '>' . esc_html( ucwords( $primary_kw ) ) . ': ' . $inner . '</h2>';

                $pos = strpos( $content, $original );
                if ( $pos !== false ) {
                    $content = substr_replace( $content, $new_h2, $pos, strlen( $original ) );
                }
            }
        }

        return $content;
    }

    /**
     * Build SEO-optimized slug.
     */
    private static function build_seo_slug( $title, $keywords = '' ) {
        $title_slug = sanitize_title( $title );
        $primary_kw = SK_SEO::get_primary_keyword( $keywords );

        if ( ! empty( $primary_kw ) ) {
            $kw_slug = sanitize_title( $primary_kw );

            // Prepend keyword if not already present
            if ( $kw_slug && strpos( $title_slug, $kw_slug ) === false ) {
                $slug = $kw_slug . '-' . $title_slug;
            } else {
                $slug = $title_slug;
            }
        } else {
            $slug = $title_slug;
        }

        // Limit to 60 chars
        if ( mb_strlen( $slug ) > 60 ) {
            $slug = mb_substr( $slug, 0, 60 );
            $last_dash = mb_strrpos( $slug, '-' );
            if ( $last_dash !== false && $last_dash > 30 ) {
                $slug = mb_substr( $slug, 0, $last_dash );
            }
        }

        return $slug ?: sanitize_title( $title );
    }

    /**
     * Build keyword-rich alt text for featured image.
     */
    private static function build_image_alt( $topic, $keywords = '', $data = [] ) {
        $primary_kw  = SK_SEO::get_primary_keyword( $keywords );
        $clean_topic = wp_strip_all_tags( $topic );

        if ( ! empty( $primary_kw ) && mb_stripos( $clean_topic, $primary_kw ) === false ) {
            return $primary_kw . ' - ' . $clean_topic;
        }

        return $clean_topic;
    }

    /* ================================================================
     * INTERNAL & EXTERNAL LINKING
     * ================================================================ */

    /**
     * Add internal and external links to post content.
     */
    private static function inject_links( $content, $topic, $keywords = '', $post_id = 0 ) {
        if ( ! get_option( 'sk_internal_links_enabled', 1 )
          && ! get_option( 'sk_external_links_enabled', 1 ) ) {
            return $content;
        }

        $insertions = [];

        // Internal links (from existing posts)
        if ( get_option( 'sk_internal_links_enabled', 1 ) ) {
            $internal = self::find_internal_link_targets( $topic, $keywords, $post_id );
            foreach ( $internal as $link ) {
                $insertions[] = [
                    'url'   => $link['url'],
                    'text'  => $link['anchor'],
                    'type'  => 'internal',
                    'title' => $link['title'],
                ];
            }
        }

        // External links (authority sources)
        if ( get_option( 'sk_external_links_enabled', 1 ) ) {
            $external = self::find_external_link_targets( $topic, $keywords );
            foreach ( $external as $link ) {
                $insertions[] = [
                    'url'   => $link['url'],
                    'text'  => $link['anchor'],
                    'type'  => 'external',
                ];
            }
        }

        if ( empty( $insertions ) ) {
            return $content;
        }

        return self::insert_links_into_content( $content, $insertions );
    }

    private static function find_internal_link_targets( $topic, $keywords, $exclude_post_id = 0, $count = 3 ) {
        $targets  = [];
        $kw_array = SK_SEO::get_all_keywords( $keywords );

        $args = [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'post__not_in'   => $exclude_post_id ? [ $exclude_post_id ] : [],
            's'              => $topic,
        ];

        $query = new WP_Query( $args );

        if ( $query->have_posts() ) {
            while ( $query->have_posts() && count( $targets ) < $count ) {
                $query->the_post();
                $pid    = get_the_ID();
                $title  = get_the_title( $pid );
                $anchor = self::create_anchor_text( $title, $kw_array );

                $targets[] = [
                    'url'    => get_permalink( $pid ),
                    'anchor' => $anchor,
                    'title'  => $title,
                    'id'     => $pid,
                ];
            }
            wp_reset_postdata();
        }

        // Fallback: use category links
        if ( empty( $targets ) ) {
            $categories = get_categories( [ 'number' => 3, 'hide_empty' => true ] );
            foreach ( $categories as $cat ) {
                if ( count( $targets ) >= $count ) { break; }
                $targets[] = [
                    'url'    => get_category_link( $cat->term_id ),
                    'anchor' => $cat->name,
                    'title'  => $cat->name,
                    'id'     => 0,
                ];
            }
        }

        return $targets;
    }

    private static function find_external_link_targets( $topic, $keywords, $count = 2 ) {
        $authority_map = [
            'ai'               => [ [ 'https://ai.google', 'Google AI' ], [ 'https://openai.com', 'OpenAI' ] ],
            'machine learning' => [ [ 'https://www.tensorflow.org', 'TensorFlow' ], [ 'https://pytorch.org', 'PyTorch' ] ],
            'engineering'      => [ [ 'https://www.asme.org', 'ASME' ], [ 'https://www.ieee.org', 'IEEE' ] ],
            'seo'              => [ [ 'https://developers.google.com/search', 'Google Search Central' ], [ 'https://moz.com', 'Moz' ] ],
            'wordpress'        => [ [ 'https://wordpress.org', 'WordPress.org' ], [ 'https://developer.wordpress.org', 'WordPress Developer' ] ],
            'marketing'        => [ [ 'https://blog.hubspot.com', 'HubSpot' ], [ 'https://neilpatel.com', 'Neil Patel' ] ],
            'health'           => [ [ 'https://www.who.int', 'WHO' ], [ 'https://www.nih.gov', 'NIH' ] ],
            'finance'          => [ [ 'https://www.investopedia.com', 'Investopedia' ], [ 'https://www.forbes.com', 'Forbes' ] ],
        ];

        $targets  = [];
        $combined = strtolower( $topic . ' ' . $keywords );

        foreach ( $authority_map as $key => $sites ) {
            if ( strpos( $combined, $key ) !== false ) {
                foreach ( $sites as $site ) {
                    if ( count( $targets ) >= $count ) { break 2; }
                    $targets[] = [
                        'url'    => $site[0],
                        'anchor' => $site[1],
                    ];
                }
            }
        }

        return $targets;
    }

    private static function create_anchor_text( $title, $keywords = [] ) {
        if ( mb_strlen( $title ) <= 40 ) {
            return $title;
        }
        foreach ( (array) $keywords as $kw ) {
            if ( ! empty( $kw ) && mb_stripos( $title, $kw ) !== false ) {
                return $kw;
            }
        }
        return mb_substr( $title, 0, 40 ) . '...';
    }

    private static function insert_links_into_content( $content, $insertions ) {
        $inserted     = 0;
        $used_anchors = [];

        shuffle( $insertions );

        foreach ( $insertions as $link ) {
            $anchor = $link['text'];

            if ( in_array( strtolower( $anchor ), $used_anchors, true ) ) {
                continue;
            }

            // Find anchor in content (not already inside <a>)
            $pattern = '/<p[^>]*>.*?\b' . preg_quote( $anchor, '/' ) . '\b.*?<\/p>/isu';

            if ( ! preg_match( $pattern, $content ) ) {
                continue;
            }

            $replacement = '<a href="' . esc_url( $link['url'] ) . '"';
            if ( $link['type'] === 'external' ) {
                $replacement .= ' target="_blank" rel="noopener nofollow"';
            } else {
                $replacement .= ' rel="internal"';
            }
            $replacement .= '>' . esc_html( $anchor ) . '</a>';

            // Replace FIRST occurrence only
            $content = preg_replace(
                '/' . preg_quote( $anchor, '/' ) . '/iu',
                $replacement,
                $content,
                1
            );

            $used_anchors[] = strtolower( $anchor );
            $inserted++;
        }

        // Fallback: append links to random paragraphs
        if ( $inserted === 0 && ! empty( $insertions ) ) {
            $content = self::inject_links_into_paragraphs( $content, $insertions );
        }

        return $content;
    }

    private static function inject_links_into_paragraphs( $content, $insertions ) {
        $parts = preg_split( '/(<\/p>)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
        if ( count( $parts ) < 3 ) { return $content; }

        $total_paras = 0;
        for ( $i = 0; $i < count( $parts ); $i += 2 ) { $total_paras++; }
        if ( $total_paras < 3 ) { return $content; }

        $insert_positions = [];
        $step = max( 1, (int) ( $total_paras / ( count( $insertions ) + 1 ) ) );
        for ( $i = $step; $i < $total_paras; $i += $step ) {
            $insert_positions[] = $i * 2;
        }

        $link_index = 0;
        foreach ( $insert_positions as $pos ) {
            if ( ! isset( $parts[$pos] ) || ! isset( $insertions[$link_index] ) ) { break; }

            $link  = $insertions[$link_index];
            $extra = ' ' . esc_html__( 'Learn more about this on ', 'sk-blogger' )
                   . '<a href="' . esc_url( $link['url'] ) . '"'
                   . ( $link['type'] === 'external' ? ' target="_blank" rel="noopener nofollow"' : '' )
                   . '>' . esc_html( $link['text'] ) . '</a>.';

            $parts[$pos - 1] = $parts[$pos - 1] . $extra;
            $link_index++;
        }

        return implode( '', $parts );
    }

    /* ================================================================
     * JSON PARSING & FALLBACK
     * ================================================================ */

    private static function parse_json( $raw ) {
        SK_Logger::info( 'RAW RESPONSE: ' . substr( $raw, 0, 3000 ), 'debug' );

        $raw = preg_replace( '/[\x{FEFF}\x{200B}-\x{200D}\x{FFFD}]/u', '', $raw );
        $raw = trim( $raw );

        $raw = preg_replace( '/^```(?:json|JSON)?\s*/im', '', $raw );
        $raw = preg_replace( '/```\s*$/im', '', $raw );
        $raw = trim( $raw );

        $data = json_decode( $raw, true );
        if ( json_last_error() === JSON_ERROR_NONE && is_array( $data ) ) {
            return $data;
        }

        $first = strpos( $raw, '{' );
        $last  = strrpos( $raw, '}' );
        if ( $first !== false && $last !== false && $last > $first ) {
            $candidate = substr( $raw, $first, $last - $first + 1 );
            $data = json_decode( $candidate, true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $data ) ) {
                return $data;
            }
        }

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
                    if ( $depth === 0 ) { $end = $i; break; }
                }
            }

            $candidate = substr( $raw, $first, $end - $first + 1 );
            $data = json_decode( $candidate, true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $data ) ) {
                return $data;
            }

            $repaired = preg_replace( '/,\s*([}\]])/', '$1', $candidate );
            $repaired = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $repaired );
            $data = json_decode( $repaired, true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $data ) ) {
                return $data;
            }

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

    private static function fallback_extract( $raw, $topic ) {
        $raw = trim( $raw );
        if ( empty( $raw ) ) { return null; }

        $title   = $topic;
        $content = '';

        if ( preg_match( '/["\']title["\']\s*:\s*["\'](.+?)["\']/is', $raw, $m ) ) {
            $title = trim( $m[1] );
        } elseif ( preg_match( '/^#\s*(.+)$/m', $raw, $m ) ) {
            $title = trim( $m[1] );
        }

        if ( preg_match( '/["\']content["\']\s*:\s*["\'](.+)["\']/is', $raw, $m ) ) {
            $content = $m[1];
        }

        if ( ! empty( $content ) ) {
            $content = str_replace( [ '\\n', '\\r', '\\t', '\\"', "\\'", '\\/' ], [ "\n", "\r", "\t", '"', "'", '/' ], $content );
            $content = wp_kses_post( $content );
        } else {
            $cleaned = preg_replace( '/^\s*\{.*?["\']content["\']\s*:\s*["\']/is', '', $raw );
            $cleaned = preg_replace( '/["\']\s*,\s*["\']\w+["\']\s*:.*$/s', '', $cleaned );
            $cleaned = trim( $cleaned, " \t\n\r\0\x0B\"'" );

            if ( ! empty( $cleaned ) && strlen( $cleaned ) > 100 ) {
                if ( strip_tags( $cleaned ) !== $cleaned ) {
                    $content = wp_kses_post( $cleaned );
                } else {
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