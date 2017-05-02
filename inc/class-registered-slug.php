<?php
defined( 'WPINC' ) OR exit;

class UL_RegisteredSlug {

	/**
	 * @param $id int The DB id.
	 * @param $slug string The slug.
	 * @param $purge_interval int The purge interval.
	 * @param $log_level int The log level.
	 */
	public function __construct( $id, $slug, $purge_interval,  $log_level ) {
		$this->id = $id;
		$this->slug = $slug;
		$this->log_level = $log_level;
		$this->purge_interval = $purge_interval;
	}

	/**
	 * Note that DB IDs are kept in code to avoid expensive joins
	 * across potentially thousands or millions of rows.
	 *
	 * @var int The unique ID originating from the DB.
	 */
	private $id;

	/**
	 * @return int The unique ID originating from the DB.
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * @param int $id The unique ID originating from the DB.
	 */
	public function set_id( $id ) {
		$this->id = $id;
	}

	/**
	 * @var string The slug identifying the logger.
	 */
	private $slug;

	/**
	 * @return string The slug identifying the logger.
	 */
	public function get_slug() {
		return $this->slug;
	}

	/**
	 * @param string $slug The slug identifying the logger.
	 */
	public function set_slug( $slug ) {
		$this->slug = $slug;
	}

	/**
	 * @see ULogLevel
	 * @var int The minimum log level to be stored.
	 */
	private $log_level;

	/**
	 * @see ULogLevel
	 * @return int The minimum log level to be stored.
	 */
	public function get_log_level() {
		return $this->log_level;
	}

	/**
	 * @see ULogLevel
	 * @param int $log_level The minimum log level to be stored.
	 */
	public function set_log_level( $log_level ) {
		$this->log_level = $log_level;
	}

	/**
	 * @var int The purge interval in hours. Entries older than this will be purged.
	 */
	private $purge_interval;

	/**
	 * @return int The purge interval in hours. Entries older than this will be purged.
	 */
	public function get_purge_interval() {
		return $this->purge_interval;
	}

	/**
	 * @param int $purge_interval The purge interval in hours. Entries older than this will be purged.
	 */
	public function set_purge_interval( $purge_interval ) {
		$this->purge_interval = $purge_interval;
	}
}