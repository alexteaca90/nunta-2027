<?php
/**
 * Ecranul de parolă al aplicației (înlocuiește formularul temei).
 *
 * Trimite parola la wp-login.php?action=postpass, exact ca formularul standard
 * WordPress; după aceea WordPress revine pe pagină.
 *
 * @package Nunta2027
 */

defined( 'ABSPATH' ) || exit;

$atwn27_post    = get_queried_object();
$atwn27_tried   = isset( $_COOKIE[ 'wp-postpass_' . COOKIEHASH ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
$atwn27_no_pass = '' === $atwn27_post->post_password;
?><!doctype html>
<html lang="ro">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<title>Nunta noastră 2027</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bodoni+Moda:ital,opsz,wght@0,6..96,400..700;1,6..96,400..600&family=Hanken+Grotesk:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="<?php echo Atwn27_Frontend::asset( 'app.css' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>">
<script>(function(){try{var t=localStorage.getItem('atwn27-theme');if(t==='light'||t==='dark')document.documentElement.setAttribute('data-theme',t);}catch(e){}})();</script>
<style>
.gate{min-height:100vh;display:grid;place-items:center;padding:24px max(16px,env(safe-area-inset-right,0px)) 24px max(16px,env(safe-area-inset-left,0px))}
.gate-card{width:100%;max-width:400px;display:grid;gap:22px;text-align:center}
.gate-names{font:400 clamp(44px,12vw,64px)/.95 var(--display);letter-spacing:-.02em}
.gate-names i{font-style:italic;color:var(--wine);padding:0 .06em}
.gate-sub{color:var(--muted);margin:0}
.gate form{display:grid;gap:10px;text-align:left}
.gate .input{padding:12px 14px;font-size:16px}
.gate .btn{justify-content:center;padding:12px;font-size:15px}
.gate-err{color:var(--bad);font-size:13.5px;margin:0}
</style>
</head>
<body>
<main class="gate">
	<div class="gate-card">
		<div class="gate-names">Andreea<i>&amp;</i>Alex</div>
		<?php if ( $atwn27_no_pass ) : ?>
			<p class="gate-sub">Platforma nu este încă disponibilă. Administratorul trebuie să seteze o parolă pentru această pagină.</p>
		<?php else : ?>
			<p class="gate-sub">Nunta noastră · 2027. Introduceți parola ca să intrați.</p>
			<form action="<?php echo esc_url( site_url( 'wp-login.php?action=postpass', 'login_post' ) ); ?>" method="post">
				<label class="field" for="pwbox"><span>Parola</span>
					<input class="input" id="pwbox" name="post_password" type="password" autocomplete="current-password" required autofocus>
				</label>
				<?php if ( $atwn27_tried ) : ?>
					<p class="gate-err" role="alert">Parola nu este corectă. Încercați din nou.</p>
				<?php endif; ?>
				<button class="btn primary" type="submit">Intră</button>
			</form>
		<?php endif; ?>
	</div>
</main>
</body>
</html>
