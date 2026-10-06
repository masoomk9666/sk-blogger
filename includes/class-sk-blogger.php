<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SK_Blogger {

    private static $instance = null;

    public static function instance() {
        if ( self::$instance === null ) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
        $this->load_textdomain();
    }

    private function init_hooks() {
        // Core init
        SK_Cron::init();
        SK_Rest::init();

        // Self-healing: verify cron is scheduled + fallback processing
        // Runs on every admin page load so problems fix themselves
        add_action( 'admin_init', [ $this, 'verify_and_run_fallback' ] );

        // Admin assets
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );

        // Admin class
        if ( is_admin() ) {
            new SK_Admin();
        }

        // Action links on plugins page
        add_filter( 'plugin_action_links_' . SK_BLOGGER_BASENAME, [ $this, 'action_links' ] );
    }

    /**
     * Self-healing + fallback processor.
     *
     * Two jobs:
     * 1. If cron hook is missing, re-schedule it (fixes after plugin updates)
     * 2. If there are due queue items and cron hasn't run recently,
     *    process them manually (fixes when WP-Cron is broken)
     */
    public function verify_and_run_fallback() {
        // Job 1: Ensure cron hook exists
        if ( ! wp_next_scheduled( 'sk_blogger_process_queue' ) ) {
            if ( class_exists( 'SK_Cron' ) ) {
                SK_Cron::schedule_events();
                if ( class_exists( 'SK_Logger' ) ) {
                    SK_Logger::info( 'Fallback: cron hook was missing, re-scheduled.', 'cron' );
                }
            }
        }

        // Job 2: Run queue processing if it's overdue
        // Uses a lock to prevent duplicate runs from multiple admin tabs
        if ( get_transient( 'sk_fallback_lock' ) ) {
            return;
        }

        // Check if DB is ready
        if ( ! class_exists( 'SK_DB' ) || ! class_exists( 'SK_Queue' ) ) {
            return;
        }

        // Only run if there are due items
        $due_items = SK_DB::get_due_items( 1 );
        if ( empty( $due_items ) ) {
            return;
        }

        // Set lock for 5 minutes to prevent duplicate runs
        set_transient( 'sk_fallback_lock', 1, 300 );

        // Run the queue processor
        SK_Queue::process();
    }

    private function load_textdomain() {
        load_plugin_textdomain( 'sk-blogger', false, dirname( SK_BLOGGER_BASENAME ) . '/languages' );
    }

    public function admin_assets( $hook ) {
        if ( strpos( $hook, 'sk-blogger' ) === false ) { return; }
        wp_enqueue_style( 'sk-blogger-admin', SK_BLOGGER_URL . 'admin/css/admin.css', [], SK_BLOGGER_VERSION );
        wp_enqueue_script( 'sk-blogger-admin', SK_BLOGGER_URL . 'admin/js/admin.js', [ 'jquery' ], SK_BLOGGER_VERSION, true );
        wp_localize_script( 'sk-blogger-admin', 'SKBlogger', [
            'rest'  => esc_url_raw( rest_url( 'sk-blogger/v1' ) ),
            'nonce' => wp_create_nonce( 'wp_rest' ),
            'i18n'  => [
                'confirm_delete' => __( 'Delete this item?', 'sk-blogger' ),
                'processing'     => __( 'Processing...', 'sk-blogger' ),
            ],
        ] );
    }

    public function action_links( $links ) {
        $links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=sk-blogger' ) ) . '">' . esc_html__( 'Dashboard', 'sk-blogger' ) . '</a>';
        $links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=sk-blogger-settings' ) ) . '">' . esc_html__( 'Settings', 'sk-blogger' ) . '</a>';
        return $links;
    }
}