<?php
/**
 * Fișierele ofertelor (PDF, imagini), într-un folder privat.
 *
 * Folderul are .htaccess „deny all” (Apache) și nume de fișiere aleatorii
 * (pentru servere fără .htaccess, ex. Nginx). Fișierele se descarcă numai prin
 * API, după verificarea parolei.
 *
 * @package Nunta2027
 */

defined( 'ABSPATH' ) || exit;

class Nn27_Files {

	const MAX_BYTES = 26214400; // 25 MB.
	const MIMES     = array(
		'pdf'  => 'application/pdf',
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'png'  => 'image/png',
		'webp' => 'image/webp',
		'gif'  => 'image/gif',
	);

	public static function dir() {
		$up = wp_upload_dir( null, false );
		return trailingslashit( $up['basedir'] ) . 'nn27-private';
	}

	public static function ensure_dir() {
		$dir = self::dir();
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return '';
		}
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" );
		}
		if ( ! file_exists( $dir . '/index.php' ) ) {
			file_put_contents( $dir . '/index.php', "<?php\n// Silence.\n" );
		}
		// phpcs:enable
		return $dir;
	}

	/**
	 * Salvează un fișier încărcat.
	 *
	 * @param array  $file  Elementul din $_FILES.
	 * @param string $venue ID-ul sălii.
	 * @return array|WP_Error Metadatele fișierului.
	 */
	public static function store( $file, $venue, $force_id = '', $mail = '' ) {
		global $wpdb;
		if ( empty( $file ) || ! isset( $file['tmp_name'] ) || UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return new WP_Error( 'nn27_upload', 'Fișierul nu a ajuns la server. Încercați din nou.', array( 'status' => 400 ) );
		}
		if ( (int) $file['size'] > self::MAX_BYTES ) {
			return new WP_Error( 'nn27_upload', 'Fișierul depășește 25 MB.', array( 'status' => 413 ) );
		}
		if ( ! preg_match( '/^[A-Za-z0-9_-]{1,40}$/', (string) $venue ) ) {
			return new WP_Error( 'nn27_upload', 'Sală invalidă.', array( 'status' => 400 ) );
		}
		$name  = sanitize_file_name( wp_basename( (string) $file['name'] ) );
		$check = wp_check_filetype_and_ext( $file['tmp_name'], $name, self::MIMES );
		if ( empty( $check['ext'] ) || empty( $check['type'] ) ) {
			return new WP_Error( 'nn27_upload', 'Se pot atașa doar PDF-uri și imagini (JPG, PNG, WEBP).', array( 'status' => 415 ) );
		}
		$dir = self::ensure_dir();
		if ( ! $dir ) {
			return new WP_Error( 'nn27_upload', 'Folderul pentru fișiere nu poate fi creat. Verificați permisiunile din wp-content/uploads.', array( 'status' => 500 ) );
		}
		$id   = $force_id ? $force_id : 'F' . strtolower( wp_generate_password( 20, false ) );
		$disk = $id . '-' . wp_generate_password( 12, false ) . '.' . $check['ext'];
		if ( ! @move_uploaded_file( $file['tmp_name'], $dir . '/' . $disk ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			// Permite și fișiere care nu vin prin HTTP (teste).
			if ( ! @rename( $file['tmp_name'], $dir . '/' . $disk ) ) { // phpcs:ignore
				return new WP_Error( 'nn27_upload', 'Fișierul nu a putut fi salvat pe server.', array( 'status' => 500 ) );
			}
		}
		$t    = Nn27_Installer::tables();
		$meta = array(
			'id'      => $id,
			'venue'   => $venue,
			'name'    => mb_substr( $name, 0, 200 ),
			'mime'    => $check['type'],
			'size'    => (int) $file['size'],
			'path'    => $disk,
			'created' => current_time( 'mysql', true ),
			'deleted' => 0,
			'mail'    => (string) $mail,
		);
		$wpdb->insert( $t['files'], $meta );
		return array(
			'id'    => $id,
			'name'  => $meta['name'],
			'type'  => $meta['mime'],
			'size'  => $meta['size'],
			'added' => time() * 1000,
		);
	}

	/**
	 * Un fișier identic (același conținut) deja salvat, oricare ar fi numele lui.
	 * Așa nu se dublează o ofertă primită pe email și pusă apoi și în Drive.
	 *
	 * @param string $data Conținutul.
	 * @return array|null Rândul existent.
	 */
	public static function find_same( $data ) {
		global $wpdb;
		$t    = Nn27_Installer::tables();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t['files']} WHERE size = %d AND deleted = 0", strlen( $data ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$hash = sha1( $data );
		foreach ( (array) $rows as $r ) {
			$path = self::dir() . '/' . $r['path'];
			if ( is_file( $path ) && sha1_file( $path ) === $hash ) {
				return $r;
			}
		}
		return null;
	}

	public static function get( $id ) {
		global $wpdb;
		$t = Nn27_Installer::tables();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t['files']} WHERE id = %s AND deleted = 0", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function delete( $id ) {
		global $wpdb;
		$row = self::get( $id );
		if ( ! $row ) {
			return false;
		}
		$t = Nn27_Installer::tables();
		$wpdb->update( $t['files'], array( 'deleted' => 1 ), array( 'id' => $id ) );
		$path = self::dir() . '/' . $row['path'];
		if ( is_file( $path ) ) {
			wp_delete_file( $path );
		}
		return true;
	}

	/**
	 * Trimite fișierul către browser și oprește execuția.
	 *
	 * @param array $row Rândul din tabel.
	 */
	public static function stream( $row ) {
		$path = self::dir() . '/' . $row['path'];
		if ( ! is_file( $path ) ) {
			status_header( 404 );
			exit;
		}
		nocache_headers();
		header( 'Content-Type: ' . $row['mime'] );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'Content-Disposition: inline; filename="' . rawurlencode( $row['name'] ) . '"; filename*=UTF-8\'\'' . rawurlencode( $row['name'] ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cache-Control: private, no-store' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}
}
