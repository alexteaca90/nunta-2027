<?php
/**
 * Stocarea datelor: înregistrări versionate, jurnal de modificări, import/export.
 *
 * Colecții: settings (o singură înregistrare „main”), cats, hh (familii și
 * persoane individuale), p (persoane), v (săli), budget, vendors, tasks.
 * Fiecare înregistrare are: ver (versiunea ei, pentru conflicte) și rev
 * (revizia globală, pentru sincronizarea între dispozitive).
 *
 * @package Nunta2027
 */

defined( 'ABSPATH' ) || exit;

class Nn27_Store {

	const COLS        = array( 'settings', 'cats', 'hh', 'p', 'v', 'budget', 'vendors', 'tasks' );
	const FORMAT      = 'nn27-state';
	const MAX_OPS     = 500;
	const MAX_RECORD  = 200000; // octeți JSON per înregistrare.

	/* ------------------------------------------------------------------ */
	/* Citire                                                              */
	/* ------------------------------------------------------------------ */

	public static function current_rev() {
		global $wpdb;
		$t = Nn27_Installer::tables();
		return (int) $wpdb->get_var( "SELECT MAX(id) FROM {$t['revs']}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function all() {
		global $wpdb;
		$t    = Nn27_Installer::tables();
		$rows = $wpdb->get_results( "SELECT col, rid, data, ver FROM {$t['records']} WHERE deleted = 0", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[] = self::row_out( $r );
		}
		return $out;
	}

	public static function changes( $since ) {
		global $wpdb;
		$t    = Nn27_Installer::tables();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT col, rid, data, ver, deleted FROM {$t['records']} WHERE rev > %d ORDER BY rev ASC", $since ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[] = self::row_out( $r );
		}
		return $out;
	}

	private static function row_out( $r ) {
		$deleted = ! empty( $r['deleted'] );
		return array(
			'col'  => $r['col'],
			'id'   => $r['rid'],
			'v'    => (int) $r['ver'],
			'data' => $deleted ? null : json_decode( (string) $r['data'], true ),
		);
	}

