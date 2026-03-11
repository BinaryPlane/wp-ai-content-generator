<?php
/**
 * Uninstall AI Content Generator.
 *
 * @package AI_Content_Generator
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'aicg_options' );
