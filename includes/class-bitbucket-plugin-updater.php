<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'BitBucket_Plugin_Updater' ) ) :

/**
 * BitBucket Plugin Updater Class
 */
class BitBucket_Plugin_Updater {

	private $slug; // plugin slug
    private $plugin_data; // plugin data
    private $username; // BitBucket username
    private $owner;
    private $repo; // BitBucket repo name
    private $private; // Private repo?
    private $sections; // Sections of our readme file
    private $plugin_file; // __FILE__ of our plugin
    private $bitbucket_API_result; // holds data from BitBucket
    private $bitbucket_auth; // array that holds our username and password for private repos
 
    function __construct( $plugin_file, $bitbucket_project_owner, $bitbucket_project_name, $bitbucket_auth, $private = false ) {
        add_filter( "pre_set_site_transient_update_plugins", array( $this, "set_transient" ) );
        add_filter( "plugins_api", array( $this, "set_plugin_info" ), 10, 3 );
        add_filter( "upgrader_post_install", array( $this, "post_install" ), 10, 3 );
        add_filter( 'http_request_args', array( $this, 'maybe_authenticate_http' ), 10, 2 );
        add_filter( 'http_request_args', array( $this, 'http_request_sslverify' ), 10, 2 );
         
        $this->owner = $bitbucket_project_owner;
        $this->repo = $bitbucket_project_name;
        $this->plugin_file = $plugin_file; 											// Format: /public_html/wp-content/plugins/woocommerce-netsuite-integrator/woocommerce-netsuite-integrator.php 
        $this->proper_folder_name = dirname(plugin_basename($this->plugin_file)); 	// Format: woocommerce-netsuite-integrator/woocommerce-netsuite-integrator.php
        $this->slug = plugin_basename($this->plugin_file); 							// Format: woocommerce-netsuite-integrator || showcase-woocommerce-netsuite-integrator-f8w009e830
        $this->sslverify = true;
        $this->auth = apply_filters('bitbucket_auth_'.$this->owner.'_'.$this->repo, $bitbucket_auth, $this);
        $this->private = $private;
        $this->sections = array(
        	'description' => '',
        	'installation' => '',
        	'screenshots' => '',
        	'changelog' => '',
        	'upgrade_notice' => '',
        );
        $this->init_plugin_data();

    }

    /*
	 * Usage self::log_action('error', 'Check out this sweet error!');
	 *
	*/
	public static function log_action($action, $message="", $logfile=false) {
	    $upload_dir = wp_upload_dir();
	    $logfile = ($logfile) ? $logfile : $upload_dir['basedir'] . '/' . date("Y-m-d").'.log';
	    $new = file_exists($logfile) ? false : true;
	    if($handle = fopen($logfile, 'a')) { // append
	        $timestamp = strftime("%Y-%m-%d %H:%M:%S", time());
	        $content = "{$timestamp} | {$action}: {$message}\n";
	        fwrite($handle, $content);
	        fclose($handle);
	        if($new) { chmod($logfile, 0755); }
	    } else {
	        return false;
	    }
	}
 
    // Get information regarding our plugin from WordPress
    private function init_plugin_data() {
    	include_once ABSPATH.'/wp-admin/includes/plugin.php';
		$this->plugin_data = get_plugin_data( $this->plugin_file );
    }
 
    // Get information regarding our plugin from GitHub
    private function get_repo_release_info() {
        // Only do this once
		if ( ! empty( $this->bitbucket_API_result ) ) {
		    return;
		}

		$api_result = $this->get_remote_tag();
		$this->bitbucket_API_result = $api_result->{$this->newest_tag};

    }
 
    // Push in plugin version information to get the update notification
    public function set_transient( $transient ) {

    	self::log_action('transient_update_plugins', print_r($transient, true), ABSPATH . '/actionlog.log');
        
        // If we have checked the plugin data before, don't re-check
		if ( empty( $transient->checked ) ) {
		    return $transient;
		}
		// Get plugin & BitBucket release information
		$this->get_repo_release_info();

		// Check the versions if we need to do an update
		$do_update = version_compare( str_ireplace('v', '', $this->newest_tag), $transient->checked[$this->plugin] );

		// Update the transient to include our updated plugin data
		if ( $do_update == 1 ) {		 
		    $obj = new stdClass();
		    $obj->slug = $this->proper_folder_name;
		    $obj->new_version = str_ireplace('v', '', $this->newest_tag);
		    $obj->url = $this->plugin_data["PluginURI"];
		    $obj->package = $this->construct_download_link();
		    $transient->response[$this->slug] = $obj;
		}

        return $transient;
    }
 
    // Push in plugin version information to display in the details lightbox
    public function set_plugin_info( $false, $action, $response ) {

    	// self::log_action('set_plugin_info_response', print_r($response, true), ABSPATH . '/actionlog.log');
    	

        // Get plugin & BitBucket release information
		$this->get_repo_release_info();

		// self::log_action('updater_object', print_r($this, true), ABSPATH . '/actionlog.log');

		// If nothing is found, do nothing
		if ( !isset( $response->slug ) || $response->slug != $this->proper_folder_name ) {
		    return false;
		}

		// Add our plugin information
		$response->last_updated = date('Y-m-d H:i:s', strtotime($this->bitbucket_API_result->timestamp));
		$response->slug = $this->slug;
		$response->plugin_name  = $this->plugin_data["Name"];
		$response->version = str_ireplace('v', '', $this->newest_tag);
		$response->author = $this->plugin_data["AuthorName"];
		$response->homepage = $this->plugin_data["PluginURI"];
		$response->downloaded = 0;
		$response->external = true;
		 
		// This is our release download zip file
		$download_link = $this->construct_download_link();
		 
		$response->download_link = $download_link;

		// We're going to parse the BitBucket markdown release notes, include the parser
		// require_once( dirname( __FILE__ ) . "/automattic-readme/class-parsedown.php" );

		$this->get_remote_readme();

		$response->sections = $this->sections;
		$response->tested = $this->tested;
		$response->requires = $this->requires;
		$response->donate = $this->donate;
		$response->contributors = $this->contributors;

		self::log_action('updater_object', print_r($this, true), ABSPATH . '/actionlog.log');

        return $response;

    }
 
