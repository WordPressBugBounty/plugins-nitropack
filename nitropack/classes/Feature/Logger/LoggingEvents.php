<?php

namespace NitroPack\Feature\Logger;

/**
 * Class LoggingEvents
 *
 * This class is responsible for handling logging events within the NitroPack application.
 * It provides methods to log various events and activities for debugging and monitoring purposes.
 *
 * @package NitroPack\Feature\Logger
 */
class LoggingEvents {
	private $logger;
	public function __construct( $logger ) {
		add_action( 'updated_option', [ $this, 'plugin_activated' ], 10, 3 );
		add_action( 'updated_option', [ $this, 'plugin_deactivated' ], 10, 3 );
		add_action( 'switch_theme', [ $this, 'theme_switch' ], 10, 1 );
		add_action( 'init', [ $this, 'plugin_added' ], 10 );
		add_action( 'wp_after_insert_post', [ $this, 'insert_post' ], 10, 3 );
		add_action( 'post_updated', [ $this, 'update_post' ], 10, 3 );
		add_action( 'delete_post', [ $this, 'delete_post' ], 10, 2 );
		add_action( 'wp_verify_nonce_failed', [ $this, 'verify_nonce_failed' ], 10, 4 );

		$this->logger = $logger;
	}
	/**
	 * Handle the event when a plugin is activated.
	 *
	 * @param string $option_name Option name.
	 * @param mixed $old_value Old value.
	 * @param mixed $value New value.
	 *
	 * @return void
	 */
	public function plugin_activated( $option_name, $old_value, $value ) {
		if ( 'active_plugins' === $option_name ) {
			$diff = array_diff( $value, $old_value );
			if ( ! empty( $diff ) ) {
				$this->logger->notice( 'Plugin activated: ' . implode( ',', $diff ) );
			}
		}
	}

	/**
	 * Handle the event when a plugin is deactivated.
	 *
	 * @param string $option_name Option name.
	 * @param mixed $old_value Old value.
	 * @param mixed $value New value.
	 *
	 * @return void
	 */
	public function plugin_deactivated( $option_name, $old_value, $value ) {
		if ( 'active_plugins' === $option_name ) {
			$diff = array_diff( $old_value, $value );
			if ( ! empty( $diff ) ) {
				$this->logger->notice( 'Plugin deactivated: ' . implode( ',', $diff ) );
			}
		}
	}
	/**
	 * Handle the event when a plugin is activated.
	 *
	 * @param string $option_name Option name.
	 * @param mixed $old_value Old value.
	 * @param mixed $value New value.
	 *
	 * @return void
	 */
	public function theme_switch( $new_name ) {
		$this->logger->notice( 'Theme switched to: ' . $new_name );
	}
	/** 
	 * Handle the event when a plugin is added.
	 *
	 * @return void
	 */
	public function plugin_added() {
		$plugin = isset( $_REQUEST['slug'] ) ? $_REQUEST['slug'] : '';
		$action = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';
		if ( $plugin && 'install-plugin' === $action ) {
			$this->logger->notice( 'Plugin added: ' . $plugin );
		}
	}

	/**
	 * Handle the event when an optimized post is inserted.
	 *
	 * @param int      $post_ID Post ID.
	 * @param \WP_Post $post Post object.
	 * @param bool     $update Whether this is an existing post being updated or not.
	 * @param \WP_Post $post_before Post object.
	 *
	 * @return void
	 */
	public function insert_post( $post_ID, $post, $update ) {
		$not_allowed_statuses = array( 'auto-draft', 'draft' );
		if ( is_null( $post ) || ! $this->is_post_marked_for_optimization( $post ) || in_array( $post->post_status, $not_allowed_statuses ) ) {
			return;
		}

		if ( ! $update ) {
			$this->logger->notice( 'Post inserted: ' . $post_ID );
		}
	}

	/**
	 * Handle the event when an optimized post is updated
	 *
	 * @param int      $post_ID Post ID.
	 * @param \WP_Post $post Post object.
	 * @param \WP_Post $post_before Post object.
	 *
	 * @return void
	 */
	public function update_post( $post_ID, $post, $post_before ) {
		$not_allowed_statuses = array( 'auto-draft', 'draft' );
		if ( is_null( $post ) || ! $this->is_post_marked_for_optimization( $post ) || in_array( $post->post_status, $not_allowed_statuses ) ) {
			return;
		}
		if ( $post != $post_before ) {
			$this->logger->notice( 'Post updated: ' . $post_ID );
		}
	}

	/**
	 * Handle the event when an optimized post is deleted.
	 *
	 * @param int $post_ID Post ID.
	 *
	 * @return void
	 */
	public function delete_post( $post_ID, $post ) {
		if ( ! is_numeric( $post_ID ) || ! $this->is_post_marked_for_optimization( $post ) )
			return;

		$this->logger->notice( 'Post deleted: ' . $post_ID );
	}

	/**
	 * Checks if a post is marked for optimization.
	 *
	 * @param \WP_Post $post The post object to check.
	 * @return bool True if the post is marked for optimization, false otherwise.
	 */
	private function is_post_marked_for_optimization( $post ) {
		$get_optimized_CPTs = nitropack_get_optimized_CPTs();
		if ( empty( $get_optimized_CPTs ) ) {
			return false;
		}

		return in_array( $post->post_type, $get_optimized_CPTs );
	}
	/**
	 * Handle the event when NITROPACK NONCE verification fails.
	 *
	 * @param string $nonce The nonce that failed verification.
	 * @param string $action The action associated with the nonce.
	 * @param WP_User|null $user The user who failed verification.
	 * @param string $token The token associated with the nonce.
	 *
	 * @return void
	 */
	public function verify_nonce_failed( $nonce, $action, $user, $token ) {

		if ( $nonce !== NITROPACK_NONCE )
			return;

		$this->logger->notice( 'Nonce verification failed: ' . $nonce . ', Action: ' . $action . ', User: ' . ( $user ? $user->ID : 'N/A' ) . ', Token: ' . $token );
	}
}
