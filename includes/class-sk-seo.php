<?php
// if ( ! defined( 'ABSPATH' ) ) { exit; }

// class SK_SEO {

//     /**
//      * Apply all SEO enhancements to a post.
//      */
//     public static function apply( $post_id, $title, $content, $keywords = '' ) {
//         // Meta title & description (always apply)
//         $meta_title = self::build_meta_title( $title, $keywords );
//         $meta_desc  = self::build_meta_description( $content, $keywords );
//         $focus_kw   = self::get_primary_keyword( $keywords );

//         // Save to Yoast / RankMath / AIOSEO
//         if ( get_option( 'sk_seo_enabled', 1 ) ) {
//             self::save_meta( $post_id, $meta_title, $meta_desc, $focus_kw );
//         }

//         // Schema markup
//         if ( get_option( 'sk_schema_enabled', 1 ) ) {
//             self::add_schema_markup( $post_id, $title, $content, $meta_desc );
//         }
//     }

//     /* ============================================================
//      * 1. META TITLE (50-60 chars)
//      * ============================================================ */

//     private static function build_meta_title( $title, $keywords = '' ) {
//         $title = wp_strip_all_tags( $title );
//         $title = trim( preg_replace( '/\s+/', ' ', $title ) );

//         $len = mb_strlen( $title );

//         // Target: 50-60 chars
//         if ( $len >= 50 && $len <= 60 ) {
//             return $title;
//         }

//         // If too long: trim to 57 + "..."
//         if ( $len > 60 ) {
//             $trimmed = mb_substr( $title, 0, 57 );
//             $last_space = mb_strrpos( $trimmed, ' ' );
//             if ( $last_space !== false ) {
//                 $trimmed = mb_substr( $trimmed, 0, $last_space );
//             }
//             return $trimmed . '...';
//         }

//         // If too short: append primary keyword or site name
//         $site_name = get_bloginfo( 'name' );
//         $primary   = self::get_primary_keyword( $keywords );

//         $candidates = [];
//         if ( $primary && mb_stripos( $title, $primary ) === false ) {
//             $candidates[] = $title . ' - ' . $primary;
//         }
//         $candidates[] = $title . ' | ' . $site_name;

//         foreach ( $candidates as $candidate ) {
//             $c_len = mb_strlen( $candidate );
//             if ( $c_len >= 50 && $c_len <= 60 ) {
//                 return $candidate;
//             }
//         }

//         // Fallback: shortest candidate that fits
//         foreach ( $candidates as $candidate ) {
//             if ( mb_strlen( $candidate ) <= 60 ) {
//                 return $candidate;
//             }
//         }

//         return $title;
//     }

//     /* ============================================================
//      * 2. META DESCRIPTION (145-160 chars)
//      * ============================================================ */

//     private static function build_meta_description( $content, $keywords = '' ) {
//         $text = wp_strip_all_tags( $content );
//         $text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
//         $text = trim( preg_replace( '/\s+/', ' ', $text ) );

//         // If short enough, pad with keyword context
//         $len = mb_strlen( $text );

//         if ( $len >= 145 && $len <= 160 ) {
//             return $text;
//         }

//         if ( $len > 160 ) {
//             // Trim to 157 + "..."
//             $trimmed = mb_substr( $text, 0, 157 );
//             $last_punct = max(
//                 mb_strrpos( $trimmed, '.' ),
//                 mb_strrpos( $trimmed, '!' ),
//                 mb_strrpos( $trimmed, '?' )
//             );
//             if ( $last_punct !== false && $last_punct > 100 ) {
//                 return mb_substr( $trimmed, 0, $last_punct + 1 );
//             }
//             $last_space = mb_strrpos( $trimmed, ' ' );
//             if ( $last_space !== false ) {
//                 $trimmed = mb_substr( $trimmed, 0, $last_space );
//             }
//             return $trimmed . '...';
//         }

//         // Too short: append keyword context
//         $primary = self::get_primary_keyword( $keywords );
//         $site    = get_bloginfo( 'name' );

