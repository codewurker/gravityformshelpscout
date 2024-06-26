<?php
// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

GFForms::include_feed_addon_framework();

/**
 * Gravity Forms Help Scout Add-On.
 *
 * @since     1.0
 * @package   GravityForms
 * @author    Rocketgenius
 * @copyright Copyright (c) 2016, Rocketgenius
 */
class GFHelpScout extends GFFeedAddOn {

	/**
	 * Contains an instance of this class, if available.
	 *
	 * @since  1.0
	 * @access private
	 * @var    object $_instance If available, contains an instance of this class.
	 */
	private static $_instance = null;

	/**
	 * Defines the version of the Help Scout Add-On.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_version Contains the version, defined from helpscout.php
	 */
	protected $_version = GF_HELPSCOUT_VERSION;

	/**
	 * Defines the minimum Gravity Forms version required.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_min_gravityforms_version The minimum version required.
	 */
	protected $_min_gravityforms_version = '2.4.6';

	/**
	 * Defines the plugin slug.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_slug The slug used for this plugin.
	 */
	protected $_slug = 'gravityformshelpscout';

	/**
	 * Defines the main plugin file.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_path The path to the main plugin file, relative to the plugins folder.
	 */
	protected $_path = 'gravityformshelpscout/helpscout.php';

	/**
	 * Defines the full path to this class file.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_full_path The full path.
	 */
	protected $_full_path = __FILE__;

	/**
	 * Defines the URL where this Add-On can be found.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string The URL of the Add-On.
	 */
	protected $_url = 'http://www.gravityforms.com/';

	/**
	 * Defines the title of this Add-On.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_title The title of the Add-On.
	 */
	protected $_title = 'Gravity Forms Help Scout Add-On';

	/**
	 * Defines the short title of the Add-On.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_short_title The short title.
	 */
	protected $_short_title = 'Help Scout';

	/**
	 * Defines if Add-On should use Gravity Forms servers for update data.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    bool
	 */
	protected $_enable_rg_autoupgrade = true;

	/**
	 * Defines if only the first matching feed will be processed.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    bool
	 */
	protected $_single_feed_submission = true;

	/**
	 * Defines the capability needed to access the Add-On settings page.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_capabilities_settings_page The capability needed to access the Add-On settings page.
	 */
	protected $_capabilities_settings_page = 'gravityforms_helpscout';

	/**
	 * Defines the capability needed to access the Add-On form settings page.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_capabilities_form_settings The capability needed to access the Add-On form settings page.
	 */
	protected $_capabilities_form_settings = 'gravityforms_helpscout';

	/**
	 * Defines the capability needed to uninstall the Add-On.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_capabilities_uninstall The capability needed to uninstall the Add-On.
	 */
	protected $_capabilities_uninstall = 'gravityforms_helpscout_uninstall';

	/**
	 * Defines the capabilities needed for the Help Scout Add-On
	 *
	 * @since  1.0
	 * @access protected
	 * @var    array $_capabilities The capabilities needed for the Add-On
	 */
	protected $_capabilities = array( 'gravityforms_helpscout', 'gravityforms_helpscout_uninstall' );

	/**
	 * Contains an instance of the Help Scout API library, if available.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    GF_HelpScout_API $api If available, contains an instance of the Help Scout API library.
	 */
	protected $api = null;

	/**
	 * Indicates if the add-on is authenticated with the Help Scout API.
	 *
	 * @since 1.13
	 *
	 * @var null|bool
	 */
	protected $_is_authenticated = null;

	/**
	 * Enabling background feed processing to prevent performance issues delaying form submission completion.
	 *
	 * @since 2.2
	 *
	 * @var bool
	 */
	protected $_async_feed_processing = true;

	/**
	 * Get instance of this class.
	 *
	 * @since  1.0
	 * @access public
	 * @static
	 *
	 * @return $_instance
	 */
	public static function get_instance() {

		if ( null === self::$_instance ) {
			self::$_instance = new GFHelpScout();
		}

		return self::$_instance;

	}

	/**
	 * Register needed plugin hooks and PayPal delayed payment support.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses GFAddOn::is_gravityforms_supported()
	 * @uses GFFeedAddOn::add_delayed_payment_support()
	 */
	public function init() {

		parent::init();

		if ( $this->is_gravityforms_supported( '2.0-beta-3' ) ) {
			add_filter( 'gform_entry_detail_meta_boxes', array( $this, 'register_meta_box' ), 10, 3 );
		} else {
			add_action( 'gform_entry_detail_sidebar_middle', array( $this, 'add_entry_detail_panel' ), 10, 2 );
		}

		add_action( 'admin_init', array( $this, 'maybe_create_conversation' ) );
		add_action( 'admin_init', array( $this, 'maybe_conversation_redirect' ) );

		add_filter( 'gform_addnote_button', array( $this, 'add_note_checkbox' ) );

		add_action( 'gform_post_note_added', array( $this, 'add_note_to_conversation' ), 10, 6 );

		add_filter( 'gform_entries_column_filter', array( $this, 'add_entry_conversation_column_link' ), 10, 5 );

		add_filter( 'gform_entry_list_bulk_actions', array( $this, 'add_bulk_action' ), 10, 2 );
		add_action( 'gform_entry_list_action_helpscout', array( $this, 'process_entry_list_bulk_action' ), 10, 3 );

		$this->add_delayed_payment_support(
			array(
				'option_label' => esc_html__( 'Create conversation in Help Scout only when payment is received.', 'gravityformshelpscout' ),
			)
		);

		add_filter( 'gform_replace_merge_tags', array( $this, 'replace_merge_tags' ), 10, 7 );

		add_filter( 'gform_settings_header_buttons', array( $this, 'filter_gform_settings_header_buttons' ), 99 );
	}

	public function init_ajax() {
		parent::init_ajax();
		add_action( 'wp_ajax_gform_helpscout_save_app_keys', array( $this, 'ajax_save_app_keys' ) );
	}

	public function scripts() {
		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';

		$scripts = array(
			array(
				'handle'  => 'gform_helpscout_plugin_settings',
				'deps'    => array( 'jquery', 'gform_form_admin' ),
				'src'     => $this->get_base_url() . "/js/plugin_settings{$min}.js",
				'version' => $this->_version,
				'enqueue' => array(
					array(
						'admin_page' => array( 'plugin_settings' ),
						'tab'        => $this->_slug,
					),
				),
				'strings' => array(
					'nonce_save'   => wp_create_nonce( 'gform_helpscout_save_app_keys' ),
					'settings_url' => admin_url( 'admin.php?page=gf_settings&subview=' . $this->_slug ),
				),
			),
			array(
				'handle'  => 'gform_helpscout_merge_tags',
				'deps'    => array( 'gform_gravityforms' ),
				'src'     => $this->get_base_url() . "/js/merge_tags{$min}.js",
				'version' => $this->_version,
				'enqueue' => array(
					array(
						'admin_page' => array( 'form_settings' ),
						'tab'        => 'notification'
					),
				),
				'strings' => array(
					'id'      => wp_strip_all_tags( __( 'Conversation ID', 'gravityformshelpscout' ) ),
					'number'  => wp_strip_all_tags( __( 'Conversation Number', 'gravityformshelpscout' ) ),
					'status'  => wp_strip_all_tags( __( 'Conversation Status', 'gravityformshelpscout' ) ),
					'subject' => wp_strip_all_tags( __( 'Conversation Subject', 'gravityformshelpscout' ) ),
					'url'     => wp_strip_all_tags( __( 'Conversation URL', 'gravityformshelpscout' ) ),
				),
			)
		);

		return array_merge( parent::scripts(), $scripts );
	}

	public function styles() {
		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';

		$styles = array(
			array(
				'handle'  => 'gform_helpscout_admin',
				'src'     => $this->get_base_url() . "/css/admin{$min}.css",
				'version' => $this->_version,
				'enqueue' => array(
					array(
						'admin_page' => array( 'plugin_settings' ),
						'tab'        => $this->_slug,
					),
				),
			),
		);

		return array_merge( parent::styles(), $styles );
	}





	// # PLUGIN SETTINGS -----------------------------------------------------------------------------------------------

	public function plugin_settings_page() {

		// Handle setting/updating auth token after being redirected back from gravityapi.
		$this->maybe_update_auth_tokens();

		if ( ! rgempty( 'gform_helpscout_retry_refresh' ) && ! $this->is_authenticated() && $this->api()->is_access_token_expired() ) {
			GFCommon::add_error_message( esc_html__( 'Unable to refresh access token with Help Scout.', 'gravityformshelpscout' ) );
		}

		// If deauth button is clicked, deauth plugin.
		elseif ( rgpost( '_gaddon_setting_deauth' ) ) {

			$this->api()->delete_access_token();
			GFCache::delete( $this->get_slug() . '_mailboxes_choices' );

		} elseif ( rgpost( 'gform_helpscout_enable_custom_app' ) ) {

			$settings                    = $this->get_plugin_settings();
			$settings['customAppEnable'] = 1;
			$this->update_plugin_settings( $settings );

		} elseif ( rgpost( 'gform_helpscout_disable_custom_app' ) ) {

			$settings                    = $this->get_plugin_settings();
			$settings['customAppEnable'] = 0;
			$this->update_plugin_settings( $settings );
			$this->api()->delete_app_keys();

		} elseif ( rgget( 'code' ) && wp_verify_nonce( rgget( 'state' ), $this->get_authentication_state_action() ) ) {

			$this->api()->do_custom_app_auth( rgget( 'code' ) );

		}
		// Migrate v1 API key to v2 access token.
		elseif ( $this->get_plugin_setting( 'api_key' ) ) {

			$this->transition();
		}

		parent::plugin_settings_page();

	}

