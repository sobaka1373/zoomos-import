<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Custom_Plugin_Activator {

    public static function activate() {
//        global $wpdb;
//        $table_name = $wpdb->get_blog_prefix() . 'virtual_events';
//        $charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset} COLLATE {$wpdb->collate}";
//        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
//        $sql = "CREATE TABLE {$table_name} (
//            id int(11) unsigned NOT NULL auto_increment,
//            name varchar(255) NOT NULL default '',
//            created_date DATETIME DEFAULT NULL,
//            author varchar(255) NOT NULL default '',
//            start_date DATETIME DEFAULT NULL,
//            end_date DATETIME DEFAULT NULL,
//            open_date DATETIME DEFAULT NULL,
//            attendees int(11) DEFAULT NULL,
//            header_img varchar(255) NOT NULL default '',
//            host_name varchar(255) NOT NULL default '',
//            host_img varchar(255) NOT NULL default '',
//            full_description text(9999) NOT NULL default '',
//            brief_description varchar(255) NOT NULL default '',
//            speakers varchar(255) NOT NULL default '',
//            sponsors varchar(255) NOT NULL default '',
//            links varchar(255) NOT NULL default '',
//            chat_settings varchar(255) NOT NULL default '',
//            stream_link varchar(255) NOT NULL default '',
//            PRIMARY KEY  (id)
//        ) {$charset_collate};";
//
//        dbDelta($sql);


        run_custom_plugin_wp();
    }

}