//         $suffix = '';
//         if ( $primary ) {
//             $suffix = " Learn more about {$primary} at {$site}.";
//         } else {
//             $suffix = " Read the complete guide at {$site}.";
//         }

//         $full = $text . $suffix;
//         if ( mb_strlen( $full ) <= 160 ) {
//             return $full;
//         }

//         return $text;
//     }

//     /* ============================================================
//      * 3. KEYWORD EXTRACTION
//      * ============================================================ */

//     public static function get_primary_keyword( $keywords = '' ) {
//         if ( empty( $keywords ) ) { return ''; }
//         $parts = array_map( 'trim', explode( ',', $keywords ) );
//         return $parts[0] ?? '';
//     }

//     public static function get_all_keywords( $keywords = '' ) {
//         if ( empty( $keywords ) ) { return []; }
//         return array_filter( array_map( 'trim', explode( ',', $keywords ) ) );
//     }

//     /* ============================================================
//      * 4. SAVE META TO SEO PLUGINS
//      * ============================================================ */

//     private static function save_meta( $post_id, $meta_title, $meta_desc, $focus_kw ) {
//         // Yoast SEO
//         update_post_meta( $post_id, '_yoast_wpseo_title',    $meta_title );
//         update_post_meta( $post_id, '_yoast_wpseo_metadesc', $meta_desc );
//         update_post_meta( $post_id, '_yoast_wpseo_focuskw',  $focus_kw );

//         // RankMath
//         update_post_meta( $post_id, 'rank_math_title',         $meta_title );
//         update_post_meta( $post_id, 'rank_math_description',   $meta_desc );
//         update_post_meta( $post_id, 'rank_math_focus_keyword', $focus_kw );

//         // All in One SEO
//         update_post_meta( $post_id, '_aioseo_title',       $meta_title );
//         update_post_meta( $post_id, '_aioseo_description', $meta_desc );
//         update_post_meta( $post_id, '_aioseo_keywords',    $focus_kw );

//         // Native fallback
//         update_post_meta( $post_id, '_sk_meta_title',       $meta_title );
//         update_post_meta( $post_id, '_sk_meta_description', $meta_desc );
//         update_post_meta( $post_id, '_sk_focus_keyword',    $focus_kw );
//     }

//     /* ============================================================
//      * 5. SCHEMA MARKUP (JSON-LD)
//      * ============================================================ */

//     private static function add_schema_markup( $post_id, $title, $content, $meta_desc ) {
//         update_post_meta( $post_id, '_sk_schema_enabled', 1 );
//     }

//     /**
//      * Output JSON-LD schema on frontend.
//      */
//     public static function output_schema() {
//         if ( ! is_singular( 'post' ) ) { return; }
//         if ( ! get_option( 'sk_schema_enabled', 1 ) ) { return; }

//         $post_id = get_the_ID();
//         $post    = get_post( $post_id );

//         $schema = [
//             '@context'         => 'https://schema.org',
//             '@type'            => 'BlogPosting',
//             'headline'         => get_the_title( $post_id ),
//             'description'      => get_post_meta( $post_id, '_sk_meta_description', true ) ?: wp_trim_words( $post->post_content, 25 ),
//             'datePublished'    => get_the_date( 'c', $post_id ),
//             'dateModified'     => get_the_modified_date( 'c', $post_id ),
//             'author'           => [
//                 '@type' => 'Person',
//                 'name'  => get_the_author_meta( 'display_name', $post->post_author ),
//             ],
//             'publisher'        => [
//                 '@type' => 'Organization',
//                 'name'  => get_bloginfo( 'name' ),
//             ],
//             'mainEntityOfPage' => [
//                 '@type' => 'WebPage',
//                 '@id'   => get_permalink( $post_id ),
//             ],
//         ];

//         if ( has_post_thumbnail( $post_id ) ) {
//             $schema['image'] = get_the_post_thumbnail_url( $post_id, 'full' );
//         }

