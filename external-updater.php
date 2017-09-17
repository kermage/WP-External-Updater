<?php

/**
 * @package External-Updater
 * @since 0.1.0
 */

if ( ! class_exists( 'External_Updater' ) ) :
class External_Updater {

	private $full_path;
	private $update_url;
	private $type;
	private $slug;
	private $key;
	private $transient = 'external_updater_';
	private $current_version = '';
	private $update_data = null;

	public function __construct( $full_path, $update_url ) {
		$this->full_path = $full_path;
		$this->update_url = $update_url;
		$this->get_file_details( $full_path );
		$this->transient .= $this->type . '_' . $this->slug;

		add_filter( 'site_transient_update_' . $this->type . 's', array( $this, 'set_available_update' ) );

		if ( $this->type == 'plugin' ) {
			add_filter( 'plugins_api', array( $this, 'set_plugin_info' ), 10, 3 );
		}

		add_filter( 'upgrader_source_selection', array( $this, 'fix_directory_name' ), 10, 4 );

		$this->maybe_delete_transient();
	}

	private function get_file_details( $path ) {
		$folder = dirname( $path );
		$folder_name = basename( $folder );

		$this->slug = $folder_name;

		if ( file_exists( $folder . '/style.css' ) ) {
			$this->type = 'theme';
			$this->key = $folder_name;

			$data = wp_get_theme( $folder_name );
			$this->current_version = $data->get( 'Version' );
		} else {
			$this->type = 'plugin';
			$this->key = plugin_basename( $path );

			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
			}

			$data = get_plugin_data( $path, false, false );
			$this->current_version = $data['Version'];
		}
	}

	public function set_available_update( $transient ) {
		$remote_data = $this->get_remote_data();

		if ( isset ( $transient->response[$this->key] ) ) {
			unset( $transient->response[$this->key] );
		}

		if ( version_compare( $this->current_version, $remote_data->new_version, '<' ) ) {
			$transient->response[$this->key] = $this->format_response( $remote_data );
		}

		return $transient;
	}

	public function set_plugin_info( $data, $action = '', $args = null ) {
		if ( $action !== 'plugin_information' || $args->slug !== $this->slug ) {
			return $data;
		}

		$remote_data = $this->get_remote_data();

		return $remote_data;
	}

	private function get_remote_data() {
		if ( $this->update_data ) {
			return $this->update_data;
		}

		$data = get_site_transient( $this->transient );

		if ( ! is_object( $data ) ) {
			$data = $this->call_remote_api();
			set_site_transient( $this->transient, $data, HOUR_IN_SECONDS );
		}

		$this->update_data = $data;

		return $data;
	}

	private function call_remote_api() {
		$request = array(
			'action' => 'update',
			'type' => $this->type,
			'slug' => $this->slug
		);
		$url = add_query_arg( $request, $this->update_url );
		$options = array( 'timeout' => 10 );
		$response = wp_remote_get( $url, $options );
		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code === 200 ) {
			return json_decode( $body );
		}
	}

	private function format_response( $unformatted ) {
		if ( $this->type == 'theme' ) {
			$formatted = (array) $unformatted;
			$formatted['theme'] = $this->slug;
		} else {
			$formatted = (object) $unformatted;
			$formatted->slug = $this->slug;
			$formatted->plugin = $this->key;
			$formatted->version = $unformatted->new_version;
			$formatted->download_link = $unformatted->package;
			$formatted->homepage = $unformatted->url;
			$formatted->sections = (array) $unformatted->sections;
		}

		return $formatted;
	}

	private function maybe_delete_transient() {
		if ( $GLOBALS['pagenow'] === 'update-core.php' && isset( $_GET['force-check'] ) ) {
			delete_site_transient( $this->transient );
		}
	}

	public function fix_directory_name( $source, $remote_source, $upgrader, $hook_extra = null ) {
		global $wp_filesystem;

		if ( isset( $hook_extra['theme'] ) && $hook_extra['theme'] == $this->key ||
			isset( $hook_extra['plugin'] ) && $hook_extra['plugin'] == $this->key ) {
			$corrected_source = trailingslashit( $remote_source ) . $this->slug . '/';
			$wp_filesystem->move( $source, $corrected_source );

			return $corrected_source;
		}

		return $source;
	}

}
endif;
