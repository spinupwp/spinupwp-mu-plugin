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
			add_action( 'transition_post_status', array( $this, 'transition_post_status' ), 10, 3 );
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
	 * Transition post status.
	 *
	 * When a post is transitioned to 'publish' for the first time purge the
	 * entire site cache. This ensures blog pages, category archives, author archives
	 * and search results are accurate. Otherwise, only update the current post URL.
	 *
	 * @param string  $new_status
	 * @param string  $old_status
	 * @param WP_Post $post
	 *
	 * @return bool
	 */
	public function transition_post_status( $new_status, $old_status, $post ) {
		if ( ! in_array( get_post_type( $post ), array( 'post', 'page' ) ) ) {
			return false;
		}

		if ( $new_status !== 'publish' ) {
			return false;
		}

		if ( $old_status === 'publish' ) {
			return $this->purge_post( $post );
		}

		return $this->purge_cache();
	}

	/**
	 * Purge the current post URL.
	 *
	 * @param WP_Post $post
	 *
	 * @return bool
	 */
	protected function purge_post( $post ) {
		return $this->purge_url( get_permalink( $post ) );
	}
	/**
	 * Purge a single URL from the cache.
	 *
	 * @param string $url
	 *
	 * @return bool
	 */
	protected function purge_url( $url ) {
		$path = $this->get_cache_path_for_url( $url );
		
		return $this->delete( $path );
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
	 * Get's the cache file path for a given URL.
	 *
	 * Must be using the default Nginx cache options (levels=1:2)
	 * and (fastcgi_cache_key "$scheme$request_method$host$request_uri").
	 * https://www.digitalocean.com/community/tutorials/how-to-setup-fastcgi-caching-with-nginx-on-your-vps#purging-the-cache
	 *
	 * @param string $url
	 *
	 * @return string
	 */
	protected function get_cache_path_for_url( $url ) {
		$parsed_url = parse_url( trailingslashit( $url ) );
		$cache_key  = md5( $parsed_url['scheme'] . 'GET' . $parsed_url['host'] . $parsed_url['path'] );
		$cache_path = substr( $cache_key, -1 ) . '/' . substr( $cache_key, -3, 2 ) . '/' . $cache_key;
		
		return trailingslashit( $this->cache_path ) . $cache_path;
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