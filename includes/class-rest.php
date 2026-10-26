<?php
/**
 * API REST: /wp-json/atwn27/v1/...
 *
 * GET    state            toate datele + revizia curentă
 * GET    changes?since=N  ce s-a schimbat după revizia N (sincronizare între dispozitive)
 * POST   commit           salvează modificări; 409 la conflict
 * POST   files            încarcă oferta unei săli (multipart: file, venue)
 * GET    files/{id}       descarcă un fișier
 * DELETE files/{id}       șterge un fișier
 *
 * @package Nunta2027
 */

defined( 'ABSPATH' ) || exit;

class Atwn27_Rest {

	const NS = 'atwn27/v1';

	public static function register_routes() {
		$perm = array( 'Atwn27_Auth', 'check' );
		register_rest_route( self::NS, '/state', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'state' ), 'permission_callback' => $perm ) );
		register_rest_route(
			self::NS,
			'/changes',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'changes' ),
				'permission_callback' => $perm,
				'args'                => array( 'since' => array( 'type' => 'integer', 'required' => true, 'minimum' => 0 ) ),
			)
		);
		register_rest_route( self::NS, '/commit', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'commit' ), 'permission_callback' => $perm ) );
		register_rest_route( self::NS, '/files', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'upload' ), 'permission_callback' => $perm ) );
		register_rest_route(
			self::NS,
			'/files/(?P<id>F[a-z0-9]{10,40})',
			array(
				array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'download' ), 'permission_callback' => $perm ),
				array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'remove_file' ), 'permission_callback' => $perm ),
			)
		);
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'no_cache' ), 10, 3 );
	}

	/**
	 * Răspunsurile noastre nu se păstrează în niciun cache.
	 */
	public static function no_cache( $response, $server, $request ) {
		if ( 0 === strpos( $request->get_route(), '/' . self::NS ) && $response instanceof WP_REST_Response ) {
			$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
			$response->header( 'X-Robots-Tag', 'noindex' );
		}
		return $response;
	}

	public static function state() {
		return rest_ensure_response(
			array(
				'rev'     => Atwn27_Store::current_rev(),
				'records' => Atwn27_Store::all(),
				'log'     => Atwn27_Store::recent_log(),
				'server'  => ATWN27_VERSION,
			)
		);
	}

	public static function changes( WP_REST_Request $req ) {
		$since = (int) $req->get_param( 'since' );
		$rev   = Atwn27_Store::current_rev();
		return rest_ensure_response(
			array(
				'rev'     => $rev,
				'changes' => $rev > $since ? Atwn27_Store::changes( $since ) : array(),
				'log'     => $rev > $since ? Atwn27_Store::recent_log() : null,
				'server'  => ATWN27_VERSION,
			)
		);
	}

	public static function commit( WP_REST_Request $req ) {
		$body = $req->get_json_params();
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'atwn27_invalid', 'Cerere invalidă.', array( 'status' => 400 ) );
		}
		$res = Atwn27_Store::commit( $body['ops'] ?? array(), $body['log'] ?? array(), $body['who'] ?? '' );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$res['log'] = Atwn27_Store::recent_log();
		return rest_ensure_response( $res );
	}

	public static function upload( WP_REST_Request $req ) {
		$files = $req->get_file_params();
		$res   = Atwn27_Files::store( $files['file'] ?? null, (string) $req->get_param( 'venue' ) );
		return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
	}

	public static function download( WP_REST_Request $req ) {
		$row = Atwn27_Files::get( $req['id'] );
		if ( ! $row ) {
			return new WP_Error( 'atwn27_404', 'Fișierul nu mai există.', array( 'status' => 404 ) );
		}
		Atwn27_Files::stream( $row );
	}

	public static function remove_file( WP_REST_Request $req ) {
		return rest_ensure_response( array( 'deleted' => Atwn27_Files::delete( $req['id'] ) ) );
	}
}
