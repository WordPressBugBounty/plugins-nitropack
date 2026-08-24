<?php
namespace NitroPack\WordPress;

use NitroPack\SDK\Filesystem;
/**
 * Handles the config.json - reading and writing the configuration file.
 */
class Config {
    private $config;

    public function __construct() {
        $this->config = NULL;
    }

    public function get() {
        if ($this->config) {
            return $this->config;
        }

        $config = [];

        if ($this->exists()) {
            $config = json_decode(Filesystem::fileGetContents(NITROPACK_CONFIG_FILE), true);
            if (!empty($config['config_path']) && $config['config_path'] != md5(NITROPACK_PLUGIN_DATA_DIR)) {
                $config = [];
            }
        }

        $this->config = $config;
        return $config;
    }

    public function set($config) {
        $np = NitroPack::getInstance();
        if (!$np->pluginDataDirExists() && !$np->initPluginDataDir()) return false;
        $config['config_path'] = md5(NITROPACK_PLUGIN_DATA_DIR);
        $this->config = $config;
        return WP_DEBUG ? Filesystem::filePutContents(NITROPACK_CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT)) : @Filesystem::filePutContents(NITROPACK_CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT));
    }

    // Used when changing the location of the data dir
    public function updateConfigPath() {
		$config = json_decode(file_get_contents(NITROPACK_CONFIG_FILE), true); // TODO: Convert this to use the Filesystem abstraction for better Redis support
        $config['config_path'] = md5(NITROPACK_PLUGIN_DATA_DIR);
        return WP_DEBUG ? Filesystem::filePutContents(NITROPACK_CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT)) : @Filesystem::filePutContents(NITROPACK_CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT));
    }

    public function exists() {
        return defined("NITROPACK_CONFIG_FILE") && Filesystem::fileExists(NITROPACK_CONFIG_FILE);
    }
}
