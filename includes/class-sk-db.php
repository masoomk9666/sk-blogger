<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SK_DB {

    public static function table( $name ) {
        global $wpdb;
        return $wpdb->prefix . 'sk_' . $name;
    }

    /* --------------------- QUEUE --------------------- */

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

    /* --------------------- TRENDING KEYWORDS --------------------- */

    public static function add_trending_keyword( $keyword, $category_id, $trend_date = null, $keywords = '' ) {
        global $wpdb;
        $trend_date = $trend_date ?: current_time( 'Y-m-d' );
        $keywords   = sanitize_text_field( $keywords );

        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM " . self::table( 'trending' ) . " 
             WHERE keyword = %s AND category_id = %d AND trend_date = %s",
            $keyword, (int) $category_id, $trend_date
        ) );

        if ( $exists ) {
            return (int) $exists;
        }

        $wpdb->insert( self::table( 'trending' ), [
            'keyword'     => sanitize_text_field( $keyword ),
            'keywords'    => $keywords,
            'category_id' => (int) $category_id,
            'trend_date'  => $trend_date,
            'status'      => 'pending',
        ] );
        return (int) $wpdb->insert_id;
    }

    public static function get_trending_keywords( $limit = 100, $offset = 0, $category_id = null, $status = null, $trend_date = null ) {
        global $wpdb;
        $table  = self::table( 'trending' );
        $where  = [];
        $params = [];

        if ( $category_id ) {
            $where[]  = 'category_id = %d';
            $params[] = (int) $category_id;
        }
        if ( $status ) {
            $where[]  = 'status = %s';
            $params[] = $status;
        }
        if ( $trend_date ) {
            $where[]  = 'trend_date = %s';
            $params[] = $trend_date;
        }

        $sql = "SELECT * FROM {$table}";
        if ( ! empty( $where ) ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where );
        }
        $sql .= " ORDER BY trend_date DESC, id DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
    }

    public static function get_today_trending_for_category( $category_id, $trend_date = null ) {
        global $wpdb;
        $trend_date = $trend_date ?: current_time( 'Y-m-d' );
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . self::table( 'trending' ) . " 
             WHERE category_id = %d AND trend_date = %s AND status = 'pending'
             ORDER BY id ASC LIMIT 1",
            (int) $category_id, $trend_date
        ) );
    }

    public static function mark_trending_used( $id, $post_id = null ) {
        global $wpdb;
        $data = [
            'status'  => 'used',
            'used_at' => current_time( 'mysql' ),
        ];
        if ( $post_id ) {
            $data['post_id'] = (int) $post_id;
        }
        return $wpdb->update( self::table( 'trending' ), $data, [ 'id' => (int) $id ] );
    }

    public static function delete_trending_keyword( $id ) {
        global $wpdb;
        return $wpdb->delete( self::table( 'trending' ), [ 'id' => (int) $id ] );
    }

    public static function trending_counts( $trend_date = null ) {
        global $wpdb;
        $trend_date = $trend_date ?: current_time( 'Y-m-d' );
        $table      = self::table( 'trending' );
        $rows       = $wpdb->get_results( $wpdb->prepare(
            "SELECT status, COUNT(*) AS c FROM {$table} 
             WHERE trend_date = %s GROUP BY status",
            $trend_date
        ), OBJECT_K );
        $out = [ 'pending' => 0, 'used' => 0, 'skipped' => 0 ];
        foreach ( $rows as $k => $row ) { $out[ $k ] = (int) $row->c; }
        return $out;
    }

    public static function get_categories_with_trending( $trend_date = null ) {
        global $wpdb;
        $trend_date = $trend_date ?: current_time( 'Y-m-d' );
        $table      = self::table( 'trending' );
        return $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT category_id FROM {$table} 
             WHERE trend_date = %s AND status = 'pending'",
            $trend_date
        ) );
    }

    /* --------------------- REUSABLE KEYWORDS (FALLBACK CHAIN) --------------------- */

    /**
     * Get recently used keywords (last 30 days) for reuse.
     * Used in STEP 3 fallback — when no new or old pending keywords.
     *
     * @param int      $limit       How many to return
     * @param int|null $category_id Filter by category (optional)
     * @return array
     */
    public static function get_reusable_keywords( $limit = 20, $category_id = null ) {
        global $wpdb;
        $table = self::table( 'trending' );

        $where  = "status = 'used' AND trend_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $params = [];

        if ( $category_id ) {
            $where   .= " AND category_id = %d";
            $params[] = (int) $category_id;
        }

        $params[] = $limit;

        $sql = "SELECT * FROM {$table} 
                WHERE {$where}
                ORDER BY used_at DESC, id DESC 
                LIMIT %d";

        return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
    }

    /**
     * Get old pending keywords from earlier dates.
     * Used in STEP 2 fallback — when today's keywords are unavailable.
     *
     * @param int|null $category_id Filter by category (optional)
     * @param int      $limit       How many to return
     * @return array
     */
    public static function get_old_pending_keywords( $category_id = null, $limit = 5 ) {
        global $wpdb;
        $table = self::table( 'trending' );
        $today = current_time( 'Y-m-d' );

        $where  = "status = 'pending' AND trend_date < %s";
        $params = [ $today ];

        if ( $category_id ) {
            $where   .= " AND category_id = %d";
            $params[] = (int) $category_id;
        }

        $params[] = $limit;

        $sql = "SELECT * FROM {$table} 
                WHERE {$where}
                ORDER BY trend_date DESC, id ASC 
                LIMIT %d";

        return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
    }

    /**
     * Check if a keyword has been used for a category (any date).
     * Useful for preventing duplicates.
     *
     * @param string $keyword
     * @param int    $category_id
     * @return bool
     */
    public static function is_keyword_used( $keyword, $category_id ) {
        global $wpdb;
        $table = self::table( 'trending' );
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} 
             WHERE keyword = %s AND category_id = %d AND status = 'used'",
            $keyword, (int) $category_id
        ) );
    }

    /**
     * Clone an old trending keyword for a new date.
     * Used in STEP 3 — when we need to reuse a recently used keyword.
     *
     * @param int         $source_id  Source trending row ID
     * @param string|null $new_date   New trend date (defaults to today)
     * @return int|false  New row ID or false on failure
     */
    public static function reuse_trending_keyword( $source_id, $new_date = null ) {
        global $wpdb;
        $new_date = $new_date ?: current_time( 'Y-m-d' );
        $table    = self::table( 'trending' );

        // Fetch source row
        $source = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            (int) $source_id
        ) );

        if ( ! $source ) {
            return false;
        }

        // Prevent duplicate for same date+category
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} 
             WHERE keyword = %s AND category_id = %d AND trend_date = %s",
            $source->keyword, (int) $source->category_id, $new_date
        ) );

        if ( $exists ) {
            return (int) $exists;
        }

        // Insert as new pending row
        $wpdb->insert( $table, [
            'keyword'     => $source->keyword,
            'keywords'    => $source->keywords,
            'category_id' => (int) $source->category_id,
            'trend_date'  => $new_date,
            'status'      => 'pending',
        ] );

        return (int) $wpdb->insert_id;
    }
}