<?php
/**
 * Orchestrates post generation: AI content, category, featured image.
 *
 * @package AI_Content_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AICG_Generator {

	/**
	 * @return AICG_AI_Provider|null
	 */
	public static function get_ai_provider() {
		$id = AICG_Settings::get( 'ai_provider', 'deepseek' );
		if ( $id === 'gemini' ) {
			return new AICG_Gemini();
		}
		return new AICG_DeepSeek();
	}

	/**
	 * @param string $source_id unsplash|pexels|pixabay
	 * @return AICG_Image_Provider|null
	 */
	public static function get_image_provider( $source_id ) {
		switch ( $source_id ) {
			case 'unsplash':
				return new AICG_Unsplash();
			case 'pexels':
				return new AICG_Pexels();
			case 'pixabay':
				return new AICG_Pixabay();
		}
		return null;
	}

	/**
	 * Get first configured image provider from selected sources.
	 *
	 * @return AICG_Image_Provider|null
	 */
	public static function get_configured_image_provider() {
		$sources = (array) AICG_Settings::get( 'image_sources' );
		foreach ( $sources as $source_id ) {
			$provider = self::get_image_provider( $source_id );
			if ( $provider && $provider->is_configured() ) {
				return $provider;
			}
		}
		return null;
	}

	/**
	 * Generate a single post and insert into WordPress.
	 *
	 * @param string $topic Content topic/description.
	 * @param int    $word_count Target word count.
	 * @param string $system_prompt Optional override.
	 * @return int|WP_Error Post ID or error.
	 */
	public static function generate_one_post( $topic, $word_count = null, $system_prompt = null ) {
		$word_count    = $word_count ? (int) $word_count : (int) AICG_Settings::get( 'default_word_count', 800 );
		$system_prompt = $system_prompt ?: AICG_Settings::get( 'system_prompt', AICG_Settings::DEFAULT_SYSTEM_PROMPT );

		$ai = self::get_ai_provider();
		if ( ! $ai || ! $ai->is_configured() ) {
			return new WP_Error( 'ai_not_configured', __( 'Selected AI provider is not configured. Set API key in settings.', 'ai-content-generator' ) );
		}

		$content = $ai->generate_post_content( $topic, $word_count, $system_prompt );
		if ( is_wp_error( $content ) ) {
			return $content;
		}

		$title = self::generate_title( $topic, $ai );
		if ( is_wp_error( $title ) ) {
			$title = sanitize_text_field( $topic );
		}

		$category_name = $ai->suggest_category( $title, wp_strip_all_tags( $content ) );
		if ( is_wp_error( $category_name ) ) {
			$category_name = __( 'Uncategorized', 'ai-content-generator' );
		}

		$cat_id = self::get_or_create_category( $category_name );

		$post_data = array(
			'post_title'   => $title,
			'post_content' => $content,
			'post_status'  => 'draft',
			'post_author'  => get_current_user_id(),
			'post_type'    => 'post',
		);

		if ( ! empty( AICG_Settings::get( 'randomize_date' ) ) ) {
			$days = max( 1, min( 3650, (int) AICG_Settings::get( 'randomize_date_days', 365 ) ) );
			$max_seconds = $days * DAY_IN_SECONDS;
			$random_offset = wp_rand( 0, $max_seconds );
			$post_timestamp = time() - $random_offset;
			$post_data['post_date']     = date( 'Y-m-d H:i:s', $post_timestamp );
			$post_data['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $post_timestamp );
		}

		$post_id = wp_insert_post( $post_data, true );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( $cat_id ) {
			wp_set_post_categories( $post_id, array( $cat_id ) );
		}

		$image_provider = self::get_configured_image_provider();
		if ( $image_provider ) {
			$image_query = $category_name . ' ' . $topic;
			$image_result = $image_provider->search_image( $image_query );
			if ( ! is_wp_error( $image_result ) && ! empty( $image_result['url'] ) ) {
				$attach_id = self::sideload_featured_image( $image_result['url'], $post_id, $image_result['attribution'] ?? '' );
				if ( $attach_id && ! is_wp_error( $attach_id ) ) {
					set_post_thumbnail( $post_id, $attach_id );
				}
			}
		}

		return $post_id;
	}

	/**
	 * Generate a post title using AI.
	 *
	 * @param string $topic
	 * @param AICG_AI_Provider $ai
	 * @return string|WP_Error
	 */
	private static function generate_title( $topic, $ai ) {
		if ( $ai instanceof AICG_DeepSeek ) {
			return self::generate_title_deepseek( $topic );
		}
		if ( $ai instanceof AICG_Gemini ) {
			return self::generate_title_gemini( $topic );
		}
		return sanitize_text_field( $topic );
	}

	private static function generate_title_deepseek( $topic ) {
		$api_key = AICG_Settings::get( 'deepseek_api_key' );
		$body = array(
			'model'       => 'deepseek-chat',
			'messages'    => array(
				array(
					'role'    => 'user',
					'content' => sprintf( 'Suggest a single, catchy blog post title (under 80 chars) for an article about: %s. Reply with ONLY the title, nothing else.', $topic ),
				),
			),
			'max_tokens'  => 50,
			'temperature' => 0.7,
		);
		$response = wp_remote_post(
			'https://api.deepseek.com/v1/chat/completions',
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
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$title = trim( $data['choices'][0]['message']['content'] ?? '' );
		$title = preg_replace( '/^["\']|["\']$/', '', $title );
		return $title ?: sanitize_text_field( $topic );
	}

	private static function generate_title_gemini( $topic ) {
		$api_key = AICG_Settings::get( 'gemini_api_key' );
		$model   = AICG_Settings::get( 'gemini_model' );
		$allowed = array_keys( AICG_Settings::get_gemini_models() );
		if ( ! in_array( $model, $allowed, true ) ) {
			$model = 'gemini-2.5-flash-lite';
		}
		$body = array(
			'contents' => array(
				array(
					'parts' => array(
						array( 'text' => sprintf( 'Suggest a single, catchy blog post title (under 80 chars) for an article about: %s. Reply with ONLY the title, nothing else.', $topic ) ),
					),
				),
			),
			'generationConfig' => array( 'maxOutputTokens' => 50, 'temperature' => 0.7 ),
		);
		$url = add_query_arg( 'key', $api_key, 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent' );
		$response = wp_remote_post( $url, array( 'headers' => array( 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( $body ), 'timeout' => 30 ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$text = '';
		if ( ! empty( $data['candidates'][0]['content']['parts'] ) ) {
			foreach ( $data['candidates'][0]['content']['parts'] as $part ) {
				if ( isset( $part['text'] ) ) {
					$text .= $part['text'];
				}
			}
		}
		$title = trim( $text );
		$title = preg_replace( '/^["\']|["\']$/', '', $title );
		return $title ?: sanitize_text_field( $topic );
	}

	/**
	 * Get category term by name or create it.
	 *
	 * @param string $name
	 * @return int Category term_id or 0.
	 */
	private static function get_or_create_category( $name ) {
		$name = sanitize_text_field( $name );
		if ( ! $name ) {
			return 0;
		}
		$term = get_term_by( 'name', $name, 'category' );
		if ( $term ) {
			return (int) $term->term_id;
		}
		$result = wp_insert_term( $name, 'category' );
		if ( is_wp_error( $result ) ) {
			return 0;
		}
		return (int) $result['term_id'];
	}

	/**
	 * Download image from URL and set as attachment for post.
	 *
	 * @param string $url
	 * @param int    $post_id
	 * @param string $attribution Optional caption/credit.
	 * @return int|WP_Error Attachment ID or error.
	 */
	private static function sideload_featured_image( $url, $post_id, $attribution = '' ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $url );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		$file_array = array(
			'name'     => basename( parse_url( $url, PHP_URL_PATH ) ) ?: 'featured-image.jpg',
			'tmp_name' => $tmp,
		);

		$attach_id = media_handle_sideload( $file_array, $post_id );
		if ( is_wp_error( $attach_id ) ) {
			@unlink( $tmp );
			return $attach_id;
		}

		if ( $attribution ) {
			wp_update_post( array(
				'ID'           => $attach_id,
				'post_excerpt' => $attribution,
			) );
		}

		return $attach_id;
	}
}
