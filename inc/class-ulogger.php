<?php
defined( 'WPINC' ) OR exit;

include_once UniversalLogger::get_path() . 'inc/class-ulog-level.php';
include_once UniversalLogger::get_path() . 'inc/interface-logger.php';

/**
 * Encapsulates the logic required to maintain and read log files.
 */
class ULogger {

	/**
	 * @var UL_ILogger The logger implementation.
	 */
	private static $logger;

	/**
	 * @return \UL_ILogger The logger implementation.
	 */
	public static function init() {
		// NOTE: May be filterable at later date.
		include_once UniversalLogger::get_path() . 'inc/class-database-logger.php';
		self::$logger = new UL_DatabaseLogger();
		add_action( UniversalLogger::PurgeLogsAction, array( __CLASS__, 'purge_expired_entries' ) );
	}

	/**
	 * Appends DG log file if logging is enabled.
	 *
	 * @param string $slug_name The slug identifying who new entry belongs to.
	 * @param int $log_level The log level (should use ULogLevel consts).
	 * @param string $entry The log entry.
	 * @param int $stacktrace_skip The number of frames to skip in stacktrace relative to within this function.
	 * Default is to just exclude the last frame (this function).
	 * @param bool $stacktrace Whether to include full stack trace (default: false).
	 */
	public static function insert_entry( $slug_name, $log_level, $entry, $stacktrace_skip = 1, $stacktrace = false ) {
		if ( ! ULogLevel::isValidValue( $log_level ) ) {
			throw new InvalidArgumentException( "Invalid log level given." );
		}

		if ( $stacktrace_skip < 0 ) {
			throw new InvalidArgumentException( "Stacktrace skip cannot be less than zero." );
		}

		$stacktrace_str = null;

		// get backtrace excluding $stacktrace_skip number of frames
		$trace = array_slice( debug_backtrace( false ), $stacktrace_skip );

		if ( $stacktrace ) {
			$fields[] = self::get_stack_trace_string( $trace );
		} else {
			$caller    = $trace[0];

			$class = isset( $caller['class'] ) ? $caller['class'] : '';
			$type = isset( $caller['type'] ) ? $caller['type'] : '';
			$caller    = $class . $type . $caller['function'];

			$entry = '(' . $caller . ') ' . $entry;
		}

		self::$logger->insert_entry( $slug_name, $log_level, $entry, $stacktrace_str );
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
	public static function get_entries( $slug_name, $min_ts = 0, $max_ts = UL_ILogger::TS_3000, $min_log_level = ULogLevel::Detail, $max_log_level = ULogLevel::Error, $blog_id = null ) {
		if ( $min_ts < 0 || $max_ts < $min_ts ) {
			throw new InvalidArgumentException( "Invalid timestamp given." );
		}

		if ( ! ULogLevel::isValidValue( $min_log_level ) || ! ULogLevel::isValidValue( $max_log_level ) ) {
			throw new InvalidArgumentException( "Invalid log level given." );
		}

		return self::$logger->get_entries( $slug_name, $min_ts, $max_ts, $min_log_level, $max_log_level, $blog_id );
	}

	/**
	 * @param $slug_name string The slug identifying who new entry belongs to.
	 * @param $blog_id int|null The blog ID (default: current blog ID).
	 *
	 * @return null|UL_RegisteredSlug The slug with the given name.
	 */
	public static function get_slug( $slug_name, $blog_id = null ) {
		return self::$logger->get_slug( $slug_name, $blog_id );
	}

	/**
	 * @param $slug_name string The slug identifying who new entry belongs to.
	 * @param $blog_id int The blog ID (default: current blog ID).
	 *
	 * @return bool Indicates whether delete was successful.
	 */
	public static function delete_slug( $slug_name, $blog_id = null ) {
		self::$logger->delete_slug( $slug_name, $blog_id );
	}

	/**
	 * @param $slug_name string The slug identifying who new entry belongs to.
	 * @param int $purge_interval The interval at which log entries should be purged (in hours).
	 * @param $log_level int The log level (should use ULogLevel consts).
	 *
	 * @return bool Indicates whether upsert was successful.
	 */
	public static function upsert_slug( $slug_name, $purge_interval = 168, $log_level = ULogLevel::Warning, $blog_id = null ) {
		if ( ! ULogLevel::isValidValue( $log_level ) ) {
			throw new InvalidArgumentException( "Invalid log level given." );
		}

		return self::$logger->upsert_slug( $slug_name, $purge_interval, $log_level, $blog_id );
	}

	/**
	 * Truncates all blog logs to the current purge interval.
	 */
	public static function purge_expired_entries() {
		self::insert_entry( UniversalLogger::Slug, ULogLevel::Detail, 'Beginning scheduled log file purge.' );

		$blogs = ! is_multisite() ? array( get_current_blog_id() ) : UL_Util::get_blog_ids();

		// truncate each blog's log file
		$time = time();
		foreach ( $blogs as $blog_num ) {
			$slugs = self::$logger->get_registered_slugs( $blog_num );
			if ( is_null( $slugs ) ) {
				continue;
			}

			foreach ( $slugs as $slug_name => $slug ) {
				$purge_time = $time - $slug->get_purge_interval() * HOUR_IN_SECONDS;
				self::$logger->delete_entries_older_than( $slug_name, $purge_time, $blog_num );
			}
		}
	}

	/**
	 * Generally not necessary to call external to this class -- only use if generating
	 * log entry will take significant resources and you want to avoid this operation
	 * if it will not actually be logged.
	 *
	 * @param $slug_name string The slug identifying who new entry belongs to.
	 * @param $log_level int The log level (should use ULogLevel consts).
	 * @param $blog_id int The blog ID (default: current blog ID).
	 *
	 * @return bool Whether given log level can be logged.
	 */
	public static function can_log( $slug_name, $log_level, $blog_id = null ) {
		$slug = self::$logger->get_slug( $slug_name, $blog_id );
		return $slug != null && ULogLevel::isValidValue( $log_level ) && $log_level >= $slug->get_log_level();
	}

	/**
	 * @param mixed[][] $trace Array containing stack trace to be converted to string.
	 *
	 * @return string The stack trace in human-readable form.
	 */
	private static function get_stack_trace_string( $trace ) {
		$trace_str = '';
		$i         = 1;

		foreach ( $trace as $node ) {
			$trace_str .= "#$i ";

			$file = '';
			if ( isset( $node['file'] ) ) {
				// convert to relative path from WP root
				$file = str_replace( ABSPATH, '', $node['file'] );
			}

			if ( isset( $node['line'] ) ) {
				$file .= "({$node['line']})";
			}

			if ( $file ) {
				$trace_str .= "$file: ";
			}

			if ( isset( $node['class'] ) ) {
				$trace_str .= "{$node['class']}{$node['type']}";
			}

			if ( isset( $node['function'] ) ) {
				// only include args for first item in stack trace
				$args = '';
				if ( 1 === $i && isset( $node['args'] ) ) {
					$args = implode( ', ', array_map( array( __CLASS__, 'print_r' ), $node['args'] ) );
				}

				$trace_str .= "{$node['function']}($args)" . PHP_EOL;
			}
			$i++;
		}

		return $trace_str;
	}

	/**
	 * Wraps print_r passing true for the return argument.
	 *
	 * @param mixed $v Value to be printed.
	 *
	 * @return string Printed value.
	 */
	private static function print_r( $v ) {
		return preg_replace( '/\s+/', ' ', print_r( $v, true ) );
	}

	/**
	 * Blocks instantiation. All functions are static.
	 */
	private function __construct() {

	}
}

// initialize class variables
ULogger::init();