	/**
	 * Returns the plugin settings.
	 *
	 * @since 2.2
	 *
	 * @return array
	 */
	public function get_plugin_settings() {
		$settings = parent::get_plugin_settings();

		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Store auth tokens when we get auth payload from HelpScout via gravityapi.
	 *
	 * @since 2.1
	 */
	public function maybe_update_auth_tokens() {
		$payload = $this->get_oauth_payload();

		if ( ! $payload ) {
			return;
		}

		$auth_payload = json_decode( base64_decode( $payload['auth_payload'] ), true );

		// Verify state.
		if ( rgpost( 'state' ) && ! wp_verify_nonce( rgar( $payload, 'state' ), $this->get_authentication_state_action() ) ) {
			GFCommon::add_error_message( esc_html__( 'Unable to connect your HelpScout account due to mismatched state.', 'gravityformshelpscout' ) );
			return;
		}

		// Get the authentication token.
		$auth_token = $this->api()->get_access_token();
		$settings   = array();


		if ( empty( $auth_token ) || $auth_token['access_token'] !== $auth_payload['access_token'] ) {
			$this->api()->save_access_token( $auth_payload );
			GFCommon::add_message( esc_html__( 'HelpScout settings have been updated.', 'gravityformshelpscout' ) );
		}

		// If error is provided, display message.
		if ( rgpost( 'auth_error' ) || isset( $payload['auth_error'] ) ) {
			// Add error message.
			GFCommon::add_error_message( esc_html__( 'Unable to connect your HelpScout account.', 'gravityformshelpscout' ) );
		}
	}

	/**
	 * Hide submit button on plugin settings page.
	 *
	 * @since 1.13
	 *
	 * @param string $html
	 *
	 * @return string
	 */
	public function filter_gform_settings_header_buttons( $html = '' ) {

		// If this is not the plugin settings page, return.
		if ( ! $this->is_plugin_settings( $this->get_slug() ) ) {
			return $html;
		}

		return '';

	}


	/**
	 * Get the authorization payload data.
	 *
	 * Returns the auth POST request if it's present, otherwise attempts to return a recent transient cache.
	 *
	 * @since 2.1
	 *
	 * @return array
	 */
	private function get_oauth_payload() {
		$payload = array_filter(
			array(
				'auth_payload' => rgpost( 'auth_payload' ),
				'auth_error'   => rgpost( 'auth_error' ),
				'state'        => rgpost( 'state' ),
			)
		);

		if ( count( $payload ) === 2 || isset( $payload['auth_error'] ) ) {
			return $payload;
		}

		$payload = get_transient( "gravityapi_response_{$this->_slug}" );

		if ( rgar( $payload, 'state' ) !== get_transient( "gravityapi_request_{$this->_slug}" ) ) {
			return array();
		}

		delete_transient( "gravityapi_response_{$this->_slug}" );

		return is_array( $payload ) ? $payload : array();
	}

	/**
	 * Exchanges a v1 API Key for a v2 OAuth token.
	 *
	 * @since 1.6
	 */
	public function transition() {

		$this->log_debug( __METHOD__ . '(): Requesting v2 OAuth token for v1 API key.' );
		$settings     = $this->get_plugin_settings();
		$v1_api_key   = rgar( $settings, 'api_key' );
		$access_token = $this->api()->transition( $v1_api_key );

		if ( $access_token ) {
			$this->log_debug( __METHOD__ . '(): Refreshing token.' );
			// Clear the v1 API key since we've now authenticated via oAuth.
			$settings['api_key'] = '';
			$this->update_plugin_settings( $settings );

			// v1 API keys can only be migrated once but will always generated a valid refresh token.
			// Let's assume that the key has been migrated before and refresh it immediately.
			$access_token = $this->api()->refresh( $access_token['refresh_token'] );
			if ( $access_token ) {
				$this->log_debug( __METHOD__ . '(): Saving token.' );
				$this->api()->save_access_token( $access_token );
			} else {
				$this->log_error( __METHOD__ . '(): Refresh request failed.' );
			}

		} else {
			$this->log_error( __METHOD__ . '(): Transition request failed.' );
		}

	}

	/**
	 * Setup plugin settings fields.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses GFHelpScout::plugin_settings_description()
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {

		$fields = array(
			array(
				'name'  => 'accessToken',
				'type'  => 'hidden',
			),
			array(
				'name'  => 'customAppEnable',
				'type'  => 'hidden',
			),
			array(
				'name'  => 'auth',
				'label' => null,
				'type'  => 'auth_token_button',
			),
		);

		return array(
			array(
				'title'       => '',
				'description' => $this->plugin_settings_description(),
				'fields'      => $fields,
			),
		);

	}

	/**
	 * Prepare plugin settings description.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @return string
	 */
	public function plugin_settings_description() {

		// Prepare description.
		$description = sprintf(
			'<p>%s</p>',
			sprintf(
				esc_html__( 'Help Scout makes it easy to provide your customers with a great support experience. Use Gravity Forms to collect customer information and automatically create a new Help Scout conversation. If you don\'t have a Help Scout account, you can %1$ssign up for one here.%2$s', 'gravityformshelpscout' ),
				'<a href="http://www.helpscout.net/" target="_blank">', '</a>'
			)
		);

		return $description;

	}

	/**
	 * Create Generate Auth Token settings field.
	 *
	 * @since  2.0
	 * @access public
	 *
	 * @param array $field Field settings.
	 * @param bool  $echo  Display field. Defaults to true.
	 *
	 * @return string
	 */
	public function settings_auth_token_button( $field, $echo = true ) {

		// Initialize return HTML.
		$html = '';

		// Get the plugin settings.
		$settings = $this->get_plugin_settings();

		$custom_app_enabled = '1' == rgar( $settings, 'customAppEnable' );

		// Get button class.
		$button_class = version_compare( GFForms::$version, '2.5-dev-1', '<' ) ? 'button-secondary' : 'button secondary';

		// If HelpScout is authenticated, display de-authorize button.
		if ( $this->is_authenticated() ) {

			$message = $custom_app_enabled ? __( 'Custom app authenticated with Help Scout.', 'gravityformshelpscout' ) : __( 'Authenticated with Help Scout.', 'gravityformshelpscout' );
			$html .= sprintf( '<p><i class="fa fa-check gf_valid"></i> %s</p>', esc_html( $message ) );
			$html .= sprintf(
				' <button class="%2$s" name="_gaddon_setting_deauth" value="1">%1$s</a>',
				esc_html__( 'Disconnect Help Scout', 'gravityformshelpscout' ),
				$button_class
			);

		} else {

			if ( rgempty( 'gform_helpscout_retry_refresh' ) && $this->api()->is_access_token_expired() ) {

				$html .= sprintf(
					' <button class="%2$s" name="gform_helpscout_retry_refresh" value="1">%1$s</a>',
					esc_html__( 'Click here to refresh access token.', 'gravityformshelpscout' ),
					$button_class
				);

			} elseif ( $custom_app_enabled ) {

				// If SSL is available, display custom app settings.
				if ( is_ssl() ) {

					$html .= $this->custom_app_settings();

				} else {

					// @todo style this as an error
					$html .= '<p>';
					$html .= esc_html__( 'To use a custom Help Scout app, you must have an SSL certificate installed and enabled. Visit this page after configuring your SSL certificate to use a custom Help Scout app.', 'gravityformshelpscout' );
					$html .= '</p>';

				}

				$html .= '<p class="gform_helpscout_disclaimer">';
				$html .= sprintf(
					'<button type="submit" id="gform_helpscout_disable_custom_app" name="gform_helpscout_disable_custom_app" value="1">%s</button>',
					esc_html__( 'I don\'t want to use a custom Help Scout app.', 'gravityformshelpscout' )
				);
				$html .= '</p>';

			} else {

				// Prepare authorization URL.
				$license_key  = GFCommon::get_key();
				$settings_url = urlencode( $this->get_redirect_url() );
				$nonce        = wp_create_nonce( $this->get_authentication_state_action() );
				$auth_url     = add_query_arg(
					array(
						'redirect_to'   => $settings_url,
						'license'       => $license_key,
						'state'         => $nonce,
						'version'       => $this->_version,
					),
					$this->api()->get_gravity_api_url( '/auth/helpscout' )
				);

				// Set the transient to be used when the user is redirected back from GF API and is logged out because of sameSite issue.
				if ( get_transient( "gravityapi_request_{$this->_slug}" ) ) {
					delete_transient( "gravityapi_request_{$this->_slug}" );
				}

				set_transient( "gravityapi_request_{$this->_slug}", $nonce, 10 * MINUTE_IN_SECONDS );

				$html .= sprintf(
					'<a href="%2$s" class="button" id="gform_helpscout_auth_button">%1$s</a>',
					esc_html__( 'Click here to connect to Help Scout.', 'gravityformshelpscout' ),
					$auth_url
				);

				$display_setting = (bool) rgar( GFCommon::get_version_info(), 'is_valid_key' );

				/**
				 * Allows the enable custom app setting to be displayed or hidden.
				 *
				 * @since 2.2
				 *
				 * @param $display_setting bool Indicates if the enable custom app setting should be displayed. Defaults to the license validation state.
				 */
				if ( apply_filters( 'gform_helpscout_display_enable_custom_app_setting', $display_setting ) ) {
					$html .= '<p class="gform_helpscout_disclaimer">';
					$html .= sprintf(
						'<button type="submit" id="gform_helpscout_enable_custom_app" name="gform_helpscout_enable_custom_app" value="1">%s</button> %s',
						esc_html__( 'I want to use a custom Help Scout app.', 'gravityformshelpscout' ),
						esc_html__( '(Recommended for advanced users only.)' )
					);
					$html .= '</p>';
				}

			}

		}

		if ( $echo ) {
			echo $html;
		}

		return $html;

	}

	/**
	 * Renders settings section for custom Dropbox app.
	 *
	 * @since  2.0
	 * @access public
	 *
	 * @uses GFAddOn::get_plugin_settings()
	 * @uses GFAddOn::single_setting_row()
	 * @uses GF_Dropbox::get_auth_url()
	 * @uses GF_Dropbox::get_redirect_uri()
	 * @uses GF_Dropbox::is_valid_app_key_secret()
	 *
	 * @return string
	 */
	public function custom_app_settings() {

		// Open custom app table.
		$html = '<table class="form-table gf-helpscout-custom-app">';

		ob_start();

		// Display redirect URI.
		$this->single_setting_row(
			array(
				'name'     => 'redirectURI',
				'type'     => 'text',
				'label'    => esc_html__( 'OAuth Redirect URI', 'gravityformshelpscout' ),
				'class'    => 'large',
				'value'    => $this->get_redirect_url(),
				'readonly' => true,
				'onclick'  => 'this.select();',
			)
		);

		// Display custom app key.
		$this->single_setting_row(
			array(
				'name'              => 'customAppKey',
				'type'              => 'api_key',
				'label'             => esc_html__( 'App Key', 'gravityformshelpscout' ),
				'class'             => 'medium',
				'callback'          => array( $this, 'settings_api_key' ),
				'feedback_callback' => array( $this, 'is_valid_app_key_secret' ),
				'value'             => $this->api()->get_custom_app_keys( 'app_key' ),
			)
		);

		// Display custom app secret.
		$this->single_setting_row(
			array(
				'name'     => 'customAppSecret',
				'type'     => 'api_key',
				'label'    => esc_html__( 'App Secret', 'gravityformshelpscout' ),
				'class'    => 'medium',
				'callback' => array( $this, 'settings_api_key' ),
				'value'    => $this->api()->get_custom_app_keys( 'app_secret' ),
			)
		);

		$html .= ob_get_contents();
		ob_end_clean();

		// Close custom app table.
		$html .= '</table>';

		// Prepare authorization URL.
		$auth_url = '';
		$app_keys = $this->api()->get_custom_app_keys();
		if( ! empty( $app_keys['app_key'] ) && ! empty( $app_keys['app_secret'] ) && ! is_wp_error( $this->api()->validate_app_keys( $app_keys['app_key'], $app_keys['app_secret'] ) ) ) {
			$auth_url = $this->api()->get_auth_url();
		}

		$html .= sprintf(
			'<a href="%2$s" class="button" id="authButton">%1$s</a>',
			esc_html__( 'Click here to connect to Help Scout.', 'gravityformshelpscout' ),
			$auth_url
		);

		return $html;

	}

	public function settings_api_key( $field, $echo = true ) {

		$attributes = $this->get_field_attributes( $field );
		$value      = rgar( $field, 'value' );
		$html       = '';

		$html .= '<input
                    type="text"
                    name="_gaddon_setting_' . esc_attr( $field['name'] ) . '"
                    value="' . esc_attr( htmlspecialchars( $value, ENT_QUOTES ) ) . '" ' .
		         implode( ' ', $attributes ) .
		         ' />';

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	public function ajax_save_app_keys() {

		// Verify nonce.
		if ( false === wp_verify_nonce( rgpost( 'nonce' ), 'gform_helpscout_save_app_keys' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Access denied.', 'gravityformshelpscout' ) ) );
		}

		// If user is not authorized, exit.
		if ( ! GFCommon::current_user_can_any( $this->_capabilities_settings_page ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Access denied.', 'gravityformshelpscout' ) ) );
		}

		$app_key    = rgpost( 'app_key' );
		$app_secret = rgpost( 'app_secret' );

		if ( ! $app_key || ! $app_secret ) {
			wp_send_json_error( array( 'message' => 'The app key or app secret is missing.' ) );
		}

		$response = $this->api()->validate_app_keys( $app_key, $app_secret );
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$this->api()->save_app_keys( $app_key, $app_secret );

		wp_send_json_success( array(
			'authUrl' => $this->api()->get_auth_url()
		) );

	}

	public function uninstall() {
		parent::uninstall();
		delete_option( 'gravityformsaddon_gravityformshelpscout_version' );
		delete_option( 'gf_helpscout_api_access_token' );
		delete_option( 'gf_helpscout_api_custom_app_keys' );
	}

	/**
	 * Defines the supported notification events.
	 *
	 * @since 1.8
	 *
	 * @param array $form The current form.
	 *
	 * @return array
	 */
	public function supported_notification_events( $form ) {

		$slug = $this->get_slug();

		return array(
			"{$slug}_conversation_created" => __( 'Help Scout Conversation Created', 'gravityformshelpscout' ),
		);

	}



	// # FEED SETTINGS -------------------------------------------------------------------------------------------------

	/**
	 * Setup fields for feed settings.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses GFAddOn::add_field_after()
	 * @uses GFAddOn::get_first_field_by_type()
	 * @uses GFFeedAddOn::get_default_feed_name()
	 * @uses GFHelpScout::file_fields_for_feed_setup()
	 * @uses GFHelpScout::mailboxes_for_feed_setting()
	 * @uses GFHelpScout::message_types_for_feed_setup()
	 * @uses GFHelpScout::status_types_for_feed_setup()
	 * @uses GFHelpScout::users_for_feed_settings()
	 *
	 * @return array
	 */
	public function feed_settings_fields() {

		$settings = array(
			array(
				'fields' => array(
					array(
						'name'          => 'feed_name',
						'type'          => 'text',
						'required'      => true,
						'class'         => 'medium',
						'label'         => esc_html__( 'Name', 'gravityformshelpscout' ),
						'default_value' => $this->get_default_feed_name(),
						'tooltip'       => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Name', 'gravityformshelpscout' ),
							esc_html__( 'Enter a feed name to uniquely identify this setup.', 'gravityformshelpscout' )
						),
					),
					array(
						'name'          => 'mailbox',
						'type'          => 'select',
						'required'      => true,
						'choices'       => $this->mailboxes_for_feed_setting(),
						'onchange'      => "jQuery(this).parents('form').submit();",
						'label'         => esc_html__( 'Destination Mailbox', 'gravityformshelpscout' ),
						'tooltip'       => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Destination Mailbox', 'gravityformshelpscout' ),
							esc_html__( 'Select the Help Scout Mailbox this form entry will be sent to.', 'gravityformshelpscout' )
						),
					),
					array(
						'name'          => 'user',
						'type'          => 'select',
						'dependency'    => 'mailbox',
						'choices'       => $this->users_for_feed_settings(),
						'label'         => esc_html__( 'Assignee', 'gravityformshelpscout' ),
						'tooltip'       => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Assignee', 'gravityformshelpscout' ),
							esc_html__( 'Choose the Help Scout Team or User this form entry will be assigned to.', 'gravityformshelpscout' )
						),
					),
				),
			),
			array(
				'title'      => esc_html__( 'Customer Details', 'gravityformshelpscout' ),
				'dependency' => 'mailbox',
				'fields'     => array(
					array(
						'name'          => 'customer_email',
						'type'          => 'field_select',
						'required'      => true,
						'label'         => esc_html__( 'Email Address', 'gravityformshelpscout' ),
						'default_value' => $this->get_first_field_by_type( 'email' ),
						'args'          => array( 'input_types' => array( 'email', 'hidden' ) ),
					),
					array(
						'name'          => 'customer_first_name',
						'type'          => 'field_select',
						'required'      => true,
						'label'         => esc_html__( 'First Name', 'gravityformshelpscout' ),
						'default_value' => $this->get_first_field_by_type( 'name', 3 ),
					),
					array(
						'name'          => 'customer_last_name',
						'type'          => 'field_select',
						'label'         => esc_html__( 'Last Name', 'gravityformshelpscout' ),
						'default_value' => $this->get_first_field_by_type( 'name', 6 ),
					),
					array(
						'name'          => 'customer_phone',
						'type'          => 'field_select',
						'required'      => false,
						'label'         => esc_html__( 'Phone Number', 'gravityformshelpscout' ),
						'default_value' => $this->get_first_field_by_type( 'phone' ),
						'args'          => array( 'input_types' => array( 'phone', 'hidden' ) ),
					),
				),
			),
			array(
				'title'      => esc_html__( 'Message Details', 'gravityformshelpscout' ),
				'dependency' => 'mailbox',
				'fields'     => array(
					array(
						'name'          => 'tags',
						'type'          => 'text',
						'label'         => esc_html__( 'Tags', 'gravityformshelpscout' ),
						'class'         => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
					),
					array(
						'name'          => 'subject',
						'type'          => 'text',
						'required'      => true,
						'class'         => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
						'label'         => esc_html__( 'Subject', 'gravityformshelpscout' ),
						'default_value' => 'New submission from {form_title}',
					),
					array(
						'name'          => 'body',
						'type'          => 'textarea',
						'required'      => true,
						'use_editor'    => true,
						'label'         => esc_html__( 'Message Body', 'gravityformshelpscout' ),
						'default_value' => '{all_fields}',
					),
				),
			),
			array(
				'title'      => esc_html__( 'Message Options', 'gravityformshelpscout' ),
				'dependency' => 'mailbox',
				'fields'     => array(
					array(
						'name'          => 'status',
						'type'          => 'select',
						'choices'       => $this->status_types_for_feed_setup(),
						'label'         => esc_html__( 'Message Status', 'gravityformshelpscout' ),
					),
					array(
						'name'          => 'type',
						'type'          => 'select',
						'choices'       => $this->message_types_for_feed_setup(),
						'label'         => esc_html__( 'Message Type', 'gravityformshelpscout' ),
					),
					array(
						'name'          => 'note',
						'type'          => 'textarea',
						'use_editor'    => true,
						'default_value' => '',
						'label'         => esc_html__( 'Note', 'gravityformshelpscout' ),
					),
					array(
						'name'          => 'auto_reply',
						'type'          => 'checkbox',
						'label'         => esc_html__( 'Auto Reply', 'gravityformshelpscout' ),
						'choices'       => array(
							array(
								'name'  => 'auto_reply',
								'label' => esc_html__( 'Send Help Scout auto reply when message is created', 'gravityformshelpscout' ),
							),
						),
					),
				),
			),
			array(
				'title'      => esc_html__( 'Feed Conditional Logic', 'gravityformshelpscout' ),
				'dependency' => 'mailbox',
				'fields'     => array(
					array(
						'name'           => 'feed_condition',
						'type'           => 'feed_condition',
						'label'          => esc_html__( 'Conditional Logic', 'gravityformshelpscout' ),
						'checkbox_label' => esc_html__( 'Enable', 'gravityformshelpscout' ),
						'instructions'   => esc_html__( 'Export to Help Scout if', 'gravityformshelpscout' ),
						'tooltip'        => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Conditional Logic', 'gravityformshelpscout' ),
							esc_html__( 'When conditional logic is enabled, form submissions will only be exported to Help Scout when the condition is met. When disabled, all form submissions will be posted.', 'gravityformshelpscout' )
						),
					),
				),
			),
		);

		// Get available file fields.
		$file_fields = $this->file_fields_for_feed_setup();

		// If file fields are available, add feed setting.
		if ( ! empty( $file_fields ) ) {

			// Prepare field.
			$field = array(
				'name'    => 'attachments',
				'type'    => 'checkbox',
				'label'   => esc_html__( 'Attachments', 'gravityformshelpscout' ),
				'choices' => $file_fields,
			);

			// Add field.
			$settings = $this->add_field_after( 'body', $field, $settings );

		}

		/**
		 * Enable the display of the CC setting on the Help Scout feed.
		 *
		 * @since  1.0
		 *
		 * @param bool $enable_cc Display CC setting.
		 */
		$enable_cc = apply_filters( 'gform_helpscout_enable_cc', true );

		// If CC field is enabled, add feed setting.
		if ( $enable_cc ) {

			// Prepare field.
			$field = array(
				'name'     => 'cc',
				'type'     => 'text',
				'required' => false,
				'class'    => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
				'label'    => esc_html__( 'CC', 'gravityformshelpscout' ),
			);

			// Add field.
			$settings = $this->add_field_after( empty( $file_fields ) ? 'body' : 'attachments', $field, $settings );

		}

		/**
		 * Enable the display of the BCC setting on the Help Scout feed.
		 *
		 * @since  1.0
		 *
		 * @param bool $enable_bcc Display BCC setting.
		 */
		$enable_bcc = apply_filters( 'gform_helpscout_enable_bcc', false );

		// If BCC field is enabled, add feed setting.
		if ( $enable_bcc ) {

			// Prepare field.
			$field = array(
				'name'     => 'bcc',
				'type'     => 'text',
				'required' => false,
				'class'    => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
				'label'    => esc_html__( 'BCC', 'gravityformshelpscout' ),
			);

			// Add field.
			$settings = $this->add_field_after( $enable_cc ? 'cc' : ( empty( $file_fields ) ? 'body' : 'attachments' ), $field, $settings );

		}

		return $settings;

	}

