<?php
/**
 * Plugin Name: Another Comments Cleaner
 * Description: Clean periodically comments depending on the comment status
 * Version: 0.8
 */
define( 'ANCC_PATH', plugin_dir_path( __FILE__ ) );
define( 'ANCC_URL', plugin_dir_url( __FILE__ ) );
require_once ANCC_PATH . 'inc/plugin.php';

/**
 *  wrapper function for translation
 */
function ancc_e( $text, $domain = 'default' ) {
	echo translate( $text, $domain );
}

/**
 *  wrapper function for translation
 */
function ancc__( $text, $domain = 'default' ) {
	return translate( $text, $domain );
}

/**
 *  wrapper function for translation
 */
function ancc_x( $text, $context, $domain = 'default' ) {
	return translate_with_gettext_context( $text, $context, $domain );
}
