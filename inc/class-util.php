<?php
defined( 'WPINC' ) OR exit;

class UL_Util {

	/**
	 * @return int[] All blog IDs.
	 */
	public static function get_blog_ids() {
		global $wpdb;
		return $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
	}

	/**
	 * @param $haystack string The string to be tested.
	 * @param $needle string The value to be tested against.
	 * @return bool Whether $haystack starts with $needle.
	 */
	public static function starts_with( $haystack, $needle ) {
		return substr( $haystack, 0, strlen( $needle ) ) === $needle;
	}

	/**
	 * @param $haystack string The string to be tested.
	 * @param $needle string The value to be tested against.
	 * @return bool Whether $haystack ends with $needle.
	 */
	public static function ends_with( $haystack, $needle ) {
		return substr( $haystack, -strlen( $needle ) ) === $needle;
	}

}