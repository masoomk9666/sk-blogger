<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SK_Activator {

    public static function activate() {
        self::create_tables();
        self::set_defaults();
        SK_Cron::schedule_events();
        flush_rewrite_rules();
        update_option( 'sk_blogger_version', SK_BLOGGER_VERSION );
    }

    private static function create_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $prefix  = $wpdb->prefix . 'sk_';

        $queue = "CREATE TABLE {$prefix}queue (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            topic TEXT NOT NULL,
            keywords TEXT NULL,
            category_id BIGINT UNSIGNED NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            priority INT NOT NULL DEFAULT 5,
            attempts INT NOT NULL DEFAULT 0,
            scheduled_at DATETIME NOT NULL,
            post_id BIGINT UNSIGNED NULL,
            error TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status_idx (status),
            KEY sched_idx (scheduled_at),
            KEY cat_idx (category_id)
        ) $charset;";

        $logs = "CREATE TABLE {$prefix}logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            level VARCHAR(20) NOT NULL DEFAULT 'info',
            context VARCHAR(64) NULL,
            message TEXT NOT NULL,
            meta LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY level_idx (level),
            KEY ctx_idx (context)
        ) $charset;";

        $topics = "CREATE TABLE {$prefix}topics (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title TEXT NOT NULL,
            keywords TEXT NULL,
            category_id BIGINT UNSIGNED NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'idea',
            used_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status_idx (status)
        ) $charset;";

        $trending = "CREATE TABLE {$prefix}trending (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            keyword TEXT NOT NULL,
            keywords TEXT NULL,
            category_id BIGINT UNSIGNED NOT NULL,
            trend_date DATE NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            used_at DATETIME NULL,
            post_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY cat_date_idx (category_id, trend_date),
            KEY status_idx (status),
            KEY date_idx (trend_date)
        ) $charset;";

        dbDelta( $queue );
        dbDelta( $logs );
        dbDelta( $topics );
        dbDelta( $trending );
    }

    private static function set_defaults() {
        $defaults = [
            'sk_ai_provider'      => 'gemini',
            'sk_openai_key'       => '',
            'sk_openai_model'     => 'gpt-4o-mini',
            'sk_gemini_key'       => '',
            'sk_gemini_model'     => 'gemini-3.5-flash',
            'sk_claude_key'       => '',
            'sk_claude_model'     => 'claude-3-5-sonnet-20241022',
            'sk_image_provider'   => 'pollinations',
            'sk_default_status'   => 'draft',
            'sk_default_author'   => get_current_user_id(),
            'sk_default_category' => 1,
            'sk_post_length'      => 'medium',
            'sk_tone'             => 'professional',
            'sk_language'         => 'en',
            'sk_include_images'   => 1,
            'sk_image_count'      => 1,
            'sk_seo_enabled'      => 1,
            'sk_schema_enabled'   => 1,
            'sk_queue_batch'      => 1,
            'sk_max_attempts'     => 3,
            'sk_posts_per_day'    => 5,
            'sk_trending_enabled' => 1,
            'sk_trending_hour'    => 9,
        ];
        foreach ( $defaults as $k => $v ) {
            if ( get_option( $k ) === false ) { add_option( $k, $v ); }
        }
    }
}