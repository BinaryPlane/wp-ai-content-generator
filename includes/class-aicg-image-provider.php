<?php
/**
 * Base image provider interface.
 *
 * @package AI_Content_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AICG_Image_Provider {

	abstract public function get_id();

	abstract public function get_label();

	abstract public function is_configured();

	/**
	 * Search for an image and return URL + attribution, or WP_Error.
	 *
	 * @param string $query Search query (e.g. category name or topic).
	 * @return array{url: string, attribution?: string}|WP_Error
	 */
	abstract public function search_image( $query );
}