//         echo "\n<!-- SK Blogger Schema -->\n";
//         echo '<script type="application/ld+json">' 
//            . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) 
//            . '</script>' . "\n";
//     }
// }

if ( ! defined( 'ABSPATH' ) ) { exit; }

class SK_SEO {

    /**
     * Apply all SEO enhancements to a post.
     */
    public static function apply( $post_id, $title, $content, $keywords = '' ) {
        // Meta title & description (always apply)
        $meta_title = self::build_meta_title( $title, $keywords );
        $meta_desc  = self::build_meta_description( $content, $keywords );
        $focus_kw   = self::get_primary_keyword( $keywords );

        // Save to Yoast / RankMath / AIOSEO
        if ( get_option( 'sk_seo_enabled', 1 ) ) {
            self::save_meta( $post_id, $meta_title, $meta_desc, $focus_kw );
        }

        // Extract and save FAQ pairs (NEW — for AEO)
        if ( get_option( 'sk_schema_enabled', 1 ) ) {
            $faqs = self::extract_faq_from_content( $content );
            if ( ! empty( $faqs ) ) {
                update_post_meta( $post_id, '_sk_faq_pairs', $faqs );
            }
        }

        // Schema markup
        if ( get_option( 'sk_schema_enabled', 1 ) ) {
            self::add_schema_markup( $post_id, $title, $content, $meta_desc );
        }
    }

    /* ============================================================
     * 1. META TITLE (50-60 chars)
     * ============================================================ */

    private static function build_meta_title( $title, $keywords = '' ) {
        $title = wp_strip_all_tags( $title );
        $title = trim( preg_replace( '/\s+/', ' ', $title ) );

        $len = mb_strlen( $title );

        if ( $len >= 50 && $len <= 60 ) {
            return $title;
        }

        if ( $len > 60 ) {
            $trimmed = mb_substr( $title, 0, 57 );
            $last_space = mb_strrpos( $trimmed, ' ' );
            if ( $last_space !== false ) {
                $trimmed = mb_substr( $trimmed, 0, $last_space );
            }
            return $trimmed . '...';
        }

        $site_name = get_bloginfo( 'name' );
        $primary   = self::get_primary_keyword( $keywords );

        $candidates = [];
        if ( $primary && mb_stripos( $title, $primary ) === false ) {
            $candidates[] = $title . ' - ' . $primary;
        }
        $candidates[] = $title . ' | ' . $site_name;

        foreach ( $candidates as $candidate ) {
            $c_len = mb_strlen( $candidate );
            if ( $c_len >= 50 && $c_len <= 60 ) {
                return $candidate;
            }
        }

        foreach ( $candidates as $candidate ) {
            if ( mb_strlen( $candidate ) <= 60 ) {
                return $candidate;
            }
        }

        return $title;
    }

    /* ============================================================
     * 2. META DESCRIPTION (145-160 chars)
     * ============================================================ */

    private static function build_meta_description( $content, $keywords = '' ) {
        $text = wp_strip_all_tags( $content );
        $text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
        $text = trim( preg_replace( '/\s+/', ' ', $text ) );

        $len = mb_strlen( $text );

        if ( $len >= 145 && $len <= 160 ) {
            return $text;
        }

        if ( $len > 160 ) {
            $trimmed = mb_substr( $text, 0, 157 );
            $last_punct = max(
                mb_strrpos( $trimmed, '.' ),
                mb_strrpos( $trimmed, '!' ),
                mb_strrpos( $trimmed, '?' )
            );
            if ( $last_punct !== false && $last_punct > 100 ) {
                return mb_substr( $trimmed, 0, $last_punct + 1 );
            }
            $last_space = mb_strrpos( $trimmed, ' ' );
            if ( $last_space !== false ) {
                $trimmed = mb_substr( $trimmed, 0, $last_space );
            }
            return $trimmed . '...';
        }

        $primary = self::get_primary_keyword( $keywords );
        $site    = get_bloginfo( 'name' );

        $suffix = '';
        if ( $primary ) {
            $suffix = " Learn more about {$primary} at {$site}.";
        } else {
            $suffix = " Read the complete guide at {$site}.";
        }

        $full = $text . $suffix;
        if ( mb_strlen( $full ) <= 160 ) {
            return $full;
        }

        return $text;
    }