	/**
	 * Prepare Help Scout Mailboxes for feed settings field.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @return array
	 */
	public function mailboxes_for_feed_setting() {

		$cache_key = $this->get_slug() . '_mailboxes_choices';
		$choices   = GFCache::get( $cache_key );

		if ( ! empty( $choices ) && is_array( $choices ) ) {
			return $choices;
		}

		// Initialize choices array.
		$choices = array(
			array(
				'label' => __( 'Choose A Mailbox', 'gravityformshelpscout' ),
				'value' => '',
			),
		);

		if ( ! $this->api() ) {
			return $choices;
		}

		// Get the Help Scout mailboxes.
		$mailboxes = $this->api()->get_mailboxes();
		if ( is_wp_error( $mailboxes ) ) {
			// Log that mailboxes could not be retrieved.
			$this->log_error( __METHOD__ . '(): Failed to get mailboxes; ' . $mailboxes->get_error_message() );

			return $choices;
		}

		// If there are no mailboxes, return.
		if ( ! $mailboxes ) {
			return $choices;
		}

		// Loop through mailboxes.
		foreach ( $mailboxes as $mailbox ) {

			// Add mailbox as choice.
			$choices[] = array(
				'label' => $mailbox['name'],
				'value' => $mailbox['id'],
			);

		}

		GFCache::set( $cache_key, $choices, true, HOUR_IN_SECONDS );

		return $choices;

	}