    // Perform additional actions to successfully install our plugin
    public function post_install( $true, $hook_extra, $result ) {

		// Remember if our plugin was previously activated
		$was_activated = is_plugin_active( $this->plugin );

		// Since we are hosted in BitBucket, our plugin folder would have a dirname of
		// reponame-tagname change it to our original one:
		global $wp_filesystem;
		$plugin_folder = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $this->plugin;
		$wp_filesystem->move( $result['destination'], $plugin_folder );
		$result['destination'] = $plugin_folder;

        // Re-activate plugin if needed
		if ( $was_activated ) {
		    $activate = activate_plugin( $this->plugin );
		    // Output the update message
			$fail  = __( 'The plugin has been updated, but could not be reactivated. Please reactivate it manually.', 'woocommerce-netsuite-integrator' );
			$success = __( 'Plugin reactivated successfully.', 'woocommerce-netsuite-integrator' );
			echo is_wp_error( $activate ) ? $fail : $success;
		}
		 
		return $result;

    }

	public function construct_download_link() {

		$download_link_base = implode( '/', array( 'https://bitbucket.org', $this->owner, $this->repo, 'get/' ) );

		$endpoint = $this->newest_tag . '.zip';

		return $download_link_base . $endpoint;

	}

	/**
	 * Parse tags and set object data.
	 *
	 * @param $response
	 * @param $repo_type
	 *
	 * @return bool
	 */
	protected function parse_tags( $response ) {

		$tags     = array();
		if ( false !== $response ) {
			foreach ( (array) $response as $num => $tag ) {
				if ( isset( $num ) ) {
					$tags[] = $num;
				}
			}

		}
		if ( empty( $tags ) ) {
			return false;
		}

		usort( $tags, 'version_compare' );

		$this->newest_tag 		= array_pop($tags);
		$this->tags       		= $tags;

		return true;

	}

	/**
	 * Get the remote info to for tags.
	 *
	 * @return bool
	 */
	public function get_remote_tag() {

		$response = wp_remote_get( 'https://bitbucket.org/api/1.0/repositories/'.$this->owner.'/'.$this->repo.'/tags' );
		$response = json_decode( wp_remote_retrieve_body( $response ) );
		$arr_resp = (array) $response;

		if ( ! $response || ! $arr_resp ) {
			$response = new \stdClass();
			$response->message = 'No tags found';
		}

		$this->parse_tags( $response );

		return $response;

	}

	/**
	 * Read and parse remote readme.txt.
	 *
	 * @return bool
	 */
	public function get_remote_readme() {

		$response = wp_remote_get( 'https://api.bitbucket.org/1.0/repositories/'.$this->owner.'/'.$this->repo.'/raw/'.$this->newest_tag.'/readme.txt' );
		$response = wp_remote_retrieve_body( $response );

		if ( ! $response ) {
			$response = array();
		}

		if ( $response ) {
			$parser   = new Automattic_Readme;
			$response = $parser->parse_readme( $response );
		}

		$this->set_readme_info( $response );

		self::log_action('parsed_readme_response', print_r($response, true), ABSPATH . '/actionlog.log');

		return true;

	}

	/**
	 * Set data from readme.txt.
	 *
	 * @param $response
	 *
	 * @return bool
	 */
	protected function set_readme_info( $response ) {

		$response = (array) $response;
		foreach ( $response['sections'] as $section => $value ) {
			$this->sections[$section] = $value;
		}

		unset( $response['sections']['screenshots'] );
		// unset( $response['sections']['installation'] );
		$this->sections     = $this->sections;
		$this->tested      	= $response['tested_up_to'];
		$this->requires    	= $response['requires_at_least'];
		$this->donate      	= $response['donate_link'];
		$this->contributors	= $response['contributors'];

		return true;

	}

	/**
	 * Add Basic Authentication $args to http_request_args filter hook
	 * for private Bitbucket repositories only.
	 *
	 * @param  $args
	 * @param  $url
	 *
	 * @return mixed $args
	 */
	public function maybe_authenticate_http( $args, $url ) {

		if ( false === stristr( $url, 'bitbucket' ) ) {
			return $args;
		}

		if ( $this->private ) {
			$username = $this->auth['username'];
			$password = $this->auth['password'];
			$args['headers']['Authorization'] = 'Basic ' . base64_encode( "$username:$password" );
		}

		return $args;

	}

	/**
	 * Callback fn for the http_request_args filter
	 *
	 * @param unknown $args
	 * @param unknown $url
	 *
	 * @return mixed
	 */
	public function http_request_sslverify( $args, $url ) {
		if ( $this->construct_download_link() == $url )
			$args[ 'sslverify' ] = $this->sslverify;
		return $args;
	}

}

endif;