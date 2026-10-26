<?php
/**
 * Pagina aplicației: fullscreen, fără nimic din tema site-ului.
 *
 * Orice pagină care conține shortcode-ul [nunta_2027] este afișată cu șablonul
 * propriu al pluginului (templates/app.php), sau cu ecranul de parolă
 * (templates/password.php) cât timp parola paginii nu a fost introdusă.
 *
 * @package Nunta2027
 */

defined( 'ABSPATH' ) || exit;

class Nn27_Frontend {

	public static function init() {
		add_shortcode( 'nunta_2027', array( __CLASS__, 'shortcode' ) );
		add_filter( 'template_include', array( __CLASS__, 'template' ), 999 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
	}

	public static function shortcode() {
		return '<p>Platforma „Nunta noastră 2027” se deschide pe toată pagina.</p>';
	}

	public static function is_app_request() {
		if ( ! is_singular() ) {
			return false;
		}
		$post = get_queried_object();
		return $post instanceof WP_Post && has_shortcode( $post->post_content, 'nunta_2027' );
	}

	public static function robots( $robots ) {
		if ( self::is_app_request() ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}
		return $robots;
	}

	public static function template( $template ) {
		if ( ! self::is_app_request() ) {
			return $template;
		}
		$post = get_queried_object();
		if ( (int) get_option( Nn27_Auth::PAGE_OPTION ) !== (int) $post->ID ) {
			update_option( Nn27_Auth::PAGE_OPTION, (int) $post->ID, false );
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // Pluginurile de cache nu salvează pagina.
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'Referrer-Policy: same-origin' );
		header( 'X-Frame-Options: SAMEORIGIN' );

		if ( post_password_required( $post ) || ( '' === $post->post_password && ! current_user_can( 'manage_options' ) ) ) {
			return NN27_DIR . 'templates/password.php';
		}
		return NN27_DIR . 'templates/app.php';
	}

	public static function asset( $file ) {
		$path = NN27_DIR . 'assets/' . $file;
		$ver  = NN27_VERSION . '-' . ( file_exists( $path ) ? filemtime( $path ) : '0' );
		return esc_url( NN27_URL . 'assets/' . $file . '?ver=' . $ver );
	}

	public static function config() {
		return array(
			'rest'    => esc_url_raw( rest_url( Nn27_Rest::NS . '/' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'version' => NN27_VERSION,
			'admin'   => current_user_can( 'manage_options' ) ? esc_url_raw( admin_url( 'admin.php?page=nn27' ) ) : '',
		);
	}
}
