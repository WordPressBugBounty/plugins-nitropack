<script>
	let nitroNonce = '<?php echo wp_create_nonce( NITROPACK_NONCE ); ?>';
</script>
<div id="nitropack-container">
	<nav class="nitro-navigation">
		<div class="nitro-navigation-inner">
			<img src="<?php echo plugin_dir_url( __FILE__ ) . 'images/nitropack_logo.svg'; ?>" height="25"
				alt="NitroPack" />
		</div>
	</nav>

	<main id="main">
		<div class="container">
			<?php if ( ! isset( $_GET['subpage'] ) ) : ?>
				<?php require_once NITROPACK_PLUGIN_DIR . "view/dashboard.php";
				?>
			<?php endif; ?>
			<?php if ( isset( $_GET['subpage'] ) && $_GET['subpage'] == 'system-report' ) : ?>
				<?php require_once NITROPACK_PLUGIN_DIR . "view/system-report.php";
				?>
			<?php endif; ?>
		</div>
	</main>
	<?php require_once NITROPACK_PLUGIN_DIR . 'view/templates/template-toast.php'; ?>
</div>

<?php if ( NITROPACK_SUPPORT_BUBBLE_VISIBLE ) { ?>
	<div class="support-widget">
		<!-- support widget -->
		<script>
			window.intercomSettings = {
				api_base: "https://api-iam.intercom.io",
				app_id: "d5v9p9vg"
			};

			(function () {
				var w = window;
				var ic = w.Intercom;
				if (typeof ic === "function") {
					ic('reattach_activator');
					ic('update', w.intercomSettings);
				} else {
					var d = document;
					var i = function () {
						i.c(arguments);
					};
					i.q = [];
					i.c = function (args) {
						i.q.push(args);
					};
					w.Intercom = i;
					var l = function () {
						var s = d.createElement('script');
						s.type = 'text/javascript';
						s.async = true;
						s.src = 'https://widget.intercom.io/widget/d5v9p9vg';
						var x = d.getElementsByTagName('script')[0];
						x.parentNode.insertBefore(s, x);
					};
					if (document.readyState === 'complete') {
						l();
					} else if (w.attachEvent) {
						w.attachEvent('onload', l);
					} else {
						w.addEventListener('load', l, false);
					}
				}
			})();
		</script>
		<!-- end support widget -->
	</div>
<?php } ?>
<script>
	(function ($) {
		window.addEventListener("cache.purge.success", function () {
			setTimeout(function () {
				document.cookie = "nitropack_apwarning=1; expires=Thu, 01 Jan 1970 00:00:01 GMT; path=<?php echo nitropack_cookiepath(); ?>";
				window.location.reload()
			}, 1500)
		});
	})(jQuery);
</script>