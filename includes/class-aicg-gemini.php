<?php
/**
 * Google Gemini AI provider.
 *
 * @package AI_Content_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AICG_Gemini extends AICG_AI_Provider {

	const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models';

	public function get_id() {
		return 'gemini';
	}

	public function get_label() {
		return __( 'Google Gemini', 'ai-content-generator' );
	}

	public function is_configured() {
		return ! empty( AICG_Settings::get( 'gemini_api_key' ) );
	}

	/**
	 * Model ID from settings (e.g. gemini-1.5-flash for free tier).
	 *
	 * @return string
	 */
	private function get_model() {
		$model = AICG_Settings::get( 'gemini_model' );
		$allowed = array_keys( AICG_Settings::get_gemini_models() );
		return in_array( $model, $allowed, true ) ? $model : 'gemini-2.5-flash-lite';
	}

	private function get_request_url() {
		return self::API_BASE . '/' . $this->get_model() . ':generateContent';
	}

	public function generate_post_content( $topic, $word_count, $system_prompt ) {
		$api_key = AICG_Settings::get( 'gemini_api_key' );
		if ( ! $api_key ) {
			return new WP_Error( 'missing_key', __( 'Gemini API key is not set.', 'ai-content-generator' ) );
		}

		$user_message = sprintf(
			'Write a single blog post about: %s. Length: approximately %d words. Output only the post body (no title). Use HTML paragraphs with <p> tags.',
			$topic,
			$word_count
		);

		$body = array(
			'contents'          => array(
				array(
					'parts' => array(
						array( 'text' => $user_message ),
					),
				),
			),
			'systemInstruction' => array(
				'parts' => array(
					array( 'text' => $system_prompt ),
				),
			),
			'generationConfig'  => array(
				'maxOutputTokens' => min( 8192, (int) ( $word_count * 1.5 ) ),
				'temperature'     => 0.8,
			),
		);

		$url = add_query_arg( 'key', $api_key, $this->get_request_url() );

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
				'timeout' => 120,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code     = wp_remote_retrieve_response_code( $response );
		$body_raw = wp_remote_retrieve_body( $response );
		$data     = json_decode( $body_raw, true );

		if ( $code !== 200 ) {
			$message = isset( $data['error']['message'] ) ? $data['error']['message'] : $body_raw;
			return new WP_Error( 'api_error', sprintf( __( 'Gemini API error: %s', 'ai-content-generator' ), $message ) );
		}

		$text = '';
		if ( ! empty( $data['candidates'][0]['content']['parts'] ) ) {
			foreach ( $data['candidates'][0]['content']['parts'] as $part ) {
				if ( isset( $part['text'] ) ) {
					$text .= $part['text'];
				}
			}
		}

		if ( empty( trim( $text ) ) ) {
			return new WP_Error( 'empty_response', __( 'Gemini returned no content.', 'ai-content-generator' ) );
		}

		return trim( $text );
	}

	public function suggest_category( $title, $content_excerpt ) {
		$api_key = AICG_Settings::get( 'gemini_api_key' );
		if ( ! $api_key ) {
			return new WP_Error( 'missing_key', __( 'Gemini API key is not set.', 'ai-content-generator' ) );
		}

		$user_message = sprintf(
			"Given this blog post title and excerpt, suggest a single WordPress category name (2-4 words) that fits the topic. Reply with ONLY the category name, nothing else.\n\nTitle: %s\nExcerpt: %s",
			$title,
			wp_trim_words( $content_excerpt, 30 )
		);

		$body = array(
			'contents'         => array(
				array(
					'parts' => array(
						array( 'text' => $user_message ),
					),
				),
			),
			'generationConfig' => array(
				'maxOutputTokens' => 30,
				'temperature'     => 0.3,
			),
		);

		$url = add_query_arg( 'key', $api_key, $this->get_request_url() );

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code     = wp_remote_retrieve_response_code( $response );
		$body_raw = wp_remote_retrieve_body( $response );
		$data     = json_decode( $body_raw, true );

		if ( $code !== 200 ) {
			$message = isset( $data['error']['message'] ) ? $data['error']['message'] : $body_raw;
			return new WP_Error( 'api_error', sprintf( __( 'Gemini API error: %s', 'ai-content-generator' ), $message ) );
		}

		$text = '';
		if ( ! empty( $data['candidates'][0]['content']['parts'] ) ) {
			foreach ( $data['candidates'][0]['content']['parts'] as $part ) {
				if ( isset( $part['text'] ) ) {
					$text .= $part['text'];
				}
			}
		}

		$category = trim( $text );
		$category = preg_replace( '/^["\']|["\']$/', '', $category );
		return $category ?: __( 'Uncategorized', 'ai-content-generator' );
	}
}
