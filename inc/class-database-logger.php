<?php
defined( 'WPINC' ) OR exit;

class UL_DatabaseLogger implements UL_ILogger {

	/**
	 * @var UL_RegisteredSlug[][] The registered slugs and related metadata.
	 */
	private $registered_slugs = array();

	/**
	 * UL_Database constructor.
	 */
	public function __construct() {
		register_activation_hook( UniversalLogger::MainFile, array( $this, 'create_schema' ) );
	}

	/**
	 * Gets reference to registered slugs array. Array is lazy loaded
	 * the first time it is needed, avoiding unnecessary DB calls.
	 *
	 * @param $blog_id int The blog ID.
	 *
	 * @return null|UL_RegisteredSlug[] The registered slugs and related metadata.
	 */
	private function &_get_registered_slugs( $blog_id ) {
		if ( ! isset( $this->registered_slugs[$blog_id] ) ) {
			global $wpdb;

			$table_name = $wpdb->get_blog_prefix( $blog_id ) . 'log_slug';
			$results = $wpdb->get_results( "SELECT slug_id, slug, purge_interval, log_level FROM {$table_name}" );
			$this->registered_slugs[$blog_id] = array();
			foreach ( $results as $v ) {
				$this->registered_slugs[$blog_id][$v->slug] = new UL_RegisteredSlug( $v->slug_id, $v->slug, $v->purge_interval, $v->log_level );
			}
		}

		return $this->registered_slugs[$blog_id];
	}

	/**
	 * Gets registered slugs.
	 *
	 * @param $blog_id int The blog ID.
	 *
	 * @return null|UL_RegisteredSlug[] The registered slugs and related metadata.
	 */
	public function get_registered_slugs( $blog_id ) {
		return $this->_get_registered_slugs( $blog_id );
	}

	/**
	 * Create schema for DB logger.
	 */
	public function create_schema() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		global $wpdb;

		/*
		 * Indexes have a maximum size of 767 bytes. Historically, we haven't need to be concerned about that.
		 * As of 4.2, however, we moved to utf8mb4, which uses 4 bytes per character. This means that an index which
		 * used to have room for floor(767/3) = 255 characters, now only has room for floor(767/4) = 191 characters.
		 */
		$max_index_length = 191;