	/**
	 * Prepares the choices (teams and users) for the Assignee setting.
	 *
	 * @since  1.0
	 * @since  1.14 Updated to arrange the teams and users into optgroups.
	 *
	 * @return array
	 */
	public function users_for_feed_settings() {

		// Initialize choices array.
		$choices = array(
			array(
				'label' => __( 'Do Not Assign', 'gravityformshelpscout' ),
				'value' => '',
			),
		);

		// Get current mailbox value.
		$mailbox_id = $this->get_setting( 'mailbox' );

		// If no mailbox is set, return choices.
		if ( rgblank( $mailbox_id ) ) {
			return $choices;
		}

		$cache_key = $this->get_slug() . '_users_choices_' . $mailbox_id;
		$cached_choices = GFCache::get( $cache_key );

		if ( ! empty( $cached_choices ) && is_array( $cached_choices ) ) {
			return $cached_choices;
		}

		// If Help Scout instance is not initialized, return choices.
		if ( ! $this->is_authenticated() ) {
			return $choices;
		}

		// Get users for mailbox.
		$users = $this->api()->get_users_for_mailbox( $mailbox_id );

		if( is_wp_error( $users ) ) {
			$this->log_error( __METHOD__ . '(): Failed to get users for mailbox; ' . $users->get_error_message() );
			return $choices;
		} else if( ! $users ) {
			return $choices;
		}

		$teams_choices = array();
		$users_group   = array(
			'label'   => __( 'Users', 'gravityformshelpscout' ),
			'choices' => array(),
		);

		// Loop through users.
		foreach ( $users as $user ) {

			if ( rgar( $user, 'type' ) === 'team' ) {
				$teams_choices[] = array(
					'label' => $user['firstName'],
					'value' => $user['id'],
				);
			} else {
				$users_group['choices'][] = array(
					'label' => $user['firstName'] . ' ' . $user['lastName'],
					'value' => $user['id'],
				);
			}

		}

		if ( ! empty( $teams_choices ) ) {
			$choices[] = array(
				'label'   => __( 'Teams', 'gravityformshelpscout' ),
				'choices' => $teams_choices,
			);
		}

		$choices[] = $users_group;

		GFCache::set( $cache_key, $choices, true, HOUR_IN_SECONDS );

		return $choices;

	}

	/**
	 * Prepare Help Scout Status Types for feed settings field.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @return array
	 */
	public function status_types_for_feed_setup() {

		return array(
			array(
				'label' => esc_html__( 'Active', 'gravityformshelpscout' ),
				'value' => 'active',
			),
			array(
				'label' => esc_html__( 'Pending', 'gravityformshelpscout' ),
				'value' => 'pending',
			),
			array(
				'label' => esc_html__( 'Closed', 'gravityformshelpscout' ),
				'value' => 'closed',
			),
			array(
				'label' => esc_html__( 'Spam', 'gravityformshelpscout' ),
				'value' => 'spam',
			),
		);

	}

	/**
	 * Prepare Help Scout Message Types for feed settings field.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @return array
	 */
	public function message_types_for_feed_setup() {

		return array(
			array(
				'label' => esc_html__( 'Email', 'gravityformshelpscout' ),
				'value' => 'email',
			),
			array(
				'label' => esc_html__( 'Chat', 'gravityformshelpscout' ),
				'value' => 'chat',
			),
			array(
				'label' => esc_html__( 'Phone', 'gravityformshelpscout' ),
				'value' => 'phone',
			),
		);

	}

	/**
	 * Prepare form file fields for feed settings field.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses GFAPI::get_form()
	 * @uses GFCommon::get_fields_by_type()
	 *
	 * @return array
	 */
	public function file_fields_for_feed_setup() {

		// Initialize choices array.
		$choices = array();

		// Get current form.
		$form = GFAPI::get_form( rgget( 'id' ) );

		// Get file fields for form.
		$file_fields = GFCommon::get_fields_by_type( $form, array( 'fileupload' ) );

		// If no file fields were found, return.
		if ( empty( $file_fields ) ) {
			return $choices;
		}

		// Loop through file fields.
		foreach ( $file_fields as $field ) {

			// Add field as choice.
			$choices[] = array(
				'name'          => 'attachments[' . $field->id . ']',
				'label'         => $field->label,
				'default_value' => 0,
			);

		}

		return $choices;

	}





	// # FEED LIST -----------------------------------------------------------------------------------------------------

	/**
	 * Set feed creation control.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @return bool
	 */
	public function can_create_feed() {
		return $this->is_authenticated();
	}

	/**
	 * Enable feed duplication.
	 *
	 * @since  1.3
	 * @access public
	 *
	 * @param int $feed_id Feed to be duplicated.
	 *
	 * @return bool
	 */
	public function can_duplicate_feed( $feed_id ) {

		return true;

	}

