<?php
/**
 * Pexels image provider.
 *
 * @package AI_Content_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AICG_Pexels extends AICG_Image_Provider {

	const API_URL = 'https://api.pexels.com/v1/search';

	public function get_id() {
		return 'pexels';
	}

	public function get_label() {
		return 'Pexels';
	}

	public function is_configured() {
		return ! empty( AICG_Settings::get( 'pexels_api_key' ) );
	}

	public function search_image( $query ) {
		$api_key = AICG_Settings::get( 'pexels_api_key' );
		if ( ! $api_key ) {
			return new WP_Error( 'missing_key', __( 'Pexels API key is not set.', 'ai-content-generator' ) );
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
					'Authorization' => $api_key,
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			return new WP_Error( 'api_error', sprintf( __( 'Pexels API error: HTTP %d', 'ai-content-generator' ), $code ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['photos'][0] ) ) {
			return new WP_Error( 'no_results', __( 'No images found for this query.', 'ai-content-generator' ) );
		}

		$photo = $body['photos'][0];
		$url_full = isset( $photo['src']['large'] ) ? $photo['src']['large'] : $photo['src']['original'];
		$attribution = '';
		if ( ! empty( $photo['photographer'] ) ) {
			$attribution = sprintf(
				'Photo by %s on Pexels',
				$photo['photographer']
			);
		}

		return array(
			'url'         => $url_full,
			'attribution' => $attribution,
		);
	}
}
