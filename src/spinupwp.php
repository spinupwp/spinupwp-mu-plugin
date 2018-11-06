<?php
/*
Plugin Name: SpinupWP
Plugin URI: https://spinupwp.com
Description: Helper plugin for SpinupWP.
Author: Delicious Brains
Version: 1.0
Author URI: https://deliciousbrains.com/
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
	 * @var string
	 */
	protected $plugin_url;

	/**
	 * Init SpinupWp.
	 */
	public function init() {
		$this->cache_path = defined( 'SPINUPWP_CACHE_PATH' ) ? SPINUPWP_CACHE_PATH : null;
		$this->plugin_url = is_multisite() ? network_site_url( '/wp-content/mu-plugins/spinupwp', 'relative' ) : content_url( '/mu-plugins/spinupwp' );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_spinupwp_dismiss_notice', array( $this, 'ajax_dismiss_notice' ) );

		if ( is_multisite() ) {
			add_action( 'network_admin_notices', array( $this, 'show_mail_notice' ) );
		} else {
			add_action( 'admin_notices', array( $this, 'show_mail_notice' ) );
		}

		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_item' ), 100 );
		add_action( 'admin_init', array( $this, 'handle_manual_purge_action' ) );
		add_action( 'admin_notices', array( $this, 'show_purge_notice' ) );
		add_action( 'transition_post_status', array( $this, 'transition_post_status' ), 10, 3 );
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

		if ( ! $this->cache_path && ! wp_using_ext_object_cache() ) {
			return;
		}

		$wp_admin_bar->add_node( array(
			'id'    => 'spinupwp',
			'title' => 'SpinupWP',
		) );

		if ( wp_using_ext_object_cache() && $this->cache_path ) {
			$wp_admin_bar->add_node( array(
				'parent' => 'spinupwp',
				'id'     => 'spinupwp-purge-all',
				'title'  => 'Purge All Caches',
				'href'   => wp_nonce_url( add_query_arg( 'spinupwp_action', 'purge-all', admin_url() ), 'purge-all' ),
			) );
		}

		if ( wp_using_ext_object_cache() ) {
			$wp_admin_bar->add_node( array(
				'parent' => 'spinupwp',
				'id'     => 'spinupwp-purge-object',
				'title'  => 'Purge Object Cache',
				'href'   => wp_nonce_url( add_query_arg( 'spinupwp_action', 'purge-object', admin_url() ), 'purge-object' ),
			) );
		}

		if ( $this->cache_path ) {
			$wp_admin_bar->add_node( array(
				'parent' => 'spinupwp',
				'id'     => 'spinupwp-purge-page',
				'title'  => 'Purge Page Cache',
				'href'   => wp_nonce_url( add_query_arg( 'spinupwp_action', 'purge-page', admin_url() ), 'purge-page' ),
			) );	
		}		
	}

	/**
	 * Handle manual purge actions.
	 */
	public function handle_manual_purge_action() {
		$action = filter_input( INPUT_GET, 'spinupwp_action' );

		if ( ! $action || ! in_array( $action, array( 'purge-all', 'purge-object', 'purge-page' ) ) ) {
			return;
		}

		if ( ! wp_verify_nonce( filter_input( INPUT_GET, '_wpnonce' ), $action ) ) {
			return;
		}

		if ( 'purge-all' === $action ) {
			$purge = wp_cache_flush() && $this->purge_cache();
			$type  = 'all';
		}

		if ( 'purge-object' === $action ) {
			$purge = wp_cache_flush();
			$type  = 'object';
		}

		if ( 'purge-page' === $action ) {
			$purge = $this->purge_cache();
			$type  = 'page';
		}

		wp_safe_redirect( add_query_arg( array(
			'purge_success' => (int) $purge,
			'cache_type'    => $type,
		), admin_url() ) );
	}

	/**
	 * Show purge success/error notice.
	 */
	public function show_purge_notice() {
		$success = filter_input( INPUT_GET, 'purge_success' );
		$type    = filter_input( INPUT_GET, 'cache_type' );
		$msg     = '';

		if ( is_null( $success ) || is_null( $type ) ) {
			return;
		}

		if ( 'all' === $type ) {
			$msg = $success ? __( 'All caches successfully purged.', 'spinupwp' ) : __( 'Caches could not be purged.', 'spinupwp' );
		}

		if ( 'object' === $type ) {
			$msg = $success ? __( 'Object cache successfully purged.', 'spinupwp' ) : __( 'Object cache could not be purged.', 'spinupwp' );
		}

		if ( 'page' === $type ) {
			$msg = $success ? __( 'Page cache successfully purged.', 'spinupwp' ) : __( 'Page cache could not be purged.', 'spinupwp' );
		}

		if ( $msg ) {
			$notice_type = $success ? 'success' : 'error';
			echo "<div class=\"notice notice-{$notice_type}\"><p>{$msg}</p></div>";
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
		if ( ! $this->cache_path ) {
			return false;
		}

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
		if ( ! file_exists( $path ) ) {
			return true;
		}

		$context = $path;
		if ( is_file( $path ) ) {
			$context = dirname( $path );
		}

		if ( ! WP_Filesystem( false, $context, true ) ) {
			return false;
		}

		global $wp_filesystem;
		$wp_filesystem->delete( $path, $recursive );
		
		return true;
	}

	/**
	 * Enqueue admin scripts.
	 */
	public function enqueue_scripts() {
		if ( ! current_user_can( 'manage_options' ) || get_site_option( 'spinupwp_mail_notice_dismissed' ) ) {
			return;
		}

		wp_enqueue_script( 'spinupwp-dismiss', $this->plugin_url . '/assets/dismiss-notice.js', array( 'jquery' ), '1.0' );
	}

	/**
	 * Show a notice about configuring mail.
	 */
	public function show_mail_notice() {
		if ( ! current_user_can( 'manage_options' ) || get_site_option( 'spinupwp_mail_notice_dismissed' ) ) {
			return;
		}

		$msg   = __( 'Your site is ready to go! You will need to set up email if you wish to send outgoing emails from this site.', 'spinupwp' );
		$link  = sprintf( '<a href="%s">%s &raquo;</a>', 'https://spinupwp.com/doc/setting-up-transactional-email-wordpress/', __( 'More info', 'spinupwp' ) );
		$nonce = wp_create_nonce( 'dismiss-notice' );
		echo "<div class=\"spinupwp notice notice-success is-dismissible\" data-nonce=\"{$nonce}\"><p><strong>SpinupWP</strong> — {$msg} {$link}</p></div>";
	}

	/**
	 * Handle AJAX request to dismiss notice.
	 */	
	public function ajax_dismiss_notice() {
		if ( ! check_ajax_referer( 'dismiss-notice', 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( -1, 403 );
		}

		update_site_option( 'spinupwp_mail_notice_dismissed', true );
	}
}

(new SpinupWp)->init();
