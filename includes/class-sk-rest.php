<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SK_Rest {

    public static function init() {
        add_action( 'rest_api_init', [ __CLASS__, 'register' ] );
    }

    public static function register() {
        $ns = 'sk-blogger/v1';

        register_rest_route( $ns, '/queue', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'add_queue' ],
            'permission_callback' => [ __CLASS__, 'can_manage' ],
            'args' => [
                'topic'    => [ 'required' => true, 'type' => 'string' ],
                'keywords' => [ 'type' => 'string' ],
                'priority' => [ 'type' => 'integer' ],
            ],
        ] );

        register_rest_route( $ns, '/queue/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [ __CLASS__, 'delete_queue' ],
            'permission_callback' => [ __CLASS__, 'can_manage' ],
        ] );

        register_rest_route( $ns, '/process', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'run_process' ],
            'permission_callback' => [ __CLASS__, 'can_manage' ],
        ] );

        register_rest_route( $ns, '/stats', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'stats' ],
            'permission_callback' => [ __CLASS__, 'can_manage' ],
        ] );
    }

    public static function can_manage() {
        return current_user_can( 'manage_options' );
    }

    public static function add_queue( $req ) {
        $id = SK_DB::enqueue(
            $req->get_param( 'topic' ),
            $req->get_param( 'keywords' ) ?: '',
            (int) ( $req->get_param( 'priority' ) ?: 5 )
        );
        return rest_ensure_response( [ 'success' => true, 'id' => $id ] );
    }

    public static function delete_queue( $req ) {
        SK_DB::delete_queue( (int) $req['id'] );
        return rest_ensure_response( [ 'success' => true ] );
    }

    public static function run_process( $req ) {
        SK_Queue::process();
        return rest_ensure_response( [ 'success' => true, 'counts' => SK_DB::queue_counts() ] );
    }

    public static function stats( $req ) {
        return rest_ensure_response( [
            'queue'  => SK_DB::queue_counts(),
            'recent' => SK_Logger::get_logs( 5 ),
        ] );
    }
}