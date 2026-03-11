<?php
/**
 * DeepSeek AI provider.
 *
 * @package AI_Content_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AICG_DeepSeek extends AICG_AI_Provider {

	const API_URL = 'https://api.deepseek.com/v1/chat/completions';
	const MODEL   = 'deepseek-chat';

	public function get_id() {
		return 'deepseek';
	}

	public function get_label() {
		return __( 'DeepSeek', 'ai-content-generator' );
	}

	public function is_configured() {
		return ! empty( AICG_Settings::get( 'deepseek_api_key' ) );
	}

	public function generate_post_content( $topic, $word_count, $system_prompt ) {
		$api_key = AICG_Settings::get( 'deepseek_api_key' );
		if ( ! $api_key ) {
			return new WP_Error( 'missing_key', __( 'DeepSeek API key is not set.', 'ai-content-generator' ) );
		}

		$user_message = sprintf(
			'Write a single blog post about: %s. Length: approximately %d words. Output only the post body (no title). Use HTML paragraphs with <p> tags.',
			$topic,
			$word_count
		);

		$body = array(
			'model'       => self::MODEL,
			'messages'    => array(
				array(
					'role'    => 'system',
					'content' => $system_prompt,
				),
				array(
					'role'    => 'user',
					'content' => $user_message,
				),
			),
			'max_tokens'  => min( 4096, (int) ( $word_count * 1.5 ) ),
			'temperature' => 0.8,
		);

		$response = wp_remote_post(
			self::API_URL,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'   => 'application/json',
				),
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
			return new WP_Error( 'api_error', sprintf( __( 'DeepSeek API error: %s', 'ai-content-generator' ), $message ) );
		}

		if ( empty( $data['choices'][0]['message']['content'] ) ) {
			return new WP_Error( 'empty_response', __( 'DeepSeek returned no content.', 'ai-content-generator' ) );
		}

		return trim( $data['choices'][0]['message']['content'] );
	}

	public function suggest_category( $title, $content_excerpt ) {
		$api_key = AICG_Settings::get( 'deepseek_api_key' );
		if ( ! $api_key ) {
			return new WP_Error( 'missing_key', __( 'DeepSeek API key is not set.', 'ai-content-generator' ) );
		}

		$user_message = sprintf(
			"Given this blog post title and excerpt, suggest a single WordPress category name (2-4 words) that fits the topic. Reply with ONLY the category name, nothing else.\n\nTitle: %s\nExcerpt: %s",
			$title,
			wp_trim_words( $content_excerpt, 30 )
		);

		$body = array(
			'model'       => self::MODEL,
			'messages'    => array(
				array(
					'role'    => 'user',
					'content' => $user_message,
				),
			),
			'max_tokens'  => 30,
			'temperature' => 0.3,
		);

		$response = wp_remote_post(
			self::API_URL,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'   => 'application/json',
				),
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
			return new WP_Error( 'api_error', sprintf( __( 'DeepSeek API error: %s', 'ai-content-generator' ), $message ) );
		}

		$category = trim( $data['choices'][0]['message']['content'] ?? '' );
		$category = preg_replace( '/^["\']|["\']$/', '', $category );
		return $category ?: __( 'Uncategorized', 'ai-content-generator' );
	}
}
