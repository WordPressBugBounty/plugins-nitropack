<?php

namespace NitroPack\WordPress\Settings;

/**
 * Logger Class for NitroPack WordPress plugin
 *
 * This class provides logging functionality with different log levels
 */
class Logger {
	/**
	 * @var mixed $siteConfig Configuration settings for the site.
	 * @var string $archive_zip_name Name of the archive zip file for logs.
	 * @var string $log_file_extension Extension of the log files.
	 */
	public $siteConfig;
	public $archive_zip_name;
	public $log_file_extension;

	public function __construct( $config ) {

		add_action( 'wp_ajax_nitropack_set_log_level_ajax', [ $this, 'nitropack_set_log_level_ajax' ] );
		add_action( 'wp_ajax_nitropack_archive_logs_ajax', [ $this, 'nitropack_archive_logs_ajax' ] );
		add_action( 'wp_ajax_nitropack_download_log_ajax', [ $this, 'nitropack_download_log_ajax' ] );

		$this->siteConfig = $config;
		$this->archive_zip_name = 'nitropack_logs.zip';
		$this->log_file_extension = 'csv';
	}
	/**
	 * Render the logger settings in System Report
	 */
	public function render() {
		$components = new Components();
		$siteConfig = nitropack_get_site_config();
		$minimumLogLevel = $siteConfig['minimumLogLevel'];
		?>
		<div class="flex">
			<div class="" style="flex-basis: 80%;">
				<h3><?php esc_html_e( 'Generate Log File', 'nitropack' ); ?></h3>
				<p><?php esc_html_e( 'Logs keep track of what is happening on your site—like when plugins are installed, posts are published, or something goes wrong. They\'re great for finding and fixing tricky problems, especially with our support\'s help. Logs are saved for up to 14 days.', 'nitropack' ); ?>
					<a href="https://support.nitropack.io/en/articles/10243831-how-to-use-the-generate-log-file-feature-in-your-wordpress-dashboard"
						target="_blank"><?php esc_html_e( 'Learn more', 'nitropack' ); ?></a>
				</p>
			</div>
			<div class="ml-auto">
				<?php $components->render_toggle( 'minimum-log-level-status', $minimumLogLevel ); ?>
			</div>
		</div>
		<div class="logging<?php echo $minimumLogLevel ? '' : ' hidden'; ?>">
			<div class="select-log-level">
				<h4><?php esc_html_e( 'Select log level', 'nitropack' );
				$components->render_tooltip( 'select-log-level', 'Select and generate log output by categorizing messages based on their urgency.' ); ?>
				</h4>
				<div class="radio-options-group">
					<?php $components->render_fancy_radio( 3, 'set-log-level-error', 'set-log-level', $minimumLogLevel === 3 ? true : false, esc_html__( 'Minimal (Errors Only)', 'nitropack' ), esc_html__( 'Perfect for live websites where you can quickly spot and address serious issues without unnecessary noise.', 'nitropack' ) );
					$components->render_fancy_radio( 2, 'set-log-level-info', 'set-log-level', $minimumLogLevel === 2 ? true : false, esc_html__( 'Moderate (Errors & Actions)', 'nitropack' ), esc_html__( 'Best used during site maintenance or troubleshooting to get a clearer view of your site\'s activities.', 'nitropack' ) );
					$components->render_fancy_radio( 1, 'set-log-level-info', 'set-log-level', $minimumLogLevel === 1 ? true : false, esc_html__( 'Detailed (Errors, Actions & Full Details)', 'nitropack' ), esc_html__( 'Great when debugging issues with our support team. Avoid using it constantly as it is very detailed and may fill up quickly.', 'nitropack' ) ); ?>
				</div>
			</div>

			<div class="log-table-container">
				<table id="log-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Log Name', 'nitropack' ); ?></th>
							<th><?php esc_html_e( 'File size', 'nitropack' ); ?></th>
							<th><?php esc_html_e( 'Date added', 'nitropack' ); ?></th>
							<th class="hidden"><?php esc_html_e( 'Action', 'nitropack' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( is_dir( NITROPACK_LOGS_DATA_DIR ) ) :
							$files = scandir( NITROPACK_LOGS_DATA_DIR );
							$files = array_filter( $files, function ($file) {
								return pathinfo( $file, PATHINFO_EXTENSION ) === $this->log_file_extension;
							} );

							// Sort files by modification time, newest first
							usort( $files, function ($a, $b) {
								$filePathA = NITROPACK_LOGS_DATA_DIR . '/' . $a;
								$filePathB = NITROPACK_LOGS_DATA_DIR . '/' . $b;
								return filemtime( $filePathB ) - filemtime( $filePathA );
							} );
							if ( ! empty( $files ) ) :
								$fileDates = [];
								foreach ( $files as $file ) :
									$filePath = NITROPACK_LOGS_DATA_DIR . '/' . $file;
									$fileURL = $this->get_logs_dir_url() . $file;
									$fileSize = filesize( $filePath );
									$fileDate = date( "F d, Y H:i", filemtime( $filePath ) );
									$fileDates[] = filemtime( $filePath );
									$fileDateParts = explode( ' ', $fileDate );
									$datePart = implode( ' ', array_slice( $fileDateParts, 0, 3 ) );
									$timePart = $fileDateParts[3]; ?>
									<tr>
										<td class="file"><a href="<?php echo esc_url( $fileURL ); ?>"
												target="_blank"><?php echo esc_html( $file ); ?></a></td>
										<td class="file-size"><?php echo esc_html( size_format( $fileSize ) ); ?></td>
										<td class="file-date"><?php echo "{$datePart} <div class='time'>{$timePart}</div>"; ?></td>
										<td class="action hidden"><?php $components->render_button( [ 
											'text' => 'Download',
											'type' => 'a',
											'href' => esc_url( $fileURL ),
											'icon' => 'download.svg',
											'classes' => 'btn btn-secondary download-log',
											'attributes' => [ 'download' => '' ]
										] ); ?></td>
									</tr>
								<?php endforeach;
							else :
								echo '<tr class="no-logs"><td colspan="4">' . esc_html__( 'No error logs yet.', 'nitropack' ) . '</td></tr>';
							endif;
						else :
							echo '<tr class="no-logs"><td colspan="4">' . esc_html__( 'No error logs yet.', 'nitropack' ) . '</td></tr>';
						endif; ?>
					</tbody>
				</table>
			</div>
			<?php
			if ( ! empty( $fileDates ) ) {
				rsort( $fileDates );
				$latestDate = date( "F d, Y", $fileDates[0] );
				$earliestDate = date( "F d, Y", end( $fileDates ) );
				?>
				<div class="download-all-logs">
					<div>
						<h3><?php esc_html_e( 'Download all logs', 'nitropack' ); ?></h3>
						<p><?php printf( esc_html__( 'Download usage for %1$s - %2$s', 'nitropack' ), esc_html( $earliestDate ),
							esc_html( $latestDate )
						); ?>
						</p>
					</div>
					<?php if ( class_exists( 'ZipArchive' ) ) {
						$components->render_button( [ 
							'text' => 'Download all (.zip)',
							'type' => 'a',
							'href' => '',
							'icon' => 'download.svg',
							'classes' => 'btn btn-secondary archive-logs',
							'attributes' => [ 'download' => '' ]
						] );
					} else {
						$components->render_button( [ 
							'text' => 'Download all (.zip)',
							'type' => 'a',
							'href' => '',
							'icon' => 'download.svg',
							'classes' => 'btn btn-secondary archive-logs disabled',
						] );
					} ?>
				</div>
				<?php if ( ! class_exists( 'ZipArchive' ) ) {
					$components->render_notification( esc_html__( 'Bulk log downloads aren\'t available because the PHP ext-zip isn\'t enabled. Contact your hosting provider or download files individually.', 'nitropack' ), 'error' );
				} ?>
			<?php } ?>
		</div>
		<?php
	}

	/**
	 * Handles the AJAX request to set the log level for NitroPack.
	 *
	 * This method verifies the AJAX nonce, retrieves the minimum log level from the POST request,
	 * updates the site configuration with the new log level, and returns a JSON response indicating
	 * success or failure.
	 * $minimumLogLevel is set to 1 for info logs and 2 for error logs, and null if the log level is not set.
	 * @return void
	 */
	public function nitropack_set_log_level_ajax() {
		nitropack_verify_ajax_nonce( $_REQUEST );

		$minimumLogLevel = isset( $_POST['minimum_log_level'] ) ? $_POST['minimum_log_level'] : null;

		if ( $minimumLogLevel && is_numeric( $minimumLogLevel ) ) {
			$minimumLogLevel = (int) $minimumLogLevel;
		} else {
			$minimumLogLevel = null;
		}

		$siteConfig = $this->siteConfig->get();
		$configKey = \NitroPack\WordPress\NitroPack::getConfigKey();
		$siteConfig[ $configKey ]['minimumLogLevel'] = $minimumLogLevel;

		//store it as null or integer in config.json
		$config_updated = $this->siteConfig->set( $siteConfig );

		//store it as empty string or numeric string in the database
		if ( $minimumLogLevel === null ) {
			$minimumLogLevel = '';
		}
		$updated = update_option( 'nitropack_minimumLogLevel', $minimumLogLevel );
		if ( $config_updated && $updated ) {

			nitropack_json_and_exit( array( "type" => "success", "message" => nitropack_admin_toast_msgs( 'success' ) ) );
		} else {
			nitropack_json_and_exit( array(
				"type" => "error",
				"message" => nitropack_admin_toast_msgs( 'error' )
			) );
		}
	}

	/**
	 * Archives log files into a zip archive and returns the archive URL via AJAX.
	 *
	 * This function is triggered via an AJAX request and performs the following steps:
	 * 1. Verifies the AJAX nonce.
	 * 2. Creates a new ZipArchive instance.
	 * 3. Iterates through the log files in the specified directory and stores mmodification date
	 * 4. Filters out non-csv files, checks if the file has been modified and adds it to the zip archive.
	 * 5. Closes the zip archive.
	 * 6. Returns a success message and the archive URL if the process is successful.
	 * 7. Returns an error message if any step fails.
	 *
	 * @return void
	 */

	public function nitropack_archive_logs_ajax() {
		nitropack_verify_ajax_nonce( $_REQUEST );

		$zip = new \ZipArchive();
		$archivePath = $this->get_archive_zip_path();

		if ( $zip->open( $archivePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) === TRUE ) {
			$files = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( NITROPACK_LOGS_DATA_DIR ),
				\RecursiveIteratorIterator::LEAVES_ONLY
			);

			// Store the modification times of the existing files in the ZIP archive
			$existingFiles = [];
			for ( $i = 0; $i < $zip->numFiles; $i++ ) {
				$stat = $zip->statIndex( $i );
				$existingFiles[ $stat['name'] ] = $stat['mtime'];
			}

			foreach ( $files as $name => $file ) {
				if ( ! $file->isDir() ) {
					$filePath = $file->getRealPath();
					$relativePath = substr( $filePath, strlen( NITROPACK_LOGS_DATA_DIR ) );

					// Filter out non-csv files
					if ( pathinfo( $filePath, PATHINFO_EXTENSION ) !== $this->log_file_extension )
						continue;

					// Check if the file has been modified
					$fileMTime = filemtime( $filePath );
					if ( ! isset( $existingFiles[ $relativePath ] ) || $fileMTime > $existingFiles[ $relativePath ] ) {
						if ( ! $zip->addFile( $filePath, $relativePath ) ) {
							error_log( "Failed to add file: $filePath" );
							nitropack_json_and_exit( array(
								"type" => "error",
								"message" => nitropack_admin_toast_msgs( 'error' )
							) );
						}
					}
				}
			}

			if ( ! $zip->close() ) {
				error_log( "Failed to close zip archive: " . $archivePath );
				nitropack_json_and_exit( array(
					"type" => "error",
					"message" => nitropack_admin_toast_msgs( 'error' )
				) );
			}

			// Check if the archive file exists
			if ( file_exists( $archivePath ) ) {
				$archive_url = $this->get_archive_url();
				error_log( "Archive URL: " . $archive_url );

				nitropack_json_and_exit( array( "type" => "success", "message" => nitropack_admin_toast_msgs( 'success' ), "url" => $archive_url ) );
			} else {
				error_log( "Archive file does not exist: " . $archivePath );
				nitropack_json_and_exit( array(
					"type" => "error",
					"message" => nitropack_admin_toast_msgs( 'error' )
				) );
			}
		} else {
			error_log( "Failed to open zip archive: " . $archivePath );
			nitropack_json_and_exit( array(
				"type" => "error",
				"message" => nitropack_admin_toast_msgs( 'error' )
			) );
		}
	}
	/**
	 * Gets the url of the log dir
	 * Example: https://website.com/wp-content/wp-content/uploads/nitropack-logs/
	 * Use the filter if you have changed the constant NITROPACK_LOGS_DATA_DIR
	 * @return string
	 */
	public function get_logs_dir_url() {
		$dir_url = content_url() . '/uploads/nitropack-logs/';
		return apply_filters( 'nitropack_logs_dir_url', $dir_url );
	}
	/**
	 * Gets the path of the nitropack_logs.zip 
	 * Example: /var/www/html/wp-content/uploads/nitropack-logs/nitropack_logs.zip
	 * @return string
	 */
	private function get_archive_zip_path() {
		$path = NITROPACK_LOGS_DATA_DIR . $this->archive_zip_name;
		return $path;
	}
	/**
	 * Gets the path of the nitropack_logs.zip 
	 * Example: https://website.com/wp-content/uploads/nitropack-logs/nitropack_logs.zip
	 * @return string
	 */
	private function get_archive_url() {
		$url = $this->get_logs_dir_url() . $this->archive_zip_name;
		return $url;
	}
}
