<?php
/**
 * Base AI provider interface.
 *
 * @package AI_Content_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AICG_AI_Provider {

	abstract public function get_id();

	abstract public function get_label();

	abstract public function generate_post_content( $topic, $word_count, $system_prompt );

	abstract public function suggest_category( $title, $content_excerpt );

	public function is_configured() {
		return false;
	}
}
