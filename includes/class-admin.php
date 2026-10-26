<?php
/**
 * Panoul din WordPress: stare, pagina aplicației, import (cu verificare), export, actualizări.
 *
 * @package Nunta2027
 */

defined( 'ABSPATH' ) || exit;

class Nn27_Admin {

	const CAP = 'manage_options';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		foreach ( array( 'import_check', 'import_do', 'import_cancel', 'export', 'create_page', 'save_settings', 'download_backup' ) as $a ) {
			add_action( 'admin_post_nn27_' . $a, array( __CLASS__, 'action_' . $a ) );
		}
		add_filter( 'plugin_action_links_' . plugin_basename( NN27_FILE ), array( __CLASS__, 'links' ) );
	}

	public static function links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=nn27' ) ) . '">Panou</a>' );
		return $links;
	}

	public static function menu() {
		add_menu_page( 'Nunta 2027', 'Nunta 2027', self::CAP, 'nn27', array( __CLASS__, 'render' ), 'dashicons-heart', 58 );
	}

	private static function guard( $action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Nu aveți acces.', 403 );
		}
		check_admin_referer( 'nn27_' . $action );
	}

	private static function back( $args = array() ) {
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=nn27' ) ) );
		exit;
	}

	private static function transient_key() {
		return 'nn27_import_' . get_current_user_id();
	}

	/* ------------------------------ acțiuni ------------------------------ */

	public static function action_import_check() {
		self::guard( 'import_check' );
		$f = $_FILES['nn27_file'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! $f || UPLOAD_ERR_OK !== (int) $f['error'] ) {
			self::back( array( 'nn27_msg' => 'nofile' ) );
		}
		$raw = file_get_contents( $f['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$doc = json_decode( (string) $raw, true );
		set_transient( self::transient_key(), $doc, HOUR_IN_SECONDS );
		self::back( array( 'nn27_step' => 'review' ) );
	}

	public static function action_import_cancel() {
		self::guard( 'import_cancel' );
		delete_transient( self::transient_key() );
		self::back();
	}

	public static function action_import_do() {
		self::guard( 'import_do' );
		$doc = get_transient( self::transient_key() );
		if ( ! $doc ) {
			self::back( array( 'nn27_msg' => 'expired' ) );
		}
		$res = Nn27_Store::import_state( $doc, wp_get_current_user()->display_name );
		delete_transient( self::transient_key() );
		if ( is_wp_error( $res ) ) {
			set_transient( 'nn27_err_' . get_current_user_id(), $res->get_error_message(), 300 );
			self::back( array( 'nn27_msg' => 'importfail' ) );
		}
		self::back( array( 'nn27_msg' => 'imported', 'n' => (int) $res['records'] ) );
	}

	public static function action_export() {
		self::guard( 'export' );
		$json = wp_json_encode( Nn27_Store::export_state(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="nunta-2027-backup-' . gmdate( 'Y-m-d-His' ) . '.json"' );
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	public static function action_download_backup() {
		self::guard( 'download_backup' );
		$name = sanitize_file_name( wp_unslash( $_GET['f'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$path = Nn27_Files::dir() . '/' . $name;
		if ( ! preg_match( '/^backup-[A-Za-z0-9._-]+\.json$/', $name ) || ! is_file( $path ) ) {
			wp_die( 'Backup inexistent.', 404 );
		}
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	public static function action_create_page() {
		self::guard( 'create_page' );
		$pass = trim( (string) wp_unslash( $_POST['nn27_pass'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( strlen( $pass ) < 8 ) {
			self::back( array( 'nn27_msg' => 'shortpass' ) );
		}
		$id = wp_insert_post(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'post_title'     => 'Nunta noastră 2027',
				'post_name'      => 'nunta-2027',
				'post_content'   => '<!-- wp:shortcode -->[nunta_2027]<!-- /wp:shortcode -->',
				'post_password'  => $pass,
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			self::back( array( 'nn27_msg' => 'pagefail' ) );
		}
		update_option( Nn27_Auth::PAGE_OPTION, (int) $id, false );
		self::back( array( 'nn27_msg' => 'pagecreated' ) );
	}

	public static function action_save_settings() {
		self::guard( 'save_settings' );
		$repo = esc_url_raw( trim( (string) wp_unslash( $_POST['nn27_repo'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( $repo && ! preg_match( '#^https://github\.com/[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+/?$#', $repo ) ) {
			self::back( array( 'nn27_msg' => 'badrepo' ) );
		}
		update_option( 'nn27_update_repo', untrailingslashit( $repo ), false );
		$page = (int) ( $_POST['nn27_page'] ?? 0 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( $page ) {
			update_option( Nn27_Auth::PAGE_OPTION, $page, false );
		}
		self::back( array( 'nn27_msg' => 'saved' ) );
	}

	/* ------------------------------ afișare ------------------------------ */

	private static function notice( $type, $html ) {
		echo '<div class="notice notice-' . esc_attr( $type ) . '"><p>' . wp_kses_post( $html ) . '</p></div>';
	}

	private static function form_open( $action, $multipart = false ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"' . ( $multipart ? ' enctype="multipart/form-data"' : '' ) . '>';
		echo '<input type="hidden" name="action" value="nn27_' . esc_attr( $action ) . '">';
		wp_nonce_field( 'nn27_' . $action );
	}

	public static function render() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification
		$msg  = sanitize_key( $_GET['nn27_msg'] ?? '' );
		$step = sanitize_key( $_GET['nn27_step'] ?? '' );
		// phpcs:enable
		$messages = array(
			'nofile'      => array( 'error', 'Alegeți fișierul JSON de importat.' ),
			'expired'     => array( 'error', 'Verificarea a expirat. Încărcați fișierul din nou.' ),
			'imported'    => array( 'success', 'Import finalizat: ' . (int) ( $_GET['n'] ?? 0 ) . ' înregistrări. Datele anterioare au fost salvate într-un backup.' ), // phpcs:ignore
			'importfail'  => array( 'error', 'Importul nu a reușit: ' . esc_html( (string) get_transient( 'nn27_err_' . get_current_user_id() ) ) ),
			'pagecreated' => array( 'success', 'Pagina aplicației a fost creată, cu parolă.' ),
			'pagefail'    => array( 'error', 'Pagina nu a putut fi creată.' ),
			'shortpass'   => array( 'error', 'Parola trebuie să aibă cel puțin 8 caractere.' ),
			'saved'       => array( 'success', 'Setările au fost salvate.' ),
			'badrepo'     => array( 'error', 'Adresa repository-ului trebuie să fie de forma https://github.com/cont/nume.' ),
		);

		$page   = Nn27_Auth::page();
		$counts = Nn27_Store::count_by_col();
		$labels = array( 'cats' => 'categorii', 'hh' => 'familii și persoane individuale', 'p' => 'persoane', 'v' => 'săli', 'budget' => 'linii de buget', 'vendors' => 'furnizori', 'tasks' => 'pași în calendar' );
		?>
		<div class="wrap">
			<h1>Nunta noastră 2027 <span style="font-size:13px;color:#777;font-weight:400">v<?php echo esc_html( NN27_VERSION ); ?></span></h1>
			<?php
			if ( isset( $messages[ $msg ] ) ) {
				self::notice( $messages[ $msg ][0], esc_html( $messages[ $msg ][1] ) );
			}
			?>

			<h2>Pagina aplicației</h2>
			<?php if ( $page ) : ?>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( get_permalink( $page ) ); ?>" target="_blank" rel="noopener">Deschide platforma</a>
					&nbsp; <code><?php echo esc_html( get_permalink( $page ) ); ?></code>
				</p>
				<?php if ( '' === $page->post_password ) : ?>
					<?php self::notice( 'warning', 'Pagina nu are parolă. Până nu setați una (Editare pagină → Vizibilitate → Protejată cu parolă), doar administratorii o pot deschide.' ); ?>
				<?php elseif ( 'publish' !== $page->post_status ) : ?>
					<?php self::notice( 'warning', 'Pagina nu este publicată.' ); ?>
				<?php else : ?>
					<p>✔ Protejată cu parolă. Parola se schimbă din editarea paginii (Vizibilitate).</p>
				<?php endif; ?>
			<?php else : ?>
				<p>Nicio pagină nu conține încă shortcode-ul <code>[nunta_2027]</code>. O creez eu, cu parolă:</p>
				<?php self::form_open( 'create_page' ); ?>
					<input type="text" name="nn27_pass" minlength="8" required placeholder="Parola paginii (min. 8 caractere)" class="regular-text" autocomplete="off">
					<?php submit_button( 'Creează pagina', 'primary', 'submit', false ); ?>
				</form>
				<p class="description">Sau creați manual o pagină cu shortcode-ul <code>[nunta_2027]</code> și setați-i Vizibilitate: Protejată cu parolă.</p>
			<?php endif; ?>

			<h2>Date</h2>
			<table class="widefat striped" style="max-width:520px">
				<tbody>
				<?php foreach ( $labels as $k => $l ) : ?>
					<tr><td><?php echo esc_html( ucfirst( $l ) ); ?></td><td style="text-align:right"><strong><?php echo (int) $counts[ $k ]; ?></strong></td></tr>
				<?php endforeach; ?>
				<tr><td>Revizie curentă</td><td style="text-align:right"><?php echo (int) Nn27_Store::current_rev(); ?></td></tr>
				</tbody>
			</table>

			<?php
			if ( 'review' === $step ) {
				$doc = get_transient( self::transient_key() );
				$rep = $doc ? Nn27_Store::validate_import( $doc ) : null;
				echo '<h2>Verificare import (nu s-a scris nimic încă)</h2>';
				if ( ! $rep ) {
					self::notice( 'error', 'Fișierul nu a putut fi citit ca JSON.' );
				} else {
					echo '<table class="widefat striped" style="max-width:520px"><tbody>';
					foreach ( $rep['counts'] as $k => $n ) {
						echo '<tr><td>' . esc_html( ucfirst( $labels[ $k ] ?? $k ) ) . '</td><td style="text-align:right"><strong>' . (int) $n . '</strong></td></tr>';
					}
					echo '</tbody></table>';
					foreach ( $rep['errors'] as $e ) {
						self::notice( 'error', esc_html( $e ) );
					}
					foreach ( $rep['warnings'] as $w ) {
						self::notice( 'warning', esc_html( $w ) );
					}
					if ( $rep['ok'] ) {
						echo '<p><strong>Atenție:</strong> importul înlocuiește toate datele actuale. Înainte de import se face automat un backup.</p>';
						self::form_open( 'import_do' );
						submit_button( 'Confirmă importul', 'primary', 'submit', false );
						echo '</form>';
					}
				}
				self::form_open( 'import_cancel' );
				submit_button( 'Renunță', 'secondary', 'submit', false );
				echo '</form>';
			} else {
				?>
				<h2>Import</h2>
				<p>Încărcați un fișier JSON exportat de platformă (sau fișierul cu datele inițiale). Întâi vedeți un raport de verificare; nimic nu se scrie până nu confirmați.</p>
				<?php self::form_open( 'import_check', true ); ?>
					<input type="file" name="nn27_file" accept="application/json,.json" required>
					<?php submit_button( 'Verifică fișierul', 'secondary', 'submit', false ); ?>
				</form>
				<?php
			}
			?>

			<h2>Backup</h2>
			<?php self::form_open( 'export' ); ?>
				<?php submit_button( 'Descarcă backup complet (JSON)', 'secondary', 'submit', false ); ?>
			</form>
			<?php
			$dir     = Nn27_Files::dir();
			$backups = is_dir( $dir ) ? glob( $dir . '/backup-*.json' ) : array();
			if ( $backups ) {
				rsort( $backups );
				echo '<p>Backup-uri automate (făcute înainte de import sau actualizări):</p><ul>';
				foreach ( array_slice( $backups, 0, 10 ) as $b ) {
					$name = basename( $b );
					$url  = wp_nonce_url( admin_url( 'admin-post.php?action=nn27_download_backup&f=' . rawurlencode( $name ) ), 'nn27_download_backup' );
					echo '<li><a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a></li>';
				}
				echo '</ul>';
			}
			?>

			<h2>Actualizări automate</h2>
			<?php self::form_open( 'save_settings' ); ?>
				<p>
					<label for="nn27_repo">Repository GitHub</label><br>
					<input type="url" id="nn27_repo" name="nn27_repo" class="regular-text" value="<?php echo esc_attr( get_option( 'nn27_update_repo', 'https://github.com/alexteaca90/nunta-2027' ) ); ?>" placeholder="https://github.com/cont/nunta-2027">
				</p>
				<p class="description">Când apare o versiune nouă în acest repository, WordPress o afișează la Module → „Actualizare disponibilă”. Pentru un repository privat, adăugați în wp-config.php: <code>define( 'NN27_GITHUB_TOKEN', '…' );</code></p>
				<?php submit_button( 'Salvează', 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}
}
