<?php
/**
 * Admin UI: settings and batch generation.
 *
 * @package AI_Content_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AICG_Admin {

	const PAGE_SLUG = 'ai-content-generator';
	const PAGE_GEN  = 'ai-content-generator-generate';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menus' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_aicg_generate_post', array( $this, 'ajax_generate_post' ) );
		add_action( 'admin_head-edit.php', array( $this, 'add_generate_posts_button' ) );
	}

	public function add_menus() {
		add_options_page(
			__( 'AI Content Generator', 'ai-content-generator' ),
			__( 'AI Content Generator', 'ai-content-generator' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);

		add_posts_page(
			__( 'Generate AI Posts', 'ai-content-generator' ),
			__( 'Generate AI Posts', 'ai-content-generator' ),
			'edit_posts',
			self::PAGE_GEN,
			array( $this, 'render_generate_page' )
		);
	}

	public function register_settings() {
		register_setting( AICG_Settings::OPTION_GROUP, AICG_Settings::OPTION_NAME, array(
			'type'              => 'array',
			'sanitize_callback' => array( $this, 'sanitize_options' ),
		) );
	}

	public function sanitize_options( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}
		$out = array();
		$out['ai_provider']        = isset( $input['ai_provider'] ) && in_array( $input['ai_provider'], array( 'deepseek', 'gemini' ), true ) ? $input['ai_provider'] : 'deepseek';
		$out['deepseek_api_key']   = isset( $input['deepseek_api_key'] ) ? sanitize_text_field( $input['deepseek_api_key'] ) : '';
		$out['gemini_api_key']     = isset( $input['gemini_api_key'] ) ? sanitize_text_field( $input['gemini_api_key'] ) : '';
		$gemini_models            = array_keys( AICG_Settings::get_gemini_models() );
		$out['gemini_model']      = isset( $input['gemini_model'] ) && in_array( $input['gemini_model'], $gemini_models, true ) ? $input['gemini_model'] : 'gemini-2.5-flash-lite';
		$out['batch_delay_seconds'] = isset( $input['batch_delay_seconds'] ) ? max( 0, min( 120, (int) $input['batch_delay_seconds'] ) ) : 0;
		$out['randomize_date']       = ! empty( $input['randomize_date'] ) ? 1 : 0;
		$out['randomize_date_days']  = isset( $input['randomize_date_days'] ) ? max( 1, min( 3650, (int) $input['randomize_date_days'] ) ) : 365;
		$out['default_word_count'] = isset( $input['default_word_count'] ) ? max( 200, min( 5000, (int) $input['default_word_count'] ) ) : 800;
		$out['system_prompt']      = isset( $input['system_prompt'] ) ? wp_kses_post( $input['system_prompt'] ) : AICG_Settings::DEFAULT_SYSTEM_PROMPT;
		$out['image_sources']      = array();
		if ( ! empty( $input['image_sources'] ) && is_array( $input['image_sources'] ) ) {
			$allowed = array_keys( AICG_Settings::get_image_sources_config() );
			$out['image_sources'] = array_values( array_intersect( $input['image_sources'], $allowed ) );
		}
		if ( empty( $out['image_sources'] ) ) {
			$out['image_sources'] = array( 'pexels' );
		}
		$out['unsplash_api_key'] = isset( $input['unsplash_api_key'] ) ? sanitize_text_field( $input['unsplash_api_key'] ) : '';
		$out['pexels_api_key']  = isset( $input['pexels_api_key'] ) ? sanitize_text_field( $input['pexels_api_key'] ) : '';
		$out['pixabay_api_key'] = isset( $input['pixabay_api_key'] ) ? sanitize_text_field( $input['pixabay_api_key'] ) : '';
		return $out;
	}

	public function enqueue_assets( $hook ) {
		if ( strpos( $hook, self::PAGE_SLUG ) === false && strpos( $hook, self::PAGE_GEN ) === false ) {
			return;
		}
		wp_enqueue_style( 'aicg-admin', AICG_PLUGIN_URL . 'assets/admin.css', array(), AICG_VERSION );
		if ( strpos( $hook, self::PAGE_GEN ) !== false ) {
			wp_enqueue_script( 'aicg-generate', AICG_PLUGIN_URL . 'assets/generate.js', array( 'jquery' ), AICG_VERSION, true );
			wp_localize_script( 'aicg-generate', 'aicgGenerate', array(
				'ajax_url'       => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'aicg_generate' ),
				'delay_seconds'  => max( 0, min( 120, (int) AICG_Settings::get( 'batch_delay_seconds', 0 ) ) ),
			) );
		}
	}

	/**
	 * Add "Generate Posts" button next to "Add New" on the Posts list screen.
	 */
	public function add_generate_posts_button() {
		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== 'post' || ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$url = admin_url( 'edit.php?page=' . self::PAGE_GEN );
		$text = __( 'Generate Posts', 'ai-content-generator' );
		?>
		<script>
		(function(){
			var el = document.querySelector('.wrap .page-title-action');
			if (el) {
				var a = document.createElement('a');
				a.href = <?php echo wp_json_encode( $url ); ?>;
				a.className = 'page-title-action';
				a.textContent = <?php echo wp_json_encode( $text ); ?>;
				el.insertAdjacentElement('afterend', a);
			}
		})();
		</script>
		<?php
	}

	public function render_settings_page() {
		$opts = AICG_Settings::get();
		$img_sources = AICG_Settings::get_image_sources_config();
		?>
		<div class="wrap aicg-settings">
			<h1><?php esc_html_e( 'AI Content Generator Settings', 'ai-content-generator' ); ?></h1>
			<p><?php esc_html_e( 'Configure your AI provider and image sources. API keys are stored in your database.', 'ai-content-generator' ); ?></p>

			<form method="post" action="options.php" id="aicg-settings-form">
				<?php settings_fields( AICG_Settings::OPTION_GROUP ); ?>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'AI Provider', 'ai-content-generator' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( AICG_Settings::OPTION_NAME ); ?>[ai_provider]">
								<option value="deepseek" <?php selected( $opts['ai_provider'], 'deepseek' ); ?>><?php esc_html_e( 'DeepSeek', 'ai-content-generator' ); ?></option>
								<option value="gemini" <?php selected( $opts['ai_provider'], 'gemini' ); ?>><?php esc_html_e( 'Google Gemini', 'ai-content-generator' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Choose which AI generates post content and categories.', 'ai-content-generator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'DeepSeek API Key', 'ai-content-generator' ); ?></th>
						<td>
							<input type="password" class="regular-text" name="<?php echo esc_attr( AICG_Settings::OPTION_NAME ); ?>[deepseek_api_key]" value="<?php echo esc_attr( $opts['deepseek_api_key'] ); ?>" autocomplete="off" />
							<p class="description"><a href="https://platform.deepseek.com/" target="_blank" rel="noopener"><?php esc_html_e( 'Get API key', 'ai-content-generator' ); ?></a></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Gemini API Key', 'ai-content-generator' ); ?></th>
						<td>
							<input type="password" class="regular-text" name="<?php echo esc_attr( AICG_Settings::OPTION_NAME ); ?>[gemini_api_key]" value="<?php echo esc_attr( $opts['gemini_api_key'] ); ?>" autocomplete="off" />
							<p class="description"><a href="https://ai.google.dev/" target="_blank" rel="noopener"><?php esc_html_e( 'Get API key (Google AI Studio)', 'ai-content-generator' ); ?></a></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Gemini model', 'ai-content-generator' ); ?></th>
						<td>
							<?php
							$gemini_models_list = AICG_Settings::get_gemini_models();
							$current_gemini    = isset( $opts['gemini_model'] ) && array_key_exists( $opts['gemini_model'], $gemini_models_list ) ? $opts['gemini_model'] : 'gemini-2.5-flash-lite';
							?>
							<select name="<?php echo esc_attr( AICG_Settings::OPTION_NAME ); ?>[gemini_model]">
								<?php foreach ( $gemini_models_list as $model_id => $label ) : ?>
									<option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $current_gemini, $model_id ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Choose a model from the list. Flash Lite is budget-friendly. See ai.google.dev/gemini-api/docs/models for details.', 'ai-content-generator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Delay between posts (seconds)', 'ai-content-generator' ); ?></th>
						<td>
							<input type="number" name="<?php echo esc_attr( AICG_Settings::OPTION_NAME ); ?>[batch_delay_seconds]" value="<?php echo esc_attr( isset( $opts['batch_delay_seconds'] ) ? $opts['batch_delay_seconds'] : 0 ); ?>" min="0" max="120" />
							<p class="description"><?php esc_html_e( 'Gemini free tier allows 10 requests per minute. Set to 6 or 7 to pause between each post and avoid quota errors. 0 = no delay. You can also set this on the Generate AI Posts page.', 'ai-content-generator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Randomize post date', 'ai-content-generator' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( AICG_Settings::OPTION_NAME ); ?>[randomize_date]" value="1" <?php checked( ! empty( $opts['randomize_date'] ) ); ?> />
								<?php esc_html_e( 'Assign a random date to each generated post', 'ai-content-generator' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'When enabled, each post gets a random date in the past. Disabled by default.', 'ai-content-generator' ); ?></p>
							<p>
								<label for="aicg-randomize-date-days"><?php esc_html_e( 'Within the past (days)', 'ai-content-generator' ); ?></label>
								<input type="number" id="aicg-randomize-date-days" name="<?php echo esc_attr( AICG_Settings::OPTION_NAME ); ?>[randomize_date_days]" value="<?php echo esc_attr( isset( $opts['randomize_date_days'] ) ? $opts['randomize_date_days'] : 365 ); ?>" min="1" max="3650" /> (1–3650)
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Default word count per post', 'ai-content-generator' ); ?></th>
						<td>
							<input type="number" name="<?php echo esc_attr( AICG_Settings::OPTION_NAME ); ?>[default_word_count]" value="<?php echo esc_attr( $opts['default_word_count'] ); ?>" min="200" max="5000" step="50" />
							<p class="description"><?php esc_html_e( 'Approximate words per generated post (200–5000). Can be overridden when generating.', 'ai-content-generator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'System prompt', 'ai-content-generator' ); ?></th>
						<td>
							<textarea name="<?php echo esc_attr( AICG_Settings::OPTION_NAME ); ?>[system_prompt]" rows="6" class="large-text"><?php echo esc_textarea( $opts['system_prompt'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Instructions for the AI style (e.g. human-like, no em dashes, fewer emojis). You can change this anytime.', 'ai-content-generator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Image sources', 'ai-content-generator' ); ?></th>
						<td>
							<?php foreach ( $img_sources as $id => $config ) : ?>
								<label class="aicg-checkbox-row">
									<input type="checkbox" name="<?php echo esc_attr( AICG_Settings::OPTION_NAME ); ?>[image_sources][]" value="<?php echo esc_attr( $id ); ?>" <?php checked( in_array( $id, $opts['image_sources'], true ) ); ?> />
									<?php echo esc_html( $config['label'] ); ?>
									<a href="<?php echo esc_url( $config['url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( '(get API key)', 'ai-content-generator' ); ?></a>
								</label><br>
							<?php endforeach; ?>
							<p class="description"><?php esc_html_e( 'First configured source with a valid API key will be used for featured images.', 'ai-content-generator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Unsplash API Key', 'ai-content-generator' ); ?></th>
						<td>
							<input type="password" class="regular-text" name="<?php echo esc_attr( AICG_Settings::OPTION_NAME ); ?>[unsplash_api_key]" value="<?php echo esc_attr( $opts['unsplash_api_key'] ); ?>" autocomplete="off" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Pexels API Key', 'ai-content-generator' ); ?></th>
						<td>
							<input type="password" class="regular-text" name="<?php echo esc_attr( AICG_Settings::OPTION_NAME ); ?>[pexels_api_key]" value="<?php echo esc_attr( $opts['pexels_api_key'] ); ?>" autocomplete="off" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Pixabay API Key', 'ai-content-generator' ); ?></th>
						<td>
							<input type="password" class="regular-text" name="<?php echo esc_attr( AICG_Settings::OPTION_NAME ); ?>[pixabay_api_key]" value="<?php echo esc_attr( $opts['pixabay_api_key'] ); ?>" autocomplete="off" />
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public function render_generate_page() {
		$ai = AICG_Generator::get_ai_provider();
		$ai_ok = $ai && $ai->is_configured();
		$word_count = (int) AICG_Settings::get( 'default_word_count', 800 );
		?>
		<div class="wrap aicg-generate">
			<h1><?php esc_html_e( 'Generate AI Posts', 'ai-content-generator' ); ?></h1>
			<?php if ( ! $ai_ok ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'Please set your AI provider API key in Settings → AI Content Generator.', 'ai-content-generator' ); ?></p></div>
			<?php endif; ?>
			<p><a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) ); ?>"><?php esc_html_e( 'Settings (API keys, word count, system prompt, image sources)', 'ai-content-generator' ); ?></a></p>

			<div class="aicg-generate-form-card">
				<form id="aicg-generate-form" class="aicg-form">
					<p>
						<label for="aicg-topic"><?php esc_html_e( 'Content topic / description', 'ai-content-generator' ); ?></label><br>
						<textarea id="aicg-topic" name="topic" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'e.g. Tips for remote work productivity, or: Healthy breakfast ideas for busy mornings', 'ai-content-generator' ); ?>"></textarea>
						<span class="description"><?php esc_html_e( 'Describe the kind of content you want. Each post will vary within this theme.', 'ai-content-generator' ); ?></span>
					</p>
					<p>
						<label for="aicg-count"><?php esc_html_e( 'Number of posts', 'ai-content-generator' ); ?></label><br>
						<input type="number" id="aicg-count" name="count" value="5" min="1" max="50" />
					</p>
					<p>
						<label for="aicg-word-count"><?php esc_html_e( 'Word count per post', 'ai-content-generator' ); ?></label><br>
						<input type="number" id="aicg-word-count" name="word_count" value="<?php echo esc_attr( $word_count ); ?>" min="200" max="5000" step="50" />
					</p>
					<p>
						<label for="aicg-delay"><?php esc_html_e( 'Delay between posts (seconds)', 'ai-content-generator' ); ?></label><br>
						<input type="number" id="aicg-delay" name="delay_seconds" value="<?php echo esc_attr( max( 0, min( 120, (int) AICG_Settings::get( 'batch_delay_seconds', 0 ) ) ) ); ?>" min="0" max="120" />
						<span class="description"><?php esc_html_e( 'Pause between each post to avoid API rate limits (e.g. 7 for Gemini free tier). 0 = no delay. Default comes from Settings.', 'ai-content-generator' ); ?></span>
					</p>
					<p>
						<button type="submit" class="button button-primary button-hero" id="aicg-start" <?php echo $ai_ok ? '' : 'disabled'; ?>>
							<?php esc_html_e( 'Generate posts', 'ai-content-generator' ); ?>
						</button>
					</p>
				</form>

				<div id="aicg-progress" class="aicg-progress" style="display:none;">
					<p><strong><?php esc_html_e( 'Progress', 'ai-content-generator' ); ?></strong></p>
					<div class="aicg-progress-bar"><div class="aicg-progress-fill" style="width:0%"></div></div>
					<p class="aicg-progress-text">0 / 0</p>
					<p class="aicg-progress-status"></p>
				</div>

				<div id="aicg-results" class="aicg-results" style="display:none;">
					<p><strong><?php esc_html_e( 'Created posts', 'ai-content-generator' ); ?></strong></p>
					<ul id="aicg-results-list"></ul>
				</div>
			</div>
		</div>
		<?php
	}

	public function ajax_generate_post() {
		check_ajax_referer( 'aicg_generate', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-content-generator' ) ) );
		}

		$topic       = isset( $_POST['topic'] ) ? sanitize_textarea_field( wp_unslash( $_POST['topic'] ) ) : '';
		$word_count  = isset( $_POST['word_count'] ) ? max( 200, min( 5000, (int) $_POST['word_count'] ) ) : (int) AICG_Settings::get( 'default_word_count', 800 );

		if ( ! $topic ) {
			wp_send_json_error( array( 'message' => __( 'Topic is required.', 'ai-content-generator' ) ) );
		}

		$post_id = AICG_Generator::generate_one_post( $topic, $word_count );
		if ( is_wp_error( $post_id ) ) {
			$message = $post_id->get_error_message();
			if ( stripos( $message, 'quota' ) !== false || stripos( $message, 'rate' ) !== false ) {
				$message .= ' ' . __( 'Tip: In Settings, set "Delay between posts" to 6 or 7 seconds to stay under Gemini free tier (10 req/min). Or use DeepSeek as AI provider.', 'ai-content-generator' );
			}
			wp_send_json_error( array( 'message' => $message ) );
		}

		$post = get_post( $post_id );
		$edit_url = get_edit_post_link( $post_id, 'raw' );
		wp_send_json_success( array(
			'post_id'   => $post_id,
			'title'     => $post->post_title,
			'edit_url'  => $edit_url,
		) );
	}
}
