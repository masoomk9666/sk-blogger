<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SK_DB {

    public static function table( $name ) {
        global $wpdb;
        return $wpdb->prefix . 'sk_' . $name;
    }

    /* --------------------- QUEUE --------------------- */

    /**
     * Add item to queue.
     *
     * @param string   $topic
     * @param string   $keywords
     * @param int      $priority
     * @param string   $scheduled_at
     * @param int|null $category_id   NEW — category to assign when post is created
     */
    public static function enqueue( $topic, $keywords = '', $priority = 5, $scheduled_at = null, $category_id = null ) {
        global $wpdb;
        $scheduled_at = $scheduled_at ?: current_time( 'mysql' );

        $data = [
            'topic'        => wp_strip_all_tags( $topic ),
            'keywords'     => sanitize_text_field( $keywords ),
            'priority'     => (int) $priority,
            'scheduled_at' => $scheduled_at,
            'status'       => 'pending',
        ];

        // Category — use provided or fall back to settings default
        if ( $category_id === null ) {
            $category_id = (int) get_option( 'sk_default_category', 1 );
        } else {
            $category_id = (int) $category_id;
        }

        if ( $category_id > 0 ) {
            $data['category_id'] = $category_id;
        }

        $wpdb->insert( self::table( 'queue' ), $data );
        return (int) $wpdb->insert_id;
    }

    public static function get_due_items( $limit = 3 ) {
        global $wpdb;
        $table = self::table( 'queue' );
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE status = 'pending' AND scheduled_at <= %s ORDER BY priority ASC, scheduled_at ASC LIMIT %d",
            current_time( 'mysql' ), $limit
        ) );
    }

    public static function update_queue( $id, $data ) {
        global $wpdb;
        return $wpdb->update( self::table( 'queue' ), $data, [ 'id' => (int) $id ] );
    }

    public static function get_queue( $status = null, $limit = 100, $offset = 0 ) {
        global $wpdb;
        $table = self::table( 'queue' );
        if ( $status ) {
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$table} WHERE status = %s ORDER BY id DESC LIMIT %d OFFSET %d",
                $status, $limit, $offset
            ) );
        }
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset
        ) );
    }

    public static function delete_queue( $id ) {
        global $wpdb;
        return $wpdb->delete( self::table( 'queue' ), [ 'id' => (int) $id ] );
    }

    public static function queue_counts() {
        global $wpdb;
        $table = self::table( 'queue' );
        $rows  = $wpdb->get_results( "SELECT status, COUNT(*) AS c FROM {$table} GROUP BY status", OBJECT_K );
        $out   = [ 'pending' => 0, 'processing' => 0, 'completed' => 0, 'failed' => 0 ];
        foreach ( $rows as $k => $row ) { $out[ $k ] = (int) $row->c; }
        return $out;
    }

    /**
     * Get queue items by status with optional category filter.
     * (Utility method — optional)
     */
    public static function get_queue_by_category( $category_id, $limit = 100 ) {
        global $wpdb;
        $table = self::table( 'queue' );
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE category_id = %d ORDER BY id DESC LIMIT %d",
            (int) $category_id, $limit
        ) );
    }

    /* --------------------- TOPICS --------------------- */

    public static function add_topic( $title, $keywords = '', $category_id = null ) {
        global $wpdb;
        $wpdb->insert( self::table( 'topics' ), [
            'title'       => sanitize_text_field( $title ),
            'keywords'    => sanitize_text_field( $keywords ),
            'category_id' => $category_id ? (int) $category_id : null,
            'status'      => 'idea',
        ] );
        return (int) $wpdb->insert_id;
    }

    public static function get_topics( $limit = 100, $offset = 0 ) {
        global $wpdb;
        $table = self::table( 'topics' );
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset
        ) );
    }

    public static function delete_topic( $id ) {
        global $wpdb;
        return $wpdb->delete( self::table( 'topics' ), [ 'id' => (int) $id ] );
    }
}