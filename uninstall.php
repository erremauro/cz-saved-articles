<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}czsa_saved_articles" );

delete_option( 'czsa_db_version' );
delete_option( 'czsa_saved_page_id' );

$wpdb->delete( $wpdb->usermeta, [ 'meta_key' => 'czsa_saved_articles' ], [ '%s' ] );
