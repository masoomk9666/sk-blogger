<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SK_Cron {

    const HOOK_PROCESS = 'sk_blogger_process_queue';
    const HOOK_DAILY   = 'sk_blogger_daily_cleanup';
    const HOOK_TOPICS  = 'sk_blogger_auto_topics';

    public static function schedule_events() {
        // CRITICAL: Register custom intervals BEFORE scheduling
        // WordPress needs these registered to accept 'sk_every_five_minutes'
        self::add_schedules( [] );

        // Schedule PROCESS hook (every 5 minutes)
        if ( ! wp_next_scheduled( self::HOOK_PROCESS ) ) {
            $result = wp_schedule_event( time() + 60, 'sk_every_five_minutes', self::HOOK_PROCESS );
            
            // Fallback: if custom interval failed, use WordPress built-in 'hourly'
            if ( $result === false || is_wp_error( $result ) ) {
                SK_Logger::warn( 'Custom 5-min interval failed, using hourly fallback.', 'cron' );
                wp_schedule_event( time() + 60, 'hourly', self::HOOK_PROCESS );
            } else {
                SK_Logger::info( 'Process queue scheduled every 5 minutes.', 'cron' );
            }
        }

        // Schedule DAILY cleanup hook
        if ( ! wp_next_scheduled( self::HOOK_DAILY ) ) {
            wp_schedule_event( time() + 3600, 'daily', self::HOOK_DAILY );
        }

        // Schedule TOPICS auto-generation hook
        if ( ! wp_next_scheduled( self::HOOK_TOPICS ) ) {
            wp_schedule_event( time() + 7200, 'daily', self::HOOK_TOPICS );
        }
    }

    public static function clear_events() {
        foreach ( [ self::HOOK_PROCESS, self::HOOK_DAILY, self::HOOK_TOPICS ] as $hook ) {
            $ts = wp_next_scheduled( $hook );
            if ( $ts ) { wp_unschedule_event( $ts, $hook ); }
            wp_clear_scheduled_hook( $hook );
        }
    }

    public static function init() {
        add_filter( 'cron_schedules', [ __CLASS__, 'add_schedules' ] );
        
        add_action( self::HOOK_PROCESS, [ 'SK_Queue', 'process' ] );
        add_action( self::HOOK_DAILY,   [ __CLASS__, 'daily_cleanup' ] );
        add_action( self::HOOK_TOPICS,  [ __CLASS__, 'auto_generate_topics' ] );

        // Fallback: verify cron is scheduled on every admin page load
        // If missing (e.g. after plugin update), re-schedule it
        add_action( 'admin_init', [ __CLASS__, 'verify_schedule' ] );
    }

    /**
     * Self-healing: If cron hook is missing, re-schedule it.
     * This runs on admin_init so any admin visit fixes the problem.
     */
    public static function verify_schedule() {
        if ( ! wp_next_scheduled( self::HOOK_PROCESS ) ) {
            SK_Logger::warn( 'Process hook was missing, re-scheduling now.', 'cron' );
            self::schedule_events();
        }
    }

    public static function add_schedules( $schedules ) {
        // Guard: $schedules may be empty array when called directly
        if ( ! is_array( $schedules ) ) {
            $schedules = [];
        }

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
        SK_Logger::info( 'Daily cleanup executed.', 'cron' );
    }

    public static function auto_generate_topics() {
        $topics = SK_DB::get_topics( 5 );
        foreach ( $topics as $t ) {
            if ( $t->status === 'idea' ) {
                SK_DB::enqueue( $t->title, $t->keywords, 5 );
                global $wpdb;
                $wpdb->update( SK_DB::table( 'topics' ), [ 'status' => 'queued' ], [ 'id' => $t->id ] );
            }
        }
    }
}