<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SK_Logger {

    public static function log( $level, $message, $context = '', $meta = null ) {
        global $wpdb;
        $wpdb->insert( SK_DB::table( 'logs' ), [
            'level'   => sanitize_key( $level ),
            'message' => wp_strip_all_tags( $message ),
            'context' => sanitize_key( $context ),
            'meta'    => $meta ? wp_json_encode( $meta ) : null,
        ] );
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf( '[SK Blogger][%s][%s] %s', $level, $context, $message ) );
        }
    }

    public static function info( $m, $c = '', $meta = null )  { self::log( 'info', $m, $c, $meta ); }
    public static function warn( $m, $c = '', $meta = null )  { self::log( 'warning', $m, $c, $meta ); }
    public static function error( $m, $c = '', $meta = null ) { self::log( 'error', $m, $c, $meta ); }

    public static function get_logs( $limit = 100, $offset = 0, $level = null ) {
        global $wpdb;
        $table = SK_DB::table( 'logs' );
        if ( $level ) {
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$table} WHERE level = %s ORDER BY id DESC LIMIT %d OFFSET %d",
                $level, $limit, $offset
            ) );
        }
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset
        ) );
    }

    public static function clear() {
        global $wpdb;
        $wpdb->query( "TRUNCATE TABLE " . SK_DB::table( 'logs' ) );
    }
}