	public static function count_by_col() {
		global $wpdb;
		$t    = Nn27_Installer::tables();
		$rows = $wpdb->get_results( "SELECT col, COUNT(*) AS n FROM {$t['records']} WHERE deleted = 0 GROUP BY col", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out  = array_fill_keys( self::COLS, 0 );
		foreach ( (array) $rows as $r ) {
			$out[ $r['col'] ] = (int) $r['n'];
		}
		return $out;
	}

	public static function recent_log( $limit = 12 ) {
		global $wpdb;
		$t    = Nn27_Installer::tables();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT created, who, msg FROM {$t['log']} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[] = array(
				't'   => strtotime( $r['created'] . ' UTC' ) * 1000,
				'who' => $r['who'],
				'm'   => $r['msg'],
			);
		}
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Validare                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Curăță recursiv o valoare primită de la client.
	 *
	 * @param mixed $v     Valoarea.
	 * @param int   $depth Adâncimea curentă.
	 * @return mixed|WP_Error
	 */
	private static function clean( $v, $depth = 0 ) {
		if ( $depth > 6 ) {
			return new WP_Error( 'nn27_invalid', 'Structură prea adâncă.' );
		}
		if ( is_null( $v ) || is_bool( $v ) || is_int( $v ) ) {
			return $v;
		}
		if ( is_float( $v ) ) {
			return is_finite( $v ) ? $v : 0;
		}
		if ( is_string( $v ) ) {
			$v = wp_check_invalid_utf8( $v, true );
			return mb_substr( $v, 0, 10000 );
		}
		if ( is_array( $v ) ) {
			$out = array();
			foreach ( $v as $k => $item ) {
				if ( is_string( $k ) && ! preg_match( '/^[A-Za-z0-9_]{1,40}$/', $k ) ) {
					return new WP_Error( 'nn27_invalid', 'Câmp nepermis: ' . sanitize_text_field( $k ) );
				}
				$c = self::clean( $item, $depth + 1 );
				if ( is_wp_error( $c ) ) {
					return $c;
				}
				$out[ $k ] = $c;
			}
			return $out;
		}
		return new WP_Error( 'nn27_invalid', 'Tip de date nepermis.' );
	}

	/**
	 * Validează o înregistrare.
	 *
	 * @param string $col  Colecția.
	 * @param string $id   ID-ul.
	 * @param mixed  $data Datele.
	 * @return array|WP_Error
	 */
	public static function validate_record( $col, $id, $data ) {
		if ( ! in_array( $col, self::COLS, true ) ) {
			return new WP_Error( 'nn27_invalid', 'Colecție necunoscută.' );
		}
		if ( ! is_string( $id ) || ! preg_match( '/^[A-Za-z0-9_-]{1,40}$/', $id ) ) {
			return new WP_Error( 'nn27_invalid', 'ID invalid.' );
		}
		if ( 'settings' === $col && 'main' !== $id ) {
			return new WP_Error( 'nn27_invalid', 'Setările au un singur ID.' );
		}
		if ( ! is_array( $data ) || ( array() !== $data && array_keys( $data ) === range( 0, count( $data ) - 1 ) ) ) {
			return new WP_Error( 'nn27_invalid', 'Înregistrarea trebuie să fie un obiect.' );
		}
		$data = self::clean( $data );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		if ( 'settings' !== $col && ( ! isset( $data['id'] ) || $data['id'] !== $id ) ) {
			return new WP_Error( 'nn27_invalid', 'ID-ul din date nu corespunde.' );
		}
		$required = array(
			'cats' => array( 'n' ),
			'hh'   => array( 'c' ),
			'p'    => array( 'h', 'n' ),
			'v'    => array( 'n' ),
		);
		foreach ( isset( $required[ $col ] ) ? $required[ $col ] : array() as $f ) {
			if ( ! isset( $data[ $f ] ) || ! is_string( $data[ $f ] ) ) {
				return new WP_Error( 'nn27_invalid', "Lipsește câmpul „{$f}” ({$col}/{$id})." );
			}
		}
		if ( strlen( wp_json_encode( $data ) ) > self::MAX_RECORD ) {
			return new WP_Error( 'nn27_invalid', 'Înregistrare prea mare.' );
		}
		return $data;
	}

	/* ------------------------------------------------------------------ */
	/* Scriere cu detectarea conflictelor                                  */
	/* ------------------------------------------------------------------ */

	private static function is_sqlite() {
		return defined( 'DB_ENGINE' ) && 'sqlite' === DB_ENGINE;
	}

	/**
	 * Aplică un set de operații, totul sau nimic.
	 *
	 * Fiecare operație: { col, id, base, data|null }. „base” este versiunea pe care
	 * clientul a modificat-o. Dacă pe server versiunea e alta, nu scriem nimic și
	 * întoarcem înregistrările actuale, ca clientul să le combine cu modificările lui.
	 *
	 * @param array  $ops  Operațiile.
	 * @param array  $logs Mesaje pentru jurnal.
	 * @param string $who  Cine a făcut modificarea.
	 * @return array|WP_Error
	 */
	public static function commit( $ops, $logs, $who ) {
		global $wpdb;
		$t   = Nn27_Installer::tables();
		$who = mb_substr( sanitize_text_field( (string) $who ), 0, 40 );

		if ( ! is_array( $ops ) || ! $ops ) {
			return new WP_Error( 'nn27_invalid', 'Nicio modificare.', array( 'status' => 400 ) );
		}
		if ( count( $ops ) > self::MAX_OPS ) {
			return new WP_Error( 'nn27_invalid', 'Prea multe modificări deodată.', array( 'status' => 400 ) );
		}

		$clean = array();
		$seen  = array();
		foreach ( $ops as $op ) {
			$col  = isset( $op['col'] ) ? (string) $op['col'] : '';
			$id   = isset( $op['id'] ) ? (string) $op['id'] : '';
			$base = isset( $op['base'] ) ? (int) $op['base'] : 0;
			$data = array_key_exists( 'data', (array) $op ) ? $op['data'] : null;
			if ( null !== $data ) {
				$data = self::validate_record( $col, $id, $data );
				if ( is_wp_error( $data ) ) {
					$data->add_data( array( 'status' => 422 ) );
					return $data;
				}
			} elseif ( ! in_array( $col, self::COLS, true ) || ! preg_match( '/^[A-Za-z0-9_-]{1,40}$/', $id ) || 'settings' === $col ) {
				return new WP_Error( 'nn27_invalid', 'Ștergere invalidă.', array( 'status' => 422 ) );
			}
			$key = $col . ':' . $id;
			if ( isset( $seen[ $key ] ) ) {
				return new WP_Error( 'nn27_invalid', 'Aceeași înregistrare apare de două ori.', array( 'status' => 422 ) );
			}
			$seen[ $key ] = true;
			$clean[]      = compact( 'col', 'id', 'base', 'data' );
		}

		$wpdb->query( 'START TRANSACTION' );
		$lock      = self::is_sqlite() ? '' : ' FOR UPDATE';
		$conflicts = array();
		$current   = array();
		foreach ( $clean as $op ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT col, rid, data, ver, deleted FROM {$t['records']} WHERE col = %s AND rid = %s{$lock}", $op['col'], $op['id'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$current[ $op['col'] . ':' . $op['id'] ] = $row;
			$server_ver = $row ? (int) $row['ver'] : 0;
			$is_dead    = ! $row || ! empty( $row['deleted'] );
			// Re-crearea unei înregistrări șterse (ex. „Anulează”) e permisă.
			if ( $is_dead && null !== $op['data'] ) {
				continue;
			}
			if ( $is_dead && null === $op['data'] ) {
				continue; // Deja ștearsă: nimic de făcut.
			}
			if ( $server_ver !== $op['base'] ) {
				$conflicts[] = self::row_out( $row );
			}
		}
		if ( $conflicts ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'nn27_conflict', 'Datele au fost modificate între timp pe alt dispozitiv.', array( 'status' => 409, 'records' => $conflicts ) );
		}

		// Integritate: fiecare persoană trebuie să aparțină unei familii existente.
		$hh_after = self::live_ids( 'hh' );
		foreach ( $clean as $op ) {
			if ( 'hh' === $op['col'] ) {
				if ( null === $op['data'] ) {
					unset( $hh_after[ $op['id'] ] );
				} else {
					$hh_after[ $op['id'] ] = true;
				}
			}
		}
		foreach ( $clean as $op ) {
			if ( 'p' === $op['col'] && null !== $op['data'] && ! isset( $hh_after[ $op['data']['h'] ] ) ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'nn27_invalid', 'Persoana „' . $op['data']['n'] . '” nu aparține unei familii existente.', array( 'status' => 422 ) );
			}
		}