	/**
	 * Configures which columns should be displayed on the feed list page.
	 *
	 * @since  1.0
	 * @since  1.14 Changed label for the user column to Assignee.
	 *
	 * @return array
	 */
	public function feed_list_columns() {

		return array(
			'feed_name' => esc_html__( 'Name', 'gravityformshelpscout' ),
			'mailbox'   => esc_html__( 'Mailbox', 'gravityformshelpscout' ),
			'user'      => esc_html__( 'Assignee', 'gravityformshelpscout' ),
		);

	}

	/**
	 * Returns the value to be displayed in the mailbox name column.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param array $feed The current Feed object.
	 *
	 * @return string
	 */
	public function get_column_value_mailbox( $feed ) {

		// If Help Scout instance is not initialized, return mailbox ID.
		if ( ! $this->is_authenticated() ) {
			return rgars( $feed, 'meta/mailbox' );
		}


		// Get feed mailbox.
		$mailbox = $this->api()->get_mailbox( rgars( $feed, 'meta/mailbox' ) );
		if( is_wp_error( $mailbox ) ) {

			// Log that mailbox could not be retrieved.
			$this->log_error( __METHOD__ . '(): Unable to get mailbox for feed; ' . $mailbox->get_error_message() );

			return rgars( $feed, 'meta/mailbox' );
		}

		return esc_html( $mailbox['name'] );

	}

	/**
	 * Returns the value to be displayed in the Assignee column.
	 *
	 * @since  1.0
	 * @since  1.14 Updated to identify when the selected assignee is a team.
	 *
	 * @param array $feed The current Feed object.
	 *
	 * @return string
	 */
	public function get_column_value_user( $feed ) {

		// If no user ID is set, return not assigned.
		if ( rgblank( $feed['meta']['user'] ) ) {
			return esc_html__( 'Not Assigned', 'gravityformshelpscout' );
		}

		// If Help Scout instance is not initialized, return user ID.
		if ( ! $this->is_authenticated() ) {
			return rgars( $feed, 'meta/user' );
		}

		// Get user for feed.
		$user = $this->get_assigned_user( $feed );
		if ( is_null( $user ) ) {
			return rgars( $feed, 'meta/user' );
		}

		if ( rgar( $user, 'type' ) === 'team' ) {
			/* translators: %s: The Help Scout team name. */
			return sprintf( esc_html__( '%s (Team)', 'gravityformshelpscout' ), $user['firstName'] );
		}

		return esc_html( $user['firstName'] . ' ' . $user['lastName'] );

	}





	// # FEED PROCESSING -----------------------------------------------------------------------------------------------

	/**
	 * Process feed, create conversation.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param array $feed  The current Feed object.
	 * @param array $entry The current Entry object.
	 * @param array $form  The current Form object.
	 */
	public function process_feed( $feed, $entry, $form ) {

		// If Help Scout instance is not initialized, exit.
		if ( ! $this->is_authenticated() ) {
			$this->add_feed_error( esc_html__( 'Unable to create conversation because API was not initialized.', 'gravityformshelpscout' ), $feed, $entry, $form );
			return;
		}

		// If this entry already has a Help Scout conversation, exit.
		if ( gform_get_meta( $entry['id'], 'helpscout_conversation_id' ) ) {
			$this->log_debug( __METHOD__ . '(): Entry already has a Help Scout conversation associated to it. Skipping processing.' );
			return;
		}

		// Prepare conversation data.
		$data = array(
			'assign_to'   => rgar( $this->get_assigned_user( $feed ), 'id' ),
			'email'       => $this->get_field_value( $form, $entry, $feed['meta']['customer_email'] ),
			'first_name'  => $this->get_field_value( $form, $entry, $feed['meta']['customer_first_name'] ),
			'last_name'   => $this->get_field_value( $form, $entry, $feed['meta']['customer_last_name'] ),
			'phone'       => $this->get_field_value( $form, $entry, $feed['meta']['customer_phone'] ),
			'subject'     => GFCommon::replace_variables( $feed['meta']['subject'], $form, $entry, false, false, false, 'text' ),
			'body'        => GFCommon::replace_variables( $feed['meta']['body'], $form, $entry ),
			'tags'        => $this->parse_comma_delimited_string( GFCommon::replace_variables( $feed['meta']['tags'], $form, $entry, false, false, false, 'text' ) ),
			'cc'          => $this->parse_comma_delimited_string( GFCommon::replace_variables( rgars( $feed, 'meta/cc' ), $form, $entry, false, false, false, 'text' ) ),
			'bcc'         => $this->parse_comma_delimited_string( GFCommon::replace_variables( rgars( $feed, 'meta/bcc' ), $form, $entry, false, false, false, 'text' ) ),
			'attachments' => $this->process_attachments( rgars( $feed, 'meta/attachments' ), $form, $entry, $feed ),
			'auto_reply'  => rgars( $feed, 'meta/auto_reply' ) == '1',
			'note'        => GFCommon::replace_variables( rgars( $feed, 'meta/note' ), $form, $entry ),
		);

		// If the email address is invalid, exit.
		if ( GFCommon::is_invalid_or_empty_email( $data['email'] ) ) {
			$this->add_feed_error( esc_html__( 'Unable to create conversation because a valid email address was not provided.', 'gravityformshelpscout' ), $feed, $entry, $form );
			return;
		}

		// Loop through first and last name fields.
		foreach ( array( 'first_name', 'last_name' ) as $field_to_check ) {

			// If field value is longer than 40 characters, truncate.
			if ( strlen( $data[ $field_to_check ] ) > 40 ) {

				// Log that we are truncating field value.
				$this->log_debug( __METHOD__ . "(): Truncating $field_to_check field value because it is longer than maximum length allowed." );

				// Truncate value.
				$data[ $field_to_check ] = substr( $data[ $field_to_check ], 0, 40 );

			}

		}

		// Parse shortcodes in thread body.
		if ( gf_apply_filters( 'gform_helpscout_process_body_shortcodes', $form['id'], false, $form, $feed ) ) {
			$data['body'] = do_shortcode( $data['body'] );
		}

		/**
		 * Filter conversation tags.
		 *
		 * @since 1.0
		 *
		 * @param array $tags  Tags to be added to conversation.
		 * @param array $feed  Current feed.
		 * @param array $entry Current entry.
		 * @param array $form  Current form.
		 */
		$data['tags'] = gf_apply_filters( array( 'gform_helpscout_tags', $form['id'] ), $data['tags'], $feed, $entry, $form );

		$customer_id = false;
		$customer    = $this->api()->get_customer_by_email( $data['email'] );

		if ( is_wp_error( $customer ) ) {

			$this->log_error( __METHOD__ . '(): Unable to determine if customer exists; ' . $customer->get_error_message() );
			$this->maybe_log_error_data( $customer );

		} elseif ( $customer ) {

			$customer_id     = $customer['id'];
			$requires_update = false;

			if ( ! empty( $data['first_name'] ) && $customer['firstName'] !== $data['first_name'] ) {
				$requires_update       = true;
				$customer['firstName'] = $data['first_name'];
			}

			if ( ! empty( $data['last_name'] ) && $customer['lastName'] !== $data['last_name'] ) {
				$requires_update      = true;
				$customer['lastName'] = $data['last_name'];
			}

			// Update customer if first or last name are provided and differ from the existing customer record.
			if ( $requires_update ) {
				$this->api()->update_customer( $customer['id'], $customer );
			}

			// Add customer phone. Don't both checking if the phone already exists. HelpScout will handle that for us.
			if( ! empty( $data['phone'] ) ) {
				$this->api()->add_customer_phone( $customer['id'], $data['phone'] );
			}

		}

		$conversation_customer = array(
			'email' => rgar( $data, 'email', rgar( $customer, 'email' ) ),
		);

		if ( $customer_id ) {
			$conversation_customer['id'] = $customer_id;
		} else {
			// Only adding when there is a value; passing an empty value results in a bad request error.
			if ( ! empty( $data['first_name'] ) ) {
				$conversation_customer['firstName'] = $data['first_name'];
			}

			if ( ! empty( $data['last_name'] ) ) {
				$conversation_customer['lastName'] = $data['last_name'];
			}

			if ( ! empty( $data['phone'] ) ) {
				$conversation_customer['phone'] = $data['phone'];
			}
		}

		$conversation = array(
			'subject'   => $data['subject'],
			'customer'  => $conversation_customer,
			'mailboxId' => $feed['meta']['mailbox'],
			'type'      => $feed['meta']['type'],
			'status'    => $feed['meta']['status'],
			'tags'      => $data['tags'],
			'assignTo'  => $data['assign_to'],
			'autoReply' => $data['auto_reply'],
			'threads'   => array(
				array(
					'type'        => 'customer',
					'customer'    => $conversation_customer,
					'text'        => $data['body'],
					'cc'          => $data['cc'],
					'bcc'         => $data['bcc'],
					'attachments' => $data['attachments'],
				)
			),
		);

		if( $data['note'] ) {

			if ( ! empty( $data['note'] ) && gf_apply_filters( 'gform_helpscout_process_note_shortcodes', $form['id'], false, $form, $feed ) ) {
				$data['note'] = do_shortcode( $data['note'] );
			}

			$conversation['threads'] = array_merge( array( array(
				'type' => 'note',
				'text' => $data['note'],
			) ), $conversation['threads'] );

		}

		// Log the conversation to be created.
		$this->log_debug( __METHOD__ . '(): Conversation to be created => ' . print_r( $conversation, true ) );

		/**
		 * Filter the conversation before it is created in HelpScout.
		 *
		 * @since 1.6
		 *
		 * @param array $conversation HelpScout Conversation object: https://developer.helpscout.com/mailbox-api/endpoints/conversations/create/
		 * @param array $feed         Current feed.
		 * @param array $entry        Current entry.
		 * @param array $form         Current form.
		 */
		$conversation = gf_apply_filters( array( 'gform_helpscout_conversation', $form['id'] ), $conversation, $feed, $entry, $form );

		$conversation_id = $this->api()->create_conversation( $conversation );
		if( is_wp_error( $conversation_id ) ) {

			// Log that conversation was not created.
			$this->add_feed_error( 'Conversation was not created; ' . $conversation_id->get_error_message(), $feed, $entry, $form );
			$this->maybe_log_error_data( $conversation_id );

			return;

		} else {

			// Add conversation ID to entry meta.
			gform_update_meta( $entry['id'], 'helpscout_conversation_id', $conversation_id );

			// Log that conversation was created.
			$this->log_debug( __METHOD__ . '(): Conversation has been created.' );

			GFAPI::send_notifications( $form, $entry, $this->get_slug() . '_conversation_created' );

		}

	}

