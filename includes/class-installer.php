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

class Atwn27_Installer {

	const DB_VERSION = '1';

	public static function activate() {
		self::install();
		Atwn27_Files::ensure_dir();
	}

	public static function maybe_upgrade() {
		$current = get_option( 'atwn27_db_version' );
		if ( self::DB_VERSION === $current ) {
			return;
		}
		if ( $current ) {
			// Există date dintr-o versiune anterioară: backup înainte de migrare.
			Atwn27_Store::backup_to_file( 'inainte-de-schema-' . self::DB_VERSION );
		}
		self::install();
		Atwn27_Files::ensure_dir();
	}

	public static function tables() {
		global $wpdb;
		return array(
			'records' => $wpdb->prefix . 'atwn27_records',
			'revs'    => $wpdb->prefix . 'atwn27_revs',
			'log'     => $wpdb->prefix . 'atwn27_log',
			'files'   => $wpdb->prefix . 'atwn27_files',
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
  PRIMARY KEY  (id),
  KEY venue (venue)
) $c;"
		);
		update_option( 'atwn27_db_version', self::DB_VERSION, false );
	}
}
