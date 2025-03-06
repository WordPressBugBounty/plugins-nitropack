<script>
	let nitroNonce = '<?php echo wp_create_nonce( NITROPACK_NONCE ); ?>';
</script>
<div id="nitropack-container">
	<main id="main">
		<div class="container">
			<h1 class="mb-4"><?php esc_html_e( 'NitroPack OneClick™', 'nitropack' ); ?></h1>
			<?php if ( ! isset( $_GET['subpage'] ) ) : ?>
				<?php require_once NITROPACK_PLUGIN_DIR . "view/dashboard-oneclick.php";
				?>
			<?php endif; ?>
		</div>
	</main>
	<?php require_once NITROPACK_PLUGIN_DIR . 'view/templates/template-toast.php'; ?>
</div>