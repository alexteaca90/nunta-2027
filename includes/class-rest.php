<?php
/**
 * API REST: /wp-json/nn27/v1/...
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

class Nn27_Rest {

	const NS = 'nn27/v1';

	public static function register_routes() {
		$perm = array( 'Nn27_Auth', 'check' );
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
		// Scriptul din Gmail (token secret).
		$tok = array( 'Nn27_Mail', 'check_token' );
		register_rest_route( self::NS, '/inbox', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'inbox' ), 'permission_callback' => $tok ) );
		register_rest_route( self::NS, '/inbox/known', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'known' ), 'permission_callback' => $tok ) );
		register_rest_route( self::NS, '/inbox/contacts', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'contacts' ), 'permission_callback' => $tok ) );
		// Platforma (parola paginii).
		register_rest_route( self::NS, '/mail', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'mail_list' ), 'permission_callback' => $perm ) );
		register_rest_route( self::NS, '/mail/(?P<id>[A-Za-z0-9_-]{1,64})', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'mail_update' ), 'permission_callback' => $perm ) );
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
				'rev'     => Nn27_Store::current_rev(),
				'records' => Nn27_Store::all(),
				'log'     => Nn27_Store::recent_log(),
				'server'  => NN27_VERSION,
			)
		);
	}

	public static function changes( WP_REST_Request $req ) {
		$since = (int) $req->get_param( 'since' );
		$rev   = Nn27_Store::current_rev();
		return rest_ensure_response(
			array(
				'rev'     => $rev,
				'changes' => $rev > $since ? Nn27_Store::changes( $since ) : array(),
				'log'     => $rev > $since ? Nn27_Store::recent_log() : null,
				'server'  => NN27_VERSION,
			)
		);
	}

	public static function commit( WP_REST_Request $req ) {
		$body = $req->get_json_params();
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'nn27_invalid', 'Cerere invalidă.', array( 'status' => 400 ) );
		}
		$res = Nn27_Store::commit( $body['ops'] ?? array(), $body['log'] ?? array(), $body['who'] ?? '' );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$res['log'] = Nn27_Store::recent_log();
		return rest_ensure_response( $res );
	}

	public static function inbox( WP_REST_Request $req ) {
		$b   = $req->get_json_params();
		$res = Nn27_Mail::ingest( (array) ( $b['message'] ?? array() ), (array) ( $b['attachments'] ?? array() ) );
		return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
	}

	public static function known( WP_REST_Request $req ) {
		$b = $req->get_json_params();
		return rest_ensure_response( array( 'known' => Nn27_Mail::known_ids( $b['ids'] ?? array() ) ) );
	}

	public static function contacts() {
		$venues = array();
		foreach ( Nn27_Store::all() as $r ) {
			if ( 'v' === $r['col'] && is_array( $r['data'] ) ) {
				$venues[] = array( 'id' => $r['id'], 'n' => (string) ( $r['data']['n'] ?? '' ), 'st' => (int) ( $r['data']['st'] ?? 0 ), 'o' => (int) ( $r['data']['o'] ?? 0 ) );
			}
		}
		usort( $venues, static function ( $a, $b ) { return $a['o'] <=> $b['o']; } );
		return rest_ensure_response( array( 'emails' => Nn27_Mail::known_emails(), 'venues' => $venues, 'label' => Nn27_Mail::LABEL, 'server' => NN27_VERSION ) );
	}

	public static function mail_list( WP_REST_Request $req ) {
		$since = (int) $req->get_param( 'since' );
		$rev   = Nn27_Mail::rev();
		return rest_ensure_response( array( 'rev' => $rev, 'mail' => $since && $since >= $rev ? null : Nn27_Mail::list_all() ) );
	}

	public static function mail_update( WP_REST_Request $req ) {
		$b = $req->get_json_params();
		$ok = Nn27_Mail::update( $req['id'], array_key_exists( 'venue', (array) $b ) ? (string) $b['venue'] : null, array_key_exists( 'hidden', (array) $b ) ? (bool) $b['hidden'] : null );
		return rest_ensure_response( array( 'ok' => $ok, 'rev' => Nn27_Mail::rev() ) );
	}

	public static function upload( WP_REST_Request $req ) {
		$files = $req->get_file_params();
		$res   = Nn27_Files::store( $files['file'] ?? null, (string) $req->get_param( 'venue' ) );
		return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
	}

	public static function download( WP_REST_Request $req ) {
		$row = Nn27_Files::get( $req['id'] );
		if ( ! $row ) {
			return new WP_Error( 'nn27_404', 'Fișierul nu mai există.', array( 'status' => 404 ) );
		}
		Nn27_Files::stream( $row );
	}

	public static function remove_file( WP_REST_Request $req ) {
		return rest_ensure_response( array( 'deleted' => Nn27_Files::delete( $req['id'] ) ) );
	}
}
