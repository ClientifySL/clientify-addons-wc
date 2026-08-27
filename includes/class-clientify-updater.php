<?php

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Maneja las actualizaciones automáticas del plugin desde GitHub Releases (repo público).
 *
 * Flujo:
 *  1. WordPress consulta periódicamente si hay actualizaciones.
 *  2. Esta clase llama a la GitHub API para obtener el último release.
 *  3. Si la versión es mayor, WordPress muestra "Actualizar".
 *  4. WordPress descarga el zip directamente desde browser_download_url (público, sin auth).
 */
class Clientify_Updater {

	private string $plugin_file;
	private string $plugin_slug;
	private string $version;
	private string $github_user;
	private string $github_repo;

	public function __construct( string $plugin_file, string $version, string $github_user, string $github_repo ) {
		$this->plugin_file = $plugin_file;
		$this->plugin_slug = plugin_basename( $plugin_file );
		$this->version     = $version;
		$this->github_user = $github_user;
		$this->github_repo = $github_repo;
	}

	public function init(): void {
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_update' ] );
		add_filter( 'plugins_api', [ $this, 'plugin_info' ], 20, 3 );
	}

	public function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $transient;
		}

		if ( version_compare( $this->version, $release->version, '<' ) ) {
			$transient->response[ $this->plugin_slug ] = (object) [
				'slug'        => dirname( $this->plugin_slug ),
				'plugin'      => $this->plugin_slug,
				'new_version' => $release->version,
				'package'     => $release->download_url,
				'tested'      => '',
				'requires'    => '',
			];
		}

		return $transient;
	}

	public function plugin_info( $result, $action, $args ) {
		if ( $action !== 'plugin_information' ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || $args->slug !== dirname( $this->plugin_slug ) ) {
			return $result;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $result;
		}

		return (object) [
			'name'          => 'Clientify-Ecommerce',
			'slug'          => dirname( $this->plugin_slug ),
			'version'       => $release->version,
			'author'        => '<a href="https://clientify.com">Clientify SL</a>',
			'download_link' => $release->download_url,
			'last_updated'  => $release->published_at,
			'sections'      => [
				'description' => 'Conecta WooCommerce con Clientify para automatizar el marketing de tu tienda online.',
				'changelog'   => $release->changelog,
			],
		];
	}

	private function get_latest_release(): ?object {
		$cache_key = 'clientify_github_release';
		$cached    = get_transient( $cache_key );

		if ( $cached !== false ) {
			return $cached ?: null;
		}

		$response = wp_remote_get(
			"https://api.github.com/repos/{$this->github_user}/{$this->github_repo}/releases/latest",
			[
				'timeout' => 10,
				'headers' => [
					'Accept'     => 'application/json',
					'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ),
				],
			]
		);

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			set_transient( $cache_key, '', HOUR_IN_SECONDS );
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ) );

		if ( empty( $data->tag_name ) ) {
			set_transient( $cache_key, '', HOUR_IN_SECONDS );
			return null;
		}

		// Con repo público browser_download_url es accesible sin autenticación.
		$download_url = '';
		if ( ! empty( $data->assets ) ) {
			foreach ( $data->assets as $asset ) {
				if ( str_ends_with( $asset->name, '.zip' ) ) {
					$download_url = $asset->browser_download_url;
					break;
				}
			}
		}

		if ( ! $download_url ) {
			$download_url = "https://github.com/{$this->github_user}/{$this->github_repo}/archive/refs/tags/{$data->tag_name}.zip";
		}

		$release = (object) [
			'version'      => ltrim( $data->tag_name, 'v' ),
			'download_url' => $download_url,
			'published_at' => $data->published_at ?? '',
			'changelog'    => nl2br( $data->body ?? '' ),
		];

		set_transient( $cache_key, $release, 12 * HOUR_IN_SECONDS );

		return $release;
	}
}