    /* ============================================================
     * 3. KEYWORD EXTRACTION
     * ============================================================ */

    public static function get_primary_keyword( $keywords = '' ) {
        if ( empty( $keywords ) ) { return ''; }
        $parts = array_map( 'trim', explode( ',', $keywords ) );
        return $parts[0] ?? '';
    }

    public static function get_all_keywords( $keywords = '' ) {
        if ( empty( $keywords ) ) { return []; }
        return array_filter( array_map( 'trim', explode( ',', $keywords ) ) );
    }

    /* ============================================================
     * 4. SAVE META TO SEO PLUGINS
     * ============================================================ */

    private static function save_meta( $post_id, $meta_title, $meta_desc, $focus_kw ) {
        // Yoast SEO
        update_post_meta( $post_id, '_yoast_wpseo_title',    $meta_title );
        update_post_meta( $post_id, '_yoast_wpseo_metadesc', $meta_desc );
        update_post_meta( $post_id, '_yoast_wpseo_focuskw',  $focus_kw );

        // RankMath
        update_post_meta( $post_id, 'rank_math_title',         $meta_title );
        update_post_meta( $post_id, 'rank_math_description',   $meta_desc );
        update_post_meta( $post_id, 'rank_math_focus_keyword', $focus_kw );

        // All in One SEO
        update_post_meta( $post_id, '_aioseo_title',       $meta_title );
        update_post_meta( $post_id, '_aioseo_description', $meta_desc );
        update_post_meta( $post_id, '_aioseo_keywords',    $focus_kw );

        // Native fallback
        update_post_meta( $post_id, '_sk_meta_title',       $meta_title );
        update_post_meta( $post_id, '_sk_meta_description', $meta_desc );
        update_post_meta( $post_id, '_sk_focus_keyword',    $focus_kw );
    }

    /* ============================================================
     * 5. FAQ EXTRACTION (NEW — AEO)
     * ============================================================ */

    /**
     * Extract FAQ pairs from post content.
     * Looks for patterns like:
     *   <h3>Question here?</h3>
     *   <p>Answer here.</p>
     *
     * Or specifically in a FAQ section:
     *   <h2>Frequently Asked Questions</h2>
     *   <h3>Q1?</h3><p>A1</p>
     */
    public static function extract_faq_from_content( $content ) {
        $faqs = [];

        // Strategy 1: Look for questions in H3 (ending with ?)
        if ( preg_match_all( '/<h3[^>]*>(.+?\?)<\/h3>\s*(?:<p[^>]*>(.+?)<\/p>)?/is', $content, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $m ) {
                $question = trim( wp_strip_all_tags( $m[1] ) );
                $answer   = isset( $m[2] ) ? trim( wp_strip_all_tags( $m[2] ) ) : '';

                if ( ! empty( $question ) && ! empty( $answer ) && strlen( $answer ) > 20 ) {
                    $faqs[] = [
                        'question' => $question,
                        'answer'   => $answer,
                    ];
                }

                if ( count( $faqs ) >= 8 ) { break; }
            }
        }

        // Strategy 2: Look for Q: / A: patterns
        if ( empty( $faqs ) && preg_match_all( '/(?:Q:|Question:)\s*(.+?)\s*(?:\n|<br\s*\/?>|\r\n)\s*(?:A:|Answer:)\s*(.+?)(?=\n\s*(?:Q:|Question:)|$)/is', $content, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $m ) {
                $question = trim( wp_strip_all_tags( $m[1] ) );
                $answer   = trim( wp_strip_all_tags( $m[2] ) );

                if ( ! empty( $question ) && ! empty( $answer ) ) {
                    $faqs[] = [
                        'question' => $question,
                        'answer'   => $answer,
                    ];
                }

                if ( count( $faqs ) >= 8 ) { break; }
            }
        }

        // Strategy 3: Look for FAQ section (H2 with "FAQ" or "Questions")
        if ( empty( $faqs ) && preg_match( '/<h2[^>]*>(?:[^<]*)(?:FAQ|Frequently Asked Questions|Common Questions)(?:[^<]*)<\/h2>(.*?)(?=<h2|$)/is', $content, $section ) ) {
            $faq_section = $section[1];

            // Extract H3 + following p
            if ( preg_match_all( '/<h3[^>]*>(.+?)<\/h3>\s*<p[^>]*>(.+?)<\/p>/is', $faq_section, $matches, PREG_SET_ORDER ) ) {
                foreach ( $matches as $m ) {
                    $question = trim( wp_strip_all_tags( $m[1] ) );
                    $answer   = trim( wp_strip_all_tags( $m[2] ) );

                    if ( ! empty( $question ) && ! empty( $answer ) ) {
                        $faqs[] = [
                            'question' => $question,
                            'answer'   => $answer,
                        ];
                    }

                    if ( count( $faqs ) >= 8 ) { break; }
                }
            }
        }

        return $faqs;
    }

