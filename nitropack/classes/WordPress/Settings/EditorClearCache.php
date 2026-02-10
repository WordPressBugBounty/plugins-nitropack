<?php
namespace NitroPack\WordPress\Settings;
use NitroPack\WordPress\NitroPack;

/**
 * Allows editor role to purge or invalidate page cache in meta boxes.
 */
class EditorClearCache {

	/** @var string */
	public $option_name;

	public function __construct() {
		add_action( 'wp_ajax_nitropack_set_can_editor_clear_cache', [ $this, 'nitropack_set_can_editor_clear_cache' ] );
		$this->option_name = 'nitropack-canEditorClearCache';
	}

	/**
	 * AJAX handler when toggle the setting in Dashboard
	 * @return void
	 */
	public function nitropack_set_can_editor_clear_cache() {
		nitropack_verify_ajax_nonce( $_REQUEST );
		$option = (int) ! empty( $_POST["data"]["canEditorClearCache"] );
		$updated = update_option( $this->option_name, $option );
		if ( $updated ) {
			NitroPack::getInstance()->getLogger()->notice( 'Allow Editors to purge cache is ' . ( $option === 1 ? 'enabled' : 'disabled' ) );
			nitropack_json_and_exit( array( "type" => "success", "message" => nitropack_admin_toast_msgs( 'success' ), "allowedEditors" => $option ) );
		} else {
			NitroPack::getInstance()->getLogger()->error( 'Allow Editors to purge cache cannot be ' . ( $option === 1 ? 'enabled' : 'disabled' ) );
			nitropack_json_and_exit( array(
				"type" => "error",
				"message" => nitropack_admin_toast_msgs( 'error' )
			) );
		}
	}

	/**
	 * Renders the Editor Purge option in the Dashboard
	 * @return void
	 */
	public function render() {
		$canEditorClearCache = get_option( $this->option_name, 1 );
		?>
		<div class="nitro-option" id="can-editor-clear-cache-widget">
			<div class="nitro-option-main">
				<div class="text-box">
					<h6>
						<?php esc_html_e( 'Allow Editors to purge cache', 'nitropack' ); ?>
					</h6>
					<p>
						<?php esc_html_e( 'Give Editors the right to purge cache when content is updated.', 'nitropack' ); ?>
					</p>
				</div>
				<?php $components = new Components();
				$components->render_toggle( 'can-editor-clear-cache', $canEditorClearCache ); ?>
			</div>
		</div>
		<?php
	}
}