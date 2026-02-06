<div id="nitropack-connect">
	<div class="nitropack-connect-inner card">
		<div class="row">
			<div class="left-col">
				<div class="progress-wrapper">
					<div class="progress-bar">
						<div class="progress" style="width: 33.33%;"></div>
					</div>
					<div class="step"><?php esc_html_e( 'Step 1/3', 'nitropack' ); ?></div>
				</div>
				<div class="headline-container">
					<h1><?php esc_html_e( 'Connect NitroPack to your WordPress site', 'nitropack' ); ?></h1>
					<p><?php esc_html_e( 'You\'re just one click away from connecting NitroPack to your site.', 'nitropack' ); ?>
					</p>
				</div>
				<form action="options.php" method="post" id="api-details-form" style="display: none">
					<div id="manual-connect-fields">
						<div class="form-row">
							<label><span><?php esc_html_e( 'API key', 'nitropack' ); ?></span>
								<div class="tooltip"><span class="tooltip-icon" data-tooltip-target="tooltip-api-key">
										<img src="<?php echo plugin_dir_url( __FILE__ ) . 'images/info.svg'; ?>">
									</span>
									<div id="tooltip-api-key" role="tooltip" class="tooltip-container hidden">
										<?php
										esc_html_e( 'API Key is a unique alphanumeric identifier assigned to each website using NitroPack.', 'nitropack' );
										?>
										<div class="tooltip-arrow" data-popper-arrow></div>
									</div>
								</div>
								<input id="nitropack-siteid-input" name="nitropack-siteId" type="text"
									class="form-control" placeholder="<?php esc_html_e( 'API key ', 'nitropack' ); ?>">
							</label>
						</div>
						<div class="form-row">
							<label><span><?php esc_html_e( 'API secret key', 'nitropack' ); ?></span>
								<div class="tooltip"><span class="tooltip-icon"
										data-tooltip-target="tooltip-secret-key">
										<img src="<?php echo plugin_dir_url( __FILE__ ) . 'images/info.svg'; ?>">
									</span>
									<div id="tooltip-secret-key" role="tooltip" class="tooltip-container hidden">
										<?php
										esc_html_e( 'Site secret is a confidential alphanumeric key associated with your website, designed to ensure secure communication between NitroPack and your site.', 'nitropack' );
										?>
										<div class="tooltip-arrow" data-popper-arrow></div>
									</div>
								</div>
								<input id="nitropack-sitesecret-input" name="nitropack-siteSecret" type="text"
									class="form-control"
									placeholder="<?php esc_html_e( 'API secret key', 'nitropack' ); ?>">
								<p class="learn-more">
									<?php esc_html_e( 'Learn where to find your site\'s API details', 'nitropack' ); ?>
									<a href="https://nitropack.io/blog/post/how-to-get-your-api-keys"
										target="_blank"><?php esc_html_e( 'here', 'nitropack' ); ?></a></a>
							</label>
						</div>
					</div>
				</form>
				<a href="#" class="btn btn-primary btn-xl w-100" id="connect-nitropack">
					<img src="<?php echo plugin_dir_url( __FILE__ ) . 'images/loading.svg'; ?>" alt="loading"
						class="icon-left hidden">
					<?php esc_html_e( 'Connect NitroPack', 'nitropack' ); ?>
				</a>
				<div class="help main">
					<?php _e( 'Having trouble connecting? Explore our <a href="#" class="btn-manual-connect">manual connect</a> option, browse our <a href="https://support.nitropack.io/en/collections/6175768-nitropack-for-wordpress-and-woocommerce" target="_blank">FAQ section</a>, or reach out to our <a href="https://support.nitropack.io/en/" target="_blank">support team</a>.', 'nitropack' ); ?>
				</div>
				<div class="help manual" style="display: none"><?php esc_html_e( 'Or', 'nitropack' ); ?> <a href="#"
						class="btn-automatic-connect"><?php esc_html_e( 'connect automatically', 'nitropack' ); ?></a>
				</div>
			</div>
			<div class="right-col logos">
				<div id="nitropack-wp-animation"></div>
				<script>
					lottie.loadAnimation({
						container: document.getElementById('nitropack-wp-animation'),
						renderer: 'svg',    // Render as 'svg', 'canvas', or 'html'
						loop: false,
						autoplay: true,
						path: '<?php echo plugin_dir_url( __FILE__ ) . 'animations/nitropack+wp.json'; ?>'
					});
				</script>
			</div>
		</div>
	</div>
