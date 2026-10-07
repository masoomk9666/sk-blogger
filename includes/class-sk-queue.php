<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SK_Queue {

    public static function process() {
        if ( get_transient( 'sk_queue_lock' ) ) { return; }
        set_transient( 'sk_queue_lock', 1, 300 );

        $batch = (int) get_option( 'sk_queue_batch', 3 );
        $items = SK_DB::get_due_items( $batch );

        foreach ( $items as $item ) {
            self::process_item( $item );
        }
        delete_transient( 'sk_queue_lock' );
    }

    public static function process_item( $item ) {
        SK_DB::update_queue( $item->id, [ 'status' => 'processing' ] );
        $attempts = (int) $item->attempts + 1;

        // Build override array — pass category if present on queue item
        $override = [];
        if ( ! empty( $item->category_id ) ) {
            $override['category'] = (int) $item->category_id;
        }

        $post_id = SK_Generator::generate_post( $item->topic, $item->keywords, $override );

        if ( is_wp_error( $post_id ) ) {
            $error_msg = $post_id->get_error_message();
            $max = (int) get_option( 'sk_max_attempts', 3 );

            // Detect transient errors (503, 429, quota, overloaded)
            $is_transient = strpos( $error_msg, 'high demand' ) !== false
                         || strpos( $error_msg, 'UNAVAILABLE' ) !== false
                         || strpos( $error_msg, '503' ) !== false
                         || strpos( $error_msg, 'overloaded' ) !== false
                         || strpos( $error_msg, 'temporarily' ) !== false
                         || strpos( $error_msg, 'quota' ) !== false
                         || strpos( $error_msg, 'RESOURCE_EXHAUSTED' ) !== false
                         || strpos( $error_msg, '429' ) !== false;

            if ( $is_transient && $attempts < $max ) {
                // Exponential backoff: 2min, 4min, 8min
                $delay = 120 * pow( 2, $attempts - 1 );
                SK_DB::update_queue( $item->id, [
                    'status'       => 'pending',
                    'attempts'     => $attempts,
                    'error'        => $error_msg,
                    'scheduled_at' => gmdate( 'Y-m-d H:i:s', time() + $delay ),
                ] );
                SK_Logger::warn( "Queue #{$item->id} transient error, retry in {$delay}s.", 'queue' );
            } else {
                $new_status = $attempts >= $max ? 'failed' : 'pending';
                SK_DB::update_queue( $item->id, [
                    'status'       => $new_status,
                    'attempts'     => $attempts,
                    'error'        => $error_msg,
                    'scheduled_at' => $new_status === 'pending'
                        ? gmdate( 'Y-m-d H:i:s', time() + 600 * $attempts )
                        : $item->scheduled_at,
                ] );
                SK_Logger::error( "Queue #{$item->id} failed (attempt {$attempts}): " . $error_msg, 'queue' );
            }
            return;
        }

        SK_DB::update_queue( $item->id, [
            'status'   => 'completed',
            'post_id'  => $post_id,
            'attempts' => $attempts,
            'error'    => null,
        ] );
    }

    /**
     * Bulk add topics to queue.
     *
     * @param array    $topics_array
     * @param string   $keywords
     * @param int      $priority
     * @param int|null $category_id   NEW — category for all bulk items
     */
    public static function add_bulk( $topics_array, $keywords = '', $priority = 5, $category_id = null ) {
        $ids = [];
        foreach ( (array) $topics_array as $t ) {
            $t = trim( $t );
            if ( $t === '' ) { continue; }
            $ids[] = SK_DB::enqueue( $t, $keywords, $priority, null, $category_id );
        }
        return $ids;
    }
}