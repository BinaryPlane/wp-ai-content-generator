<?php
/**
 * GitHub-based plugin updater. Uses releases/latest and zipball_url;
 * after_install moves the extracted folder so the plugin path stays correct.
 *
 * @package AI_Content_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';

class GitHub_Plugin_Updater {

	private $file;
	private $plugin;
	private $basename;
	private $active;
	private $username;
	private $repository;
	private $authorize_token;
	private $github_response;

	public function __construct( $file, $username = null, $repository = null ) {
		$this->file = $file;
		add_action( 'admin_init', array( $this, 'set_plugin_properties' ) );

		$this->basename = plugin_basename( $this->file );
		$this->active   = is_plugin_active( $this->basename );
		$this->username = $username;
		$this->repository = $repository;
		$this->authorize_token = null;
	}

	public function set_plugin_properties() {
		$this->plugin = get_plugin_data( $this->file, false, false );
		if ( ! $this->username ) {
			$this->username = 'BinaryPlane';
		}
		if ( ! $this->repository ) {
			if ( defined( 'AICG_GITHUB_REPO' ) && preg_match( '#^([^/]+)/([^/]+)$#', AICG_GITHUB_REPO, $m ) ) {
				$this->username   = $m[1];
				$this->repository = $m[2];
			} else {
				$this->repository = 'wp-ai-content-generator';
			}
		}
	}

	private function get_repository_info() {
		if ( is_null( $this->github_response ) ) {
			$request_uri = sprintf(
				'https://api.github.com/repos/%s/%s/releases/latest',
				$this->username,
				$this->repository
			);

			$args = array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github.v3+json',
					'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ),
				),
			);
			if ( $this->authorize_token ) {
				$args['headers']['Authorization'] = 'bearer ' . $this->authorize_token;
			}

			$response = wp_remote_get( $request_uri, $args );

			if ( is_wp_error( $response ) ) {
				return;
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			if ( $response_code !== 200 ) {
				return;
			}

			$response_body = wp_remote_retrieve_body( $response );
			$result       = json_decode( $response_body, true );

			if ( is_array( $result ) && isset( $result['tag_name'] ) ) {
				$this->github_response = $result;
			}
		}
	}

	private static function normalize_version( $tag ) {
		return preg_replace( '/^v/', '', trim( $tag ) );
	}

	public function initialize() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'modify_transient' ), 10, 1 );
		add_filter( 'plugins_api', array( $this, 'plugin_popup' ), 10, 3 );
		add_filter( 'upgrader_post_install', array( $this, 'after_install' ), 10, 3 );
	}

	public function modify_transient( $transient ) {
		if ( ! property_exists( $transient, 'checked' ) || empty( $transient->checked ) ) {
			return $transient;
		}

		$this->get_repository_info();

		if ( empty( $this->github_response ) ) {
			return $transient;
		}

		$checked         = $transient->checked;
		$current_version = isset( $checked[ $this->basename ] ) ? $checked[ $this->basename ] : '0.0';
		$latest_tag      = isset( $this->github_response['tag_name'] ) ? $this->github_response['tag_name'] : '';
		$latest_version  = self::normalize_version( $latest_tag );

		$out_of_date = version_compare( $latest_version, $current_version, 'gt' );

		if ( $out_of_date && ! empty( $this->github_response['zipball_url'] ) ) {
			$slug = current( explode( '/', $this->basename ) );

			$transient->response[ $this->basename ] = (object) array(
				'slug'        => $slug,
				'plugin'      => $this->basename,
				'new_version' => $latest_version,
				'url'         => isset( $this->plugin['PluginURI'] ) ? $this->plugin['PluginURI'] : '',
				'package'     => $this->github_response['zipball_url'],
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

	public function plugin_popup( $result, $action, $args ) {
		if ( $action !== 'plugin_information' || empty( $args->slug ) ) {
			return $result;
		}

		$slug = current( explode( '/', $this->basename ) );
		if ( $args->slug !== $slug ) {
			return $result;
		}

		$this->get_repository_info();

		if ( empty( $this->github_response ) ) {
			return $result;
		}

		$version = self::normalize_version( $this->github_response['tag_name'] );

		return (object) array(
			'name'              => isset( $this->plugin['Name'] ) ? $this->plugin['Name'] : 'AI Content Generator',
			'slug'              => $slug,
			'version'           => $version,
			'author'            => isset( $this->plugin['Author'] ) ? $this->plugin['Author'] : 'BinaryPlane',
			'author_profile'    => isset( $this->plugin['AuthorURI'] ) ? $this->plugin['AuthorURI'] : 'https://binaryplane.com',
			'last_updated'      => isset( $this->github_response['published_at'] ) ? $this->github_response['published_at'] : '',
			'homepage'          => isset( $this->plugin['PluginURI'] ) ? $this->plugin['PluginURI'] : '',
			'short_description' => isset( $this->plugin['Description'] ) ? $this->plugin['Description'] : '',
			'sections'          => array(
				'description' => isset( $this->plugin['Description'] ) ? $this->plugin['Description'] : '',
				'updates'     => isset( $this->github_response['body'] ) ? $this->github_response['body'] : '',
			),
			'download_link'     => $this->github_response['zipball_url'],
		);
	}

	public function after_install( $response, $hook_extra, $result ) {
		if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->basename ) {
			return $response;
		}

		global $wp_filesystem;

		$install_directory = plugin_dir_path( $this->file );
		$wp_filesystem->move( $result['destination'], $install_directory );
		$result['destination'] = $install_directory;

		if ( $this->active ) {
			activate_plugin( $this->basename );
		}

		return $result;
	}
}
