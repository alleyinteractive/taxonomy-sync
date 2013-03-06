<?php
/**
 * @package Yahoo API
 * @version 0.1
 */
 
/*
 Plugin Name: Taxonomy Sync
 Description: Synchronizes taxonomies between two WordPress sites. It can handle
 Author: Bradford Campeau-Laurion
 Version: 0.1
 Author URI: http://alleyinteractive.com
 */
 
if( !class_exists( 'Taxonomy_Sync' ) ) :
 
class Taxonomy_Sync {

	/** @type array Current plugin settings */
	private $settings = array();
	
	/** @type array Default plugin settings */
	private $default_settings = array();

	/** @type string Prefix to use for all variables throughout the plugin */
	private $prefix = 'taxonomy_sync';
	
	/** @type string  */
	private $sync_uri = 'receive_term';
	
	/** @type array Stores errors from the most recent API request */
	public $errors = array();

	
	/**
	 * Prepare settings, variables and hooks
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		add_action( 'init', array( &$this, 'setup_plugin' ) );
	}


	/**
	 * Setup filters and actions, menus, JS and CSS scripts
	 *
	 * @access public
	 * @return void
	 */
	public function setup_plugin() {
		
		// Initialize settings and defaults
		$this->default_settings = array(
			'key' => '',
			'mode' => '',
			'initial_sync' => false,
			'remote_url' => '',
			'taxonomies' => array()
		);

		$user_settings = get_option( $this->prefix . '_settings' );
		if ( false === $user_settings )
			$user_settings = array();

		$this->settings = wp_parse_args( $user_settings, $this->default_settings );
	
		// Initialize admin settings and pages
		if ( is_admin() ) {
			add_action( 'admin_init', array( &$this, 'register_settings' ) );
			add_action( 'admin_menu', array( &$this, 'register_settings_page' ) );
			add_action( 'admin_notices', array( $this, 'settings_notice' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			add_action( 'created_term', array( $this, 'sync_term' ), 100, 3 );
			add_action( 'edited_term', array( $this, 'sync_term' ), 100, 3 );
		}
		
	}

	
	/**
	 * Checks to see if defaults are set, displays notice if not set
	 *
	 * @return void
	 */
	function settings_notice() {
		if ( ( !isset( $this->settings['key'] ) || empty( $this->settings['key'] ) || !isset( $this->settings['mode'] ) || empty( $this->settings['mode'] ) ) && current_user_can( 'manage_options' ) ) {
			_e( "<div class='error'><p>You have not entered the required settings for Taxonomy Sync. Please manage them here: <a href='options-general.php?page=" . $this->prefix ."-settings'>Taxonomy Sync Settings</a></p></div>", $this->prefix );
		}
		
	}
	
	/**
	 * Add CSS and JS to admin area, hooked into admin_enqueue_scripts.
	 */
	function enqueue_scripts() {
		wp_enqueue_style( 'taxonomy_sync_style', $this->get_baseurl() . 'css/taxonomy-sync.css' );
	
		// Chosen.js library used for post type and taxonomy selection
		wp_enqueue_script( 'chosen', $this->get_baseurl() . 'js/chosen/chosen.jquery.js' );
		wp_enqueue_style( 'chosen_css', $this->get_baseurl() . 'js/chosen/chosen.css' );
	}

	/**
	 * Register settings page
	 *
	 * @access public
	 * @return void
	 */
	public function register_settings_page() {
		add_options_page( 'Taxonomy Sync Options', 'Taxonomy Sync', 'manage_options', $this->prefix . '-settings', array( &$this, 'admin_settings_page' ) );
	}

	/**
	 * Register a single setting for the Taxonomy Sync to store all options in a single object
	 *
	 * @access public
	 * @return void
	 */
	public function register_settings() {

		register_setting( $this->prefix . '_settings', $this->prefix . '_settings', array( &$this, 'validate_settings') );
		
		// General settings
		add_settings_section( $this->prefix . '_general_settings_section',
			_( 'General Settings' ),
			array( $this, 'general_settings_section' ),
			$this->prefix . '_settings'
		);
		
		// Taxonomy settings
		add_settings_section( $this->prefix . '_taxonomy_settings_section',
			_( 'Taxonomy Settings' ),
			array( $this, 'taxonomy_settings_section' ),
			$this->prefix . '_settings'
		);
	}
	
	/**
	 * Output the HTML for the admin settings page
	 *
	 * @return void
	 */
	function admin_settings_page() {
		if ( !current_user_can( 'manage_options' ) )  {
			wp_die( __( 'You do not permission to access this page', $this->prefix ) );
		}
		?>
		<div class="wrap">
		<h2><?php _e( 'Taxonomy Sync Settings', $this->prefix ) ?></h2>
		<form method="post" action="options.php">
			<?php 
			settings_fields( $this->prefix . '_settings' );
			do_settings_sections( $this->prefix . '_settings' );
			submit_button( __(' Save Taxonomy Sync Settings' ), 'primary', $this->prefix . '_save_settings' );
			?>
		</form>
		</div>
		<?php	
	}
	
	/**
	 * Output for the general settings section
	 *
	 * @access public
	 * @return void
	 */
	function general_settings_section() {
		?>
		<table class="form-table">
			<tr valign="top">
				<th scope="row">
					<label for="<?php echo $this->prefix ?>_key"><?php _e( 'Key' ) ?></label>
				</th>
				<td>
					<div>
					<?php
					echo sprintf(
						'<input type="text" id="%s" name="%s" value="%s" size="50" /><br><i>%s <b>(%s)</b></i>',
						$this->prefix . "_settings-key",
						$this->prefix . "_settings[key]",
						$this->settings['key'],
						__( 'The secret key used to authorize access between WordPress instances.' ),
						__( 'required' )
					);
					?>
					</div>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">
					<label for="<?php echo $this->prefix ?>_remote_url"><?php _e( 'Mode' ) ?></label>
				</th>
				<td>
					<div>
					<?php
					$modes = array( '', 'master', 'slave' );
					$options = "";
					foreach( $modes as $mode ) {
						$options .= sprintf(
							'<option value="%s" %s>%s</option>',
							$mode,
							( $this->settings['mode'] == $mode ) ? "selected" : "",
							ucwords( $mode )
						);
					}
					
					echo sprintf(
						'<select id="%s" name="%s">%s</select><br><i>%s<br><b>(%s)</b></i>',
						$this->prefix . "_settings-mode",
						$this->prefix . "_settings[mode]",
						$options,
						__( 'The mode for this script.<br><b>Master</b> should be used for a site that is syncing terms <b>TO</b> another site.<br><b>Slave</b> should be used if it is receiving terms <b>FROM</b> another site.' ),
						__( 'required' )
					);
					?>
					</div>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">
					<label for="<?php echo $this->prefix ?>_remote_url"><?php _e( 'Remote URL' ) ?></label>
				</th>
				<td>
					<div>
					<?php
					echo sprintf(
						'<input type="text" id="%s" name="%s" value="%s" size="50" /><br><i>%s <b>(%s)</b></i>',
						$this->prefix . "_settings-remote-url",
						$this->prefix . "_settings[remote_url]",
						$this->settings['remote_url'],
						__( 'The URL of the remote WordPress site to send terms for syncing' ),
						__( 'required only if this is the master site' )
					);
					?>
					</div>
				</td>
			</tr>	
		</table>
		<?php
	}
	
	/**
	 * Output for the taxonomy settings section
	 *
	 * @access public
	 * @return void
	 */
	public function taxonomy_settings_section() {
	
		// Get currently selected taxonomies
		$selected_taxonomies = $this->settings['taxonomies'];
		if( !empty( $selected_taxonomies ) && !is_array( $selected_taxonomies ) ) $selected_taxonomies = array( $selected_taxonomies );

		// Get all post types available in the system that have show_ui enabled.
		// Otherwise, it would be pointless to make them available for meta box display.
		$args = array(
			'public' => true,
			'show_ui' => true
		);
		$taxonomies = get_taxonomies( $args, 'objects' );
		
		// Order by name
		uasort( $taxonomies, function( $a, $b ) {
			 return ( $a->label < $b->label ) ? -1 : 1;
		} );
		
		// Display as option elements
		$options = "";
		foreach( $taxonomies as $taxonomy ) {
			$options .= sprintf( 
				'<option value="%s" %s>%s</option>',
				$taxonomy->name,
				( is_array( $selected_taxonomies ) && in_array( $taxonomy->name, $selected_taxonomies ) ) ? "selected" : "",
				$taxonomy->label
			);
		}
		
		?>
		<table class="form-table">
			<tr valign="top">
				<th scope="row">
					<label for="<?php echo $this->prefix ?>_key"><?php _e( 'Taxonomies' ) ?></label>
				</th>
				<td>
		<?php
		echo sprintf(
			'<select class="chzn-select" multiple="multiple" data-placeholder="%s" id="%s" name="%s">%s</select><br><i>%s<br><b>(%s)</b></i>',
			__( 'Select Taxonomies' ),
			$this->prefix . "-settings-taxonomies",
			$this->prefix . "_settings[taxonomies][]",
			$options,
			__( 'The taxonomies to synchronize.<br>For the <b>master</b> site, the taxonomies to <b>send</b>.<br>For the <b>slave</b> site, the taxonomies to <b>accept</b>. If left blank, no errors will occur but nothing will be synchronized' ),
			__( 'required' )
		);
		?>
				</td>
			</tr>
		</table>
		<?php
		// Initialize chosen.js for this field
		echo sprintf(
			'<script type="text/javascript"> $("#%s").chosen()</script>',
			$this->prefix . "-settings-taxonomies"
		);
	
	}
	
	/**
	 * Validate settings
	 *
	 * @access public
	 * @param mixed $settings
	 * @return mixed
	 */
	public function validate_settings( $settings ) {

		foreach ( $settings as $key => $value ) {
			if ( !isset( $this->default_settings[$key] ) ) {
				unset( $settings[$key] );
			}
		}
		
		// Return the validated data
		return $settings;
	}


	/**
	 * Log an error
	 *
	 * @param string $message 
	 * @return bool false
	 */
	private function error( $message ) {
		$this->errors[] = $message;
		return false;
	}


	/**
	 * Display any errors on the site
	 *
	 * @return void
	 */
	public function display_errors() {
		if ( count( $this->errors ) ) :
		?>
		<div id="message" class="error">
			<p>
				<?php echo _n( 'There was an issue with Taxonomy Sync: ', 'There were issues with Taxonomy Sync: ', count( $this->errors ), $this->prefix ) ?>
				<br /> &bull; <?php echo implode( "<br /> &bull; ", $this->errors ) ?>
			</p>
		</div>
		<?php
		endif;
	}
	
	/**
	 * Return an option value
	 *
	 * @param string $key The key of the option
	 * @return bool|string The value of the option or false on failure
	 */
	public function get_option( $key ) {
		return ( array_key_exists( $key, $this->settings ) ) ? $this->settings[$key] : false;
	}
	
	/**
	 * Get the prefix
	 *
	 * @return string The global prefix
	 */
	public function get_prefix() {
		return $this->prefix;
	}
	
	/**
	 * Get the base URL for this plugin.
	 *
	 * @return string URL pointing to plugin top directory.
	 */
	function get_baseurl() {
		return plugin_dir_url( __FILE__ );
	}
	
	/**
	 * Sync the newly created or updated term to the remote site
	 * This function runs at a low priority so any other hooks have likely run and therefore all data is current
	 *
	 * @param int $term_id
	 * @param int $tt_id
	 * @param string $taxonomy
	 *
	 * @return void
	 */
	function sync_term( $term_id, $tt_id, $taxonomy ) {
		// If the taxonomy is not one that we are syncing, just exit
		if( !in_array( $taxonomy, $this->settings['taxonomies'] ) )
			return;
	
		// Get the full term object
		$term = get_term( intval( $term_id ), $taxonomy );
		
		// Assemble the post vars
		$post_vars = array(
			'key' => $this->settings['key'],
			'taxonomy' => $taxonomy,
			'term' => json_encode( $term )
		);
		
		// TODO - add Term_Meta data if plugin is installed and active
		
		// Send the POST request to the receiver
		$response = wp_remote_post(
			$this->settings['remote_url'] . '/' . $this->sync_uri,
			array(
				'method' => 'POST',
				'timeout' => 45,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking' => true,
				'headers' => array(),
				'body' => $post_vars,
				'cookies' => array()
			)
		);
	
		if ( ! is_wp_error( $response ) && is_numeric( $response['body'] ) ) {
			// success
		}
	}
	
	/**
	 * Receive a term for synchronization
	 *
	 * @param int $term_id
	 * @param int $tt_id
	 * @param string $taxonomy
	 *
	 * @return void
	 */
	function receive_term() {
		// Ensure the key matches. If not, die immediately.
		if( !array_key_exists( 'key', $_POST ) || $_POST['key'] != $this->settings['key'] )
			die( __( 'Invalid key specified' ) );
			
		// Ensure the taxonomy is set up to be received. If not, die with an error since this is a likely misconfiguration.
		if( !array_key_exists( 'taxonomy', $_POST ) || !in_array( $_POST['taxonomy'], $this->settings['taxonomies'] ) )
			die( __( 'Taxonomy not specified for synchronization at slave site' ) );
		
		// Get the term object from the request. If it doesn't exist or fails to parse, die with an error.
		if( !array_key_exists( 'term', $_POST ) )
			die( __( 'Term not included in the request' ) );
		
		$term = json_decode( $_POST['term'] );
		if( $term == null )
			die( __( 'The term object was invalid.' ) );
			
		// TODO - determine if term was previously inserted or needs to be created, handle update/insert
			
		// TODO - process Term_Meta data if plugin is installed and active
	}
}

// Create a global instance of the class
global $taxonomy_sync;
$taxonomy_sync = new Taxonomy_Sync;

endif;