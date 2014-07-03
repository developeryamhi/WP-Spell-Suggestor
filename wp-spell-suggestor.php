<?php
/*
  Plugin Name: WP Spell Suggestor
  Plugin URI: http://www.pdsteam.info/
  Description: Spelling Correction and Suggestion Plugin for Wordpress 3.5+
  Version: 0.1.0
  Author: PDS Team
  Author URI: http://www.pdsteam.info/
  License: GPLv3
 */

//  Define Path Constants
define('PDS_SS_URL', plugin_dir_url(__FILE__));
define('PDS_SS_PATH', plugin_dir_path(__FILE__));
define('PDS_SS_LIB_PATH', PDS_SS_PATH . 'lib/');

//  Load Library Files
require_once PDS_SS_LIB_PATH . 'class/PDS_SS_Suggestion_Finder.php';
require_once PDS_SS_LIB_PATH . 'functions.php';

//  Register Activate Hook
register_activation_hook(__FILE__, 'pds_ss_activate');

//  Add Table Name
global $wpdb;
$wpdb->table_spell_dict = $wpdb->prefix . "spell_dict";

//  On Activation
function pds_ss_activate() {
    global $wpdb;

    //  Create Table Query
    $sql = "CREATE TABLE {$wpdb->table_spell_dict} (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        query VARCHAR(255) NOT NULL,
        suggestion VARCHAR(255) NOT NULL DEFAULT '',
        provider VARCHAR(255) NOT NULL DEFAULT '',
        created_at timestamp
    );";

    //	Check Table Exists
    if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->table_spell_dict}'") != $wpdb->table_spell_dict) {

        //	Run Table Creation
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta($sql);
    }
}