	/**
	 * Process attachments for feed.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param array $files File paths to convert to Help Scout attachments.
	 * @param array $feed  The current Feed object.
	 * @param array $entry The current Entry object.
	 * @param array $form  The current Form object.
	 *
	 * @return array
	 */
	public function process_feed_attachments( $files, $feed, $entry, $form ) {

		_deprecated_function( __METHOD__, '1.6', 'GFHelpScout::process_attachments' );

		// Initialize attachments array.
		$attachments = array();

		// If Help Scout instance is not initialized or no files are ready for conversion, return attachments.
		if ( ! $this->is_authenticated() || rgblank( $files ) ) {
			return $attachments;
		}

		// Loop through files.
		foreach ( $files as $file ) {

			// Get the file name and path.
			$file_name     = basename( $file );
			$file_path = str_replace( WP_CONTENT_URL, WP_CONTENT_DIR, $file );

			// Get the file's mime type.
			$file_info      = finfo_open( FILEINFO_MIME_TYPE );
			$file_mime_type = finfo_file( $file_info, $file_path );
			finfo_close( $file_info );

			// Initialize attachment object.
			$attachment = new \HelpScout\model\Attachment();
			$attachment->setFileName( $file_name );
			$attachment->setMimeType( $file_mime_type );
			$attachment->setData( file_get_contents( $file_path ) );

			try {

				// Create the attachment.
				$this->api()->createAttachment( $attachment );

				// Add attachment to attachments array.
				$attachments[] = $attachment;

			} catch ( Exception $e ) {

				// Log that attachment could not be created.
				$this->add_feed_error( 'Unable to upload attachment; ' . $e->getMessage(), $feed, $entry, $form );

			}

		}

		return $attachments;

	}

	/**
	 * Gets an array of attachments data suitable for adding to the conversation.
	 *
	 * @since 1.6
	 * @since 1.10 Added the $feed param.
	 *
	 * @param array $field_ids The IDs of the fields selected as the source of the attachments.
	 * @param array $form      The form currently being processed.
	 * @param array $entry     The entry currently being processed.
	 * @param array $feed      The feed currently being processed.
	 *
	 * @return array
	 */
	public function process_attachments( $field_ids, $form, $entry, $feed = array() ) {

		if ( empty( $field_ids ) ) {
			return array();
		}

		// Get attachment field IDs.
		$attachment_fields = array_keys( $field_ids );

		// Initialize attachment files array.
		$attachments = array();

		// Loop through attachment fields.
		foreach ( $attachment_fields as $attachment_field ) {

			// Get field value.
			$field_value = $this->get_field_value( $form, $entry, $attachment_field );
			$field_value = $this->is_json( $field_value ) ? json_decode( $field_value, true ) : $field_value;
			$field_value = strpos( $field_value, ' , ' ) !== false ? explode( ' , ', $field_value ) : $field_value;

			// If no field value is set, skip field.
			if ( empty( $field_value ) ) {
				continue;
			}

			$files = is_array( $field_value ) ? $field_value : array( $field_value );

			$size_limit = 10 * MB_IN_BYTES;

			foreach ( $files as $file ) {
				$file_path = GFFormsModel::get_physical_file_path( $file );
				$file_name = basename( $file_path );
				$raw_data  = file_get_contents( $file_path );
				$file_size = strlen( $raw_data );

				if ( $file_size >= $size_limit ) {
					/* translators: 1: File name 2: File size including unit */
					$this->add_feed_error( esc_html__( sprintf( 'Not attaching %1$s; %2$s exceeds Help Scout API limit of 10 MB.', $file_name, size_format( $file_size, 2 ) ), 'gravityformshelpscout' ), $feed, $entry, $form );
					continue;
				}

				$mime_type = wp_check_filetype_and_ext( $file_path, $file_name );

				$attachments[] = array(
					'fileName' => $file_name,
					'mimeType' => $mime_type['type'],
					'data'     => base64_encode( $raw_data ),
				);
			}

		}

		return $attachments;
	}





	// # ENTRY DETAILS -------------------------------------------------------------------------------------------------

	/**
	 * Add Create Conversation to entry list bulk actions.
	 *
	 * @since  1.4.2
	 * @access public
	 *
	 * @param array $actions Bulk actions.
	 * @param int   $form_id The current form ID.
	 *
	 * @return array
	 */
	public function add_bulk_action( $actions = array(), $form_id = '' ) {

		// Add action.
		if( GFCommon::current_user_can_any( $this->_capabilities_form_settings ) ) {
			$actions['helpscout'] = esc_html__( 'Create Help Scout Conversation', 'gravityformshelpscout' );
		}

		return $actions;

	}

	/**
	 * Process Help Scout entry list bulk actions.
	 *
	 * @since  1.4.2
	 * @access public
	 *
	 * @param string $action  Action being performed.
	 * @param array  $entries The entry IDs the action is being applied to.
	 * @param int    $form_id The current form ID.
	 *
	 * @uses GFAPI::get_entry()
	 * @uses GFAPI::get_form()
	 * @uses GFFeedAddOn::maybe_process_feed()
	 * @uses GFHelpScout::get_entry_conversation_id()
	 */
	public function process_entry_list_bulk_action( $action = '', $entries = array(), $form_id = '' ) {

		if ( ! GFCommon::current_user_can_any( $this->_capabilities_form_settings ) || empty( $entries ) ) {
			return;
		}

		// Get the current form.
		$form = GFAPI::get_form( $form_id );

		// Loop through entries.
		foreach ( $entries as $entry_id ) {

			// Get the entry.
			$entry = GFAPI::get_entry( $entry_id );

			// If a Help Scout conversation ID exists for this entry, skip.
			if ( $this->get_entry_conversation_id( $entry ) ) {
				continue;
			}

			// Process feeds.
			$this->maybe_process_feed( $entry, $form );

		}

	}





	// # ENTRY DETAILS -------------------------------------------------------------------------------------------------

	/**
	 * Add the Help Scout details meta box to the entry detail page.
	 *
	 * @since  1.3.1
	 * @access public
	 *
	 * @param array $meta_boxes The properties for the meta boxes.
	 * @param array $entry      The entry currently being viewed/edited.
	 * @param array $form       The form object used to process the current entry.
	 *
	 * @return array
	 */
	public function register_meta_box( $meta_boxes, $entry, $form ) {

		if ( $this->get_active_feeds( $form['id'] ) && $this->is_authenticated() ) {
			$meta_boxes[ $this->_slug ] = array(
				'title'    => esc_html__( 'Help Scout Details', 'gravityformshelpscout' ),
				'callback' => array( $this, 'add_details_meta_box' ),
				'context'  => 'side',
			);
		}

		return $meta_boxes;

	}

	/**
	 * The callback used to echo the content to the meta box.
	 *
	 * @since  1.3.1
	 * @access public
	 *
	 * @param array $args An array containing the form and entry objects.
	 *
	 * @uses GFHelpScout::get_panel_markup()
	 */
	public function add_details_meta_box( $args ) {

		echo $this->get_panel_markup( $args['form'], $args['entry'] );

	}

	/**
	 * Generate the markup for use in the meta box.
	 *
	 * @since  1.3.1
	 * @access public
	 *
	 * @param array $form  The current Form object.
	 * @param array $entry The current Entry object.
	 *
	 * @uses GFAddOn::log_error()
	 * @uses GFCommon::format_date()
	 * @uses GFHelpScout::get_entry_conversation_id()
	 *
	 * @return string
	 */
	public function get_panel_markup( $form, $entry ) {

		// Initialize HTML string.
		$html = '';

		// Get conversation ID.
		$conversation_id = $this->get_entry_conversation_id( $entry );

		// If a Help Scout conversation exists, display conversation details.
		if ( $conversation_id ) {

			// Get conversation.
			$conversation = $this->get_conversation( $conversation_id, $entry['id'] );

			if ( empty( $conversation ) ) {
				return $html;
			}

			$html .= esc_html__( 'Conversation ID', 'gravityformshelpscout' ) . ': <a href="https://secure.helpscout.net/conversation/' . $conversation['id'] . '/' . $conversation['number'] . '/" target="_blank">' . $conversation['id'] . '</a><br /><br />';
			$html .= esc_html__( 'Status', 'gravityformshelpscout' ) . ': ' . ucwords( $conversation['status'] ) . '<br /><br />';
			$html .= esc_html__( 'Created At', 'gravityformshelpscout' ) . ': ' . GFCommon::format_date( $conversation['createdAt'], false, 'Y/m/d', true ) . '<br /><br />';
			$html .= esc_html__( 'Last Updated At', 'gravityformshelpscout' ) . ': ' . GFCommon::format_date( $conversation['userUpdatedAt'], false, 'Y/m/d', true ) . '<br /><br />';

		} else {

			// Get create conversation URL.
			$url = add_query_arg( array( 'gf_helpscout' => 'process', 'lid' => $entry['id'] ) );
			$url = wp_nonce_url( $url, 'gform_helpscout_create_conversation' );

			// Display create conversation button.
			$html .= '<a href="' . esc_url( $url ) . '" class="button">' . esc_html__( 'Create Conversation', 'gravityformshelpscout' ) . '</a>';

		}

		return $html;

	}

