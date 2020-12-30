<?php
/**
 * entry point bootstrap file for this wordpress plugin
 * @license GPL-3.0
 *
 * @wordpress-plugin
 * Plugin Name: WWF-Donations-Plugin
 * Plugin URI: https://github.com/EXXETA/wordpress-plugin-donations
 * Description: Plugin providing WWF donation products for a WooCommerce shop with a banner ready for integration into a wordpress system via shortcode and/or block.
 * Author: Marius Schuppert, EXXETA AG
 * Version: 1.1.0
 * License: GNU General Public License version 3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.en.html
 * Requires PHP: 7.3
 * Requires at least: 5.2
 * WC requires at least: 4.0.0
 * WC tested up to: 4.3.1
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('BASE_PATH', plugin_dir_path(__FILE__));
require_once BASE_PATH . 'vendor/autoload.php';

use donations\Plugin;

$plugin = new Plugin(__FILE__);
$plugin->check();
$plugin->registerPluginHooks();