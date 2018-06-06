<?php
/*
Plugin Name: SpinupWP
Plugin URI: https://spinupwp.com
Description: Helper plugin for SpinupWP.
Author: Delicious Brains
Version: 1.0
Author URI: https://deliciousbrainss.com
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SpinupWp {

	/**
	 * @var string
	 */
	protected $cache_path;

	/**
	 * Init SpinupWp.
	 */
	public function init() {
		if ( defined( 'SPINUPWP_CACHE_PATH' ) ) {
			$this->cache_path = SPINUPWP_CACHE_PATH;

			add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_item' ), 100 );
			add_action( 'admin_init', array( $this, 'handle_manual_purge_action' ) );
			add_action( 'admin_notices', array( $this, 'show_purge_notice' ) );
		}
	}

	/**
	 * Add purge option to admin bar.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar
	 */
	public function add_admin_bar_item( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$wp_admin_bar->add_node( array(
			'id'    => 'spinupwp',
			'title' => 'SpinupWP',
		) );

		$wp_admin_bar->add_node( array(
			'parent' => 'spinupwp',
			'id'     => 'spinupwp-purge-cache',
			'title'  => 'Purge Cache',
			'href'   => wp_nonce_url( add_query_arg( 'spinupwp_action', 'purge-cache', admin_url() ), 'purge-cache' ),
		) );
	}

	/**
	 * Handle manual purge actions.
	 */
	public function handle_manual_purge_action() {
		$action = filter_input( INPUT_GET, 'spinupwp_action' );

		if ( ! $action ) {
			return;
		}

		if ( ! wp_verify_nonce( filter_input( INPUT_GET, '_wpnonce' ), $action ) ) {
			return;
		}

		$purge = $this->purge_cache();
		wp_safe_redirect( add_query_arg( 'purge_success', (int) $purge, admin_url() ) );
	}

	/**
	 * Show purge success/error notice.
	 */
	public function show_purge_notice() {
		$success = filter_input( INPUT_GET, 'purge_success' );

		if ( is_null( $success ) ) {
			return;
		}

		if ( $success ) {
			echo '<div class="updated notice"><p>Nginx cache purged.</p></div>';
		}

		if ( ! $success ) {
			echo '<div class="error notice"><p>Nginx cache could not be purged.</p></div>';
		}
	}

	/**
	 * Purge entire cache.
	 *
	 * @return bool
	 */
	public function purge_cache() {
		return $this->delete( $this->cache_path, true );
	}

	/**
	 * Delete a file/dir from the local filesystem.
	 *
	 * @param string $path Absolute path to file
	 * @param bool   $recursive
	 *
	 * @return bool
	 */
	protected function delete( $path, $recursive = false ) {
		global $wp_filesystem;

		if ( ! WP_Filesystem( false, $path, true ) ) {
			return false;
		}

		$wp_filesystem->delete( $path, $recursive );
		
		return true;
	}
}

(new SpinupWp)->init();