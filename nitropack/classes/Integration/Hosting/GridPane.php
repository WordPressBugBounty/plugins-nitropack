<?php

namespace NitroPack\Integration\Hosting;

class GridPane extends Hosting {
	const STAGE = null;

	public static function detect() {
		$configFilePath = \NitroPack\WordPress\CoreFiles::get_wp_config_path();
		if ( ! $configFilePath ) {
			return false;
		}
		return strpos( file_get_contents( $configFilePath ), 'GridPane Cache Settings' ) !== false;
	}
}


