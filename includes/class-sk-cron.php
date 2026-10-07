<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SK_Cron {

    const HOOK_PROCESS  = 'sk_blogger_process_queue';
    const HOOK_DAILY    = 'sk_blogger_daily_cleanup';
    const HOOK_TOPICS   = 'sk_blogger_auto_topics';
    const HOOK_TRENDING = 'sk_blogger_process_trending';

    public static function schedule_events() {
        self::add_schedules( [] );

        if ( ! wp_next_scheduled( self::HOOK_PROCESS ) ) {
            $result = wp_schedule_event( time() + 60, 'sk_every_five_minutes', self::HOOK_PROCESS );
            if ( $result === false || is_wp_error( $result ) ) {
                wp_schedule_event( time() + 60, 'hourly', self::HOOK_PROCESS );
            }
        }

        if ( ! wp_next_scheduled( self::HOOK_DAILY ) ) {
            wp_schedule_event( time() + 3600, 'daily', self::HOOK_DAILY );
        }

        if ( ! wp_next_scheduled( self::HOOK_TOPICS ) ) {
            wp_schedule_event( time() + 7200, 'daily', self::HOOK_TOPICS );
        }

        if ( ! wp_next_scheduled( self::HOOK_TRENDING ) ) {
            $hour     = (int) get_option( 'sk_trending_hour', 9 );
            $next_run = strtotime( "tomorrow {$hour}:00:00" );
            if ( $next_run < time() ) {
                $next_run = strtotime( "today {$hour}:00:00" );
                if ( $next_run < time() ) {
                    $next_run = time() + 300;
                }
            }
            wp_schedule_event( $next_run, 'daily', self::HOOK_TRENDING );
        }
    }

    public static function clear_events() {
        foreach ( [ self::HOOK_PROCESS, self::HOOK_DAILY, self::HOOK_TOPICS, self::HOOK_TRENDING ] as $hook ) {
            $ts = wp_next_scheduled( $hook );
            if ( $ts ) { wp_unschedule_event( $ts, $hook ); }
            wp_clear_scheduled_hook( $hook );
        }
    }

    public static function init() {
        add_filter( 'cron_schedules', [ __CLASS__, 'add_schedules' ] );

        add_action( self::HOOK_PROCESS,  [ 'SK_Queue', 'process' ] );
        add_action( self::HOOK_DAILY,    [ __CLASS__, 'daily_cleanup' ] );
        add_action( self::HOOK_TOPICS,   [ __CLASS__, 'auto_generate_topics' ] );
        add_action( self::HOOK_TRENDING, [ __CLASS__, 'process_daily_trending' ] );

        add_action( 'admin_init', [ __CLASS__, 'verify_schedule' ] );
    }

    public static function verify_schedule() {
        if ( ! wp_next_scheduled( self::HOOK_PROCESS ) ) {
            self::schedule_events();
        }
    }

    public static function add_schedules( $schedules ) {
        if ( ! is_array( $schedules ) ) { $schedules = []; }

        $schedules['sk_every_five_minutes'] = [
            'interval' => 300,
            'display'  => __( 'Every 5 Minutes (SK Blogger)', 'sk-blogger' ),
        ];
        $schedules['sk_every_fifteen_minutes'] = [
            'interval' => 900,
            'display'  => __( 'Every 15 Minutes (SK Blogger)', 'sk-blogger' ),
        ];
        return $schedules;
    }

    public static function daily_cleanup() {
        global $wpdb;
        $table = SK_DB::table( 'logs' );
        $wpdb->query( "DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)" );

        $trending_table = SK_DB::table( 'trending' );
        $wpdb->query( "DELETE FROM {$trending_table} WHERE trend_date < DATE_SUB(NOW(), INTERVAL 60 DAY)" );

        SK_Logger::info( 'Daily cleanup executed.', 'cron' );
    }

    public static function auto_generate_topics() {
        if ( ! get_option( 'sk_auto_topics_enabled', 0 ) ) { return; }

        $counts    = SK_DB::queue_counts();
        $pending   = (int) $counts['pending'];
        $threshold = (int) get_option( 'sk_auto_topics_threshold', 3 );

        if ( $pending >= $threshold ) { return; }

        $niche    = get_option( 'sk_auto_topics_niche', '' );
        $keywords = get_option( 'sk_auto_topics_keywords', '' );
        $count    = (int) get_option( 'sk_auto_topics_count', 10 );

        if ( empty( $niche ) ) { return; }
        if ( ! class_exists( 'SK_AI' ) ) { return; }

        $topics = SK_AI::generate_topics( $count, $niche, $keywords );
        if ( empty( $topics ) ) { return; }

        $added = 0;
        foreach ( $topics as $topic ) {
            SK_DB::enqueue( $topic, $keywords, 5 );
            $added++;
        }

        SK_Logger::info( "Auto-topics: Added {$added} topics.", 'cron' );
    }

    /**
     * Daily trending processor — pick ONE keyword per category, add to queue.
     */
    public static function process_daily_trending() {
        if ( ! get_option( 'sk_trending_enabled', 1 ) ) {
            SK_Logger::info( 'Trending disabled. Skipping.', 'cron' );
            return;
        }

        SK_Logger::info( 'Daily trending processor started.', 'cron' );

        $today      = current_time( 'Y-m-d' );
        $categories = SK_DB::get_categories_with_trending( $today );

        if ( empty( $categories ) ) {
            SK_Logger::info( "No trending keywords for {$today}.", 'cron' );
            return;
        }

        $added   = 0;
        $skipped = 0;

        foreach ( $categories as $cat_id ) {
            $cat_id = (int) $cat_id;

            $trending = SK_DB::get_today_trending_for_category( $cat_id, $today );

            if ( empty( $trending ) ) { $skipped++; continue; }

            $item = $trending[0];

            global $wpdb;
            $queue_table = SK_DB::table( 'queue' );
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$queue_table} 
                 WHERE topic = %s AND category_id = %d AND status IN ('pending', 'processing')",
                $item->keyword, $cat_id
            ) );

            if ( $exists ) {
                SK_DB::mark_trending_used( $item->id );
                $skipped++;
                continue;
            }

            // Build combined keywords — trending keyword as primary
            $primary_kw  = $item->keyword;
            $extra_kws   = ! empty( $item->keywords ) ? $item->keywords : '';
            $combined_kw = $extra_kws ? $primary_kw . ', ' . $extra_kws : $primary_kw;

            $queue_id = SK_DB::enqueue( $item->keyword, $combined_kw, 1, null, $cat_id );

            SK_DB::mark_trending_used( $item->id );

            $cat = get_category( $cat_id );
            $cat_name = $cat ? $cat->name : "Category #{$cat_id}";

            $added++;
            SK_Logger::info( "Trending queued: '{$item->keyword}' → {$cat_name} (queue #{$queue_id})", 'cron' );
        }

        SK_Logger::info( "Trending done: {$added} added, {$skipped} skipped.", 'cron' );
    }
}