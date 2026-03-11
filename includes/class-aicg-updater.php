<?php
/**
 * GitHub-based plugin update checker.
 *
 * Checks GitHub Releases for a newer version and lets WordPress show an update
 * when the release has a .zip asset (plugin zip with correct folder structure).
 *
 * @package AI_Content_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AICG_Updater {

	/**
	 * GitHub repo in form "owner/repo". Override with define( 'AICG_GITHUB_REPO', 'your/user-repo' ); in wp-config.php if needed.
	 *
	 * @var string
	 */
	const GITHUB_REPO = 'BinaryPlane/wp-ai-content-generator';

	/**
	 * Cache key for the last checked version info.
	 *
	 * @var string
	 */
	const CACHE_KEY = 'aicg_github_release';

	/**
	 * Cache duration in seconds (12 hours).
	 *
	 * @var int
	 */
	const CACHE_TTL = 43200;

	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 20, 3 );
	}

	/**
	 * Inject our plugin into the update_plugins transient if a newer release exists.
	 *
	 * @param object $transient Value of update_plugins transient.
	 * @return object
	 */
	public static function inject_update( $transient ) {
		if ( empty( $transient->checked ) || ! isset( $transient->checked[ AICG_PLUGIN_BASENAME ] ) ) {
			return $transient;
		}

		$release = self::get_latest_release();
		if ( ! $release || empty( $release['version'] ) || empty( $release['package'] ) ) {
			return $transient;
		}

		if ( version_compare( $release['version'], AICG_VERSION, '>' ) ) {
			$repo = self::get_repo();
			$transient->response[ AICG_PLUGIN_BASENAME ] = (object) array(
				'slug'        => dirname( AICG_PLUGIN_BASENAME ),
				'plugin'      => AICG_PLUGIN_BASENAME,
				'new_version' => $release['version'],
				'url'         => 'https://github.com/' . $repo,
				'package'     => $release['package'],
				'icons'       => array(),
				'banners'     => array(),
				'banners_rtl' => array(),
				'tested'      => get_bloginfo( 'version' ),
				'requires_php' => '7.4',
				'compatibility' => new stdClass(),
			);
		}

		return $transient;
	}

	/**
	 * Provide plugin info for the "View details" / install popup.
	 *
	 * @param false|object|array $result Result of plugins_api.
	 * @param string             $action Action (plugin_information).
	 * @param object             $args   Arguments.
	 * @return false|object
	 */
	public static function plugin_info( $result, $action, $args ) {
		if ( $action !== 'plugin_information' || empty( $args->slug ) ) {
			return $result;
		}
		if ( $args->slug !== dirname( AICG_PLUGIN_BASENAME ) ) {
			return $result;
		}

		$release = self::get_latest_release();
		if ( ! $release ) {
			return $result;
		}
		$repo = self::get_repo();
		$result = new stdClass();
		$result->name          = 'AI Content Generator';
		$result->slug           = dirname( AICG_PLUGIN_BASENAME );
		$result->version        = $release['version'];
		$result->author         = '<a href="https://binaryplane.com">BinaryPlane</a>';
		$result->homepage       = 'https://github.com/' . $repo;
		$result->requires       = '5.8';
		$result->tested         = get_bloginfo( 'version' );
		$result->requires_php   = '7.4';
		$result->downloaded     = 0;
		$result->last_updated   = isset( $release['published'] ) ? $release['published'] : '';
		$result->sections       = array(
			'description' => __( 'Mass-generate blog posts using AI (DeepSeek, Gemini) with automatic categories and featured images from free stock photo sources.', 'ai-content-generator' ),
			'changelog'    => isset( $release['body'] ) ? $release['body'] : '',
		);
		$result->download_link = $release['package'];

		return $result;
	}

	/**
	 * Fetch latest release from GitHub API (with cache).
	 * Only returns a package URL if the release has a .zip asset (recommended so the
	 * plugin folder name stays correct). Otherwise no update is offered.
	 *
	 * @return array|null Keys: version, package (zip URL), body, published; or null on failure.
	 */
	private static function get_latest_release() {
		$cached = get_site_transient( self::CACHE_KEY );
		if ( is_array( $cached ) && ! empty( $cached['version'] ) ) {
			return $cached;
		}

		$repo = self::get_repo();
		$url = 'https://api.github.com/repos/' . $repo . '/releases/latest';
		$response = wp_remote_get( $url, array(
			'timeout' => 10,
			'headers' => array(
				'Accept' => 'application/vnd.github.v3+json',
				'User-Agent' => 'WordPress/AI-Content-Generator',
			),
		) );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			set_site_transient( self::CACHE_KEY, array( 'version' => null ), 300 );
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || ! isset( $body['tag_name'] ) ) {
			set_site_transient( self::CACHE_KEY, array( 'version' => null ), 300 );
			return null;
		}

		$version = preg_replace( '/^v/', '', trim( $body['tag_name'] ) );
		if ( ! $version || ! preg_match( '/^\d+\.\d+(\.\d+)?/', $version ) ) {
			set_site_transient( self::CACHE_KEY, array( 'version' => null ), 300 );
			return null;
		}

		$package = '';
		if ( ! empty( $body['assets'] ) && is_array( $body['assets'] ) ) {
			foreach ( $body['assets'] as $asset ) {
				if ( isset( $asset['browser_download_url'] ) && preg_match( '/\.zip$/i', $asset['browser_download_url'] ) ) {
					$package = $asset['browser_download_url'];
					break;
				}
			}
		}

		if ( ! $package ) {
			set_site_transient( self::CACHE_KEY, array( 'version' => null ), 300 );
			return null;
		}

		$release = array(
			'version'   => $version,
			'package'   => $package,
			'body'      => isset( $body['body'] ) ? $body['body'] : '',
			'published' => isset( $body['published_at'] ) ? $body['published_at'] : '',
		);

		set_site_transient( self::CACHE_KEY, $release, self::CACHE_TTL );
		return $release;
	}

	/**
	 * Clear update cache (e.g. after releasing a new version).
	 */
	public static function clear_cache() {
		delete_site_transient( self::CACHE_KEY );
	}

	/**
	 * @return string GitHub repo "owner/repo".
	 */
	private static function get_repo() {
		return defined( 'AICG_GITHUB_REPO' ) ? AICG_GITHUB_REPO : self::GITHUB_REPO;
	}
}
