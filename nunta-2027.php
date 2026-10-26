<?php
/**
 * Plugin Name:       Nunta noastră 2027
 * Plugin URI:        https://github.com/alexteaca90/nunta-2027
 * Description:       Platforma privată de organizare a nunții: invitați, săli, buget, furnizori și calendar, cu date sincronizate între dispozitive. Se afișează pe pagina care conține shortcode-ul [nunta_2027].
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Alex Teacă
 * License:           GPL-2.0-or-later
 * Text Domain:       nunta-2027
 *
 * @package Nunta2027
 */

defined( 'ABSPATH' ) || exit;

define( 'ATWN27_VERSION', '0.1.0' );
define( 'ATWN27_FILE', __FILE__ );
define( 'ATWN27_DIR', plugin_dir_path( __FILE__ ) );
define( 'ATWN27_URL', plugin_dir_url( __FILE__ ) );

require_once ATWN27_DIR . 'includes/class-installer.php';
require_once ATWN27_DIR . 'includes/class-store.php';
require_once ATWN27_DIR . 'includes/class-auth.php';
require_once ATWN27_DIR . 'includes/class-files.php';
require_once ATWN27_DIR . 'includes/class-rest.php';
require_once ATWN27_DIR . 'includes/class-frontend.php';
require_once ATWN27_DIR . 'includes/class-admin.php';
require_once ATWN27_DIR . 'includes/class-updater.php';

register_activation_hook( __FILE__, array( 'Atwn27_Installer', 'activate' ) );

add_action( 'plugins_loaded', array( 'Atwn27_Installer', 'maybe_upgrade' ) );
add_action( 'rest_api_init', array( 'Atwn27_Rest', 'register_routes' ) );
Atwn27_Frontend::init();
Atwn27_Admin::init();
Atwn27_Updater::init();