		$now = current_time( 'mysql', true );
		$wpdb->insert( $t['revs'], array( 'created' => $now, 'who' => $who ) );
		$rev = (int) $wpdb->insert_id;
		if ( ! $rev ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'nn27_db', 'Nu am putut salva. Încercați din nou.', array( 'status' => 500 ) );
		}

		$applied = array();
		foreach ( $clean as $op ) {
			$res = self::write( $op['col'], $op['id'], $op['data'], $current[ $op['col'] . ':' . $op['id'] ], $rev, $now, $who );
			if ( false === $res ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'nn27_db', 'Nu am putut salva. Încercați din nou.', array( 'status' => 500 ) );
			}
			if ( null !== $res ) {
				$applied[] = $res;
			}
		}

		// Persoane rămase fără familie (familie ștearsă): le ștergem și pe ele.
		$deleted_hh = array();
		foreach ( $clean as $op ) {
			if ( 'hh' === $op['col'] && null === $op['data'] ) {
				$deleted_hh[ $op['id'] ] = true;
			}
		}
		if ( $deleted_hh ) {
			$people = $wpdb->get_results( "SELECT rid, data, ver, deleted FROM {$t['records']} WHERE col = 'p' AND deleted = 0", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( (array) $people as $row ) {
				$d = json_decode( $row['data'], true );
				if ( isset( $d['h'] ) && isset( $deleted_hh[ $d['h'] ] ) ) {
					$row['col'] = 'p';
					$res        = self::write( 'p', $row['rid'], null, $row, $rev, $now, $who );
					if ( $res ) {
						$applied[] = $res;
					}
				}
			}
		}

		foreach ( array_slice( (array) $logs, 0, 20 ) as $m ) {
			$m = mb_substr( sanitize_text_field( (string) $m ), 0, 255 );
			if ( '' !== $m ) {
				$wpdb->insert( $t['log'], array( 'created' => $now, 'who' => $who, 'msg' => $m ) );
			}
		}

		$wpdb->query( 'COMMIT' );
		return array(
			'rev'     => $rev,
			'applied' => $applied,
		);
	}

	private static function live_ids( $col ) {
		global $wpdb;
		$t   = Nn27_Installer::tables();
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT rid FROM {$t['records']} WHERE col = %s AND deleted = 0", $col ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array_fill_keys( (array) $ids, true );
	}

	/**
	 * Scrie o înregistrare (sau o marchează ștearsă).
	 *
	 * @return array|null|false Rezultatul aplicat, null dacă nu a fost nimic de făcut, false la eroare.
	 */
	private static function write( $col, $id, $data, $row, $rev, $now, $who ) {
		global $wpdb;
		$t = Nn27_Installer::tables();
		if ( null === $data ) {
			if ( ! $row || ! empty( $row['deleted'] ) ) {
				return null;
			}
			$ver = (int) $row['ver'] + 1;
			$ok  = $wpdb->update(
				$t['records'],
				array( 'data' => null, 'deleted' => 1, 'ver' => $ver, 'rev' => $rev, 'updated_at' => $now, 'updated_by' => $who ),
				array( 'col' => $col, 'rid' => $id )
			);
			return false === $ok ? false : array( 'col' => $col, 'id' => $id, 'v' => $ver, 'deleted' => true );
		}
		$json = wp_json_encode( $data, JSON_UNESCAPED_UNICODE );
		if ( $row ) {
			$ver = (int) $row['ver'] + 1;
			$ok  = $wpdb->update(
				$t['records'],
				array( 'data' => $json, 'deleted' => 0, 'ver' => $ver, 'rev' => $rev, 'updated_at' => $now, 'updated_by' => $who ),
				array( 'col' => $col, 'rid' => $id )
			);
		} else {
			$ver = 1;
			$ok  = $wpdb->insert(
				$t['records'],
				array( 'col' => $col, 'rid' => $id, 'data' => $json, 'deleted' => 0, 'ver' => $ver, 'rev' => $rev, 'updated_at' => $now, 'updated_by' => $who )
			);
		}
		return false === $ok ? false : array( 'col' => $col, 'id' => $id, 'v' => $ver );
	}

	/* ------------------------------------------------------------------ */
	/* Export / import                                                     */
	/* ------------------------------------------------------------------ */

	public static function export_state() {
		$state = array( 'settings' => new stdClass() );
		foreach ( self::COLS as $c ) {
			if ( 'settings' !== $c ) {
				$state[ $c ] = array();
			}
		}
		foreach ( self::all() as $r ) {
			if ( 'settings' === $r['col'] ) {
				$state['settings'] = $r['data'];
			} else {
				$state[ $r['col'] ][] = $r['data'];
			}
		}
		foreach ( $state as $c => &$list ) {
			if ( is_array( $list ) && 'settings' !== $c ) {
				usort(
					$list,
					static function ( $a, $b ) {
						return ( isset( $a['o'] ) ? $a['o'] : 0 ) <=> ( isset( $b['o'] ) ? $b['o'] : 0 );
					}
				);
			}
		}
		unset( $list );
		return array(
			'format'   => self::FORMAT,
			'version'  => 1,
			'exported' => gmdate( 'c' ),
			'plugin'   => NN27_VERSION,
			'state'    => $state,
		);
	}

	public static function backup_to_file( $label ) {
		if ( ! self::current_rev() ) {
			return '';
		}
		$dir = Nn27_Files::ensure_dir();
		if ( ! $dir ) {
			return '';
		}
		$name = 'backup-' . gmdate( 'Ymd-His' ) . '-' . sanitize_file_name( $label ) . '-' . wp_generate_password( 8, false ) . '.json';
		file_put_contents( $dir . '/' . $name, wp_json_encode( self::export_state(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		return $name;
	}

	/**
	 * Verifică un fișier de import fără să scrie nimic (dry-run).
	 *
	 * @param array $doc Documentul decodat.
	 * @return array { ok:bool, errors:[], warnings:[], counts:[] }
	 */
	public static function validate_import( $doc ) {
		$errors   = array();
		$warnings = array();
		$counts   = array();
		if ( ! is_array( $doc ) || ! in_array( $doc['format'] ?? '', array( self::FORMAT, 'atwn27-state' ), true ) || ! isset( $doc['state'] ) || ! is_array( $doc['state'] ) ) {
			return array( 'ok' => false, 'errors' => array( 'Fișierul nu este un export al acestei platforme (format ' . self::FORMAT . ').' ), 'warnings' => array(), 'counts' => array() );
		}
		$s = $doc['state'];
		foreach ( self::COLS as $c ) {
			if ( 'settings' === $c ) {
				if ( ! isset( $s['settings'] ) || ! is_array( $s['settings'] ) ) {
					$errors[] = 'Lipsesc setările.';
				}
				continue;
			}
			$list = isset( $s[ $c ] ) && is_array( $s[ $c ] ) ? $s[ $c ] : array();
			$ids  = array();
			foreach ( $list as $i => $item ) {
				$id = is_array( $item ) && isset( $item['id'] ) ? (string) $item['id'] : '';
				$ok = self::validate_record( $c, $id, $item );
				if ( is_wp_error( $ok ) ) {
					$errors[] = "{$c} #" . ( $i + 1 ) . ': ' . $ok->get_error_message();
					continue;
				}
				if ( isset( $ids[ $id ] ) ) {
					$errors[] = "{$c}: ID duplicat {$id}.";
				}
				$ids[ $id ] = true;
			}
			$counts[ $c ] = count( $list );
		}
		$hh   = array();
		$cats = array();
		foreach ( (array) ( $s['hh'] ?? array() ) as $h ) {
			$hh[ $h['id'] ?? '' ] = 0;
		}
		foreach ( (array) ( $s['cats'] ?? array() ) as $c ) {
			$cats[ $c['id'] ?? '' ] = true;
		}
		foreach ( (array) ( $s['p'] ?? array() ) as $p ) {
			$h = $p['h'] ?? '';
			if ( ! isset( $hh[ $h ] ) ) {
				$errors[] = 'Persoana „' . ( $p['n'] ?? '?' ) . "” trimite la familia inexistentă {$h}.";
			} else {
				++$hh[ $h ];
			}
		}
		$empty = array_keys( array_filter( $hh, static function ( $n ) { return 0 === $n; } ) );
		if ( $empty ) {
			$warnings[] = count( $empty ) . ' familii fără nicio persoană: ' . implode( ', ', array_slice( $empty, 0, 10 ) );
		}
		foreach ( (array) ( $s['hh'] ?? array() ) as $h ) {
			if ( ! isset( $cats[ $h['c'] ?? '' ] ) ) {
				$warnings[] = 'Familia ' . ( $h['id'] ?? '?' ) . ' are o categorie inexistentă.';
			}
		}
		return array( 'ok' => ! $errors, 'errors' => $errors, 'warnings' => $warnings, 'counts' => $counts );
	}

	/**
	 * Înlocuiește toate datele cu cele din fișier. Face backup înainte.
	 *
	 * @param array  $doc Documentul validat.
	 * @param string $who Cine importă.
	 * @return array|WP_Error
	 */
	/* ------------------------------------------------------------------ */
	/* Săli din cercetare: înlocuirea listei de săli                       */
	/* ------------------------------------------------------------------ */

	/** Lista de săli livrată cu pluginul (includes/data/sali-cercetare-2027.json). */
	public static function venue_research() {
		$path = NN27_DIR . 'includes/data/sali-cercetare-2027.json';
		$doc  = is_readable( $path ) ? json_decode( (string) file_get_contents( $path ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return ( is_array( $doc ) && isset( $doc['venues'] ) && is_array( $doc['venues'] ) ) ? $doc : null;
	}

	/** Numele alternative din cercetare pentru o sală (după nume), ca scriptul Drive să recunoască folderele. */
	public static function venue_aliases( $name ) {
		static $map = null;
		if ( null === $map ) {
			$map = array();
			$doc = self::venue_research();
			foreach ( $doc ? $doc['venues'] : array() as $nv ) {
				$all = array_values( array_filter( array_merge( array( (string) $nv['n'] ), (array) ( $nv['aliases'] ?? array() ) ) ) );
				foreach ( $all as $x ) {
					$map[ self::vkey( $x ) ] = $all;
				}
			}
		}
		return $map[ self::vkey( $name ) ] ?? array();
	}

	private static function vkey( $s ) {
		$s = remove_accents( mb_strtolower( (string) $s ) );
		return trim( preg_replace( '/[^a-z0-9]+/', ' ', $s ) );
	}

	/**
	 * Ce s-ar întâmpla la înlocuire, fără să scrie nimic.
	 *
	 * @return array|null { match: [newKey => oldId], keep: [[old, new]], add: [names], remove: [[id, name, mails]] }
	 */
	public static function plan_venue_replace() {
		global $wpdb;
		$doc = self::venue_research();
		if ( ! $doc ) {
			return null;
		}
		$old = array();
		foreach ( self::all() as $r ) {
			if ( 'v' === $r['col'] && is_array( $r['data'] ) ) {
				$old[ $r['id'] ] = $r['data'];
			}
		}
		$match = array();
		$used  = array();
		$keep  = array();
		$add   = array();
		foreach ( $doc['venues'] as $nv ) {
			$names = array_map( array( __CLASS__, 'vkey' ), array_merge( array( $nv['n'] ), (array) ( $nv['aliases'] ?? array() ) ) );
			$found = '';
			foreach ( $old as $id => $ov ) {
				if ( ! isset( $used[ $id ] ) && in_array( self::vkey( $ov['n'] ?? '' ), $names, true ) ) {
					$found = $id;
					break;
				}
			}
			if ( $found ) {
				$used[ $found ]       = true;
				$match[ $nv['key'] ] = $found;
				$keep[]              = array( $old[ $found ]['n'], $nv['n'] );
			} else {
				$add[] = $nv['n'];
			}
		}
		$t      = Nn27_Installer::tables();
		$remove = array();
		foreach ( $old as $id => $ov ) {
			if ( isset( $used[ $id ] ) ) {
				continue;
			}
			$mails    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t['mail']} WHERE venue = %s", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$files    = isset( $ov['of']['files'] ) && is_array( $ov['of']['files'] ) ? count( $ov['of']['files'] ) : 0;
			$remove[] = array( $id, $ov['n'] ?? $id, $mails, $files, (int) ( $ov['st'] ?? 0 ) );
		}
		// Săli regăsite ale căror date din cercetare s-au schimbat (de ex. un email nou).
		$update = array();
		foreach ( $doc['venues'] as $nv ) {
			$id = $match[ $nv['key'] ] ?? '';
			if ( ! $id ) {
				continue;
			}
			$cur = $old[ $id ];
			$new = self::research_record( $nv, $id, $cur );
			unset( $cur['o'], $new['o'] );
			ksort( $cur );
			ksort( $new );
			if ( wp_json_encode( $cur ) !== wp_json_encode( $new ) ) {
				$update[] = $nv['n'];
			}
		}
		return array( 'match' => $match, 'keep' => $keep, 'add' => $add, 'remove' => $remove, 'update' => $update, 'researched' => $doc['researched'] ?? '' );
	}

	/** Construiește înregistrarea unei săli: datele din cercetare peste ce ați completat voi. */
	private static function research_record( $nv, $id, $base ) {
		$keepUser = array( 'st', 'sc', 'nt', 'av', 'ppp', 'cp', 'cmp', 'of', 'req', 'reqn' );
		$rec      = array( 'id' => $id, 'st' => 0, 'sc' => array( 0, 0, 0, 0 ), 'nt' => '', 'av' => '', 'ppp' => 0, 'cp' => 0, 'cmp' => false );
		foreach ( $keepUser as $k ) {
			if ( is_array( $base ) && array_key_exists( $k, $base ) ) {
				$rec[ $k ] = $base[ $k ];
			}
		}
		$emails = array();
		foreach ( array( $base['em'] ?? '', $nv['em'] ?? '' ) as $raw ) {
			preg_match_all( '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', (string) $raw, $m );
			foreach ( $m[0] as $e ) {
				$emails[ strtolower( $e ) ] = true;
			}
		}
		foreach ( (array) ( $nv['emDrop'] ?? array() ) as $e ) { // adrese vechi, greșite
			unset( $emails[ strtolower( (string) $e ) ] );
		}
		$rec['n']    = (string) $nv['n'];
		$rec['cap']  = (int) ( $nv['cap'] ?? 0 );
		$rec['min']  = (int) ( $nv['min'] ?? 0 ) ? (int) $nv['min'] : (int) ( $base['min'] ?? 0 );
		$rec['rt']   = (float) ( $nv['rt'] ?? 0 );
		$rec['rc']   = (int) ( $nv['rc'] ?? 0 );
		$rec['cond'] = (string) ( $nv['tip'] ?? '' );
		$rec['url']  = (string) ( $nv['web'] ?? '' );
		$rec['em']   = implode( ', ', array_keys( $emails ) );
		foreach ( array( 'zone', 'addr', 'maps', 'km', 'drive', 'tel', 'web', 'ph', 'halls', 'rv', 'pub', 'tip', 'src' ) as $k ) {
			$rec[ $k ] = $nv[ $k ] ?? '';
		}
		$rec['res'] = 1;
		if ( ! empty( $nv['o27'] ) && is_array( $nv['o27'] ) ) {
			$rec = self::apply_offer( $rec, $nv['o27'] );
		}
		return $rec;
	}

	/**
	 * Oferta 2027 analizată: completează prețul, disponibilitatea și fișa ofertei.
	 * Nu suprascrie ce ați scris voi; câmpurile completate automat (din emailuri sau
	 * dintr-o analiză anterioară) se actualizează. Blocul „ANALIZĂ OFERTĂ” din
	 * „Ce include” se înlocuiește, restul textului rămâne.
	 */
	private static function apply_offer( $rec, $o ) {
		$of   = ( isset( $rec['of'] ) && is_array( $rec['of'] ) ) ? $rec['of'] : array( 'date' => '', 'valid' => '', 'drinks' => 0, 'deposit' => '', 'extra' => 0, 'inc' => '', 'files' => array() );
		$auto = isset( $of['auto'] ) && is_array( $of['auto'] ) ? $of['auto'] : array();
		$prev = isset( $of['anv'] ) && is_array( $of['anv'] ) ? $of['anv'] : array(); // valorile puse de analiză data trecută
		$anv  = array();
		// Un câmp se poate completa dacă e gol, dacă a fost completat automat din emailuri
		// sau dacă are încă valoarea pusă de analiza anterioară (nu l-ați schimbat voi).
		$free = static function ( $k, $cur ) use ( $auto, $prev ) {
			return empty( $cur ) || in_array( $k, $auto, true ) || ( array_key_exists( $k, $prev ) && (string) $prev[ $k ] === (string) $cur );
		};
		foreach ( array( 'ppp', 'cp' ) as $k ) {
			if ( ! empty( $o[ $k ] ) && $free( $k, $rec[ $k ] ?? 0 ) ) {
				$rec[ $k ] = (int) $o[ $k ];
				$anv[ $k ] = $rec[ $k ];
			}
		}
		foreach ( array( 'extra', 'drinks' ) as $k ) {
			if ( ! empty( $o[ $k ] ) && $free( $k, $of[ $k ] ?? 0 ) ) {
				$of[ $k ]  = (int) $o[ $k ];
				$anv[ $k ] = $of[ $k ];
			}
		}
		foreach ( array( 'deposit', 'valid', 'date' ) as $k ) {
			if ( '' !== (string) ( $o[ $k ] ?? '' ) && $free( $k, trim( (string) ( $of[ $k ] ?? '' ) ) ) ) {
				$of[ $k ]  = (string) $o[ $k ];
				$anv[ $k ] = $of[ $k ];
			}
		}
		if ( ! empty( $o['av'] ) ) {
			if ( $free( 'av', trim( (string) ( $rec['av'] ?? '' ) ) ) ) {
				$rec['av'] = (string) $o['av'];
				$anv['av'] = $rec['av'];
			} elseif ( false === strpos( (string) $rec['av'], (string) $o['av'] ) ) {
				$mine      = trim( (string) preg_replace( '/\s*·\s*Din ofertă: .*$/us', '', (string) $rec['av'] ) );
				$rec['av'] = $mine . ' · Din ofertă: ' . (string) $o['av'];
			}
		}
		if ( ! empty( $o['inc'] ) ) {
			// Textul vostru rămâne; doar blocul analizei se înlocuiește.
			$mine      = trim( (string) preg_replace( '/ANALIZĂ OFERTĂ 2027.*$/us', '', (string) ( $of['inc'] ?? '' ) ) );
			$of['inc'] = ( '' !== $mine ? $mine . "\n\n" : '' ) . (string) $o['inc'];
		}
		$of['anv'] = $anv + array_diff_key( $prev, $anv );
		$rec['of'] = $of;
		if ( (int) $rec['st'] < 2 && ! empty( $o['ppp'] ) ) {
			$rec['st'] = 2; // Ofertă primită.
		}
		return $rec;
	}

	/**
	 * Înlocuiește toate sălile cu lista din cercetare. Sălile care se regăsesc își păstrează
	 * ID-ul, etapa, notele, prețul, oferta atașată și emailurile; celelalte sunt șterse.
	 * Emailurile primite de la sălile șterse devin nealocate. Backup înainte.
	 */
	public static function replace_venues( $who ) {
		global $wpdb;
		$doc  = self::venue_research();
		$plan = self::plan_venue_replace();
		if ( ! $doc || ! $plan ) {
			return new WP_Error( 'nn27_invalid', 'Lista de săli din cercetare lipsește din plugin.' );
		}
		$backup = self::backup_to_file( 'inainte-de-sali-noi' );
		$t      = Nn27_Installer::tables();
		$now    = current_time( 'mysql', true );
		$cur    = array();
		foreach ( $wpdb->get_results( "SELECT rid, data, ver, deleted FROM {$t['records']} WHERE col = 'v'", ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$cur[ $r['rid'] ] = $r;
		}
		$wpdb->query( 'START TRANSACTION' );
		$wpdb->insert( $t['revs'], array( 'created' => $now, 'who' => $who ) );
		$rev = (int) $wpdb->insert_id;
		$n   = 0;
		foreach ( $doc['venues'] as $i => $nv ) {
			$id   = $plan['match'][ $nv['key'] ] ?? ( 'VR' . preg_replace( '/[^a-z0-9]/', '', (string) $nv['key'] ) );
			$row  = $cur[ $id ] ?? null;
			$base = ( $row && empty( $row['deleted'] ) ) ? json_decode( (string) $row['data'], true ) : array();
			$rec  = self::research_record( $nv, $id, is_array( $base ) ? $base : array() );
			$rec['o'] = $i;
			$row  = $row ? array( 'col' => 'v', 'rid' => $id, 'ver' => $row['ver'], 'deleted' => $row['deleted'] ) : null;
			if ( false === self::write( 'v', $id, $rec, $row, $rev, $now, $who ) ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'nn27_db', 'Înlocuirea a eșuat; sălile au rămas neschimbate.' );
			}
			++$n;
		}
		$removed = array();
		foreach ( $plan['remove'] as $r ) {
			$row = $cur[ $r[0] ] ?? null;
			if ( $row ) {
				self::write( 'v', $r[0], null, array( 'col' => 'v', 'rid' => $r[0], 'ver' => $row['ver'], 'deleted' => $row['deleted'] ), $rev, $now, $who );
			}
			$removed[] = $r[0];
		}
		// Setarea „sala din calcule” nu trebuie să trimită la o sală ștearsă.
		$srow = $wpdb->get_row( "SELECT data, ver, deleted FROM {$t['records']} WHERE col = 'settings' AND rid = 'main'", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $srow ) {
			$sd = json_decode( (string) $srow['data'], true );
			if ( is_array( $sd ) && ! empty( $sd['refVenue'] ) && in_array( $sd['refVenue'], $removed, true ) ) {
				$sd['refVenue'] = null;
				self::write( 'settings', 'main', $sd, array( 'col' => 'settings', 'rid' => 'main', 'ver' => $srow['ver'], 'deleted' => $srow['deleted'] ), $rev, $now, $who );
			}
		}
		$wpdb->insert( $t['log'], array( 'created' => $now, 'who' => $who, 'msg' => sprintf( 'Săli înlocuite cu lista din cercetare: %d săli, %d păstrate, %d șterse', $n, count( $plan['keep'] ), count( $removed ) ) ) );
		$wpdb->query( 'COMMIT' );
		if ( $removed ) {
			$in = implode( ',', array_fill( 0, count( $removed ), '%s' ) );
			$wpdb->query( $wpdb->prepare( "UPDATE {$t['mail']} SET venue = '' WHERE venue IN ($in)", $removed ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$wpdb->query( $wpdb->prepare( "UPDATE {$t['files']} SET venue = 'mail' WHERE mail <> '' AND venue IN ($in)", $removed ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			update_option( 'nn27_mail_rev', (int) get_option( 'nn27_mail_rev', 0 ) + 1, false );
		}
		return array( 'venues' => $n, 'kept' => count( $plan['keep'] ), 'removed' => count( $removed ), 'backup' => $backup );
	}

	public static function import_state( $doc, $who ) {
		global $wpdb;
		$check = self::validate_import( $doc );
		if ( ! $check['ok'] ) {
			return new WP_Error( 'nn27_invalid', implode( ' ', $check['errors'] ) );
		}
		$backup = self::backup_to_file( 'inainte-de-import' );
		$t      = Nn27_Installer::tables();
		$now    = current_time( 'mysql', true );
		$wpdb->query( 'START TRANSACTION' );
		$wpdb->insert( $t['revs'], array( 'created' => $now, 'who' => $who ) );
		$rev = (int) $wpdb->insert_id;
		// Ștergerile sunt păstrate ca „tombstones”, ca dispozitivele deschise să afle de ele.
		$wpdb->query( $wpdb->prepare( "UPDATE {$t['records']} SET data = NULL, deleted = 1, ver = ver + 1, rev = %d, updated_at = %s WHERE deleted = 0", $rev, $now ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$s = $doc['state'];
		$n = 0;
		foreach ( self::COLS as $c ) {
			$items = 'settings' === $c ? array( $s['settings'] ) : ( $s[ $c ] ?? array() );
			foreach ( $items as $i => $item ) {
				$id   = 'settings' === $c ? 'main' : (string) $item['id'];
				$data = self::validate_record( $c, $id, $item );
				if ( 'settings' !== $c && ! isset( $data['o'] ) ) {
					$data['o'] = $i;
				}
				$row = $wpdb->get_row( $wpdb->prepare( "SELECT col, rid, ver, deleted FROM {$t['records']} WHERE col = %s AND rid = %s", $c, $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				if ( false === self::write( $c, $id, $data, $row, $rev, $now, $who ) ) {
					$wpdb->query( 'ROLLBACK' );
					return new WP_Error( 'nn27_db', 'Importul a eșuat; datele existente au rămas neschimbate.' );
				}
				++$n;
			}
		}
		$wpdb->insert( $t['log'], array( 'created' => $now, 'who' => $who, 'msg' => "Import: {$n} înregistrări" ) );
		$wpdb->query( 'COMMIT' );
		return array( 'records' => $n, 'backup' => $backup, 'counts' => $check['counts'] );
	}
}
