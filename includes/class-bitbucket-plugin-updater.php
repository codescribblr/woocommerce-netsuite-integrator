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
        add_filter( "plugins_api_result", array( $this, "set_plugin_info" ), 10, 3 );
        add_filter( "upgrader_post_install", array( $this, "post_install" ), 10, 3 );
        add_filter( 'http_request_args', array( $this, 'maybe_authenticate_http' ), 10, 2 );
         
        $this->owner = $bitbucket_project_owner;
        $this->repo = $bitbucket_project_name;
        $this->plugin_file = $plugin_file; 						// Format: /public_html/wp-content/plugins/woocommerce-netsuite-integrator/woocommerce-netsuite-integrator.php 
        $this->plugin = plugin_basename($this->plugin_file); 	// Format: woocommerce-netsuite-integrator/woocommerce-netsuite-integrator.php
        $this->slug = basename(dirname($this->plugin_file)); 	// Format: woocommerce-netsuite-integrator || showcase-woocommerce-netsuite-integrator-f8w009e830
        $this->auth = apply_filters('bitbucket_auth_'.$this->owner.'_'.$this->repo, $bitbucket_auth, $this->slug);
        $this->private = $private;
        $this->sections = array(
        	'description' => '',
        	'installation' => '',
        	'FAQ' => '',
        	'changelog' => '',
        	'support' => '',
        );
        $this->init_plugin_data();

    }
 
    // Get information regarding our plugin from WordPress
    private function init_plugin_data() {
        $this->slug = basename(dirname($this->plugin_file));
		$this->plugin_data = get_plugin_data( $this->plugin_file );
    }
 
    // Get information regarding our plugin from GitHub
    private function get_repo_release_info() {
        // Only do this once
		if ( ! empty( $this->bitbucket_API_result ) ) {
		    return;
		}

		$api_result = $this->get_remote_tag();
		$this->bitbucket_API_result = $api_result[$this->newest_tag];

    }
 
    // Push in plugin version information to get the update notification
    public function set_transient( $transient ) {
        
        // If we have checked the plugin data before, don't re-check
		if ( empty( $transient->checked ) ) {
		    return $transient;
		}
		// Get plugin & BitBucket release information
		$this->get_repo_release_info();

		// Check the versions if we need to do an update
		$do_update = version_compare( str_ireplace('v', '', $this->newest_tag), $transient->checked[$this->slug] );

		// Update the transient to include our updated plugin data
		if ( $do_update == 1 ) {
		    $package = $this->construct_download_link();
		 
		    $obj = new stdClass();
		    $obj->slug = $this->slug;
		    $obj->plugin = $this->plugin;
		    $obj->new_version = str_ireplace('v', '', $this->newest_tag);
		    $obj->url = $this->plugin_data["PluginURI"];
		    $obj->package = $package;
		    $transient->response[$this->plugin] = $obj;
		}

        return $transient;
    }
 
    // Push in plugin version information to display in the details lightbox
    public function set_plugin_info( $false, $action, $response ) {

        // Get plugin & BitBucket release information
		$this->get_repo_release_info();

		// If nothing is found, do nothing
		if ( empty( $response->slug ) || $response->slug != $this->slug ) {
		    return $response;
		}

		// Add our plugin information
		$response->last_updated = $this->bitbucket_API_result->timestamp;
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
		// require_once( plugin_dir_path( __FILE__ ) . "class-parsedown.php" );

		$this->get_remote_readme();

		$response->sections = $this->sections;
		$response->tested = $this->tested;
		$response->requires = $this->requires;
		$response->donate = $this->donate;
		$response->contributors = $this->contributors;

        return $response;

    }
 
    // Perform additional actions to successfully install our plugin
    public function post_install( $true, $hook_extra, $result ) {

		// Remember if our plugin was previously activated
		$was_activated = is_plugin_active( $this->slug );

		// Since we are hosted in GitHub, our plugin folder would have a dirname of
		// reponame-tagname change it to our original one:
		global $wp_filesystem;
		$plugin_folder = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . dirname( $this->slug );
		$wp_filesystem->move( $result['destination'], $plugin_folder );
		$result['destination'] = $plugin_folder;

        // Re-activate plugin if needed
		if ( $was_activated ) {
		    $activate = activate_plugin( $this->slug );
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

		return true;

	}

	/**
	 * Read and parse remote readme.txt.
	 *
	 * @return bool
	 */
	public function get_remote_readme() {

		$response = wp_remote_get( 'https://bitbucket.org/api/1.0/repositories/'.$this->owner.'/'.$this->repo.'/src/master/readme.txt' );
		$response = json_decode( wp_remote_retrieve_body( $response ) );

		if ( ! $response ) {
			$response = new \stdClass();
			$response->message = 'No readme found';
		}

		if ( $response && isset( $response->data ) ) {
			$parser   = new Automattic_Readme;
			$response = $parser->parse_readme( $response->data );
		}

		$this->set_readme_info( $response );

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

		$readme = array();
		$response = (array) $response;
		foreach ( $this->sections as $section => $value ) {
			if ( 'description' === $section ) {
				continue;
			}
			$readme['sections/' . $section ] = $value;
		}
		foreach ( $readme as $key => $value ) {
			$key = explode( '/', $key );
			if ( ! empty( $value ) && 'sections' === $key[0] ) {
				unset( $response['sections'][ $key[1] ] );
			}
		}

		unset( $response['sections']['screenshots'] );
		unset( $response['sections']['installation'] );
		$this->sections     = array_merge( (array) $this->sections, (array) $response['sections'] );
		$this->tested       = $response['tested_up_to'];
		$this->requires     = $response['requires_at_least'];
		$this->donate       = $response['donate_link'];
		$this->contributors = $response['contributors'];

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

}

endif;