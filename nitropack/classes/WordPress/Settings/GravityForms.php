<?php

namespace NitroPack\WordPress\Settings;
use NitroPack\WordPress\NitroPack;
use NitroPack\Integration\Plugin\GravityForms as GravityFormsPlugin;
use NitroPack\WordPress\Settings\Components;

/**
 * Sync Gravity Forms cache when purging NitroPack cache in class NitroPack\Integration\Plugin\GravityForms
 */
class GravityForms {
    /** @var GravityForms|null */
    private static $instance = null;
    /** @var string */
    public $option_name;
    public function __construct() {
        add_action( 'wp_ajax_nitropack_set_gravity_forms_honeypot_ajax', [ $this, 'nitropack_set_gravity_forms_honeypot_ajax' ] );
        $this->option_name = 'nitropack-gravity-forms-honeypot';
    }
    public static function getInstance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    /**
     * AJAX handler when toggling the setting in the Dashboard
     * @return void
     */
    public function nitropack_set_gravity_forms_honeypot_ajax() {
        nitropack_verify_ajax_nonce( $_REQUEST );
        $option = (int) ! empty( $_POST["gravityFormsHoneypotStatus"] );
        $updated = update_option( $this->option_name, $option );
        if ( $updated ) {
            NitroPack::getInstance()->getLogger()->notice( 'Gravity Forms Status is ' . ( $option === 1 ? 'enabled' : 'disabled' ) );
            nitropack_json_and_exit( array( "type" => "success", "message" => nitropack_admin_toast_msgs( 'success' ), 'gravityFormsHoneypotStatus' => $option ) );
        } else {
            NitroPack::getInstance()->getLogger()->error( 'Gravity Forms Status cannot be ' . ( $option === 1 ? 'enabled' : 'disabled' ) );
            nitropack_json_and_exit( array(
                "type" => "error",
                "message" => nitropack_admin_toast_msgs( 'error' )
            ) );
        }
    }

    /**
     * Renders the Gravity Forms option in the Dashboard if the plugin is active
     * Default: Disabled
     * @return void
     */
    public function render() {
        if ( GravityFormsPlugin::isActive() ) {
            $gravity_forms_setting = get_option( $this->option_name, null );
            //set a default value of 0 - disabled
            if ( null === $gravity_forms_setting ) {
                $gravity_forms_setting = 0;
                add_option( $this->option_name, $gravity_forms_setting );
            }
            ?>
            <div class="nitro-option" id="gravity-forms-widget">
                <div class="nitro-option-main">
                    <div class="text-box">
                        <h6><?php esc_html_e( 'Gravity Forms - Honeypot', 'nitropack' ); ?></h6>
                        <p>
                            <?php esc_html_e( 'Loads Gravity Forms asynchronously via AJAX to ensure honeypot spam protection works correctly.', 'nitropack' ); ?>
                        </p>
                    </div>
                    <?php $components = new Components();
                    $components->render_toggle( 'gravity-forms-honeypot-status', $gravity_forms_setting );
                    ?>
                </div>
            </div>
        <?php }
    }
}
