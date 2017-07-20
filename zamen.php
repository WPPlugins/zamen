<?php

/**
 * @package ZamenApp
 */

/*
  Plugin Name: Zamen App
  Plugin URI: http://zamenapp.com
  Description: Zamen WordPress plugin lets you sync your WordPress blog with Zamen Platform.
  Version: 1.1.1
  Author: Zamen App Team
  Author URI: http://zamenapp.com
  License: GPLv2
  Text Domain: zamen
*/

// Make sure we don't expose any info if called directly
if ( ! function_exists( 'add_action' ) ) {
	// We fall in love with this message from akismet plugin
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

define( 'MINIMUM_PHP_VERSION', '5.3' );
define( 'MINIMUM_WORDPRESS_VERSION', '4.4.0' );


define( 'ZAMEN_VERSION', '1.1.1' );
define( 'ZAMEN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ZAMEN_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'ZAMEN_PLUGIN_NAME', plugin_basename( __FILE__ ) );
define( 'ZAMEN_PLUGIN_MAIN_FILE', __FILE__ );

require_once ZAMEN_PLUGIN_PATH . '/includes/zamen-class.php';

// Execute Zamen plugin
$zamen = new Zamen();