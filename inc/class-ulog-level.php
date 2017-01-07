<?php
defined( 'WPINC' ) OR exit;

/**
 * LogLevel acts as an enumeration of all possible log levels.
 */
class ULogLevel {
	/**
	 * @var int Log level for anything that doesn't indicate a problem.
	 */
	const Detail = 0;

	/**
	 * @var int Log level for anything that is a minor issue.
	 */
	const Warning = 1;

	/**
	 * @var int Log level for when something went very wrong.
	 */
	const Error = 2;

	/**
	 * @var ReflectionClass Backs the getter.
	 */
	private static $ref = null;

	/**
	 * @return ReflectionClass Instance of reflection class for this class.
	 */
	private static function getReflectionClass() {
		if ( is_null( self::$ref ) ) {
			self::$ref = new ReflectionClass( __CLASS__ );
		}

		return self::$ref;
	}

	/**
	 * @var int[] Backs the getter.
	 */
	private static $levels = null;

	/**
	 * @return int[] Associative array containing all log level names mapped to their int value.
	 */
	public static function getLogLevels() {
		if ( is_null( self::$levels ) ) {
			$ref          = self::getReflectionClass();
			self::$levels = $ref->getConstants();
		}

		return self::$levels;
	}

	/**
	 * @param string $name Name to be checked for validity.
	 *
	 * @return bool Whether given name represents valid log level.
	 */
	public static function isValidName( $name ) {
		return array_key_exists( $name, self::getLogLevels() );
	}

	/**
	 * @param int $value Value to be checked for validity.
	 *
	 * @return bool Whether given value represents valid log level.
	 */
	public static function isValidValue( $value ) {
		return in_array( $value, self::getLogLevels() );
	}

	/**
	 * @param string $name The name for which to retrieve a value.
	 *
	 * @return int|null The value associated with the given name.
	 */
	public static function getValueByName( $name ) {
		$levels = self::getLogLevels();

		return array_key_exists( $name, self::getLogLevels() ) ? $levels[ $name ] : null;
	}

	/**
	 * @param int $value The value for which to retrieve a name.
	 *
	 * @return string|null The name associated with the given value.
	 */
	public static function getNameByValue( $value ) {
		$ret = array_search( $value, self::getLogLevels() );

		return ( false !== $ret ) ? $ret : null;
	}

	/**
	 * Blocks instantiation. All functions are static.
	 */
	private function __construct() {

	}
}