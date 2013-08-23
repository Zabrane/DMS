<?php
/**
 *  DMS 3rd party plugin/theme updating class.
 *
 *  @package DMS
 *  @since 1.0
 *
 *
 */
class PageLinesEditorUpdates {
	
	function __construct() {

		if ( ! current_user_can( 'edit_theme_options' ) )
			return;

		add_filter( 'site_transient_update_plugins', array( $this, 'injectUpdatePlugins' ), 999 );		
		add_filter( 'site_transient_update_themes', array( $this, 'injectUpdateThemes' ), 999 );
	}
	
	function injectUpdateThemes( $updates ) {

		if( ! $this->pl_is_pro() )
			return $updates;

		global $storeapi;
		if( ! is_object( $storeapi ) )
			$storeapi = new EditorStoreFront;
		$mixed_array = $storeapi->get_latest();
		$themes = $this->get_pl_themes();
		
		foreach( $themes as $slug => $data ) {
			
			if( ! isset( $mixed_array[$slug]['version'] ) )
				continue;
				
			if( $mixed_array[$slug]['version'] > $data['Version'] ) {
				$updates->response[$slug] = $this->build_theme_array( $mixed_array[$slug], $data );		
			}	
		}
		return $updates;
	}
	
	function injectUpdatePlugins( $updates ) {
		
		global $pl_plugins;
		global $storeapi;
		if( ! is_object( $storeapi ) )
			$storeapi = new EditorStoreFront;
		$mixed_array = $storeapi->get_latest();
		
		if( ! $pl_plugins )
			$pl_plugins = $this->get_pl_plugins();		

		if( ! is_array( $pl_plugins ) || empty( $pl_plugins ) )
			return $updates;
		
		foreach( $pl_plugins as $path => $data ) {
			$slug = dirname( $path );
			
			$default_headers = array(
				'Version'	=> 'Version',
				'v3'	=> 'v3'
				);

			$fullpath = sprintf( '%s%s', trailingslashit( WP_PLUGIN_DIR ), $path );
			
			$data = get_file_data( $fullpath, $default_headers );

			if( isset( $mixed_array[$slug] ) ) {
				
				// remove a wp.org plugin trying to update a dms plugin.			
				if( ! isset( $mixed_array[$slug]['version'] ) ) {
					if( is_object( $updates ) && isset( $updates->response[$path] ) ) {
						unset( $updates->response[$path] );
					}
					continue;
				}

				if( $mixed_array[$slug]['version'] <= $data['Version'] ) {
					if( is_object( $updates ) && isset( $updates->response[$path] ) ) {
						unset( $updates->response[$path] );
					}
					continue;
				}
				
				if( $this->pl_is_pro() )
					$object = $this->build_plugin_object( $mixed_array[$slug], $data );
				if( isset( $object->new_version ) ) {
					$updates->response[$path] = $object; 
				}
			} elseif( isset( $updates->response[$path] ) ) {				
				unset( $updates->response[$path] );
			}
		}
		return $updates;
	}
	
	function build_theme_array( $api_data, $data ) {
		
		$object = array();
		$object['new_version'] = $api_data['version'];
		$object['upgrade_notice'] = '<strong>Simon is awesome!</strong>';
		$object['url'] = $api_data['overview'];
		$object['package'] = $this->build_url( $api_data, $data );
		return $object;
	}
	
	function build_plugin_object( $api_data, $data ) {

		$object = new stdClass;		
		$object->id = rand();
		$object->slug = $api_data['slug'];
		$object->new_version = $api_data['version'];
		$object->upgrade_notice = 'This is a PageLines Premium plugin.';
		$object->url = $api_data['overview'];
		$object->package = $this->build_url( $api_data, $data );
		
		return $object;
	}
	
	function build_url( $api_data, $data ) {
		
		if( $api_data['type'] == 'sections' && true == $data['v3'] ) {
			$prefix = 'v3/';
		} else {
			$prefix = 'plugin-';
		}
		if( $api_data['type'] == 'themes' ) {
			$prefix = 'theme-';
		}
		
		return sprintf( 'http://api.pagelines.com/store/%s%s.zip', $prefix, $api_data['slug'] );
	}
	
	
	function get_pl_plugins() {
		
		global $pl_plugins;
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		
		$pl_plugins = get_plugins();

		return $pl_plugins;
	}
	
	function get_pl_themes() {
		$installed_themes = pl_get_themes();
		
		foreach( $installed_themes as $slug => $theme ) {

			if( 'dms' != $theme['Template'] )
				unset( $installed_themes[$slug]);
			if( 'dms' == $slug )
				unset( $installed_themes[$slug]);
		}
		return $installed_themes;
	}

	function pl_is_pro(){	
		// editor functions not loaded yet so we need this
		$status = get_option( 'dms_activation', array( 'active' => false, 'key' => '', 'message' => '', 'email' => '' ) );

		$pro = (isset($status['active']) && true === $status['active']) ? true : false;

		return $pro;	
	}
}

new PageLinesEditorUpdates;