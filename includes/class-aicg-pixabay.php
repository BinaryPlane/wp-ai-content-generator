<?php
/**
 * Pixabay image provider.
 *
 * @package AI_Content_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AICG_Pixabay extends AICG_Image_Provider {

	const API_URL = 'https://pixabay.com/api/';

	public function get_id() {
		return 'pixabay';
	}

	public function get_label() {
		return 'Pixabay';
	}

	public function is_configured() {
		return ! empty( AICG_Settings::get( 'pixabay_api_key' ) );
	}

	public function search_image( $query ) {
		$api_key = AICG_Settings::get( 'pixabay_api_key' );
		if ( ! $api_key ) {
			return new WP_Error( 'missing_key', __( 'Pixabay API key is not set.', 'ai-content-generator' ) );
		}

		$url = add_query_arg(
			array(
				'key'       => $api_key,
				'q'         => $query,
				'per_page'  => 5,
				'image_type' => 'photo',
				'safesearch' => 'true',
			),
			self::API_URL
		);

		$response = wp_remote_get( $url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			return new WP_Error( 'api_error', sprintf( __( 'Pixabay API error: HTTP %d', 'ai-content-generator' ), $code ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['hits'][0] ) ) {
			return new WP_Error( 'no_results', __( 'No images found for this query.', 'ai-content-generator' ) );
		}

		$photo = $body['hits'][0];
		$url_full = isset( $photo['webformatURL'] ) ? $photo['webformatURL'] : $photo['largeImageURL'];
		$attribution = '';
		if ( ! empty( $photo['user'] ) ) {
			$attribution = sprintf(
				'Image by %s on Pixabay',
				$photo['user']
			);
		}

		return array(
			'url'         => $url_full,
			'attribution' => $attribution,
		);
	}
}
