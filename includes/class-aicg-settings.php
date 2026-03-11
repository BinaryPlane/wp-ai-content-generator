<?php
/**
 * Settings management for AI Content Generator.
 *
 * @package AI_Content_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AICG_Settings {

	const OPTION_GROUP = 'aicg_settings';
	const OPTION_NAME  = 'aicg_options';

	const DEFAULT_SYSTEM_PROMPT = 'Write in a natural, human voice. Avoid em dashes (—). Use minimal or no emojis. Sound like a real person, not like generic AI content. Vary sentence length and structure. Do not use phrases like "delve into," "landscape," "realm," or other overused AI clichés.';

	public static function get( $key = null, $default = null ) {
		$options = get_option( self::OPTION_NAME, array() );
		if ( $key === null ) {
			return wp_parse_args( $options, self::get_defaults() );
		}
		$defaults = self::get_defaults();
		$value    = isset( $options[ $key ] ) ? $options[ $key ] : ( isset( $defaults[ $key ] ) ? $defaults[ $key ] : $default );
		return $value;
	}

	public static function set( $key, $value ) {
		$options         = get_option( self::OPTION_NAME, array() );
		$options[ $key ] = $value;
		return update_option( self::OPTION_NAME, $options );
	}

	public static function set_defaults() {
		$current  = get_option( self::OPTION_NAME, array() );
		$defaults = self::get_defaults();
		$merged   = wp_parse_args( $current, $defaults );
		update_option( self::OPTION_NAME, $merged );
	}

	public static function get_defaults() {
		return array(
			'ai_provider'        => 'deepseek',
			'deepseek_api_key'   => '',
			'gemini_api_key'     => '',
			'gemini_model'       => 'gemini-2.5-flash-lite',
			'batch_delay_seconds'=> 0,
			'randomize_date'     => 0,
			'randomize_date_days'=> 365,
			'default_word_count' => 800,
			'system_prompt'      => self::DEFAULT_SYSTEM_PROMPT,
			'image_sources'      => array( 'pexels' ),
			'unsplash_api_key'   => '',
			'pexels_api_key'     => '',
			'pixabay_api_key'    => '',
		);
	}

	/**
	 * Allowed Gemini model IDs. Aligned with current API; see https://github.com/google-gemini/cookbook
	 * and https://ai.google.dev/gemini-api/docs/models
	 *
	 * @return array<string, string> Model ID => label.
	 */
	public static function get_gemini_models() {
		return array(
			'gemini-2.5-flash-lite'        => 'Gemini 2.5 Flash Lite (budget-friendly)',
			'gemini-2.5-flash'             => 'Gemini 2.5 Flash',
			'gemini-2.5-pro'               => 'Gemini 2.5 Pro',
			'gemini-3.1-flash-lite-preview'=> 'Gemini 3.1 Flash Lite (preview)',
			'gemini-3-flash-preview'        => 'Gemini 3 Flash (preview)',
			'gemini-3.1-pro-preview'       => 'Gemini 3.1 Pro (preview)',
		);
	}

	public static function get_image_sources_config() {
		return array(
			'unsplash' => array(
				'label'      => 'Unsplash',
				'api_key'    => true,
				'url'        => 'https://unsplash.com/developers',
				'option_key' => 'unsplash_api_key',
			),
			'pexels'   => array(
				'label'      => 'Pexels',
				'api_key'    => true,
				'url'        => 'https://www.pexels.com/api/',
				'option_key' => 'pexels_api_key',
			),
			'pixabay'  => array(
				'label'      => 'Pixabay',
				'api_key'    => true,
				'url'        => 'https://pixabay.com/api/docs/',
				'option_key' => 'pixabay_api_key',
			),
		);
	}
}
