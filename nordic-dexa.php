<?php
/**
 * Plugin Name: Nordic DEXA optimering
 * Description: Hastighed og tilgængelighed for nordicdexascan.dk uden at ændre synlig tekst: forudindlæsning af hero-billede, hovedindhold-landmark og skiplink-mål, navne på logo-links og statusbjælker, ingen doven indlæsning af logo.
 * Version: 1.2
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* Hero-billedet på forsiden er en CSS-baggrund, som browseren først finder sent. Forudindlæs det. */
add_action( 'wp_head', function () {
	if ( is_front_page() ) {
		echo '<link rel="preload" as="image" href="' . esc_url( content_url( '/uploads/2026/10/hero_q90.webp' ) ) . '" fetchpriority="high">' . "\n";
	}
	// Skrifttyperne (latin, variable) hentes straks, så overskrifter ikke ombrydes om, når skriften skifter (layoutskift).
	foreach ( array( 'manrope-58ee1998', 'cormorantgaramond-abcaa8b4' ) as $f ) {
		if ( file_exists( WP_CONTENT_DIR . '/uploads/elementor/google-fonts/fonts/' . $f . '.woff2' ) ) {
			echo '<link rel="preload" as="font" type="font/woff2" href="' . esc_url( content_url( '/uploads/elementor/google-fonts/fonts/' . $f . '.woff2' ) ) . '" crossorigin>' . "\n";
		}
	}
}, 1 );

function nordic_dexa_fix( $html ) {
	if ( false === stripos( $html, '<html' ) ) { return $html; }
	// Sidens Elementor-indpakning bliver hovedindhold og mål for "Spring til indhold" (#content).
	if ( false === stripos( $html, '<main' ) && false === stripos( $html, 'id="content"' ) ) {
		$html = preg_replace( '/<div((?![^>]*\sid=)[^>]*\sdata-elementor-type="wp-page"[^>]*)>/', '<div$1 id="content" role="main" tabindex="-1">', $html, 1 );
	}
	// Første sektion (hero) må ikke vente på Elementors dovne baggrundsbilleder (ellers forsinkes LCP til JavaScript har kørt).
	$wp = stripos( $html, 'data-elementor-type="wp-page"' );
	if ( false !== $wp && preg_match( '/<div class="(elementor-element [^"]*\be-con\b[^"]*\be-parent\b[^"]*)"/', $html, $m, PREG_OFFSET_CAPTURE, $wp ) && false === strpos( $m[1][0], 'e-lazyloaded' ) ) {
		$html = substr_replace( $html, $m[1][0] . ' e-lazyloaded', $m[1][1], strlen( $m[1][0] ) );
	}
	// Logo-links uden tekst får et navn; logoet indlæses straks (undgår layoutskift).
	$html = preg_replace_callback( '/<a href="https:\/\/nordicdexascan\.dk\/?">(\s*<img\b)([^>]*)>/i', function ( $m ) {
		$img = $m[2];
		if ( false === stripos( $img, 'data-no-lazy' ) ) { $img = ' data-no-lazy="1"' . $img; }
		$img = preg_replace( '/\sfetchpriority="high"/i', '', $img ); // hero-billedet skal have forrang
		$img = preg_replace( '/\ssizes="[^"]*"/i', ' sizes="260px"', $img ); // logoet vises ca. 250 px bredt - hent ikke 1024 px-versionen
		// Logoet som WebP (16-28 KB i stedet for 57-90 KB PNG). Samme billede, samme mål.
		if ( false !== strpos( $img, '7050f406-fc18-419f-a21d-562c33540744-Edited' ) ) {
			$u = plugins_url( '', __FILE__ );
			$img = preg_replace( '/\ssrcset="[^"]*"/i', ' srcset="' . $u . '/logo-520.webp 520w, ' . $u . '/logo-780.webp 780w"', $img );
			$img = preg_replace( '/\ssrc="[^"]*"/i', ' src="' . $u . '/logo-520.webp"', $img );
		}
		return '<a href="https://nordicdexascan.dk/" aria-label="Nordic DEXA Scan - forside">' . $m[1] . $img . '>';
	}, $html );
	// Statusbjælker (eksempelresultater) får navn efter overskriften lige før.
	$out = ''; $pos = 0;
	while ( preg_match( '/<div class="elementor-progress-wrapper" role="progressbar"(?![^>]*aria-label)/', $html, $mm, PREG_OFFSET_CAPTURE, $pos ) ) {
		$at = $mm[0][1];
		$before = substr( $html, max( 0, $at - 1500 ), min( 1500, $at ) );
		$label = '';
		if ( preg_match_all( '/<h[1-6][^>]*>([^<]{1,60})<\/h[1-6]>/', $before, $hh ) ) { $label = trim( end( $hh[1] ) ); }
		$tag = $mm[0][0] . ( $label ? ' aria-label="' . esc_attr( $label ) . '"' : '' );
		$out .= substr( $html, $pos, $at - $pos ) . $tag;
		$pos = $at + strlen( $mm[0][0] );
	}
	$html = $out . substr( $html, $pos );
	return $html;
}
add_action( 'template_redirect', function () {
	if ( is_admin() || is_feed() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || isset( $_GET['elementor-preview'] ) ) { return; }
	ob_start( 'nordic_dexa_fix' );
}, 0 );