    /* ============================================================
     * 6. SCHEMA MARKUP (JSON-LD)
     * ============================================================ */

    private static function add_schema_markup( $post_id, $title, $content, $meta_desc ) {
        update_post_meta( $post_id, '_sk_schema_enabled', 1 );
    }

    /**
     * Output JSON-LD schemas on frontend.
     * Outputs: BlogPosting + FAQPage (if FAQ pairs exist)
     */
    public static function output_schema() {
        if ( ! is_singular( 'post' ) ) { return; }
        if ( ! get_option( 'sk_schema_enabled', 1 ) ) { return; }

        $post_id = get_the_ID();
        $post    = get_post( $post_id );

        // ===== BlogPosting Schema =====
        $article_schema = [
            '@context'         => 'https://schema.org',
            '@type'            => 'BlogPosting',
            'headline'         => get_the_title( $post_id ),
            'description'      => get_post_meta( $post_id, '_sk_meta_description', true ) ?: wp_trim_words( $post->post_content, 25 ),
            'datePublished'    => get_the_date( 'c', $post_id ),
            'dateModified'     => get_the_modified_date( 'c', $post_id ),
            'author'           => [
                '@type' => 'Person',
                'name'  => get_the_author_meta( 'display_name', $post->post_author ),
            ],
            'publisher'        => [
                '@type' => 'Organization',
                'name'  => get_bloginfo( 'name' ),
            ],
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id'   => get_permalink( $post_id ),
            ],
        ];

        if ( has_post_thumbnail( $post_id ) ) {
            $article_schema['image'] = get_the_post_thumbnail_url( $post_id, 'full' );
        }

        echo "\n<!-- SK Blogger — BlogPosting Schema -->\n";
        echo '<script type="application/ld+json">'
           . wp_json_encode( $article_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
           . '</script>' . "\n";

        // ===== FAQPage Schema (NEW — AEO) =====
        $faqs = get_post_meta( $post_id, '_sk_faq_pairs', true );
        if ( ! empty( $faqs ) && is_array( $faqs ) ) {
            $faq_entities = [];
            foreach ( $faqs as $faq ) {
                if ( empty( $faq['question'] ) || empty( $faq['answer'] ) ) { continue; }

                $faq_entities[] = [
                    '@type'          => 'Question',
                    'name'           => $faq['question'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text'  => $faq['answer'],
                    ],
                ];
            }

            if ( ! empty( $faq_entities ) ) {
                $faq_schema = [
                    '@context'   => 'https://schema.org',
                    '@type'      => 'FAQPage',
                    'mainEntity' => $faq_entities,
                ];

                echo "\n<!-- SK Blogger — FAQPage Schema (AEO) -->\n";
                echo '<script type="application/ld+json">'
                   . wp_json_encode( $faq_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
                   . '</script>' . "\n";
            }
        }
    }
}