<?php
/**
 * Unsplash image provider.
 *
 * @package AI_Content_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AICG_Unsplash extends AICG_Image_Provider {

	const API_URL = 'https://api.unsplash.com/search/photos';

	public function get_id() {
		return 'unsplash';
	}

	public function get_label() {
		return 'Unsplash';
	}

	public function is_configured() {
		return ! empty( AICG_Settings::get( 'unsplash_api_key' ) );
	}

	public function search_image( $query ) {
		$api_key = AICG_Settings::get( 'unsplash_api_key' );
		if ( ! $api_key ) {
			return new WP_Error( 'missing_key', __( 'Unsplash API key is not set.', 'ai-content-generator' ) );
		}

		$url = add_query_arg(
			array(
				'query'    => $query,
				'per_page' => 5,
				'orientation' => 'landscape',
			),
			self::API_URL
		);

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Client-ID ' . $api_key,
					'Accept-Version' => 'v1',
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			return new WP_Error( 'api_error', sprintf( __( 'Unsplash API error: HTTP %d', 'ai-content-generator' ), $code ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['results'][0] ) ) {
			return new WP_Error( 'no_results', __( 'No images found for this query.', 'ai-content-generator' ) );
		}

		$photo = $body['results'][0];
		$url_full = isset( $photo['urls']['regular'] ) ? $photo['urls']['regular'] : $photo['urls']['full'];
		$attribution = '';
		if ( ! empty( $photo['user']['name'] ) ) {
			$attribution = sprintf(
				'Photo by %s on Unsplash',
				$photo['user']['name']
			);
		}

		return array(
			'url'         => $url_full,
			'attribution' => $attribution,
		);
	}
}
