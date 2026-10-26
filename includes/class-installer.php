<?php
/**
 * Tabele proprii, versiune de schemă și folderul privat pentru fișiere.
 *
 * De ce tabele proprii și nu un singur câmp în wp_options: fiecare înregistrare
 * (familie, persoană, sală...) are propria versiune. Doi oameni care editează
 * lucruri diferite în același timp nu se mai suprascriu, iar un conflict pe
 * aceeași înregistrare este detectat explicit.
 *
 * @package Nunta2027
 */

defined( 'ABSPATH' ) || exit;

class Nn27_Installer {

	const DB_VERSION = '2';

	public static function activate() {
		self::install();
		Nn27_Files::ensure_dir();
	}

	public static function maybe_upgrade() {
		$current = get_option( 'nn27_db_version' );
		if ( self::DB_VERSION === $current ) {
			return;
		}
		if ( $current ) {
			// Există date dintr-o versiune anterioară: backup înainte de migrare.
			Nn27_Store::backup_to_file( 'inainte-de-schema-' . self::DB_VERSION );
		}
		self::install();
		Nn27_Files::ensure_dir();
	}

	public static function tables() {
		global $wpdb;
		return array(
			'records' => $wpdb->prefix . 'nn27_records',
			'revs'    => $wpdb->prefix . 'nn27_revs',
			'log'     => $wpdb->prefix . 'nn27_log',
			'files'   => $wpdb->prefix . 'nn27_files',
			'mail'    => $wpdb->prefix . 'nn27_mail',
		);
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		$t = self::tables();

		dbDelta(
			"CREATE TABLE {$t['records']} (
  col varchar(20) NOT NULL,
  rid varchar(40) NOT NULL,
  data longtext NULL,
  ver int(10) unsigned NOT NULL DEFAULT 1,
  rev bigint(20) unsigned NOT NULL DEFAULT 0,
  deleted tinyint(1) NOT NULL DEFAULT 0,
  updated_at datetime NOT NULL,
  updated_by varchar(40) NOT NULL DEFAULT '',
  PRIMARY KEY  (col,rid),
  KEY rev (rev)
) $c;"
		);
		dbDelta(
			"CREATE TABLE {$t['revs']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  created datetime NOT NULL,
  who varchar(40) NOT NULL DEFAULT '',
  PRIMARY KEY  (id)
) $c;"
		);
		dbDelta(
			"CREATE TABLE {$t['log']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  created datetime NOT NULL,
  who varchar(40) NOT NULL DEFAULT '',
  msg varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  KEY created (created)
) $c;"
		);
		dbDelta(
			"CREATE TABLE {$t['files']} (
  id varchar(40) NOT NULL,
  venue varchar(40) NOT NULL DEFAULT '',
  name varchar(255) NOT NULL DEFAULT '',
  mime varchar(100) NOT NULL DEFAULT '',
  size bigint(20) unsigned NOT NULL DEFAULT 0,
  path varchar(255) NOT NULL DEFAULT '',
  created datetime NOT NULL,
  deleted tinyint(1) NOT NULL DEFAULT 0,
  mail varchar(64) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  KEY venue (venue)
) $c;"
		);
		dbDelta(
			"CREATE TABLE {$t['mail']} (
  id varchar(64) NOT NULL,
  thread varchar(64) NOT NULL DEFAULT '',
  sent datetime NOT NULL,
  direction varchar(3) NOT NULL DEFAULT 'in',
  from_email varchar(190) NOT NULL DEFAULT '',
  from_name varchar(255) NOT NULL DEFAULT '',
  to_email varchar(500) NOT NULL DEFAULT '',
  subject varchar(255) NOT NULL DEFAULT '',
  snippet text NULL,
  body mediumtext NULL,
  venue varchar(40) NOT NULL DEFAULT '',
  auto tinyint(1) NOT NULL DEFAULT 1,
  hidden tinyint(1) NOT NULL DEFAULT 0,
  offer text NULL,
  files text NULL,
  created datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY venue (venue),
  KEY sent (sent)
) $c;"
		);
		update_option( 'nn27_db_version', self::DB_VERSION, false );
		self::migrate_legacy();
	}

	/**
	 * v0.1.0 folosea prefixul „atwn27”, același cu un plugin mai vechi de pe site.
	 * Dacă tabelele noi sunt goale și cele vechi au structura noastră, copiem datele.
	 */
	public static function migrate_legacy() {
		global $wpdb;
		$t   = self::tables();
		$old = array(
			'records' => $wpdb->prefix . 'atwn27_records',
			'revs'    => $wpdb->prefix . 'atwn27_revs',
			'log'     => $wpdb->prefix . 'atwn27_log',
			'files'   => $wpdb->prefix . 'atwn27_files',
		);
		if ( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t['records']}" ) > 0 ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return;
		}
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old['records'] ) );
		if ( $exists !== $old['records'] ) {
			return;
		}
		$cols = $wpdb->get_col( "SHOW COLUMNS FROM {$old['records']}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( array_diff( array( 'col', 'rid', 'data', 'ver', 'rev', 'deleted', 'updated_at', 'updated_by' ), (array) $cols ) ) {
			return; // Alt plugin, altă structură: nu atingem nimic.
		}
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "INSERT INTO {$t['records']} (col, rid, data, ver, rev, deleted, updated_at, updated_by) SELECT col, rid, data, ver, rev, deleted, updated_at, updated_by FROM {$old['records']}" );
		foreach ( array( 'revs' => 'id, created, who', 'log' => 'id, created, who, msg' ) as $k => $list ) {
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old[ $k ] ) ) === $old[ $k ] ) {
				$wpdb->query( "INSERT INTO {$t[$k]} ({$list}) SELECT {$list} FROM {$old[$k]}" );
			}
		}
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old['files'] ) ) === $old['files'] ) {
			$wpdb->query( "INSERT INTO {$t['files']} (id, venue, name, mime, size, path, created, deleted) SELECT id, venue, name, mime, size, path, created, deleted FROM {$old['files']}" );
			$up  = wp_upload_dir( null, false );
			$src = trailingslashit( $up['basedir'] ) . 'atwn27-private';
			$dst = Nn27_Files::ensure_dir();
			if ( $dst && is_dir( $src ) ) {
				foreach ( (array) glob( $src . '/F*' ) as $f ) {
					@copy( $f, $dst . '/' . basename( $f ) ); // phpcs:ignore
				}
			}
		}
		// phpcs:enable
		foreach ( array( 'page_id', 'update_repo' ) as $o ) {
			$v = get_option( 'atwn27_' . $o );
			if ( $v && ! get_option( 'nn27_' . $o ) ) {
				update_option( 'nn27_' . $o, $v, false );
			}
		}
	}
}