		$charset_collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$wpdb->prefix}log_slug (
slug_id bigint(20) unsigned NOT NULL auto_increment,
slug varchar(256) NOT NULL,
purge_interval smallint(16) NULL,
log_level tinyint(4) NOT NULL,
PRIMARY KEY (slug_id),
UNIQUE KEY slug (slug($max_index_length))
) $charset_collate;
CREATE TABLE {$wpdb->prefix}log (
entry_id bigint(20) unsigned NOT NULL auto_increment,
slug_id bigint(20) unsigned NOT NULL,
log_level tinyint(4) unsigned NOT NULL,
entry varchar(2048) NOT NULL,
entry_time datetime NOT NULL DEFAULT NOW(),
entry_stacktrace varchar(2048) NULL,
PRIMARY KEY (entry_id),
FOREIGN KEY (slug_id) REFERENCES {$wpdb->prefix}log_slug(slug_id) ON DELETE CASCADE,
KEY entry_time (entry_time)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * @param $slug_name string The slug identifying who new entry belongs to.
	 * @param $blog_id int The blog ID (default: current blog ID).
	 *
	 * @return null|UL_RegisteredSlug The slug with the given name.
	 */
	public function get_slug( $slug_name, $blog_id = null ) {
		if ( is_null( $blog_id ) ) {
			$blog_id = get_current_blog_id();
		}

		$slugs = $this->_get_registered_slugs( $blog_id );
		return isset( $slugs[$slug_name] ) ? $slugs[$slug_name] : null;
	}

	/**
	 * @param $slug_name string The slug identifying who new entry belongs to.
	 * @param int $purge_interval The interval at which log entries should be purged (in hours).
	 * @param $log_level int The log level (should use ULogLevel consts).
	 * @param $blog_id int The blog ID (default: current blog ID).
	 *
	 * @return bool Indicates whether upsert was successful.
	 */
	public function upsert_slug( $slug_name, $purge_interval = UL_ILogger::PURGE_INTERVAL, $log_level = UL_ILogger::MIN_LOG_LEVEL, $blog_id = null ) {
		global $wpdb;

		if ( is_null( $blog_id ) ) {
			$blog_id = get_current_blog_id();
		}

		$slugs = $this->_get_registered_slugs( $blog_id );
		$prefix = $wpdb->get_blog_prefix( $blog_id );
		$table_name = $prefix . 'log_slug';

		if ( ! isset( $slugs[$slug_name] ) ) {
			// insert
			$vals = array( 'slug' => $slug_name, 'purge_interval' => $purge_interval, 'log_level' => $log_level );
			$fmts = array( '%s', '%d', '%d' );

			// update DB & in-memory cache
			$ret = (bool)$wpdb->insert( $table_name, $vals, $fmts );
			if ( $ret ) {
				$slugs[$slug_name] = new UL_RegisteredSlug( $wpdb->insert_id, $slug_name, $purge_interval, $log_level );
			}
		} else {
			// update
			$vals = array( 'purge_interval' => $purge_interval, 'log_level' => $log_level );
			$fmts = '%d';
			$where = array( 'slug_id' => $slugs[$slug_name]->get_id() );
			$where_fmts = '%d';

			// update DB & in-memory cache
			$ret = (bool)$wpdb->update( $table_name, $vals, $where, $fmts, $where_fmts );
			if ( $ret ) {
				$slugs[$slug_name]->set_purge_interval( $purge_interval );
				$slugs[$slug_name]->set_log_level( $log_level );
			}
		}

		return $ret;
	}

	/**
	 * @param $slug_name string The slug identifying who new entry belongs to.
	 * @param $blog_id int The blog ID (default: current blog ID).
	 *
	 * @return bool Indicates whether delete was successful.
	 */
	public function delete_slug( $slug_name, $blog_id = null ) {
		global $wpdb;

		if ( is_null( $blog_id ) ) {
			$blog_id = get_current_blog_id();
		}

		$slugs = $this->_get_registered_slugs( $blog_id );
		$ret = isset( $slugs[$slug_name] );
		if ( $ret ) {
			$table_name = $wpdb->get_blog_prefix( $blog_id ) . 'log_slug';
			$vals = array( 'slug_id' => $slugs[$slug_name]->get_id() );
			$fmts = '%d';
			$ret = (bool)$wpdb->delete( $table_name, $vals, $fmts );
			if ( $ret ) {
				unset( $slugs[$slug_name] );
			}
		}

		return $ret;
	}

	/**
	 * @param $slug_name string The slug identifying who new entry belongs to.
	 * @param $log_level int The log level (should use ULogLevel consts).
	 * @param $entry string The log entry.
	 * @param $stacktrace null|string The stacktrace associated with the entry (default: null).
	 * @param $blog_id int The blog ID (default: current blog ID).
	 *
	 * @return bool Indicates whether insert was successful.
	 */
	public function insert_entry( $slug_name, $log_level, $entry, $stacktrace = null, $blog_id = null ) {
		global $wpdb;

		if ( is_null( $blog_id ) ) {
			$blog_id = get_current_blog_id();
		}

		$slugs = $this->_get_registered_slugs( $blog_id );
		$slug = $slugs[$slug_name];

		// short circuit log entries outside log level
		if ( $log_level < $slug->get_log_level() ) {
			return false;
		}

		$slug_id = $slug->get_id();
		$prefix = $wpdb->get_blog_prefix( $blog_id );

		$table_name = $prefix . 'log';
		$vals = array( 'slug_id' => $slug_id, 'log_level' => $log_level, 'entry' => $entry, 'entry_stacktrace' => $stacktrace );
		$fmts = array( '%d', '%d', '%s', '%s' );
		return (bool)$wpdb->insert( $table_name, $vals, $fmts );
	}

	/**
	 * @param $slug_name string The slug identifying who new entry belongs to.
	 * @param int $min_ts The minimum timestamp to be included.
	 * @param int $max_ts The maximum timestamp to be included.
	 * @param int $min_log_level The minimum log level to be included.
	 * @param int $max_log_level The maximum log level to be included.
	 * @param null $blog_id The blog ID (default: current blog ID).
	 *
	 * @return object[][] The entries, ordered oldest to newest. Each array has the following fields:
	 *                  log_level, entry, entry_time, and entry_stacktrace
	 */
	public function get_entries( $slug_name, $min_ts = UL_ILogger::MIN_TIMESTAMP, $max_ts = UL_ILogger::MAX_TIMESTAMP, $min_log_level = UL_ILogger::MIN_LOG_LEVEL, $max_log_level = UL_ILogger::MAX_LOG_LEVEL, $blog_id = null ) {
		global $wpdb;

		if ( is_null( $blog_id ) ) {
			$blog_id = get_current_blog_id();
		}

		$slugs = $this->_get_registered_slugs( $blog_id );
		$slug_id = $slugs[$slug_name]->get_id();
		$prefix = $wpdb->get_blog_prefix( $blog_id );

		$min_time = date("Y-m-d H:i:s", $min_ts);
		$max_time = date("Y-m-d H:i:s", $max_ts);

		$sql = "SELECT log_level, entry, entry_time, entry_stacktrace
FROM {$prefix}log
WHERE slug_id = %d
AND log_level BETWEEN %d AND %d
AND entry_time BETWEEN %s AND %s
ORDER BY entry_time ASC";
		return $wpdb->get_results( $wpdb->prepare( $sql, $slug_id, $min_log_level, $max_log_level, $min_time, $max_time ), ARRAY_A );
	}

	/**
	 * @param $slug_name string The slug identifying which entries are being purged.
	 * @param $ts int The timestamp marking threshold for deletion.
	 * @param $blog_id int The blog ID (default: current blog ID).
	 */
	public function delete_entries_older_than( $slug_name, $ts, $blog_id = null ) {
		global $wpdb;

		if ( is_null( $blog_id ) ) {
			$blog_id = get_current_blog_id();
		}

		$slugs = $this->_get_registered_slugs( $blog_id );
		$slug_id = $slugs[$slug_name]->get_id();
		$prefix = $wpdb->get_blog_prefix( $blog_id );

		$sql = "DELETE FROM {$prefix}log WHERE slug_id = %d AND entry_time < %s";
		$wpdb->query( $wpdb->prepare( $sql, $slug_id, date( 'Y-m-d h:i:s', $ts ) ) );
	}
}