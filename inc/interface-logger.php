<?php
defined( 'WPINC' ) OR exit;

interface UL_ILogger {

	/**
	 * @var int Unix timestamp for January 1, 3000. Just trust me.
	 */
	const MAX_TIMESTAMP = PHP_INT_MAX;

	/**
	 * @param $slug_name string The slug identifying who new entry belongs to.
	 * @param $blog_id int|null The blog ID (default: current blog ID).
	 *
	 * @return null|UL_RegisteredSlug The slug with the given name.
	 */
	public function get_slug( $slug_name, $blog_id = null );

	/**
	 * Gets registered slugs.
	 *
	 * @param $blog_id int The blog ID.
	 *
	 * @return UL_RegisteredSlug[] The registered slugs and related metadata.
	 */
	public function get_registered_slugs( $blog_id );

	/**
	 * @param $slug_name string The slug identifying who new entry belongs to.
	 * @param $purge_interval int The interval at which log entries should be purged (in hours).
	 * @param $log_level int The log level (should use ULogLevel consts).
	 * @param $blog_id int|null The blog ID (default: current blog ID).
	 *
	 * @return bool Indicates whether upsert was successful.
	 */
	public function upsert_slug( $slug_name, $purge_interval = 168, $log_level = ULogLevel::Warning, $blog_id = null );

	/**
	 * @param $slug_name string The slug identifying who new entry belongs to.
	 * @param $blog_id int|null The blog ID (default: current blog ID).
	 *
	 * @return bool Indicates whether delete was successful.
	 */
	public function delete_slug( $slug_name, $blog_id = null );

	/**
	 * @param $slug_name string The slug identifying who new entry belongs to.
	 * @param $log_level int The log level (should use ULogLevel consts).
	 * @param $entry string The log entry.
	 * @param $stacktrace null|string The stacktrace associated with the entry (default: null).
	 * @param $blog_id int|null The blog ID (default: current blog ID).
	 *
	 * @return bool Indicates whether insert was successful.
	 */
	public function insert_entry( $slug_name, $log_level, $entry, $stacktrace = null, $blog_id = null );

	/**
	 * @param $slug_name string The slug identifying who new entry belongs to.
	 * @param $min_ts int The minimum timestamp to be included.
	 * @param $max_ts int The maximum timestamp to be included.
	 * @param $min_log_level int The minimum log level to be included.
	 * @param $max_log_level int The maximum log level to be included.
	 * @param $blog_id int|null The blog ID (default: current blog ID).
	 *
	 * @return object[][] The entries, ordered oldest to newest. Each array has the following fields:
	 *                  log_level, entry, entry_time, and entry_stacktrace
	 */
	public function get_entries( $slug_name, $min_ts = 0, $max_ts = self::MAX_TIMESTAMP, $min_log_level = ULogLevel::Detail, $max_log_level = ULogLevel::Error, $blog_id = null );

	/**
	 * @param $slug_name string The slug identifying which entries are being purged.
	 * @param $ts int The timestamp marking threshold for deletion.
	 * @param $blog_id int|null The blog ID (default: current blog ID).
	 */
	public function delete_entries_older_than( $slug_name, $ts, $blog_id = null );
}
