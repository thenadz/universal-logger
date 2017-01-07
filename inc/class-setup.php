<?php
defined( 'WPINC' ) OR exit;

class UL_Setup {

	/**
	 * Sets up Document Gallery on all blog(s) activated.
	 *
	 * @param bool $networkwide Whether this is a network-wide update (multisite only).
	 */
	public static function activate( $networkwide ) {
		if ( ! is_multisite() || ! $networkwide ) {
			$blogs = array( get_current_blog_id() );
		} else {
			$blogs = UL_Util::get_blog_ids();
		}

		foreach ( $blogs as $blog ) {
			self::_activate( $blog );
		}

		// handle purging log entries regularly
		wp_schedule_event( time(), 'hourly', UniversalLogger::PurgeLogsAction );
	}

	/**
	 * Hooked into wpmu_new_blog to handle activating a new blog when plugin
	 * is already network activated.
	 * See discussion: https://core.trac.wordpress.org/ticket/14170
	 *
	 * @param int $blog Blog ID.
	 */
	public static function activate_new_blog( $blog ) {
		if ( is_plugin_active_for_network( UniversalLogger::get_basename() ) ) {
			self::_activate( $blog );
		}
	}

	/**
	 * Runs activation setup for Universal Logger on all blog(s) it is activated on.
	 *
	 * @param int $blog Blog to update or null if updating current blog.
	 */
	private static function _activate( $blog ) {
		ULogger::upsert_slug( UniversalLogger::Slug );
	}

	/**
	 * Runs when DG is uninstalled.
	 */
	public static function uninstall() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$blogs = array( null );

		if ( is_multisite() ) {
			$blogs = UL_Util::get_blog_ids();
		}

		foreach ( $blogs as $blog ) {
			self::_uninstall( $blog );
		}

		wp_clear_scheduled_hook( UniversalLogger::PurgeLogsAction );
	}

	/**
	 * Runs when Universal Logger is uninstalled for an individual blog.
	 */
	private static function _uninstall( $blog ) {
		// TODO: Anything to do here?
	}
}