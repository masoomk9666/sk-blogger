<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SK_SEO {

    public static function apply( $post_id, $title, $content, $keywords = '' ) {
        if ( ! get_option( 'sk_seo_enabled' ) ) { return; }

        $meta_title = self::truncate( $title, 60 );
        $meta_desc  = self::truncate( wp_strip_all_tags( $content ), 155 );
        $focus_kw   = trim( explode( ',', $keywords )[0] ?? '' );

        update_post_meta( $post_id, '_yoast_wpseo_title',    $meta_title );
        update_post_meta( $post_id, '_yoast_wpseo_metadesc', $meta_desc );
        update_post_meta( $post_id, '_yoast_wpseo_focuskw',  $focus_kw );

        update_post_meta( $post_id, 'rank_math_title',       $meta_title );
        update_post_meta( $post_id, 'rank_math_description', $meta_desc );
        update_post_meta( $post_id, 'rank_math_focus_keyword', $focus_kw );

        update_post_meta( $post_id, '_aioseo_title',       $meta_title );
        update_post_meta( $post_id, '_aioseo_description', $meta_desc );
        update_post_meta( $post_id, '_aioseo_keywords',    $focus_kw );
    }

    private static function truncate( $text, $len ) {
        $text = trim( preg_replace( '/\s+/', ' ', $text ) );
        if ( mb_strlen( $text ) <= $len ) { return $text; }
        return mb_substr( $text, 0, $len - 3 ) . '...';
    }
}