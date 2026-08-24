<?php
namespace NitroPack\WordPress\Notifications;
use NitroPack\HttpClient\HttpClient;

/**
 * Class Dismiss
 *
 * Handles the dismissal of notifications in the NitroPack plugin for WordPress, mostly via AJAX.
 */
class Dismiss {
	/**
	 * Singleton instance of the Dismiss class.
	 *
	 * @var Dismiss|null
	 */
	private static $instance = null;

	public function __construct() {
		add_action( 'admin_init', [ $this, 'move_existing_notices' ] );
		//ajax
		add_action( 'wp_ajax_nitropack_dismiss_permanently_notification', [ $this, 'nitropack_dismiss_permanently_notification' ] );
		add_action( 'wp_ajax_nitropack_safemode_notification', [ $this, 'nitropack_safemode_notification' ] );
		add_action( 'wp_ajax_nitropack_dismiss_notification_by_transient', [ $this, 'nitropack_dismiss_notification_by_transient' ] );
		add_action( 'wp_ajax_nitropack_conflict_plugin_deactivate', [ $this, 'nitropack_conflict_plugin_deactivate' ] );
		add_action( 'wp_ajax_nitropack_cookie_path_ajax', [ $this, 'nitropack_cookie_path_ajax' ] );
		//not in use atm
		add_action( 'wp_ajax_nitropack_dismiss_hosting_notice', [ $this, 'nitropack_dismiss_hosting_notice' ] );
	}

	public static function getInstance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Handles the dismissal of a notification permanently by updating nitropack-dismissed-notices option in the database.
	 *
	 * @return void Outputs a JSON response and terminates the script execution.
	 */
	public function nitropack_dismiss_permanently_notification() {
		if ( ! Notifications::pass_notification_capabilities() ) {
			wp_send_json_error( [ 'message' => __( 'You do not have sufficient permissions.', 'nitropack' ) ] );
		}

		if ( empty( $_POST['notification_id'] ) ) {
			wp_send_json_error( [ 'message' => __( 'Missing notification ID.', 'nitropack' ) ] );
		}

		nitropack_verify_ajax_nonce( $_REQUEST );

		$notification_id = sanitize_text_field( wp_unslash( $_POST['notification_id'] ) );
		$notices = get_option( 'nitropack-dismissed-notices', [] );

		if ( ! in_array( $notification_id, $notices, true ) ) {
			$notices[] = $notification_id;
			update_option( 'nitropack-dismissed-notices', $notices );
		}

		wp_send_json_success();
	}
	/* Dismiss notification by using set_transient -> temporary dismissal with auto-expiry or by dismiss url if set by the app notification */
	public function nitropack_dismiss_notification_by_transient() {
		if ( ! Notifications::pass_notification_capabilities() ) {
			wp_die( __( 'You do not have sufficient permissions.', 'nitropack' ) );
		}

		if ( empty( $_POST['notification_id'] ) ) {
			wp_send_json_error( [ 'message' => __( 'Missing notification ID.', 'nitropack' ) ] );
		}
		nitropack_verify_ajax_nonce( $_REQUEST );

		$notification_id = $_POST['notification_id'];
		$notification_end = $_POST['notification_end'];
		$midpoint = ( time() + strtotime( $notification_end ) ) / 2;
		$notification_end = strtotime( $notification_end ) - time();

		//Use dimiss url from the app notification if set. It will remove it from notifications.json
		if ( ! empty( $_POST["dismiss_url"] ) ) {
			$dismiss_url = $_POST["dismiss_url"];
			$http_client = new HttpClient( $dismiss_url );
			$http_client->fetch( true, "GET" );
			$resp = $http_client->getStatusCode() == 200 ? json_decode( $http_client->getBody(), true ) : false;
			if ( $resp['status'] ) {
				$app_notifications = AppNotifications::getInstance();
				$removed = $app_notifications->removeNotificationById( $notification_id );
				if ( $removed ) {
					nitropack_json_and_exit( array(
						"status" => true,
					) );
				} else {
					wp_send_json_error( [ 'message' => __( 'Failed to dismiss the notification.', 'nitropack' ) ] );
				}
			} else {
				wp_send_json_error( [ 'message' => __( 'Failed to dismiss the notification.', 'nitropack' ) ] );
			}
		} else {
			//if there is not dismiss url, use the transient dismissal method which will remove the notification from the screen and it will not show it again until the time set in notification_end
			$transient_status = set_transient( $notification_id, $midpoint, $notification_end );
			nitropack_json_and_exit( array(
				"status" => $transient_status,
			) );
		}
	}
	/**
	 * Deactivates a conflicting plugin from NitroPack.
	 *
	 * It verifies the nonce for security and checks if the specified plugin is in the list of conflicting plugins 
	 * and finally deactivates it if it is active.
	 *
	 * @return void Outputs a JSON response indicating success or failure.
	 */
	public function nitropack_conflict_plugin_deactivate() {
		if ( ! Notifications::pass_notification_capabilities() ) {
			wp_send_json_error( [ 'message' => __( 'You do not have sufficient permissions.', 'nitropack' ) ] );
		}

		if ( empty( $_POST['plugin'] ) ) {
			wp_send_json_error( [ 'message' => __( 'Missing plugin.', 'nitropack' ) ] );
		}
		$plugin = sanitize_text_field( wp_unslash( $_POST['plugin'] ) );

		if ( empty( $_POST['plugin_name'] ) ) {
			wp_send_json_error( [ 'message' => __( 'Missing plugin name.', 'nitropack' ) ] );
		}
		$plugin_name = sanitize_text_field( wp_unslash( $_POST['plugin_name'] ) );

		nitropack_verify_ajax_nonce( $_REQUEST );

		// Check if the plugin is in the list of conflicting plugins for extra security measures.
		$conflictingPlugins = \NitroPack\WordPress\ConflictingPlugins::getInstance();
		$conflictingPlugins_list = $conflictingPlugins->nitropack_get_conflicting_plugins();
		$plugin_found = false;
		foreach ( $conflictingPlugins_list as $conflict_plugin ) {
			if ( $conflict_plugin['plugin'] === $plugin ) {
				$plugin_found = true;
				break;
			}
		}
		if ( ! $plugin_found ) {
			wp_send_json_error( [ 'message' => __( 'Plugin not found in the list of conflicting plugins.', 'nitropack' ) ] );
		}

		if ( is_plugin_active( $plugin ) ) {
			deactivate_plugins( $plugin );
			if ( ! is_plugin_active( $plugin ) ) {
				/* translators: %s: Name of the plugin that was deactivated */
				wp_send_json_success( [ 'message' => sprintf( esc_html__( '%s deactivated successfully.', 'nitropack' ), $plugin_name ) ] );
			} else {
				wp_send_json_error( [ 'message' => __( 'Failed to deactivate the plugin.', 'nitropack' ) ] );
			}
		} else {
			/* translators: %s: Name of the plugin */
			wp_send_json_error( [ 'message' => sprintf( esc_html__( '%s is not active.', 'nitropack' ), $plugin_name ) ] );
		}
	}
	public function nitropack_safemode_notification() {
		nitropack_verify_ajax_nonce( $_REQUEST );
		$components = new \NitroPack\WordPress\Settings\Components;
		$components->render_notification( 'Visitors are accessing your unoptimized pages. Make sure to disable it once you are done testing.', 'warning', 'Test Mode Enabled', false, [ 'test-mode' ] );
		wp_die();
	}