	/**
	 * Add a panel to the entry view with details about the Help Scout conversation.
	 *
	 * @since  1.3
	 * @access public
	 *
	 * @param array $form  The current Form object.
	 * @param array $entry The current Entry object.
	 *
	 * @return string
	 */
	public function add_entry_detail_panel( $form, $entry ) {

		// If the API isn't initialized, return.
		if ( ! $this->get_active_feeds( $form['id'] ) || ! $this->is_authenticated() ) {
			return;
		}

		$html  = '<div id="helpscoutdiv" class="stuffbox">';
		$html .= '<h3 class="hndle" style="cursor:default;"><span>' . esc_html__( 'Help Scout Details', 'gravityformshelpscout' ) . '</span></h3>';
		$html .= '<div class="inside">';
		$html .= $this->get_panel_markup( $form, $entry );
		$html .= '</div>';
		$html .= '</div>';

		echo $html;

	}

	/**
	 * Create Help Scout creation on the entry view page.
	 *
	 * @since  1.3
	 * @access public
	 *
	 * @uses GFAddOn::get_current_entry()
	 * @uses GFAPI::get_form()
	 * @uses GFFeedAddOn::maybe_process_feed()
	 * @uses GFHelpScout::get_entry_conversation_id()
	 */
	public function maybe_create_conversation() {

		// If we're not on the entry view page, return.
		if ( rgget( 'page' ) !== 'gf_entries' || rgget( 'view' ) !== 'entry' || rgget( 'gf_helpscout' ) !== 'process' ) {
			return;
		}

		// Verify nonce.
		if ( wp_verify_nonce( rgget( '_wpnonce' ), 'gform_helpscout_create_conversation' ) === false ) {
			wp_die( esc_html__( 'Access denied.', 'gravityformshelpscout' ) );
		}

		// If user is not authorized, exit.
		if ( ! GFCommon::current_user_can_any( $this->_capabilities_settings_page ) ) {
			wp_die( esc_html__( 'Access denied.', 'gravityformshelpscout' ) );
		}

		// Get the current form and entry.
		$form  = GFAPI::get_form( rgget( 'id' ) );
		$entry = $this->get_current_entry();

		// If a Help Scout conversation ID exists for this entry, return.
		if ( $this->get_entry_conversation_id( $entry ) ) {
			return;
		}

		// Process feeds.
		$this->_bypass_feed_delay = true;
		$this->maybe_process_feed( $entry, $form );

	}

	/**
	 * Redirects to the conversation on the Help Scout site when the link in the conversation ID column of the entries list page is used.
	 *
	 * @since 1.14
	 */
	public function maybe_conversation_redirect() {

		if ( empty( $_GET['gf_helpscout_redirect'] ) || empty( $_GET['lid'] ) || ! $this->is_entry_view() || ! $this->current_user_can_any( 'gravityforms_view_entries' ) ) {
			return;
		}

		$entry_id = absint( $_GET['lid'] );
		if ( ! GFAPI::entry_exists( $entry_id ) ) {
			return;
		}

		$conversation_id = gform_get_meta( $entry_id, 'helpscout_conversation_id' );

		if ( $conversation_id && wp_verify_nonce( $_GET['gf_helpscout_redirect'], $conversation_id ) ) {
			$conversation_url = rgars( $this->get_conversation( $conversation_id, $entry_id ), '_links/web/href' );

			if ( $conversation_url && strpos( $conversation_url, 'https://secure.helpscout.net/' ) === 0 && wp_redirect( $conversation_url ) ) {
				exit;
			}
		}

		GFCommon::add_dismissible_message( esc_html__( 'Unable to redirect to Help Scout conversation.', 'gravityformshelpscout' ), 'gf_helpscout_redirect', 'error' );

	}

	/**
	 * Insert "Add Note to Help Scout Conversation" checkbox to add note form.
	 *
	 * @since  1.3
	 * @access public
	 *
	 * @param string $note_button Add note button.
	 *
	 * @return string $note_button
	 */
	public function add_note_checkbox( $note_button ) {

		// Get current entry.
		$entry = $this->get_current_entry();

		// If API is not initialized or entry does not have a Help Scout conversation ID, return existing note button.
		if ( ! $this->is_authenticated() || is_wp_error( $entry ) || ! $this->get_entry_conversation_id( $entry ) ) {
			return $note_button;
		}

		$note_button .= '<span style="float:right;line-height:28px;">';
		$note_button .= '<input type="checkbox" name="helpscout_add_note" value="1" id="gform_helpscout_add_note" style="margin-top:0;" ' . checked( rgpost( 'helpscout_add_note' ), '1', false ) . ' /> ';
		$note_button .= '<label for="gform_helpscout_add_note">' . esc_html__( 'Add Note to Help Scout Conversation', 'gravityformshelpscout' ) . '</label>';
		$note_button .= '</span>';

		return $note_button;

	}

	/**
	 * Add note to Help Scout conversation.
	 *
	 * @since  1.3
	 * @access public
	 *
	 * @param int    $note_id   The ID of the created note.
	 * @param int    $entry_id  The ID of the entry the note belongs to.
	 * @param int    $user_id   The ID of the user who created the note.
	 * @param string $user_name The name of the user who created the note.
	 * @param string $note      The note contents.
	 * @param string $note_type The note type.
	 */
	public function add_note_to_conversation( $note_id, $entry_id, $user_id, $user_name, $note, $note_type ) {

		// If add note checkbox not selected, return.
		if ( rgpost( 'helpscout_add_note' ) !== '1' ) {
			return;
		}

		// Get entry.
		$entry = GFAPI::get_entry( $entry_id );

		// Get conversation ID.
		$conversation_id = $this->get_entry_conversation_id( $entry );

		// If API is not initialized or entry does not have a Help Scout conversation ID, exit.
		if ( ! $this->is_authenticated() || ! $conversation_id ) {
			return;
		}

		$note_added = $this->api()->add_note( $conversation_id, $note );
		if( is_wp_error( $note_added ) ) {

			// Log that note was not added.
			$this->log_error( __METHOD__ . '(): Note was not added to conversation; ' . $note_added->get_error_message() );

		} else {

			// Log that note was added.
			$this->log_debug( __METHOD__ . '(): Note was successfully added to conversation.' );

		}

	}





	// # HELPER METHODS ------------------------------------------------------------------------------------------------

	/**
	 * Initializes Help Scout API if API credentials are valid.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses GFAddOn::get_plugin_setting()
	 * @uses GFAddOn::log_debug()
	 * @uses GFAddOn::log_error()
	 *
	 * @return bool|null
	 */
	public function initialize_api() {
		return $this->is_authenticated();
	}

	/**
	 * Initializes Help Scout API and determines if the add-on is authenticated.
	 *
	 * @since 1.6
	 *
	 * @return bool
	 */
	public function is_authenticated() {
		if ( ! is_null( $this->_is_authenticated ) ) {
			return $this->_is_authenticated;
		}

		$access_token = $this->api()->get_access_token();
		if ( rgblank( $access_token ) ) {
			// Check for v1 key and, if found, transition to v2. This should only happen once when upgrading to GF HS 2.0.
			if ( $this->get_plugin_setting( 'api_key' ) ) {
				$this->transition();
			} else {
				if ( $this->api()->is_access_token_expired() ) {
					$this->log_error( __METHOD__ . '(): The access token refresh request failed. The add-on is still connected to Help Scout and will retry the refresh during the next request.' );
				} else {
					$this->log_error( __METHOD__ . '(): No v1 API key to transition and no v2 API oAuth access token.' );
				}

				$this->_is_authenticated = false;

				return false;
			}
		}

		$this->log_debug( __METHOD__ . '(): Validating API credentials.' );

		$response = $this->api()->get_me();
		if ( is_wp_error( $response ) ) {
			$this->log_error( __METHOD__ . '(): API credentials are invalid; ' . $response->get_error_message() );
			$this->_is_authenticated = false;

			return false;
		}

		$this->log_debug( __METHOD__ . '(): API credentials are valid.' );

		$this->_is_authenticated = true;

		return true;
	}

	/**
	 * @return false|GF_HelpScout_API
	 */
	public function api() {

		if ( ! is_null( $this->api ) ) {
			return $this->api;
		}

		require_once( 'includes/class-gf-helpscout-api.php' );

		$this->api = new GF_HelpScout_API();

		return $this->api;
	}

	/**
	 * Get HelpScout app key.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses GFAddOn::get_plugin_settings()
	 *
	 * @return string
	 */
	public function get_app_key() {

		// Get plugin settings.
		$settings = $this->get_plugin_settings();

		return rgar( $settings, 'customAppKey' ) ? rgar( $settings, 'customAppKey' ) : null;

	}

	/**
	 * Get HelpScout app secret.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses GFAddOn::get_plugin_settings()
	 *
	 * @return string
	 */
	public function get_app_secret() {

		// Get plugin settings.
		$settings = $this->get_plugin_settings();

		return rgar( $settings, 'customAppSecret' ) ? rgar( $settings, 'customAppSecret' ) : null;

	}

	public function get_redirect_url() {
		return admin_url( 'admin.php?page=gf_settings&subview=' . $this->_slug );
	}

