<?php
/**
 * Emailurile cu sălile: primire din Gmail (Google Apps Script), potrivire cu
 * sala, citirea automată a ofertei din text și din PDF.
 *
 * Fluxul: un script care rulează în contul Gmail al proprietarului trimite aici
 * doar mesajele schimbate cu sălile (după adresele cunoscute sau eticheta
 * „Nunta 2027”). Endpointul /inbox acceptă numai cereri cu tokenul secret
 * generat în panoul pluginului. Platforma citește apoi emailurile prin /mail,
 * cu aceeași protecție (parola paginii) ca restul datelor.
 *
 * @package Nunta2027
 */

defined( 'ABSPATH' ) || exit;

class Nn27_Mail {

	const TOKEN_OPTION = 'nn27_inbox_token';
	const LABEL        = 'Nunta 2027';

	/* ------------------------------------------------------------------ */
	/* Token                                                               */
	/* ------------------------------------------------------------------ */

	public static function token() {
		$t = get_option( self::TOKEN_OPTION );
		if ( ! $t ) {
			$t = wp_generate_password( 40, false );
			update_option( self::TOKEN_OPTION, $t, false );
		}
		return $t;
	}

	public static function regenerate_token() {
		delete_option( self::TOKEN_OPTION );
		return self::token();
	}

	/**
	 * Permission callback pentru rutele apelate de scriptul din Gmail.
	 *
	 * @param WP_REST_Request $request Cererea.
	 * @return true|WP_Error
	 */
	public static function check_token( $request ) {
		$sent = (string) $request->get_header( 'x_nn27_token' );
		if ( '' === $sent || ! hash_equals( self::token(), $sent ) ) {
			return new WP_Error( 'nn27_token', 'Token invalid.', array( 'status' => 401 ) );
		}
		return true;
	}

	/* ------------------------------------------------------------------ */
	/* Săli: adrese și potrivire                                           */
	/* ------------------------------------------------------------------ */

	private static function venues() {
		$out = array();
		foreach ( Nn27_Store::all() as $r ) {
			if ( 'v' === $r['col'] && is_array( $r['data'] ) ) {
				$out[] = $r['data'];
			}
		}
		return $out;
	}

	public static function venue_emails( $v ) {
		$raw = isset( $v['em'] ) ? (string) $v['em'] : '';
		preg_match_all( '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', $raw, $m );
		return array_values( array_unique( array_map( 'strtolower', $m[0] ) ) );
	}

	public static function known_emails() {
		$all = array();
		foreach ( self::venues() as $v ) {
			$all = array_merge( $all, self::venue_emails( $v ) );
		}
		return array_values( array_unique( $all ) );
	}

	private static function norm( $s ) {
		$s = remove_accents( mb_strtolower( (string) $s ) );
		return preg_replace( '/[^a-z0-9]+/', ' ', $s );
	}

	/**
	 * Găsește sala unui email: adresă exactă, apoi domeniu, apoi numele sălii.
	 *
	 * @return array{0:string,1:string} [venue id, motiv]
	 */
	public static function match( $from_email, $to_email, $from_name, $subject ) {
		$public = array( 'gmail.com', 'yahoo.com', 'yahoo.ro', 'outlook.com', 'hotmail.com', 'icloud.com', 'live.com', 'ymail.com' );
		$addrs  = array_filter( array_map( 'strtolower', array_merge( array( $from_email ), preg_split( '/[,;\s]+/', (string) $to_email ) ) ) );
		$venues = self::venues();
		foreach ( $venues as $v ) {
			if ( array_intersect( $addrs, self::venue_emails( $v ) ) ) {
				return array( $v['id'], 'adresă' );
			}
		}
		foreach ( $venues as $v ) {
			foreach ( self::venue_emails( $v ) as $e ) {
				$dom = substr( strrchr( $e, '@' ), 1 );
				if ( $dom && ! in_array( $dom, $public, true ) ) {
					foreach ( $addrs as $a ) {
						if ( substr( strrchr( $a, '@' ), 1 ) === $dom ) {
							return array( $v['id'], 'domeniu' );
						}
					}
				}
			}
		}
		$generic = array( 'restaurant', 'hotel', 'event', 'events', 'venue', 'sala', 'sali', 'salon', 'iasi', 'boutique', 'pensiunea', 'complex', 'centrul', 'grand', 'casa', 'vila', 'motel', 'the', 'la', 'de', 'si' );
		$hay    = ' ' . self::norm( implode( ' ', array_merge( $addrs, array( $from_name, $subject ) ) ) ) . ' ';
		$hay2   = str_replace( ' ', '', $hay );
		$best   = array( '', 0 );
		foreach ( $venues as $v ) {
			$tokens = array_diff( array_filter( explode( ' ', self::norm( $v['n'] ?? '' ) ), static function ( $t ) { return strlen( $t ) >= 4; } ), $generic );
			if ( ! $tokens ) {
				continue;
			}
			$hits = 0;
			foreach ( $tokens as $t ) {
				if ( false !== strpos( $hay, ' ' . $t . ' ' ) || false !== strpos( $hay2, $t ) ) {
					++$hits;
				}
			}
			$score = $hits / count( $tokens );
			if ( $hits && $score > $best[1] ) {
				$best = array( $v['id'], $score );
			}
		}
		return $best[1] >= 0.5 ? array( $best[0], 'nume' ) : array( '', '' );
	}

