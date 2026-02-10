<?php

namespace NitroPack\WordPress\Settings;
use Nitropack\WordPress\NitroPack;

class CartCache {

	public function __construct() {
		add_action( 'wp_ajax_nitropack_set_cart_cache_ajax', [ $this, 'nitropack_set_cart_cache_ajax' ] );
	}

	/**
	 * AJAX handler when toggling the setting in the Dashboard
	 * @return void
	 */
	public function nitropack_set_cart_cache_ajax() {
		nitropack_verify_ajax_nonce( $_REQUEST );
		if ( get_nitropack()->isConnected() && class_exists( 'WooCommerce' ) ) {
			$cartCacheStatus = (int) ( ! empty( $_POST["cartCacheStatus"] ) );

			if ( $cartCacheStatus == 1 ) {
				$this->enable_cart_cache();
			} else {
				$this->disable_cart_cache();
			}
		}
		nitropack_json_and_exit( array(
			"type" => "error",
			"message" => nitropack_admin_toast_msgs( 'error' )
		) );
	}
	public function enable_cart_cache() {
		if ( null !== $nitro = get_nitropack_sdk() ) {
			try {
				$nitro->enableCartCache();
				NitroPack::getInstance()->getLogger()->notice( 'Cart cache is enabled' );
				nitropack_json_and_exit( array(
					"type" => "success",
					"message" => nitropack_admin_toast_msgs( 'success' )
				) );
			} catch (\Exception $e) {
				NitroPack::getInstance()->getLogger()->error( 'Cart cache cannot be enabled. Error: ' . $e );
			}
		}

		nitropack_json_and_exit( array(
			"type" => "error",
			"message" => nitropack_admin_toast_msgs( 'error' )
		) );
	}

	public function disable_cart_cache() {
		if ( null !== $nitro = get_nitropack_sdk() ) {
			try {
				$nitro->disableCartCache();
				NitroPack::getInstance()->getLogger()->notice( 'Cart cache is disabled' );
				nitropack_json_and_exit( array(
					"type" => "success",
					"message" => nitropack_admin_toast_msgs( 'success' )
				) );
			} catch (\Exception $e) {
				NitroPack::getInstance()->getLogger()->error( 'Cart cache cannot be disabled. Error: ' . $e );
			}
		}

		nitropack_json_and_exit( array(
			"type" => "error",
			"message" => nitropack_admin_toast_msgs( 'error' )
		) );
	}
	public function nitropack_is_cart_cache_active() {
		$nitro = get_nitropack()->getSdk();
		if ( $nitro ) {
			$config = $nitro->getConfig();
			if ( ! empty( $config->StatefulCache->Status ) && ! empty( $config->StatefulCache->CartCache ) ) {
				return $this->nitropack_is_cart_cache_available();
			}
		}
		return false;
	}
	public function nitropack_is_cart_cache_available() {
		$nitro = get_nitropack()->getSdk();
		if ( $nitro ) {
			$config = $nitro->getConfig();
			if ( ! empty( $config->StatefulCache->isCartCacheAvailable ) ) {
				return true;
			}
		}
		return false;
	}
	/**
	 * Renders the Cart Cache option in the Dashboard
	 * @return void
	 */
	public function render() {
		if ( class_exists( 'WooCommerce' ) ) { ?>
			<div class="nitro-option" id="cart-cache-widget">
				<div class="nitro-option-main">
					<div class="text-box">
						<h6><?php esc_html_e( 'Cart cache', 'nitropack' ); ?></h6>
						<p>
							<?php esc_html_e( 'Your visitors will enjoy full site speed while browsing with items in cart. Fully optimized page cache will be served.', 'nitropack' ); ?>
						</p>
					</div>
					<?php $components = new Components();
					$components->render_toggle( 'cart-cache-status', $this->nitropack_is_cart_cache_active(), [ 'disabled' => ! $this->nitropack_is_cart_cache_available() ] ); ?>
				</div>
				<?php if ( ! $this->nitropack_is_cart_cache_available() ) : ?>
					<div class="msg-container bg-success paid-msg">
						<p><svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"
								class="text-success">
								<g clip-path="url(#clip0_1244_36215)">
									<path
										d="M10.0001 18.3333C14.6025 18.3333 18.3334 14.6023 18.3334 9.99996C18.3334 5.39759 14.6025 1.66663 10.0001 1.66663C5.39771 1.66663 1.66675 5.39759 1.66675 9.99996C1.66675 14.6023 5.39771 18.3333 10.0001 18.3333Z"
										stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
									<path d="M13.3334 9.99996L10.0001 6.66663L6.66675 9.99996" stroke="currentColor" stroke-width="1.5"
										stroke-linecap="round" stroke-linejoin="round"></path>
									<path d="M10 13.3333V6.66663" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
										stroke-linejoin="round"></path>
								</g>
								<defs>
									<clipPath id="clip0_1244_36215">
										<rect width="20" height="20" fill="white"></rect>
									</clipPath>
								</defs>
							</svg>
							<?php esc_html_e( 'This feature is available on Plus plan and above.', 'nitropack' ); ?>
							<a href="https://app.nitropack.io/subscription/buy" class="text-primary"
								target="_blank"><b><?php esc_html_e( 'Upgrade here', 'nitropack' ); ?></b></a>
						</p>
					</div>
				<?php endif; ?>
			</div>
			<?php
		}
	}
}