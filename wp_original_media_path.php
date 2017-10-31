<?php
/*
Plugin Name: 	WP Original Media Path
Plugin URI: 	https://github.com/rvola/wp-original-media-path

Description: 	Change the location for the uploads folder for WordPress | <a href="http://wordpress.org/plugins/wp-original-media-path/faq/" target="_blank">FAQ</a> | <a href="http://wordpress.org/plugins/wp-original-media-path/installation/" target="_blank">How to Configure</a> | <a href="https://www.paypal.me/rvola" target="_blank">Donate</a>

License:		GPLv3
License URI:	https://www.gnu.org/licenses/gpl-3.0

Version: 		2.1.1
Revision:		2017-05-22
Creation:		2013-01-06

Author:			studio RVOLA
Author URI:		https://www.rvola.com

Domain Path: /languages/
Text Domain: wp-original-media-path
Requires at least:      3.5
Tested up to:           4.9
Requires PHP:           5.3

*/

namespace RVOLA;
if( ! defined( 'ABSPATH' ) ) exit;

final class WPOMP {

	const NAME = "WP Original Media Path";
	const I18N = "wp-original-media-path";
	const SLUG = "wpomp";
	const VERSION = "2.1.0";

	/*--------------------------------------------------------- */

	public static function activate() {
		$upload_path	 = get_option( 'upload_path' );
		$upload_url_path = get_option( 'upload_url_path' );

		if (
			   isset( $upload_path ) && empty( $upload_path)
			&& isset( $upload_url_path ) && empty( $upload_url_path)
		){
			$upload_dir = wp_upload_dir();
			$default_url  = self::clean_slash( $upload_dir['baseurl'] );

			self::set_uploadPath( $default_url );
			update_option( 'upload_url_path', $default_url, true );

		}

	}

	/*--------------------------------------------------------- */
	private static $singleton = null;

