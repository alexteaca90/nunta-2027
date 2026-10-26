<?php
/**
 * Plugin Name:       Nunta noastră 2027
 * Plugin URI:        https://github.com/alexteaca90/nunta-2027
 * Description:       Platforma privată de organizare a nunții: invitați, săli, buget, furnizori și calendar, cu date sincronizate între dispozitive. Se afișează pe pagina care conține shortcode-ul [nunta_2027].
 * Version:           0.3.1
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Alex Teacă
 * License:           GPL-2.0-or-later
 * Text Domain:       nunta-2027
 *
 * @package Nunta2027
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'NN27_VERSION' ) ) {
	return; // O altă copie a pluginului este deja încărcată.
}

define( 'NN27_VERSION', '0.3.1' );
define( 'NN27_FILE', __FILE__ );
define( 'NN27_DIR', plugin_dir_path( __FILE__ ) );
define( 'NN27_URL', plugin_dir_url( __FILE__ ) );

require_once NN27_DIR . 'includes/class-installer.php';
require_once NN27_DIR . 'includes/class-store.php';
require_once NN27_DIR . 'includes/class-auth.php';
require_once NN27_DIR . 'includes/class-files.php';
require_once NN27_DIR . 'includes/class-mail.php';
require_once NN27_DIR . 'includes/class-rest.php';
require_once NN27_DIR . 'includes/class-frontend.php';
require_once NN27_DIR . 'includes/class-admin.php';
require_once NN27_DIR . 'includes/class-updater.php';

register_activation_hook( __FILE__, array( 'Nn27_Installer', 'activate' ) );

add_action( 'plugins_loaded', array( 'Nn27_Installer', 'maybe_upgrade' ) );
add_action( 'rest_api_init', array( 'Nn27_Rest', 'register_routes' ) );
Nn27_Frontend::init();
Nn27_Admin::init();
Nn27_Updater::init();

/**
 * Avertizează dacă e activ un alt plugin de nuntă (ex. o versiune mai veche, făcută separat).
 */
add_action(
	'admin_notices',
	static function () {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$mine = plugin_basename( NN27_FILE );
		foreach ( (array) get_option( 'active_plugins', array() ) as $p ) {
			if ( $p !== $mine && false !== stripos( $p, 'nunta' ) ) {
				$url = wp_nonce_url( admin_url( 'plugins.php?action=deactivate&plugin=' . rawurlencode( $p ) ), 'deactivate-plugin_' . $p );
				echo '<div class="notice notice-error"><p><strong>Nunta noastră 2027:</strong> este activ și pluginul <code>' . esc_html( $p ) . '</code>, care poate intra în conflict cu platforma. <a href="' . esc_url( $url ) . '">Dezactivează-l acum</a>.</p></div>';
			}
		}
	}
);
