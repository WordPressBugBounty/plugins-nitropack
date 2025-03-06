<?php

namespace NitroPack\Feature\Logger;

class Logger {
	private $nitro;
	public $separator;
	const ERROR = 3;
	const NOTICE = 2;
	const INFO = 1;

	/** Mapping of log levels to their string representations */
	private $level_value;

	/**
	 * Logger constructor.
	 *
	 * @param int $level The logging level (default: ERROR)
	 */
	public function __construct( $nitro ) {
		$this->nitro = $nitro;
		$this->separator = ';';
		$this->level_value = [ 
			self::ERROR => 'ERROR',
			self::NOTICE => 'NOTICE',
			self::INFO => 'INFO'
		];
	}

	/**
	 * Determines if the current execution environment is CLI (Command Line Interface).
	 *
	 * This function checks various indicators to determine if the script is being run from the command line.
	 * It returns true if any of the following conditions are met:
	 * - The STDIN constant is defined.
	 * - The PHP SAPI name is 'cli'.
	 * - The 'SHELL' key exists in the $_ENV array.
	 * - The $_SERVER array lacks 'REMOTE_ADDR' and 'HTTP_USER_AGENT', and has arguments in 'argv'.
	 * - The 'REQUEST_METHOD' key does not exist in the $_SERVER array.
	 *
	 * @return bool True if the script is running in CLI mode, false otherwise.
	 */
	private function is_cli() {
		if ( defined( 'STDIN' ) ) {
			return true;
		}

		if ( php_sapi_name() === 'cli' ) {
			return true;
		}

		if ( array_key_exists( 'SHELL', $_ENV ) ) {
			return true;
		}

		if ( empty( $_SERVER['REMOTE_ADDR'] ) and ! isset( $_SERVER['HTTP_USER_AGENT'] ) and count( $_SERVER['argv'] ) > 0 ) {
			return true;
		}

		if ( ! array_key_exists( 'REQUEST_METHOD', $_SERVER ) ) {
			return true;
		}

		return false;
	}
	/**
	 * Log a message with the specified level.
	 * The message will be logged only if the current log level meets the minimum log level requirement.
	 * The log level is first fetched from the config.json if the value is not found then it is fetched from the database.
	 * The reason is that when connecting or disconnecting NitroPack, the config.json is empty.
	 *
	 * @param int $level The log level of the message
	 * @param string $message The message to log
	 * @return void
	 */
	private function log( $level, $message ) {
		$siteConfig = $this->nitro->getSiteConfig();
		$configLevel = ! empty( $siteConfig['minimumLogLevel'] ) ? $siteConfig['minimumLogLevel'] : null;

		if ( ! $configLevel ) {
			if ( ! function_exists( 'get_option' ) ) {
				return;
			}

			$configLevel = (int) get_option( 'nitropack-minimumLogLevel', null );
		}

		// Check if the log level is set and if the current log level meets the minimum log level requirement
		if ( $configLevel === null || $level < $configLevel ) {
			return;
		}

		if ( ! $this->init_logs_dir() )
			return;

		$log_file = $this->get_log_file_path();
		$max_size = $this->get_max_log_filesize();

		if ( file_exists( $log_file ) && filesize( $log_file ) > $max_size ) {
			error_log( "NitroPack log file has reached maximum size. Logging stopped." );
			return;
		}

		$prepend = '';
		if ( $this->is_cli() )
			$prepend = '[CLI] ';
		$level = $this->level_value[ $level ];
		$content = [ 
			'Date' => date( 'Y-m-d H:i:s' ),
			'Level' => $level,
			'Message' => $prepend . $message,
		];

		$this->write_to_log_file( $log_file, $content );
	}
	public function error( $message ) {
		$this->log( self::ERROR, $message );
	}
	public function notice( $message ) {
		$this->log( self::NOTICE, $message );
	}
	public function info( $message ) {
		$this->log( self::INFO, $message );
	}
	/**
	 * Initialize the logs directory.
	 *
	 * @return bool True if the directory exists or was created successfully, false otherwise
	 */
	private function init_logs_dir() {
		if ( $this->data_logs_dir_exists() )
			return true;

		$create_dir = mkdir( NITROPACK_LOGS_DATA_DIR );

		if ( $create_dir ) {
			// Create .htaccess file with deny from all
			$htaccess_path = NITROPACK_LOGS_DATA_DIR . '/.htaccess';
			file_put_contents( $htaccess_path, "Order Allow,Deny\nAllow from all\n<FilesMatch \"\.(csv|zip)$\">\nOrder Deny,Allow\nAllow from all\n</FilesMatch>" );

			// Create empty index.html file
			$index_path = NITROPACK_LOGS_DATA_DIR . '/index.html';
			file_put_contents( $index_path, "" );
			return true;
		} else {
			error_log( "Failed to create nitroopack logs directory: " . NITROPACK_LOGS_DATA_DIR );
		}
		return false;
	}

	/**
	 * Check if the data logs directory exists.
	 *
	 * @return bool True if the directory exists, false otherwise
	 */
	private function data_logs_dir_exists() {
		return defined( "NITROPACK_LOGS_DATA_DIR" ) && is_dir( NITROPACK_LOGS_DATA_DIR );
	}

	/**
	 * Get the full path of the log file.
	 *
	 * @return string The log file path
	 */
	private function get_log_file_path() {
		return nitropack_trailingslashit( NITROPACK_LOGS_DATA_DIR ) . $this->get_log_filename();
	}

	/**
	 * Get the log filename based on the current date.
	 *
	 * @return string The log filename
	 */
	private function get_log_filename() {
		return date( 'Y-m-d' ) . '_nitropack_log.csv';
	}

	/**
	 * Get the maximum log file size.
	 *
	 * @return int The maximum log file size in bytes
	 */
	private function get_max_log_filesize() {
		return apply_filters( 'nitropack_max_log_filesize', 20 * 1024 * 1024 ); // 20MB
	}

	/**
	 * Rotate the log file by renaming it with a timestamp.
	 *
	 * @param string $log_file The path to the log file
	 * @return bool True if rotation was successful, false otherwise
	 */
	private function rotate_log_file( $log_file ) {
		$rotated_file = $log_file . '.' . date( 'Y-m-d-H-i-s' );
		return rename( $log_file, $rotated_file );
	}

	/**
	 * Write content to the log file.
	 *
	 * @param string $log_file The path to the log file
	 * @param array $content The content to write to the log file
	 * @return void
	 */
	private function write_to_log_file( $log_file, $content ) {
		$file_exists = file_exists( $log_file );
		$file_handle = fopen( $log_file, 'a' );

		if ( $file_handle === false ) {
			error_log( "Failed to open nitropack log file: $log_file" );
			return;
		}

		//chmod($log_file, $this->get_chmod());

		if ( ! $file_exists ) {
			fputcsv( $file_handle, array_keys( $content ), $this->separator );
		}

		fputcsv( $file_handle, $content, $this->separator );
		fclose( $file_handle );
	}
}