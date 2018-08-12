<?php
/*
 * Plugin Name: WooCommerce Mondido Checkout Gateway
 * Plugin URI: https://www.mondido.com/
 * Description: Provides a Payment Gateway through Mondido for WooCommerce.
 * Author: Mondido
 * Author URI: https://www.mondido.com/
 * Version: 1.1.0
 * Text Domain: woocommerce-gateway-mondido-checkout
 * Domain Path: /languages
 * WC requires at least: 3.0.0
 * WC tested up to: 3.4.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WC_Mondido_Checkout {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );

		// Activation
		register_activation_hook( __FILE__, array( $this, 'activate' ) );

		// Actions
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array(
			$this,
			'plugin_action_links'
		) );
		add_action( 'plugins_loaded', array( $this, 'init' ), 0 );

		add_action( 'woocommerce_loaded', array(
			$this,
			'woocommerce_loaded'
		), 40 );
	}

	/**
	 * Activate Plugin
	 */
	public function activate() {
		// Required plugin: WooCommerce Mondido Payments Gateway
		if ( class_exists( 'WC_Mondido_Payments', FALSE ) ) {
			return TRUE;
		}

		// Download and Install WC_Mondido_Payments package
		include_once ABSPATH . '/wp-includes/pluggable.php';
		include_once ABSPATH . '/wp-admin/includes/plugin.php';
		include_once ABSPATH . '/wp-admin/includes/file.php';

		try {
			if ( ! $plugin = self::get_core_plugin() ) {
				// Install plugin
				self::install_core_plugin();

				// Plugin path
				$plugin = self::get_core_plugin();
			}

			// Check is active
			if ( ! is_plugin_active( $plugin ) ) {
				// Activate plugin
				self::activate_core_plugin();

				WC_Admin_Notices::add_custom_notice(
					'wc-mondido-checkout-notice',
					__( 'Required WooCommerce Mondido Payments Gateway plugin was automatically installed.', 'woocommerce-gateway-mondido-checkout' )
				);
			}
		} catch ( \Exception $e ) {
			self::add_admin_notice( $e->getMessage(), 'error' );

			return FALSE;
		}

		// Set Version
		if ( ! get_option( 'woocommerce_mondido_checkout_version' ) ) {
			add_option( 'woocommerce_mondido_checkout_version', '1.0.0' );
		}

		return TRUE;
	}

	/**
	 * Add relevant links to plugins page
	 *
	 * @param  array $links
	 *
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$plugin_links = array(
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=mondido_checkout' ) . '">' . __( 'Settings', 'woocommerce-gateway-mondido-checkout' ) . '</a>'
		);

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Init localisations and files
	 * @return void
	 */
	public function init() {
		// Localization
		load_plugin_textdomain( 'woocommerce-gateway-mondido-checkout', FALSE, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * WooCommerce Loaded: load classes
	 * @return void
	 */
	public function woocommerce_loaded() {
		if ( ! class_exists( 'WC_Mondido_Payments', FALSE ) ) {
			return;
		}

		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-mondido-checkout.php' );
	}

	/**
	 * Display admin notices
	 * @return void
	 */
	public function display_admin_notices() {
		$notices = self::get_admin_notices();
		if ( count( $notices ) === 0 ) {
			return;
		}

		foreach ( $notices as $type => $messages ):
			?>
			<div class="<?php echo esc_html( $type ); ?> notice">
				<?php foreach ( $messages as $message ): ?>
					<p>
						<?php echo esc_html( $message ); ?>
					</p>
				<?php endforeach; ?>
			</div>
		<?php
		endforeach;

		// Remove notices
		delete_transient( 'wc-mondido-checkout-notice' );

		// Deactivate plugin
		deactivate_plugins( array( __FILE__ ), TRUE );
	}

	/**
	 * Add admin notice
	 *
	 * @param string $message
	 * @param string $type
	 * @return void
	 */
	public static function add_admin_notice( $message, $type = 'error' ) {
		wp_cache_delete( 'wc-mondido-checkout-notice', 'transient' );
		if ( ! ( $notices = get_transient( 'wc-mondido-checkout-notice' ) ) ) {
			$notices = array();
		}

		if ( ! isset( $notices[ $type ] ) ) {
			$notices[ $type ] = array();
		}

		$notices[ $type ][] = $message;

		set_transient( 'wc-mondido-checkout-notice', $notices );
	}

	/**
	 * Get admin notices
	 * @return array
	 */
	public static function get_admin_notices() {
		if ( ! ( $notices = get_transient( 'wc-mondido-checkout-notice' ) ) ) {
			$notices = array();
		}

		return $notices;
	}

	/**
	 * Get Core Plugin path
	 * @return bool|string
	 */
	protected static function get_core_plugin() {
		wp_cache_delete( 'plugins', 'plugins' );

		$plugins = get_plugins();
		foreach ( $plugins as $file => $plugin ) {
			if ( strpos( $file, 'woocommerce-gateway-mondido.php' ) !== FALSE ) {
				return $file;
			}
		}

		return FALSE;
	}

	/**
	 * Activate Core Plugin
	 * @return bool
	 * @throws \Exception
	 */
	public static function activate_core_plugin() {
		if ( $plugin = self::get_core_plugin() ) {
			$result = activate_plugin( $plugin );
			if ( is_wp_error( $result ) ) {
				throw new Exception( $result->get_error_message() );
			}

			return TRUE;
		}

		throw new Exception( 'Failed to activate plugin' );
	}

	/**
	 * Install Core Plugin
	 * @throws \Exception
	 * @return void
	 */
	protected static function install_core_plugin() {
		WP_Filesystem();

		/** @var WP_Filesystem_Base $wp_filesystem */
		global $wp_filesystem;

		// Install plugin
		// Get latest release from Github
		$response = wp_remote_get( 'https://api.github.com/repos/Mondido/MondidoWooCommerce/releases/latest', array(
			'headers' => array( 'Accept' => 'application/vnd.github.v3+json' ),
		) );
		if ( is_wp_error( $response ) ) {
			throw new Exception( $response->get_error_message() );
		}

		$release = json_decode( $response['body'], TRUE );
		if ( ! isset( $release['zipball_url'] ) ) {
			throw new Exception( 'Failed to get latest release of WooCommerce Mondido Payments Gateway plugin' );
		}

		// Download package
		$tmpfile = download_url( $release['zipball_url'] );
		if ( is_wp_error( $tmpfile ) ) {
			throw new Exception( $tmpfile->get_error_message() );
		}

		// Extract package
		$tmpdir = rtrim( get_temp_dir(), '/' ) . '/' . uniqid( 'mondido_' );
		if ( ! $wp_filesystem->exists( $tmpdir ) ) {
			$wp_filesystem->mkdir( $tmpdir, FS_CHMOD_DIR );
		}
		$result = unzip_file( $tmpfile, $tmpdir );
		if ( is_wp_error( $result ) ) {
			throw new Exception( $result->get_error_message() );
		}

		// Remove temp file
		$wp_filesystem->delete( $tmpfile );

		// Move plugin to plugins directory
		$files = $wp_filesystem->dirlist( $tmpdir );
		foreach ( $files as $name => $details ) {
			if ( strpos( $name, 'MondidoWooCommerce' ) !== FALSE ) {
				$destination = WP_PLUGIN_DIR . '/MondidoWooCommerce';
				// Remove destination directory if exists
				if ( $wp_filesystem->exists( $destination ) ) {
					$wp_filesystem->rmdir( $destination );
				}

				// Make destination directory
				$wp_filesystem->mkdir( $destination, FS_CHMOD_DIR );

				// Copy unpacked directory to destination directory
				$result = copy_dir( $tmpdir . '/' . $name, $destination );
				if ( is_wp_error( $result ) ) {
					throw new Exception( $result->get_error_message() );
				}

				// Remove temp directory
				$wp_filesystem->rmdir( $tmpdir );
				return;
			}
		}

		// Remove temp directory
		$wp_filesystem->rmdir( $tmpdir );

		throw new Exception( 'Failed to install WooCommerce Mondido Payments Gateway plugin' );
	}
}

new WC_Mondido_Checkout();