	/* ------------------------------------------------------------------ */
	/* Citirea ofertei                                                     */
	/* ------------------------------------------------------------------ */

	private static function to_num( $s ) {
		$s = str_replace( array( ' ', "\u{00a0}" ), '', (string) $s );
		if ( preg_match( '/^\d{1,3}(\.\d{3})+(,\d+)?$/', $s ) ) {
			$s = str_replace( '.', '', $s );
		}
		return (float) str_replace( ',', '.', $s );
	}

	/**
	 * Extrage din text elementele unei oferte. Valorile sunt propuneri: în
	 * platformă apar marcate „citit din email”, ca să fie verificate.
	 *
	 * @param string $text Textul emailului și al atașamentelor.
	 * @return array
	 */
	public static function parse_offer( $text ) {
		$t   = preg_replace( '/[ \t]+/u', ' ', (string) $text );
		$low = mb_strtolower( $t );
		$o   = array();
		$cur = '(lei|ron|€|euro|eur)';
		$per = '(?:\s*\/\s*|\s+(?:de\s+)?(?:pe\s+)?)(?:pers(?:oan[aăe])?|invitat|meniu|cap|pax)\b';

		$prices = array();
		if ( preg_match_all( '/(\d{2,4}(?:[.,]\d{1,2})?)\s*' . $cur . $per . '/iu', $t, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			foreach ( $m as $x ) {
				$prices[] = array( self::to_num( $x[1][0] ), mb_strtolower( $x[2][0] ), $x[1][1] );
			}
		}
		if ( preg_match_all( '/(?:meniu(?:l)?|pre[tț](?:ul)?)[^\n.]{0,40}?(\d{2,4}(?:[.,]\d{1,2})?)\s*' . $cur . '/iu', $t, $m2, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			foreach ( $m2 as $x ) {
				$prices[] = array( self::to_num( $x[1][0] ), mb_strtolower( $x[2][0] ), $x[1][1] );
			}
		}
		$adult = array();
		$child = array();
		$seen  = array();
		foreach ( $prices as $p ) {
			if ( isset( $seen[ $p[2] ] ) ) {
				continue; // Același preț prins de ambele tipare.
			}
			$seen[ $p[2] ] = true;
			list( $val, $c, $pos ) = $p;
			$isEur = in_array( $c, array( '€', 'euro', 'eur' ), true );
			if ( $val < ( $isEur ? 15 : 60 ) || $val > ( $isEur ? 400 : 2000 ) ) {
				continue;
			}
			// Contextul e doar textul dinaintea prețului, din aceeași propoziție/rând.
			$pos = mb_strlen( substr( $t, 0, $pos ) );
			$ctx = mb_strtolower( mb_substr( $t, max( 0, $pos - 50 ), min( 50, $pos ) ) );
			$ctx = preg_split( '/[.;\n!?]/u', $ctx );
			$ctx = end( $ctx );
			$row = array( 'v' => $val, 'cur' => $isEur ? 'EUR' : 'RON' );
			if ( preg_match( '/cop(ii|il)|kids|child/u', $ctx ) ) {
				$child[] = $row;
			} else {
				$adult[] = $row;
			}
		}
		if ( $adult ) {
			usort( $adult, static function ( $a, $b ) { return $a['v'] <=> $b['v']; } );
			$o['ppp']     = $adult[0];
			$o['pppMax']  = end( $adult );
			$o['options'] = count( $adult );
		}
		if ( $child ) {
			$o['cp'] = $child[0];
		} elseif ( preg_match( '/cop(?:ii|iii|il)[^\n.]{0,40}?(\d{2})\s*%/u', $low, $m ) ) {
			$o['childPct'] = (int) $m[1];
		}
		if ( preg_match( '/avans[^\n.]{0,40}?(\d{1,2})\s*%/u', $low, $m ) ) {
			$o['deposit'] = $m[1] . '%';
		} elseif ( preg_match( '/avans[^\n]{0,40}?(\d{1,3}(?:[.,]\d{3})+|\d{3,6})\s*' . $cur . '/iu', $t, $m ) ) {
			$o['deposit'] = $m[1] . ' ' . $m[2];
		}
		if ( preg_match( '/b[ăa]utur[^\n.]{0,60}?(nu sunt incluse|nu este inclus|nu sunt incluși|separat|nu intr[ăa])/u', $low ) ) {
			$o['drinks'] = 3;
		} elseif ( preg_match( '/b[ăa]utur[^\n.]{0,60}?(par[tț]ial|doar|r[ăa]coritoare)/u', $low ) ) {
			$o['drinks'] = 2;
		} elseif ( preg_match( '/b[ăa]utur[^\n.]{0,60}?(nelimitat|incluse|inclusă|inclus|open bar)/u', $low ) || false !== strpos( $low, 'open bar' ) ) {
			$o['drinks'] = 1; // Incluse complet.
		}
		if ( preg_match( '/(?:capacitate|maxim|p[âa]n[ăa] la)[^\n.]{0,25}?(\d{2,3})\s*(?:de\s+)?(?:persoane|locuri|invita[tț]i)/u', $low, $m ) ) {
			$o['cap'] = (int) $m[1];
		}
		if ( preg_match( '/(?:minim(?:um)?)[^\n.]{0,25}?(\d{2,3})\s*(?:de\s+)?(?:persoane|invita[tț]i|pers)/u', $low, $m ) ) {
			$o['min'] = (int) $m[1];
		}
		if ( preg_match( '/valabil[ăa]?[^\n]{0,30}?(\d{1,2})[.\/\-](\d{1,2})[.\/\-](\d{2,4})/u', $low, $m ) ) {
			$y        = strlen( $m[3] ) === 2 ? '20' . $m[3] : $m[3];
			$o['valid'] = sprintf( '%04d-%02d-%02d', $y, $m[2], $m[1] );
		}
		if ( preg_match( '/(?:tax[ăa] (?:de )?sal[ăa]|chirie|tax[ăa] (?:de )?serviciu|dj|forma[tț]ie)[^\n]{0,40}?(\d{1,3}(?:[.,]\d{3})+|\d{3,6})\s*' . $cur . '/iu', $t, $m ) ) {
			$o['extraHint'] = trim( $m[0] );
		}
		return $o;
	}

	/**
	 * Text din PDF fără biblioteci: decomprimă fluxurile și citește operatorii
	 * de text. Merge pentru PDF-urile generate din Word/Excel; pentru PDF-uri
	 * scanate, textul vine din Google Drive (OCR) prin script.
	 *
	 * @param string $bin Conținutul PDF.
	 * @return string
	 */
	public static function pdf_text( $bin ) {
		if ( 0 !== strpos( $bin, '%PDF' ) ) {
			return '';
		}
		$out = '';
		if ( preg_match_all( '/stream\r?\n(.*?)\r?\nendstream/s', $bin, $streams ) ) {
			foreach ( $streams[1] as $s ) {
				$d = @gzuncompress( $s ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				if ( false === $d ) {
					$d = @gzinflate( substr( $s, 2 ) ); // phpcs:ignore
				}
				if ( false === $d ) {
					$d = $s;
				}
				if ( false === strpos( $d, 'BT' ) ) {
					continue;
				}
				if ( preg_match_all( '/BT(.*?)ET/s', $d, $blocks ) ) {
					foreach ( $blocks[1] as $b ) {
						if ( preg_match_all( '/\((?:\\\\.|[^\\\\)])*\)\s*Tj|\[(.*?)\]\s*TJ|(T\*|Td|TD|\')/s', $b, $ops, PREG_SET_ORDER ) ) {
							foreach ( $ops as $op ) {
								if ( ! empty( $op[2] ) ) {
									$out .= "\n";
									continue;
								}
								$chunk = isset( $op[1] ) && '' !== $op[1] ? $op[1] : $op[0];
								if ( preg_match_all( '/\(((?:\\\\.|[^\\\\)])*)\)/s', $chunk, $strs ) ) {
									foreach ( $strs[1] as $str ) {
										$out .= stripcslashes( $str );
									}
								}
							}
						}
						$out .= "\n";
					}
				}
			}
		}
		$out = preg_replace( '/[^\P{C}\n]+/u', ' ', (string) @iconv( 'UTF-8', 'UTF-8//IGNORE', $out ) ); // phpcs:ignore
		return trim( mb_substr( (string) $out, 0, 60000 ) );
	}

	/* ------------------------------------------------------------------ */
	/* Primire din Gmail                                                   */
	/* ------------------------------------------------------------------ */

	private static function addr( $s ) {
		if ( preg_match( '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', (string) $s, $m ) ) {
			return strtolower( $m[0] );
		}
		return '';
	}

	/**
	 * Salvează un mesaj (idempotent după ID-ul Gmail).
	 *
	 * @param array $msg Mesajul.
	 * @param array $atts Atașamentele (name, mime, data base64, text opțional).
	 * @return array|WP_Error
	 */
	public static function ingest( $msg, $atts ) {
		global $wpdb;
		$t  = Nn27_Installer::tables();
		$id = isset( $msg['id'] ) ? preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $msg['id'] ) : '';
		if ( ! $id || strlen( $id ) > 64 ) {
			return new WP_Error( 'nn27_invalid', 'ID mesaj lipsă.', array( 'status' => 400 ) );
		}
		// Adresele se extrag din textul brut: „Nume <adresa@domeniu>” ar fi tăiat de sanitize_text_field.
		$raw_from  = (string) ( $msg['from'] ?? '' );
		preg_match_all( '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', (string) ( $msg['to'] ?? '' ), $tos );
		$to        = mb_substr( implode( ', ', array_unique( array_map( 'strtolower', $tos[0] ) ) ), 0, 500 );
		$subject   = mb_substr( sanitize_text_field( (string) ( $msg['subject'] ?? '' ) ), 0, 255 );
		$body      = mb_substr( wp_check_invalid_utf8( (string) ( $msg['body'] ?? '' ), true ), 0, 60000 );
		$direction = 'out' === ( $msg['direction'] ?? '' ) ? 'out' : 'in';
		$when      = isset( $msg['date'] ) ? (int) ( $msg['date'] / 1000 ) : time();
		$from_mail = self::addr( $raw_from );
		$from_name = mb_substr( sanitize_text_field( trim( preg_replace( '/<[^>]*>/', '', str_replace( '"', '', $raw_from ) ) ) ), 0, 255 );
		$thread    = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $msg['threadId'] ?? '' ) );

		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, venue, auto FROM {$t['mail']} WHERE id = %s", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		list( $venue, $why ) = self::match( $from_mail, $to, $from_name, $subject );
		if ( $existing && ! (int) $existing['auto'] ) {
			$venue = $existing['venue']; // Alocare manuală: nu o schimbăm.
			$why   = 'manual';
		}

		// Atașamente: PDF și imagini, salvate în folderul privat.
		$texts = array( $subject, $body );
		$files = array();
		foreach ( array_slice( (array) $atts, 0, 10 ) as $a ) {
			$name = sanitize_file_name( (string) ( $a['name'] ?? 'atasament' ) );
			$data = base64_decode( (string) ( $a['data'] ?? '' ), true );
			if ( false === $data || '' === $data || strlen( $data ) > Nn27_Files::MAX_BYTES ) {
				continue;
			}
			$fid = 'F' . substr( md5( $id . '|' . $name ), 0, 20 );
			$row = Nn27_Files::get( $fid );
			if ( ! $row ) {
				if ( ! function_exists( 'wp_tempnam' ) ) {
					require_once ABSPATH . 'wp-admin/includes/file.php';
				}
				$tmp = wp_tempnam( $name );
				file_put_contents( $tmp, $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				$res = Nn27_Files::store( array( 'tmp_name' => $tmp, 'name' => $name, 'size' => strlen( $data ), 'error' => 0 ), $venue ? $venue : 'mail', $fid, $id );
				if ( is_wp_error( $res ) ) {
					@unlink( $tmp ); // phpcs:ignore
					continue;
				}
				$files[] = $res;
			} else {
				$files[] = array( 'id' => $fid, 'name' => $row['name'], 'type' => $row['mime'], 'size' => (int) $row['size'], 'added' => strtotime( $row['created'] . ' UTC' ) * 1000 );
			}
			$atext = isset( $a['text'] ) ? (string) $a['text'] : '';
			if ( '' === trim( $atext ) && false !== stripos( $name, '.pdf' ) ) {
				$atext = self::pdf_text( $data );
			}
			if ( '' !== trim( $atext ) ) {
				$texts[] = mb_substr( wp_check_invalid_utf8( $atext, true ), 0, 60000 );
			}
		}

		$offer = 'in' === $direction ? self::parse_offer( implode( "\n", $texts ) ) : array();
		$row   = array(
			'id'        => $id,
			'thread'    => $thread,
			'sent'      => gmdate( 'Y-m-d H:i:s', $when ),
			'direction' => $direction,
			'from_email' => $from_mail,
			'from_name' => mb_substr( $from_name, 0, 255 ),
			'to_email'  => $to,
			'subject'   => $subject,
			'snippet'   => mb_substr( trim( preg_replace( '/\s+/u', ' ', $body ) ), 0, 300 ),
			'body'      => $body,
			'venue'     => $venue,
			'auto'      => 'manual' === $why ? 0 : 1,
			'offer'     => wp_json_encode( $offer ),
			'files'     => wp_json_encode( $files ),
			'created'   => current_time( 'mysql', true ),
		);
		if ( $existing ) {
			unset( $row['created'] );
			$wpdb->update( $t['mail'], $row, array( 'id' => $id ) );
		} else {
			$row['hidden'] = 0;
			$wpdb->insert( $t['mail'], $row );
		}
		self::bump();
		return array( 'stored' => true, 'venue' => $venue, 'match' => $why, 'offer' => $offer, 'files' => count( $files ) );
	}

	private static function bump() {
		update_option( 'nn27_mail_rev', (int) get_option( 'nn27_mail_rev', 0 ) + 1, false );
	}

	/* ------------------------------------------------------------------ */
	/* Citire în platformă                                                 */
	/* ------------------------------------------------------------------ */

	public static function known_ids( $ids ) {
		global $wpdb;
		$t   = Nn27_Installer::tables();
		$ids = array_values( array_filter( array_map( static function ( $i ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $i ); }, array_slice( (array) $ids, 0, 500 ) ) ) );
		if ( ! $ids ) {
			return array();
		}
		$ph = implode( ',', array_fill( 0, count( $ids ), '%s' ) );
		return (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$t['mail']} WHERE id IN ($ph)", $ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	public static function stats() {
		global $wpdb;
		$t = Nn27_Installer::tables();
		return $wpdb->get_row( "SELECT COUNT(*) AS n, MAX(created) AS last, SUM(venue = '') AS unassigned FROM {$t['mail']}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function script() {
		$tpl = (string) file_get_contents( NN27_DIR . 'tools/gmail-sync.gs' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return str_replace( array( '%%URL%%', '%%TOKEN%%' ), array( esc_url_raw( rest_url( Nn27_Rest::NS . '/' ) ), self::token() ), $tpl );
	}

	public static function rev() {
		return (int) get_option( 'nn27_mail_rev', 0 );
	}

	public static function list_all() {
		global $wpdb;
		$t    = Nn27_Installer::tables();
		$rows = $wpdb->get_results( "SELECT id, thread, sent, direction, from_email, from_name, to_email, subject, snippet, body, venue, auto, hidden, offer, files FROM {$t['mail']} ORDER BY sent DESC LIMIT 500", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[] = array(
				'id'      => $r['id'],
				'thread'  => $r['thread'],
				't'       => strtotime( $r['sent'] . ' UTC' ) * 1000,
				'dir'     => $r['direction'],
				'from'    => $r['from_email'],
				'name'    => $r['from_name'],
				'to'      => $r['to_email'],
				'subject' => $r['subject'],
				'snippet' => $r['snippet'],
				'body'    => mb_substr( (string) $r['body'], 0, 8000 ),
				'venue'   => $r['venue'],
				'auto'    => (int) $r['auto'],
				'hidden'  => (int) $r['hidden'],
				'offer'   => json_decode( (string) $r['offer'], true ) ?: new stdClass(),
				'files'   => json_decode( (string) $r['files'], true ) ?: array(),
			);
		}
		return $out;
	}

	public static function update( $id, $venue, $hidden ) {
		global $wpdb;
		$t   = Nn27_Installer::tables();
		$set = array();
		if ( null !== $venue ) {
			$set['venue'] = preg_match( '/^[A-Za-z0-9_-]{0,40}$/', $venue ) ? $venue : '';
			$set['auto']  = 0;
		}
		if ( null !== $hidden ) {
			$set['hidden'] = $hidden ? 1 : 0;
		}
		if ( ! $set ) {
			return false;
		}
		$ok = $wpdb->update( $t['mail'], $set, array( 'id' => $id ) );
		if ( isset( $set['venue'] ) ) {
			$wpdb->update( $t['files'], array( 'venue' => $set['venue'] ? $set['venue'] : 'mail' ), array( 'mail' => $id ) );
		}
		self::bump();
		return false !== $ok;
	}
}
