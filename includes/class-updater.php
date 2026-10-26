<?php
/**
 * Actualizări dintr-un click, din release-urile GitHub (biblioteca plugin-update-checker).
 *
 * Fluxul: o versiune nouă este publicată ca release pe GitHub, cu fișierul
 * nunta-2027.zip atașat. WordPress verifică periodic și afișează
 * „Actualizare disponibilă” la Module. După actualizare, migrațiile de schemă
 * rulează automat (cu backup înainte).
 *
 * @package Nunta2027
 */

defined( 'ABSPATH' ) || exit;

class Nn27_Updater {

	public static function init() {
		$repo = get_option( 'nn27_update_repo', 'https://github.com/alexteaca90/nunta-2027' );
		if ( ! $repo || ! is_admin() && ! wp_doing_cron() ) {
			return;
		}
		$lib = NN27_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';
		if ( ! file_exists( $lib ) ) {
			return;
		}
		require_once $lib;
		if ( ! class_exists( '\YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
			return;
		}
		$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker( $repo . '/', NN27_FILE, 'nunta-2027' );
		$checker->setBranch( 'main' );
		$api = $checker->getVcsApi();
		if ( $api && method_exists( $api, 'enableReleaseAssets' ) ) {
			$api->enableReleaseAssets( '/nunta-2027\.zip$/' );
		}
		if ( defined( 'NN27_GITHUB_TOKEN' ) && NN27_GITHUB_TOKEN ) {
			$checker->setAuthentication( NN27_GITHUB_TOKEN );
		}
	}
}