	public function __construct() {

		$this->check_multisite();

		add_action( 'init', array( $this, 'loadLanguages' ), 10 );

		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'pluginLinkPage' ), 10, 1 );

		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ), 10, 1 );

		add_action( 'admin_menu', array( $this, 'linkSidebar' ), 10 );
		add_action( 'admin_init', array( $this, 'registerSections' ), 10 );
		add_action( 'admin_init', array( $this, 'registerFields' ), 10 );
		add_action( 'admin_init', array( $this, 'addFields' ), 10 );
	}

	private function check_multisite() {
		if ( is_multisite() ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die(
				__( 'This plugin is not compatible with WordPress multisite. Sorry for the inconvenience.', self::I18N ),
				sprintf (
					__( 'Error : %s', self::I18N ),
					self::NAME
				),
				array(
					'back_link' => true
				)
			);
		}
	}

	/*--------------------------------------------------------- */
	public static function load() {

		if ( is_null( self::$singleton ) ) {
			$class           = __CLASS__;
			self::$singleton = new $class;
		}

		return self::$singleton;
	}

	public function loadLanguages() {

		load_plugin_textdomain( self::I18N, false, dirname( __FILE__ ) . '/languages' );
	}

	/*--------------------------------------------------------- */
	public function pluginLinkPage( $links ) {

		array_unshift(
			$links,
			sprintf(
				'<a href="%1$s">%2$s</a>',
				admin_url( 'admin.php?page=wpomp-options' ),
				__( 'Settings', self::I18N )
			)
		);
		return $links;
	}
	public function linkSidebar() {
		add_submenu_page(
			'options-general.php',
			self::NAME,
			self::NAME,
			'manage_options',
			self::SLUG . '-options',
			array( $this, 'optionsPages' )
		);
	}

	/*--------------------------------------------------------- */
	public function assets( $hook ) {

		/*script*/
		if ( $hook == 'settings_page_' . self::SLUG . '-options' ) {
			wp_enqueue_script( self::SLUG, plugins_url( 'assets/' . self::SLUG . '.js', __FILE__ ), array( 'jquery' ),
				self::VERSION, true );
		}
		/*style*/
		if ( $hook == 'options-media.php' || $hook == 'settings_page_' . self::SLUG . '-options' ) {
			wp_enqueue_style( self::SLUG, plugins_url( 'assets/' . self::SLUG . '.css', __FILE__ ), null, self::VERSION,
				'all' );
		}
	}
	/*--------------------------------------------------------- */

	public function optionsPages() {

		include( dirname( __FILE__ ) . '/pages/options.php' );
	}

	public function registerSections() {
		add_settings_section(
			'wpomp_section_main',
			__( 'Uploading Files', self::I18N ),
			null,
			'wpomp_pages'
		);
	}
	public function registerFields() {
		register_setting(
			'wpomp_fields',
			'wpomp_mode'
		);
		register_setting(
			'wpomp_fields',
			'upload_path'
		);
		register_setting(
			'wpomp_fields',
			'upload_url_path',
			array( $this, 'sanitize_url' )
		);
	}
	public function addFields() {
		$fields = array(
			'wpomp_mode'      => array(
				'id'             => 'wpomp_mode',
				'title'          => __( 'Expert mode', self::I18N ),
				'description'    => __( 'Activate that if you are aware of what you are doing.', self::I18N ),
				'type'        => 'checkbox',
				'method'      => 'checkbox',
			),
			'upload_path'     => array(
				'id'          => 'upload_path',
				'type'        => 'text',
				'method'      => 'text',
				'title'       => __( 'Store uploads in this folder', self::I18N ),
				'description' => sprintf( __( 'Default is %s', self::I18N ), '<code>wp-content/uploads</code>' ),
				'placeholder' => '',
			),
			'upload_url_path' => array(
				'id'          => 'upload_url_path',
				'title'       => __( 'Full URL path to files', self::I18N ),
				'type'        => 'url',
				'method'      => 'text',
				'description' => sprintf( __( 'Simply specify the url for your upload folder. Be careful, if you want a domain other than %s, make sure to point the domain (DNS) to the desired folder on your current server. The plugin can not upload to any other server than this one.',
					self::I18N ), '<strong>' . home_url() . '</strong>' ),
				'placeholder' => 'http://'
			),
		);

		foreach( $fields as $id => $field ){

			$field_fonction = 'inputFields' . ucfirst( $field['method'] );

			add_settings_field(
				$id,
				$field['title'],
				array( $this, $field_fonction ),
				'wpomp_pages',
				'wpomp_section_main',
				$field
			);
		}
	}

	public function inputFieldsText( $datafield ) {
		printf(
			'<input name="%1$s" type="%2$s" id="%1$s" value="%3$s" class="regular-text code" placeholder="%4$s"/>',
			$datafield['id'],
			$datafield['type'],
			get_option( $datafield['id'] ),
			$datafield['placeholder']
		);

		printf(
			'<p class="description">%s</p>',
			$datafield['description']
		);
	}

	public function inputFieldsCheckbox( $datafield ) {
		printf(
			'<input name="%1$s" type="checkbox" id="%1$s" value="1" %2$s /> %3$s',
			$datafield['id'],
			checked( 1, get_option( $datafield['id'] ), false ),
			sprintf(
				'<span class="description">%s</span>',
				$datafield['description']
			)
		);


	}

	/*--------------------------------------------------------- */

	public function sanitize_url( $value ) {
		$value = $this->clean_slash( $value );
		$value = esc_url( $value );

		if ( empty( $value ) ) {
			$value = home_url() . '/wp-content/uploads';
		}

		//save path automatically
		if ( get_option( 'wpomp_mode' ) != true ) {
			$this->set_uploadPath( $value );
		}


		return $value;
	}
	private static function clean_slash( $value ) {
		$value = rtrim( $value, '/\\' );
		$value = trim( $value, '/\\' );
		return $value;
	}

	/*--------------------------------------------------------- */

	private static function set_uploadPath( $url ) {
		$value = null;
		if ( strpos( $url, home_url() ) !== false ) {
			$value = str_replace( home_url(), '', $url );
		} else {
			$path = parse_url( $url );
			if ( isset( $path['path'] ) ) {
				$value = $path['path'];
			}
		}
		$value = self::clean_slash( $value );
		update_option( 'upload_path', $value, true );
	}

}

add_action( 'plugins_loaded', array( 'RVOLA\WPOMP', 'load' ), 10 );
register_activation_hook( __FILE__, array( 'RVOLA\WPOMP', 'activate' ) );