	/**
	 * AJAX handler to return the cookie path for the current site and remove the plugins/themes warning notification.
	 * 
	 * @return void
	 */
	public function nitropack_cookie_path_ajax() {
		nitropack_verify_ajax_nonce( $_REQUEST );
		nitropack_json_and_exit( array(
			'cookie_path' => nitropack_cookiepath()
		) );
	}

	/**
	 * Dismiss hosting notice. Not in use.
	 * @return void
	 */
	public function nitropack_dismiss_hosting_notice() {
		nitropack_verify_ajax_nonce( $_REQUEST );
		$hostingNoticeFile = nitropack_trailingslashit( NITROPACK_DATA_DIR ) . "hosting_notice";
		if ( WP_DEBUG ) {
			touch( $hostingNoticeFile );
		} else {
			@touch( $hostingNoticeFile );
		}
	}
	/**
	 * Moves existing dismissed notices to the new dismissed option as an array.
	 *
	 * Notices being migrated:
	 * - `nitropack-wcNotice` (mapped to `WooCommerce`)
	 * - `nitropack-noticeOptimizeCPT` (mapped to `OptimizeCPT`)
	 *
	 * @return void
	 */

	public function move_existing_notices() {
		$existing_notices = [ 'nitropack-wcNotice' => 'WooCommerce', 'nitropack-noticeOptimizeCPT' => 'OptimizeCPT' ];
		foreach ( $existing_notices as $notice => $new_notice ) {
			if ( get_option( $notice ) ) {
				$notices = get_option( 'nitropack-dismissed-notices', [] );
				if ( ! in_array( $notice, $notices, true ) ) {
					$notices[] = $new_notice;
					update_option( 'nitropack-dismissed-notices', $notices );
				}
				delete_option( $notice );
			}
		}
	}
}