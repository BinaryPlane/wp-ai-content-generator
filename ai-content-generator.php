<?php
/**
 * Plugin Name: AI Content Generator
 * Plugin URI: https://binaryplane.com/ai-content-generator
 * Description: Mass-generate blog posts using AI (DeepSeek, Gemini) with automatic categories and featured images from free stock photo sources.
 * Version: 1.0.1
 * Author: BinaryPlane
 * Author URI: https://binaryplane.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-content-generator
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * When releasing: bump Version above and AICG_VERSION below; update readme.txt Stable tag and Changelog. See RELEASES.md.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AICG_VERSION', '1.0.1' );
define( 'AICG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AICG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AICG_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once AICG_PLUGIN_DIR . 'includes/class-aicg-settings.php';
require_once AICG_PLUGIN_DIR . 'includes/class-aicg-ai-provider.php';
require_once AICG_PLUGIN_DIR . 'includes/class-aicg-deepseek.php';
require_once AICG_PLUGIN_DIR . 'includes/class-aicg-gemini.php';
require_once AICG_PLUGIN_DIR . 'includes/class-aicg-image-provider.php';
require_once AICG_PLUGIN_DIR . 'includes/class-aicg-unsplash.php';
require_once AICG_PLUGIN_DIR . 'includes/class-aicg-pexels.php';
require_once AICG_PLUGIN_DIR . 'includes/class-aicg-pixabay.php';
require_once AICG_PLUGIN_DIR . 'includes/class-aicg-generator.php';
require_once AICG_PLUGIN_DIR . 'includes/class-aicg-admin.php';
require_once AICG_PLUGIN_DIR . 'includes/class-aicg-updater.php';

function aicg_init() {
	load_plugin_textdomain( 'ai-content-generator', false, dirname( AICG_PLUGIN_BASENAME ) . '/languages' );
	AICG_Updater::init();
	if ( is_admin() ) {
		new AICG_Admin();
	}
}
add_action( 'plugins_loaded', 'aicg_init' );

add_filter( 'plugin_action_links_' . AICG_PLUGIN_BASENAME, function ( $links ) {
	$links[] = '<a href="' . esc_url( admin_url( 'options-general.php?page=' . AICG_Admin::PAGE_SLUG ) ) . '">' . __( 'Settings', 'ai-content-generator' ) . '</a>';
	return $links;
} );

register_activation_hook( __FILE__, function () {
	AICG_Settings::set_defaults();
} );
