<?php
/**
 * Șablonul aplicației (fullscreen, independent de temă).
 *
 * @package Nunta2027
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html lang="ro">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#FFFEFD" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0B0B0A" media="(prefers-color-scheme: dark)">
<title>Nunta noastră 2027</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bodoni+Moda:ital,opsz,wght@0,6..96,400..700;1,6..96,400..600&family=Hanken+Grotesk:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="<?php echo Atwn27_Frontend::asset( 'app.css' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>">
<script>
(function(){try{var t=localStorage.getItem('atwn27-theme');if(t==='light'||t==='dark')document.documentElement.setAttribute('data-theme',t);}catch(e){}})();
window.ATWN27 = <?php echo wp_json_encode( Atwn27_Frontend::config() ); ?>;
</script>
</head>
<body>
<div class="app">
  <aside class="rail">
    <div class="mono"><b>A &amp; A</b><span>Andreea și Alex · 2027</span></div>
    <nav class="nav" id="nav"></nav>
    <div class="rail-foot"><div class="themes" role="group" aria-label="Temă"><button data-theme-set="auto">Auto</button><button data-theme-set="light">Luminos</button><button data-theme-set="dark">Întunecat</button></div><div class="sync" id="sync" role="status" aria-live="polite"><span class="dot"></span><span class="txt">Se încarcă…</span><button class="sync-act" hidden>Reîncearcă</button></div></div>
  </aside>
  <main class="main">
    <div class="wrap topbar-m"><b>A &amp; A</b><div class="sync m" role="status"><span class="dot"></span><span class="txt"></span><button class="sync-act" hidden>Reîncearcă</button></div><div class="themes" role="group" aria-label="Temă"><button data-theme-set="auto">Auto</button><button data-theme-set="light">Luminos</button><button data-theme-set="dark">Întunecat</button></div><button class="btn ghost" data-go="setari" aria-label="Setări"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg><span class="setlbl">Setări</span></button></div>
    <div class="thread" id="thread" aria-label="Firul nunții"><div class="wrap" id="threadIn"></div></div>
    <div class="wrap" id="view"></div>
  </main>
</div>
<nav class="tabbar" id="tabbar"></nav>
<div class="scrim" id="scrim" data-act="close"></div>
<aside class="drawer" id="drawer" aria-hidden="true"></aside>
<div class="toast" id="toast" role="status"></div>
<script src="<?php echo Atwn27_Frontend::asset( 'app.js' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>"></script>
</body>
</html>