	/**
	 * Get action name for authentication state.
	 *
	 * @since 1.14
	 *
	 * @return string
	 */
	public function get_authentication_state_action() {

		return 'gform_helpscout_authentication_state';

	}

	/**
	 * Add the conversation ID entry meta property.
	 *
	 * @since  1.3
	 * @access public
	 * @param  array $entry_meta An array of entry meta already registered with the gform_entry_meta filter.
	 * @param  int   $form_id The form id.
	 *
	 * @return array The filtered entry meta array.
	 */
	public function get_entry_meta( $entry_meta, $form_id ) {

		$entry_meta['helpscout_conversation_id'] = array(
			'label'             => __( 'Help Scout Conversation ID', 'gravityformshelpscout' ),
			'is_numeric'        => true,
			'is_default_column' => false,
		);

		return $entry_meta;

	}

	/**
	 * Helper function to get current entry.
	 *
	 * @since  1.3.1
	 * @access public
	 *
	 * @uses GFAddOn::is_gravityforms_supported()
	 * @uses GFAPI::get_entries()
	 * @uses GFAPI::get_entry()
	 * @uses GFCommon::get_base_path()
	 * @uses GFEntryDetail::get_current_entry()
	 *
	 * @return array $entry
	 */
	public function get_current_entry() {

		if ( $this->is_gravityforms_supported( '2.0-beta-3' ) ) {

			if ( ! class_exists( 'GFEntryDetail' ) ) {
				require_once( GFCommon::get_base_path() . '/entry_detail.php' );
			}

			return GFEntryDetail::get_current_entry();

		} else {

			$entry_id = rgpost( 'entry_id' ) ? absint( rgpost( 'entry_id' ) ) : absint( rgget( 'lid' ) );

			if ( $entry_id > 0 ) {

				return GFAPI::get_entry( $entry_id );

			} else {

				$position = rgget( 'pos' ) ? rgget( 'pos' ) : 0;
				$paging   = array( 'offset' => $position, 'page_size' => 1 );
				$entries  = GFAPI::get_entries( rgget( 'id' ), array(), null, $paging );

				return $entries[0];

			}

		}

	}

	/**
	 * Add Help Scout conversation link to entry list column.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param string $value Current value that will be displayed in this cell.
	 * @param int    $form_id ID of the current form.
	 * @param int    $field_id ID of the field that this column applies to.
	 * @param array  $entry Current entry object.
	 * @param string $query_string Current page query string with search and pagination state.
	 *
	 * @return string
	 */
	public function add_entry_conversation_column_link( $value, $form_id, $field_id, $entry, $query_string ) {

		// If this is not the Help Scout Conversation ID column, return value.
		if ( 'helpscout_conversation_id' !== $field_id || empty( $value ) ) {
			return $value;
		}

		$url = add_query_arg( array(
			'page'                  => 'gf_entries',
			'view'                  => 'entry',
			'id'                    => $form_id,
			'lid'                   => absint( $entry['id'] ),
			'gf_helpscout_redirect' => wp_create_nonce( $value ),
		), admin_url( 'admin.php' ) );

		return sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( $url ), $value );

	}

	/**
	 * Retrieve the conversation id for the current entry.
	 *
	 * @since  1.3.1
	 * @access public
	 *
	 * @param array $entry The entry currently being viewed/edited.
	 *
	 * @return string
	 */
	public function get_entry_conversation_id( $entry ) {

		// Define entry meta key.
		$key = 'helpscout_conversation_id';

		// Get conversation ID.
		$id = rgar( $entry, $key );

		if ( empty( $id ) && rgget( 'gf_helpscout' ) === 'process' ) {
			$id = gform_get_meta( $entry['id'], $key );
		}

		return $id;

	}

	/**
	 * Parse comma-delimited string into an array.
	 *
	 * @param $str
	 *
	 * @return array
	 */
	public function parse_comma_delimited_string( $str ) {

		if( ! is_string( $str ) ) {
			return array();
		}

		$array = explode( ',', $str );
		$array = array_map( 'trim', $array );

		// Clean array of duplicate and empty items.
		$array = array_values( array_unique( array_filter( $array ) ) );

		return $array;
	}

	/**
	 * Writes the supplied error data to the error log.
	 *
	 * @since 1.8
	 *
	 * @param WP_Error|mixed $error_data A WP_Error object or the error data to be written to the log.
	 */
	public function maybe_log_error_data( $error_data ) {
		if ( is_wp_error( $error_data ) ) {
			$error_data = $error_data->get_error_data();
		}

		if ( empty( $error_data ) ) {
			return;
		}

		$backtrace = debug_backtrace();
		$method    = $backtrace[1]['class'] . '::' . $backtrace[1]['function'];
		$this->log_error( $method . '(): ' . print_r( $error_data, true ) );
	}

	/**
	 * Gets the Help Scout user properties for the assigned user.
	 *
	 * @since 1.10
	 *
	 * @param array $feed The current feed.
	 *
	 * @return array|null
	 */
	public function get_assigned_user( $feed ) {
		$user_id = rgars( $feed, 'meta/user' );

		if ( empty( $user_id ) ) {
			return null;
		}

		$cache_key = $this->get_slug() . '_user_' . $user_id;

		$found = false;
		$user  = GFCache::get( $cache_key, $found, true );

		if ( $found === false ) {
			$user = $this->api()->get_user( $user_id );
			GFCache::set( $cache_key, $user, true, DAY_IN_SECONDS );
		}

		if ( is_wp_error( $user ) ) {
			$this->log_error( __METHOD__ . "(): Unable to get user #{$user_id}; " . $user->get_error_message() );
			$user = null;
		}

		return $user;
	}

	/**
	 * Return the plugin's icon for the plugin/form settings menu.
	 *
	 * @since 1.13
	 *
	 * @return string
	 */
	public function get_menu_icon() {
		return $this->is_gravityforms_supported( '2.5-beta-4' ) ? 'gform-icon--helpscout' : 'dashicons-admin-generic';
	}


	// # MERGE TAGS ----------------------------------------------------------------------------------------------------


	/**
	 * Replace the merge tags.
	 *
	 * @since 1.8
	 *
	 * @param string $text       The current text in which merge tags are being replaced.
	 * @param array  $form       The current form object.
	 * @param array  $entry      The current entry object.
	 * @param bool   $url_encode Whether or not to encode any URLs found in the replaced value.
	 * @param bool   $esc_html   Whether or not to encode HTML found in the replaced value.
	 * @param bool   $nl2br      Whether or not to convert newlines to break tags.
	 * @param string $format     The format requested for the location the merge is being used. Possible values: html, text or url.
	 *
	 * @return string
	 */
	public function replace_merge_tags( $text, $form, $entry, $url_encode, $esc_html, $nl2br, $format ) {

		if ( empty( $entry['id'] ) || strpos( $text, '{' ) === false ) {
			return $text;
		}

		$matches = array();

		preg_match_all( '/{helpscout(:(.*?))?}/', $text, $matches, PREG_SET_ORDER );

		if ( ! empty( $matches ) ) {

			$key             = 'helpscout_conversation_id';
			$conversation_id = ! empty( $entry[ $key ] ) ? $entry[ $key ] : gform_get_meta( $entry['id'], $key );
			$conversation    = $this->get_conversation( $conversation_id, $entry['id'] );

			foreach ( $matches as $match ) {

				$full_tag    = $match[0];
				$property    = trim( rgar( $match, 2 ) );
				$replacement = '';

				if ( empty( $conversation ) ) {
					$text = str_replace( $full_tag, $replacement, $text );
					continue;
				}

				switch ( $property ) {

					case 'id':
					case 'number':
					case 'status':
					case 'subject':
						$replacement = rgar( $conversation, $property );
						break;

					case 'url':
						$replacement = rgars( $conversation, '_links/web/href' );
						break;

				}

				if ( $replacement ) {
					$replacement = GFCommon::format_variable_value( $replacement, $url_encode, $esc_html, $format, $nl2br );
				}

				$text = str_replace( $full_tag, $replacement, $text );

			}

		}

		return $text;

	}

	/**
	 * Gets the specified conversation and caches it for an hour.
	 *
	 * @since 1.14
	 *
	 * @param int|string $id       The ID of the conversation to be retrieved.
	 * @param int        $entry_id The ID of the entry the conversation was created by.
	 *
	 * @return bool|array
	 */
	public function get_conversation( $id, $entry_id ) {
		if ( empty( $id ) ) {
			return false;
		}

		$cache_key    = $this->get_slug() . '_conversation_' . $id;
		$conversation = GFCache::get( $cache_key );

		if ( ! empty( $conversation ) ) {
			$this->log_debug( sprintf( '%s(): Returning cached conversation #%s for entry #%d.', __METHOD__, $id, $entry_id ) );

			return $conversation;
		}

		if ( ! $this->is_authenticated() ) {
			$this->log_error( sprintf( '%s(): Could not get conversation #%s for entry #%d.', __METHOD__, $id, $entry_id ) );

			return false;
		}

		$conversation = $this->api()->get_conversation( $id );

		if ( is_wp_error( $conversation ) ) {
			$this->log_error( sprintf( '%s(): Could not get conversation #%s for entry #%d. %s', __METHOD__, $id, $entry_id, $conversation->get_error_message() ) );

			if ( $conversation->get_error_code() == 404 ) {
				gform_delete_meta( $entry_id, 'helpscout_conversation_id' );
			}

			return false;
		}

		$this->log_debug( sprintf( '%s(): Caching conversation #%s for 1 hour for entry #%d.', __METHOD__, $id, $entry_id ) );
		GFCache::set( $cache_key, $conversation, true, HOUR_IN_SECONDS );

		return $conversation;
	}

}
