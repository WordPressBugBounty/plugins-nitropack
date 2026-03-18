<script>
	let nitroNonce = '<?php echo wp_create_nonce( NITROPACK_NONCE ); ?>';
</script>
<div id="nitropack-container">
	<nav class="nitro-navigation">
		<div class="nitro-navigation-inner">
			<img src="<?php echo plugin_dir_url( __FILE__ ) . 'images/nitropack_wp_logo.svg'; ?>"
				alt="NitroPack" />
		</div>
	</nav>
	<main id="main">
		<div class="container">
			<h1 class="mb-4"><?php esc_html_e( 'NitroPack OneClick™', 'nitropack' ); ?></h1>
			<?php
			if ( ! isset( $_GET['subpage'] ) ) {
				require_once NITROPACK_PLUGIN_DIR . "view/dashboard-oneclick.php";
			} ?>
		</div>
	</main>
	<?php require_once NITROPACK_PLUGIN_DIR . 'view/templates/template-toast.php'; ?>
</div>