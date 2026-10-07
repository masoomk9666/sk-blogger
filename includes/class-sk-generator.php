<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SK_Generator {

    public static function generate_post( $topic, $keywords = '', $override = [] ) {
        $length_map = [ 'short' => 600, 'medium' => 1200, 'long' => 2000 ];
        $length     = $length_map[ get_option( 'sk_post_length', 'medium' ) ] ?? 1200;
        $tone       = get_option( 'sk_tone', 'professional' );
        $language   = get_option( 'sk_language', 'en' );

        if ( empty( $keywords ) ) {
            $keywords = $topic;
            SK_Logger::info( "No keywords provided, using topic as focus keyword: {$topic}", 'generator' );
        }

        $primary_kw = '';
        if ( ! empty( $keywords ) ) {
            $parts = array_map( 'trim', explode( ',', $keywords ) );
            $primary_kw = $parts[0] ?? '';
        }

        // ===== SYSTEM PROMPT =====
        $system = "You are an elite content writer and SEO/AEO strategist with 15+ years of experience. "
                . "You write content that ranks #1 on Google AND gets cited by AI assistants.\n\n"

                . "OUTPUT FORMAT (CRITICAL):\n"
                . "Respond with ONLY a single valid JSON object. Start with { and end with }. "
                . "No markdown, no code fences, no explanations.\n"
                . "Required JSON keys:\n"
                . "- title: string\n"
                . "- content: string (HTML using <h2>, <h3>, <p>, <ul>, <ol>, <li>, <strong>, <em>)\n"
                . "- excerpt: string (150-200 chars)\n"
                . "- meta_description: string (145-160 chars)\n"
                . "- tags: array of 4-6 strings\n\n"

                . "WRITING STYLE:\n"
                . "1. USER-FIRST: Use 'you' and 'your'. Answer directly. Be helpful.\n"
                . "2. ACTIVE VOICE: 90%+ active voice. Strong verbs.\n"
                . "3. NATIVE LANGUAGE: Natural {$language} flow. Contractions. No robotic text.\n"
                . "4. GRAMMAR PERFECT: Zero errors. Vary sentence length.\n"
                . "5. SEMANTIC SEO: Cover related concepts, synonyms, LSI keywords.\n\n"

                . "AEO — ANSWER ENGINE OPTIMIZATION:\n"
                . "1. ANSWER-FIRST: Under each <h2>, start with a 1-2 sentence direct answer.\n"
                . "2. FAQ SECTION: End with <h2>Frequently Asked Questions</h2> + 4-6 Q&A pairs.\n"
                . "3. Each question in <h3>, answer in following <p>.\n"
                . "4. Inline source mentions: '(Source: IEEE)' where relevant.\n\n"

                . "STRUCTURE:\n"
                . "- Opening paragraph: Hook + primary keyword.\n"
                . "- 4-7 <h2> sections (each starts with answer-first summary).\n"
                . "- Bullet lists, <strong> takeaways.\n"
                . "- FAQ section at end.\n"
                . "- NEVER use <h1> tags.\n"
                . "- NEVER use HTML comments (<!-- -->).\n\n"

                . "Tone: {$tone}. Language: {$language}.";

        $prompt = "Write a comprehensive, original, world-class blog post of ~{$length} words.\n\n"
                . "TOPIC: {$topic}\n"
                . ( $primary_kw ? "PRIMARY KEYWORD: {$primary_kw}\n" : '' )
                . ( $keywords ? "RELATED KEYWORDS: {$keywords}\n" : '' )
                . "\nREQUIREMENTS:\n"
                . "1. SEMANTIC SEO: Primary keyword in title, first para, one H2, meta description. LSI keywords.\n"
                . "2. USER-FIRST: 'You/your', actionable, examples.\n"
                . "3. 100% COVERAGE: what/why/how/when/who/mistakes.\n"
                . "4. LANGUAGE: Zero errors. Native {$language}. Active voice.\n"
                . "5. AEO: Answer-first. FAQ section (4-6 Q&A). Inline sources.\n"
                . "6. FORMATTING: <h2>, <h3>, <p>, <ul>, <ol>, <li>, <strong>. No <h1>, no HTML comments.\n\n"
                . "CRITICAL: Return ONLY raw JSON. Start { end }. No markdown.";

        $max_tokens = (int) ( $length * 4 );

        $raw = SK_AI::generate_text( $prompt, $system, $max_tokens );

        if ( is_wp_error( $raw ) ) {
            SK_Logger::error( 'AI generation failed: ' . $raw->get_error_message(), 'generator', [ 'topic' => $topic ] );
            return $raw;
        }

        $data = self::parse_json( $raw );

        if ( ! $data || empty( $data['content'] ) ) {
            SK_Logger::warn( 'JSON parse failed, using fallback.', 'generator', [ 'raw_preview' => substr( $raw, 0, 500 ) ] );
            $fallback = self::fallback_extract( $raw, $topic );
            if ( $fallback ) {
                $data = $fallback;
            } else {
                return new WP_Error( 'bad_json', 'AI returned invalid JSON.' );
            }
        }

        // SEO Enhancements
        $post_title = wp_strip_all_tags( $data['title'] ?? $topic );
        $content    = self::optimize_content_for_keywords( $data['content'], $topic, $keywords, $data );
        $content    = self::inject_links( $content, $topic, $keywords, 0 );
        $slug       = self::build_seo_slug( $post_title, $keywords );

        $status   = $override['status']   ?? get_option( 'sk_default_status', 'draft' );
        $author   = $override['author']   ?? get_option( 'sk_default_author', 1 );
        $category = $override['category'] ?? get_option( 'sk_default_category', 1 );

        $post_id = wp_insert_post( [
            'post_title'    => $post_title,
            'post_content'  => wp_kses_post( $content ),
            'post_excerpt'  => sanitize_text_field( $data['excerpt'] ?? '' ),
            'post_status'   => $status,
            'post_author'   => (int) $author,
            'post_category' => [ (int) $category ],
            'post_type'     => 'post',
            'post_name'     => $slug,
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
            $image_provider = get_option( 'sk_image_provider', 'pollinations' );
            $has_provider   = ( $image_provider === 'pollinations' )
                           || ( $image_provider === 'openai' && get_option( 'sk_openai_key' ) )
                           || ( $image_provider === 'gemini' && get_option( 'sk_gemini_key' ) );

            if ( $has_provider ) {
                $img_prompt = "Professional blog featured image about: {$topic}. "
                            . "Modern, clean, photorealistic style. Wide 16:9. "
                            . "No text, no watermarks, no logos, no people faces.";

                $alt_text = self::build_image_alt( $topic, $keywords, $data );
                $att = SK_Image::generate_and_sideload( $img_prompt, $post_id, $alt_text );

                if ( ! is_wp_error( $att ) ) {
                    set_post_thumbnail( $post_id, $att );
                } else {
                    SK_Logger::warn( 'Image failed: ' . $att->get_error_message(), 'generator' );
                }
            }
        }

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
     * SEO HELPERS
     * ================================================================ */

    private static function optimize_content_for_keywords( $content, $topic, $keywords, $data = [] ) {
        $content = trim( $content );

        // Remove HTML comments
        $content = preg_replace( '/<!--.*?-->/s', '', $content );

        // H1 → H2
        $content = preg_replace( '/<h1(\s[^>]*)?>(.*?)<\/h1>/is', '<h2$1>$2</h2>', $content );

        $primary_kw = SK_SEO::get_primary_keyword( $keywords );
        if ( empty( $primary_kw ) ) { return $content; }

        // Ensure primary keyword in first paragraph
        if ( preg_match( '/<p>(.*?)<\/p>/is', $content, $m ) ) {
            $first_para = $m[1];
            if ( mb_stripos( $first_para, $primary_kw ) === false ) {
                $kw_label  = esc_html( ucwords( $primary_kw ) );
                $new_first = '<p><strong>' . $kw_label . '</strong> — ' . $first_para . '</p>';
                $pos = strpos( $content, $m[0] );
                if ( $pos !== false ) {
                    $content = substr_replace( $content, $new_first, $pos, strlen( $m[0] ) );
                }
            }
        }

        // Ensure one H2 contains keyword
        $has_kw_in_h2 = false;
        if ( preg_match_all( '/<h2(\s[^>]*)?>(.*?)<\/h2>/is', $content, $h2_matches ) ) {
            foreach ( $h2_matches[2] as $h2_text ) {
                if ( mb_stripos( $h2_text, $primary_kw ) !== false ) {
                    $has_kw_in_h2 = true;
                    break;
                }
            }

            if ( ! $has_kw_in_h2 && ! empty( $h2_matches[0][0] ) ) {
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

    private static function build_seo_slug( $title, $keywords = '' ) {
        $title_slug = sanitize_title( $title );
        $primary_kw = SK_SEO::get_primary_keyword( $keywords );

        if ( ! empty( $primary_kw ) ) {
            $kw_slug = sanitize_title( $primary_kw );
            if ( $kw_slug && strpos( $title_slug, $kw_slug ) === false ) {
                $slug = $kw_slug . '-' . $title_slug;
            } else {
                $slug = $title_slug;
            }
        } else {
            $slug = $title_slug;
        }

        if ( mb_strlen( $slug ) > 60 ) {
            $slug = mb_substr( $slug, 0, 60 );
            $last_dash = mb_strrpos( $slug, '-' );
            if ( $last_dash !== false && $last_dash > 30 ) {
                $slug = mb_substr( $slug, 0, $last_dash );
            }
        }

        return $slug ?: sanitize_title( $title );
    }

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

    private static function inject_links( $content, $topic, $keywords = '', $post_id = 0 ) {
        $internal_enabled = get_option( 'sk_internal_links_enabled', 1 );
        $external_enabled = get_option( 'sk_external_links_enabled', 1 );

        if ( ! $internal_enabled && ! $external_enabled ) {
            return $content;
        }

        $internal_links = [];
        $external_links = [];

        if ( $internal_enabled ) {
            $internal_links = self::find_internal_link_targets( $topic, $keywords, $post_id, 3 );
        }

        if ( $external_enabled ) {
            $external_links = self::find_external_link_targets( $topic, $keywords, 2 );
        }

        if ( empty( $internal_links ) && empty( $external_links ) ) {
            return $content;
        }

        return self::insert_links_into_content( $content, $internal_links, $external_links );
    }

    /**
 * GUARANTEED KEYWORD LINKS — Every keyword gets a link.
 *
 * Strategy (per keyword):
 *   1. Find post with keyword in title/content → link to post
 *   2. If not found → create search-results link (?s=keyword)
 *   3. Final fallback → category link (only if no keywords given)
 */
private static function find_internal_link_targets( $topic, $keywords, $exclude_post_id = 0, $count = 3 ) {
    $targets  = [];
    $used_ids = [];
    $kw_array = SK_SEO::get_all_keywords( $keywords );

    // If user provided keywords, we MUST create a link for EACH one
    if ( ! empty( $kw_array ) ) {

        // Limit to $count keywords (default 3)
        $kw_array = array_slice( array_filter( $kw_array ), 0, $count );

        foreach ( $kw_array as $kw ) {
            $kw = trim( $kw );
            if ( empty( $kw ) ) { continue; }

            // ===== STEP 1: Try to find a real post matching this keyword =====
            $matched_post = self::find_post_for_keyword( $kw, $exclude_post_id, $used_ids );

            if ( $matched_post ) {
                $targets[] = [
                    'url'    => get_permalink( $matched_post->ID ),
                    'anchor' => $kw,   // ✅ EXACT keyword as anchor
                    'title'  => get_the_title( $matched_post->ID ),
                    'id'     => $matched_post->ID,
                    'type'   => 'post',
                    'source' => 'keyword-post',
                ];
                $used_ids[] = $matched_post->ID;
                continue;
            }

            // ===== STEP 2: No matching post → use SEARCH RESULTS link =====
            // This guarantees a link exists for the keyword
            $search_url = home_url( '/?s=' . rawurlencode( $kw ) );

            $targets[] = [
                'url'    => $search_url,
                'anchor' => $kw,   // ✅ EXACT keyword as anchor
                'title'  => sprintf( 'Search results for: %s', $kw ),
                'id'     => 0,
                'type'   => 'search',
                'source' => 'keyword-search',
            ];
        }

        return $targets;  // ✅ Always returns keyword links
    }

    // ===== FALLBACK: No keywords provided → use category links =====
    $needed = $count - count( $targets );

    if ( $needed > 0 ) {
        $categories = get_categories( [
            'taxonomy'   => 'category',
            'hide_empty' => true,
            'number'     => $needed + 5,
            'orderby'    => 'count',
            'order'      => 'DESC',
        ] );

        // Prefer categories matching the topic
        $matched_cats   = [];
        $unmatched_cats = [];

        foreach ( $categories as $cat ) {
            if ( mb_stripos( $topic, $cat->name ) !== false ) {
                $matched_cats[] = $cat;
            } else {
                $unmatched_cats[] = $cat;
            }
        }

        foreach ( array_merge( $matched_cats, $unmatched_cats ) as $cat ) {
            if ( count( $targets ) >= $count ) { break; }

            $targets[] = [
                'url'    => get_category_link( $cat->term_id ),
                'anchor' => $cat->name,
                'title'  => $cat->name,
                'id'     => 0,
                'type'   => 'category',
                'source' => 'category-fallback',
            ];
        }
    }

    return $targets;
}

/**
 * Find a single post matching the given keyword.
 * Searches title first, then content.
 *
 * @param string $keyword
 * @param int    $exclude_post_id
 * @param array  $used_ids
 * @return WP_Post|null
 */
private static function find_post_for_keyword( $keyword, $exclude_post_id = 0, $used_ids = [] ) {
    $exclude = $used_ids;
    if ( $exclude_post_id ) { $exclude[] = $exclude_post_id; }

    // ---- Try 1: Search in TITLE only (most relevant) ----
    $title_query = new WP_Query( [
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'post__not_in'   => $exclude,
        's'              => $keyword,
        'orderby'        => 'relevance',
    ] );

    if ( $title_query->have_posts() ) {
        $post = $title_query->posts[0];
        wp_reset_postdata();

        // Verify keyword actually appears in title OR content
        $title   = strtolower( get_the_title( $post->ID ) );
        $content = strtolower( wp_strip_all_tags( $post->post_content ) );
        $kw_low  = strtolower( $keyword );

        if ( mb_strpos( $title, $kw_low ) !== false || mb_strpos( $content, $kw_low ) !== false ) {
            return $post;
        }
    }
    wp_reset_postdata();

    // ---- Try 2: Search by each word of the keyword ----
    $words = array_filter( explode( ' ', $keyword ), function( $w ) { return mb_strlen( $w ) > 3; } );

    if ( ! empty( $words ) ) {
        foreach ( $words as $word ) {
            $word_query = new WP_Query( [
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'post__not_in'   => $exclude,
                's'              => $word,
                'orderby'        => 'relevance',
            ] );

            if ( $word_query->have_posts() ) {
                $post = $word_query->posts[0];
                wp_reset_postdata();

                $title = strtolower( get_the_title( $post->ID ) );
                if ( mb_strpos( $title, strtolower( $word ) ) !== false ) {
                    return $post;
                }
            }
            wp_reset_postdata();
        }
    }

    // ---- Try 3: Search by TAG matching keyword ----
    $tag = get_term_by( 'name', $keyword, 'post_tag' );
    if ( ! $tag ) {
        $tag = get_term_by( 'slug', sanitize_title( $keyword ), 'post_tag' );
    }

    if ( $tag && ! is_wp_error( $tag ) ) {
        $tag_query = new WP_Query( [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'post__not_in'   => $exclude,
            'tag_id'         => $tag->term_id,
        ] );

        if ( $tag_query->have_posts() ) {
            $post = $tag_query->posts[0];
            wp_reset_postdata();
            return $post;
        }
        wp_reset_postdata();
    }

    return null;
}

    /**
     * Insert links into content — SAFE from HTML comments, scripts, styles.
     */
    private static function insert_links_into_content( $content, $internal_links, $external_links ) {
        $used_anchors = [];
        $ext_count    = 0;

        // ===== 1. Insert internal links =====
        $internal_inserted = 0;

        foreach ( $internal_links as $link ) {
            $anchor = $link['anchor'];

            if ( in_array( strtolower( $anchor ), $used_anchors, true ) ) {
                continue;
            }

            // Try to find anchor in <p>
            $inserted = self::insert_link_by_anchor( $content, $anchor, $link, 'internal' );

            if ( $inserted ) {
                $used_anchors[] = strtolower( $anchor );
                $internal_inserted++;
            }
        }

        // ===== 2. Insert external links =====
        foreach ( $external_links as $link ) {
            $anchor = $link['anchor'];

            if ( in_array( strtolower( $anchor ), $used_anchors, true ) ) {
                continue;
            }

            $ext_count++;
            $rel = ( $ext_count === 1 ) ? 'noopener' : 'noopener nofollow';

            $inserted = self::insert_link_by_anchor( $content, $anchor, [
                'url'    => $link['url'],
                'anchor' => $anchor,
            ], 'external', $rel );

            if ( $inserted ) {
                $used_anchors[] = strtolower( $anchor );
            }
        }

        // ===== 3. Fallback: paragraph injection if none inserted =====
        if ( $internal_inserted === 0 && ! empty( $internal_links ) ) {
            $content = self::inject_internal_links_into_paragraphs( $content, $internal_links );
        }

        // ===== 4. Fallback: append external links if none inserted =====
        if ( $ext_count === 0 && ! empty( $external_links ) ) {
            $content = self::append_external_links_to_content( $content, $external_links );
        }

        return $content;
    }

    /**
     * Try to insert a link by finding the anchor text (safe from comments/scripts/a).
     * If anchor doesn't exist in <p>, inject it into a paragraph.
     */
    private static function insert_link_by_anchor( &$content, $anchor, $link, $type = 'internal', $rel = 'internal' ) {
        // Check if anchor exists in a safe <p> region
        $exists = self::anchor_exists_in_paragraph( $content, $anchor );

        if ( $exists ) {
            // Build replacement
            if ( $type === 'external' ) {
                $replacement = '<a href="' . esc_url( $link['url'] ) . '" target="_blank" rel="' . esc_attr( $rel ) . '">' . esc_html( $anchor ) . '</a>';
            } else {
                $replacement = '<a href="' . esc_url( $link['url'] ) . '" rel="internal">' . esc_html( $anchor ) . '</a>';
            }

            // Replace only first safe occurrence
            $content = self::replace_first_anchor_outside_tags( $content, $anchor, $replacement );
            return true;
        }

        // Anchor not found — inject at end of a random paragraph
        return self::inject_link_into_random_paragraph( $content, $anchor, $link, $type, $rel );
    }

    /**
     * Inject a link into a random <p> paragraph (when anchor not found).
     */
    private static function inject_link_into_random_paragraph( &$content, $anchor, $link, $type = 'internal', $rel = 'internal' ) {
        // Split by </p>
        $parts = preg_split( '/(<\/p>)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE );

        if ( count( $parts ) < 6 ) {
            return false; // Not enough paragraphs
        }

        // Find candidate paragraphs (mid-article preferred)
        $total_paras = 0;
        for ( $i = 0; $i < count( $parts ); $i += 2 ) { $total_paras++; }

        if ( $total_paras < 3 ) { return false; }

        // Pick a random paragraph in the second half
        $start_idx = (int) ( $total_paras / 3 );
        $end_idx   = $total_paras - 1;
        $para_idx  = wp_rand( $start_idx, $end_idx );

        // Get the actual position in $parts array
        $pos = $para_idx * 2;

        if ( ! isset( $parts[$pos - 1] ) ) { return false; }

        // Build link HTML
        if ( $type === 'external' ) {
            $link_html = '<a href="' . esc_url( $link['url'] ) . '" target="_blank" rel="' . esc_attr( $rel ) . '">' . esc_html( $anchor ) . '</a>';
        } else {
            $link_html = '<a href="' . esc_url( $link['url'] ) . '" rel="internal">' . esc_html( $anchor ) . '</a>';
        }

        // Append as a sentence
        $parts[$pos - 1] = rtrim( $parts[$pos - 1], '.' ) . '. ' 
                         . sprintf( 
                             /* translators: %s: link HTML */
                             esc_html__( 'For more on this, see %s.', 'sk-blogger' ), 
                             $link_html 
                           );

        $content = implode( '', $parts );
        return true;
    }

    /**
     * Check if anchor exists inside a <p> tag (safe location).
     */
    private static function anchor_exists_in_paragraph( $content, $anchor ) {
        $test_content = preg_replace( '/<!--.*?-->/s', '', $content );
        $test_content = preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', $test_content );
        $test_content = preg_replace( '/<style\b[^>]*>.*?<\/style>/is', '', $test_content );
        $test_content = preg_replace( '/<a\b[^>]*>.*?<\/a>/is', '', $test_content );

        $pattern = '/<p\b[^>]*>(?:(?!<\/p>).)*?\b' . preg_quote( $anchor, '/' ) . '\b(?:(?!<\/p>).)*?<\/p>/isu';

        return (bool) preg_match( $pattern, $test_content );
    }

    /**
     * Replace FIRST occurrence of anchor OUTSIDE comments, scripts, styles, and existing <a>.
     */
    private static function replace_first_anchor_outside_tags( $content, $anchor, $replacement ) {
        $pattern = '/(<!--.*?-->|<script\b[^>]*>.*?<\/script>|<style\b[^>]*>.*?<\/style>|<a\b[^>]*>.*?<\/a>)/is';

        $parts = preg_split( $pattern, $content, -1, PREG_SPLIT_DELIM_CAPTURE );

        if ( ! $parts ) {
            return $content;
        }

        $replaced = false;

        foreach ( $parts as $i => $part ) {
            if ( preg_match( '/^\s*(<!--|<script|<style|<a\b)/i', $part ) ) {
                continue;
            }

            if ( ! $replaced && mb_stripos( $part, $anchor ) !== false ) {
                $new_part = preg_replace(
                    '/' . preg_quote( $anchor, '/' ) . '/iu',
                    $replacement,
                    $part,
                    1,
                    $count
                );

                if ( $count > 0 ) {
                    $parts[ $i ] = $new_part;
                    $replaced = true;
                    break;
                }
            }
        }

        return implode( '', $parts );
    }

    /**
     * Fallback: Bulk-inject internal links at end of random paragraphs.
     */
    private static function inject_internal_links_into_paragraphs( $content, $links ) {
        $parts = preg_split( '/(<\/p>)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE );

        if ( count( $parts ) < 6 ) {
            // Append "Related Reading" section
            $extra = "\n<p><strong>" . esc_html__( 'Related Reading:', 'sk-blogger' ) . '</strong> ';
            $anchors = [];
            foreach ( $links as $link ) {
                $anchors[] = '<a href="' . esc_url( $link['url'] ) . '" rel="internal">' . esc_html( $link['anchor'] ) . '</a>';
            }
            $extra .= implode( ', ', $anchors ) . '.</p>';
            return $content . $extra;
        }

        $total_paras = 0;
        for ( $i = 0; $i < count( $parts ); $i += 2 ) { $total_paras++; }

        $positions = [];
        $step = max( 1, (int) ( $total_paras / ( count( $links ) + 1 ) ) );
        for ( $i = $step; $i < $total_paras; $i += $step ) {
            $positions[] = $i * 2;
        }

        $link_index = 0;
        foreach ( $positions as $pos ) {
            if ( ! isset( $parts[$pos] ) || ! isset( $links[$link_index] ) ) { break; }

            $link  = $links[$link_index];
            $extra = ' <a href="' . esc_url( $link['url'] ) . '" rel="internal">' . esc_html( $link['anchor'] ) . '</a>.';

            $parts[$pos - 1] = rtrim( $parts[$pos - 1], '.' ) . $extra;
            $link_index++;
        }

        return implode( '', $parts );
    }

    /**
     * Fallback: Append external links at the end.
     */
    private static function append_external_links_to_content( $content, $links ) {
        $extra = "\n<p><em>" . esc_html__( 'Sources:', 'sk-blogger' ) . ' ';
        $anchors = [];
        foreach ( $links as $i => $link ) {
            $rel = ( $i === 0 ) ? 'noopener' : 'noopener nofollow';
            $anchors[] = '<a href="' . esc_url( $link['url'] ) . '" target="_blank" rel="' . esc_attr( $rel ) . '">' . esc_html( $link['anchor'] ) . '</a>';
        }
        $extra .= implode( ', ', $anchors ) . '.</em></p>';
        return $content . $extra;
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

    /* ================================================================
     * JSON PARSING
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
            $depth = 0; $in_string = false; $escape = false; $end = $first;
            $len = strlen( $raw );
            for ( $i = $first; $i < $len; $i++ ) {
                $c = $raw[$i];
                if ( $escape ) { $escape = false; continue; }
                if ( $c === '\\' ) { $escape = true; continue; }
                if ( $c === '"' ) { $in_string = ! $in_string; continue; }
                if ( $in_string ) { continue; }
                if ( $c === '{' ) { $depth++; }
                elseif ( $c === '}' ) { $depth--; if ( $depth === 0 ) { $end = $i; break; } }
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