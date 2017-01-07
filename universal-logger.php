<?php
defined( 'WPINC' ) OR exit;

/*
  Plugin Name: Universal Logger
  Plugin URI: https://wordpress.org/plugins/universal-logger/
  Description: A general purpose logging plugin designed to be used by other plugins.
  Version: 1.0
  Author: Dan Rossiter
  Author URI: https://danrossiter.org/
  License: GPLv3
  Text Domain: universal-logger
 */

class UniversalLogger {

	/**
	 * @var string The fully-qualified name of the main file.
	 */
	const MainFile = __FILE__;

	/**
	 * @var string Name of the log purge action.
	 */
	const PurgeLogsAction = 'universal-logger_purge-logs';

	/**
	 * @var string The slug identifying Universal Logger.
	 */
	const Slug = 'universal-logger';

	/**
	 * @return string The Universal Logger plugin basename.
	 */
	public static function get_basename() {
		static $basename;
		if ( is_null( $basename ) ) {
			$basename = plugin_basename( self::MainFile );
		}

		return $basename;
	}

	/**
	 * @return string The Universal Logger plugin install path.
	 */
	public static function get_path() {
		static $path = null;
		if ( is_null( $path ) ) {
			$path = plugin_dir_path( self::MainFile );
		}

		return $path;
	}

}

include_once UniversalLogger::get_path() . 'inc/class-util.php';
include_once UniversalLogger::get_path() . 'inc/class-setup.php';
include_once UniversalLogger::get_path() . 'inc/class-registered-slug.php';
include_once UniversalLogger::get_path() . 'class-ulogger.php';

// register activation/uninstall hooks
register_activation_hook( UniversalLogger::MainFile, array( 'UL_Setup', 'activate' ) );
add_action( 'wpmu_new_blog', array( 'UL_Setup', 'activate_new_blog' ) );
register_uninstall_hook( UniversalLogger::MainFile, array( 'UL_Setup', 'uninstall' ) );