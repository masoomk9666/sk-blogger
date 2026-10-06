<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SK_Deactivator {
    public static function deactivate() {
        SK_Cron::clear_events();
    }
}