</div>
<script>
	(function ($) {

		let connectPopup = null;
		const homePageUrl = "<?php echo get_home_url(); ?>";
		const nitroNonce = '<?php echo wp_create_nonce( NITROPACK_NONCE ); ?>';

		$(document).ready(function () {
			function automaticConnect() {
				if (!connectPopup || !connectPopup.window) {
					let screenWidth = window.screen.availWidth;
					let screenHeight = window.screen.availHeight;
					let windowWidth = 800;
					let windowHeight = 700;
					let leftPos = window.top.outerWidth / 2 + window.top.screenX - (windowWidth / 2);
					let topPos = window.top.outerHeight / 2 + window.top.screenY - (windowHeight / 2);

					connectPopup = window.open("https://<?php echo NITROPACKIO_HOST; ?>/auth?website=" + homePageUrl, "QuickConnect", "width=" + windowWidth + ",height=" + windowHeight + ",left=" + leftPos + ",top=" + topPos);
				} else if (connectPopup && connectPopup.window) {
					connectPopup.focus();
				}
			}
			$('.btn-manual-connect').click(function (e) {
				$('#api-details-form, .help').toggle();
			});
			$('.btn-automatic-connect').click(function () {
				automaticConnect();
			});
			$("#connect-nitropack").on("click", function (e) {
				e.preventDefault();
				let siteId = $("#nitropack-siteid-input").val();
				let siteSecret = $("#nitropack-sitesecret-input").val();
				let loading_icon = $(this).find('.icon-left');
				let isManualConnect = $("#api-details-form").is(":visible");

				loading_icon.removeClass('hidden');

				if (isManualConnect || (siteId && siteSecret)) {

					$.post(ajaxurl, {
						action: 'nitropack_verify_connect',
						siteId: siteId,
						siteSecret: siteSecret,
						nonce: nitroNonce
					})
						.done(function (response) {
							let resp = JSON.parse(response);
							if (resp.status == "success") {
								$(".success-container").removeClass('hidden');
								$(".header, .connect").addClass('hidden');
								window.location.href = resp.url;								
							} else {
								$("#nitropack-siteid-input, #nitropack-sitesecret-input").val("");
								$("#main .notification").remove();
								let errorMessage = resp.message ? resp.message : "<?php esc_html_e( 'Api details verification failed! Please check whether you entered correct details.', 'nitropack' ); ?>";

								if ($('#api-details-form .nitro-notification').length) {
									$('#api-details-form .nitro-notification .notification-inner p').text(errorMessage);
								} else {
									$('#api-details-form').prepend('<div class="nitro-notification notification-error"><div class="text-box text-center"><div class="notification-inner" style="justify-content: center; gap: 0;""><img src="<?php echo plugin_dir_url( __FILE__ ) . 'images/alert-circle.svg'; ?>" alt="Error" class="icon"><p>' + errorMessage + '</p></div></div></div>');
								}
								loading_icon.addClass('hidden');
							}
						})
						.fail(function () {
							console.error("An error occurred during the AJAX request.");
						})
				} else if (!isManualConnect) {
					automaticConnect();
				}
			});
		});

		window.addEventListener("message", function (e) {
			if (e.data.messageType == "nitropack-connect") {
				$("#nitropack-siteid-input").val(e.data.api.key);
				$("#nitropack-sitesecret-input").val(e.data.api.secret);
				$("#connect-nitropack").click();
				if (connectPopup && !connectPopup.closed) {
					connectPopup.close();
					connectPopup = null;
				}
			}
		});

	})(jQuery);
</script>