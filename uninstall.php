<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }

global $wpdb;

$tables = [ 'sk_queue', 'sk_logs', 'sk_topics' ];
foreach ( $tables as $t ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$t}" );
}

$options = [
    'sk_ai_provider', 'sk_openai_key', 'sk_openai_model',
    'sk_gemini_key', 'sk_gemini_model', 'sk_claude_key', 'sk_claude_model',
    'sk_image_provider', 'sk_default_status', 'sk_default_author',
    'sk_default_category', 'sk_post_length', 'sk_tone', 'sk_language',
    'sk_include_images', 'sk_image_count', 'sk_seo_enabled',
    'sk_queue_batch', 'sk_max_attempts', 'sk_posts_per_day',
    'sk_blogger_version',
];
foreach ( $options as $o ) { delete_option( $o ); }

wp_clear_scheduled_hook( 'sk_blogger_process_queue' );
wp_clear_scheduled_hook( 'sk_blogger_daily_cleanup' );
wp_clear_scheduled_hook( 'sk_blogger_auto_topics' );