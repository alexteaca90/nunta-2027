<?php
/**
 * Cine are acces la date.
 *
 * Model de securitate:
 * - Pagina aplicației este protejată cu parola de pagină din WordPress.
 *   Când o introduceți, WordPress setează cookie-ul wp-postpass_* (hash-ul parolei).
 * - Fiecare cerere către API verifică pe server acel cookie față de parola
 *   paginii (post_password_required). Fără parola corectă: 401.
 * - Administratorii autentificați în WordPress au mereu acces.
 * - Dacă pagina NU are parolă, accesul anonim este refuzat (nu expunem datele din greșeală).
 * - Antetul X-ATWN27 este obligatoriu: un formular de pe alt site nu îl poate trimite,
 *   deci cererile cross-site (CSRF) sunt blocate.
 *
 * @package Nunta2027
 */

defined( 'ABSPATH' ) || exit;

class Atwn27_Auth {

	const PAGE_OPTION = 'atwn27_page_id';

	public static function page() {
		$id = (int) get_option( self::PAGE_OPTION );
		if ( ! $id ) {
			return null;
		}
		$post = get_post( $id );
		if ( ! $post || 'trash' === $post->post_status || ! has_shortcode( $post->post_content, 'nunta_2027' ) ) {
			return null;
		}
		return $post;
	}

	public static function page_has_password() {
		$p = self::page();
		return $p && '' !== $p->post_password;
	}

	public static function can_access() {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		$post = self::page();
		if ( ! $post || 'publish' !== $post->post_status || '' === $post->post_password ) {
			return false;
		}
		return ! post_password_required( $post );
	}

	/**
	 * Permission callback pentru toate rutele REST.
	 *
	 * @param WP_REST_Request $request Cererea.
	 * @return true|WP_Error
	 */
	public static function check( $request ) {
		if ( '1' !== (string) $request->get_header( 'x_atwn27' ) ) {
			return new WP_Error( 'atwn27_csrf', 'Cerere respinsă.', array( 'status' => 403 ) );
		}
		if ( ! self::can_access() ) {
			return new WP_Error( 'atwn27_auth', 'Introduceți din nou parola paginii.', array( 'status' => 401 ) );
		}
		return true;
	}
}
