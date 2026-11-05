<?php
/**
 * Plugin Name: Nordic DEXA optimering
 * Description: Hastighed og tilgængelighed for nordicdexascan.dk uden at ændre synlig tekst: forudindlæsning af hero-billede, hovedindhold-landmark og skiplink-mål, navne på logo-links og statusbjælker, ingen doven indlæsning af logo.
 * Version: 1.12
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

/* ElementsKit-ikonskriften er 454 KB. Sitet bruger 18 ikoner: et udsnit på 3 KB dækker dem via unicode-range.
   Den fulde skrift hentes kun, hvis en side viser et ikon uden for udsnittet (nye ikoner virker stadig). */
add_action( 'wp_head', function () {
	$u = plugins_url( 'elementskit-udsnit.woff2', __FILE__ );
	echo '<link rel="preload" as="font" type="font/woff2" href="' . esc_url( $u ) . '" crossorigin>' . "\n";
	echo '<style id="nordic-ek-udsnit">@font-face{font-family:elementskit;src:url(' . esc_url( $u ) . ') format("woff2");font-weight:400;font-style:normal;font-display:swap;unicode-range:U+E804,U+E818,U+E830,U+E877-E878,U+E973,U+E9C5,U+E9DA,U+E9F7,U+EA0E,U+EA1C,U+EA52,U+EA89,U+EAAF,U+EACA,U+EAD6,U+EAD9,U+EB23}</style>' . "\n";
}, 99 );

/* Fem kortetiketter på /dexa-scanning/ er ændret fra h4 til h3 (korrekt overskriftsrækkefølge). De beholder temaets h4-udseende. */
add_action( 'wp_head', function () {
	$sel = implode( ',', array_map( function ( $i ) { return '.elementor-element-' . $i . ' h3.elementor-heading-title'; }, array( '4e7b33d', '2f662f0', 'de6c368', '7137383', '0463f6c' ) ) );
	echo '<style id="nordic-menu">.elementor-nav-menu--main .nordic-kun-mobil{display:none!important}.elementor-element-6a7a627 .elementor-button,.elementor-element-e9f5609 .elementor-button{white-space:nowrap}@media(max-width:1279px){[data-elementor-type=header] .elementor-element-6a7a627{display:none}}.elementor-element-d1d6203 .elementor-button,.elementor-element-b84d909 .elementor-button,.elementor-element-d0d9596 .elementor-button{white-space:nowrap}.e-con:has(>.elementor-element-b84d909){flex-wrap:wrap}</style>' . "\n";
	echo '<style id="nordic-h4-udseende">' . $sel . '{font-family:"Manrope",Sans-serif;font-size:14px;letter-spacing:1px}@media(max-width:1024px){' . $sel . '{font-size:12px}}@media(max-width:767px){' . $sel . '{letter-spacing:0}}</style>' . "\n";
}, 99 );

/* Én fælles privatlivspolitik for alle Nordic-klinikker (ejerens beslutning 2026-10-25). */
add_action( 'template_redirect', function () {
	$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
	if ( 'privatlivspolitik' === $path && ! is_user_logged_in() ) {
		wp_redirect( 'https://nordicspecialhospital.dk/privatlivspolitik/', 301, 'Nordic DEXA' );
		exit;
	}
}, 0 );
add_filter( 'rank_math/sitemap/entry', function ( $url, $type, $obj ) {
	return ( 'post' === $type && isset( $obj->post_name ) && 'privatlivspolitik' === $obj->post_name ) ? false : $url;
}, 10, 3 );

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
		if ( preg_match_all( '/<(?:h[1-6]|p|div|span)[^>]*class="elementor-heading-title[^"]*"[^>]*>([^<]{1,60})</', $before, $hh ) ) { $label = trim( end( $hh[1] ) ); }
		$tag = $mm[0][0] . ( $label ? ' aria-label="' . esc_attr( $label ) . '"' : '' );
		$out .= substr( $html, $pos, $at - $pos ) . $tag;
		$pos = $at + strlen( $mm[0][0] );
	}
	$html = $out . substr( $html, $pos );
	return nordic_dexa_schema( $html );
}

/* Strukturerede data: alle sider får klinik, website, side og brødkrumme; FAQ samles i én FAQPage ud fra sidens synlige spørgsmål. */
function nordic_dexa_schema( $html ) {
	if ( is_admin() || ! is_singular() ) { return $html; }
	$base = 'https://nordicdexascan.dk';
	// Fjern Elementors separate FAQPage-blokke (flere FAQPage på én side giver fejl i Search Console).
	$html = preg_replace( '#<script type="application/ld\+json">\s*\{"@context":"https:\\\\/\\\\/schema\.org","@type":"FAQPage".*?</script>\s*#s', '', $html );
	$faq = array();
	if ( preg_match_all( '#<summary class="e-n-accordion-item-title".*?<div class="e-n-accordion-item-title-text">(.*?)</div>.*?</summary>(.*?)</details>#s', $html, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $x ) {
			$q = trim( html_entity_decode( wp_strip_all_tags( $x[1] ), ENT_QUOTES, 'UTF-8' ) );
			$a = trim( preg_replace( '/\s+/', ' ', html_entity_decode( wp_strip_all_tags( str_replace( array( '</p>', '<br>', '</li>' ), array( '</p> ', ' ', '</li> ' ), $x[2] ) ), ENT_QUOTES, 'UTF-8' ) ) );
			if ( $q && $a ) { $faq[] = array( '@type' => 'Question', 'name' => $q, 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $a ) ); }
		}
	}
	$url = get_permalink();
	$graph = array();
	$has_rm = false !== strpos( $html, 'rank-math-schema' );
	if ( ! $has_rm ) {
		$graph[] = array(
			'@type' => array( 'MedicalClinic', 'Organization' ), '@id' => $base . '/#organization', 'name' => 'Nordic DEXA Scan', 'url' => $base,
			'email' => 'info@nordicspecialhospital.dk', 'telephone' => '+4577340036', 'legalName' => 'Nordic Specialhospital',
			'address' => array( '@type' => 'PostalAddress', 'streetAddress' => 'Laurits Olsens Vej 1, 1. sal', 'postalCode' => '2650', 'addressLocality' => 'Hvidovre', 'addressRegion' => 'Region Hovedstaden', 'addressCountry' => 'DK' ),
			'logo' => $base . '/wp-content/uploads/2026/10/Nordic-Dexa-Scan-2.png',
			'openingHoursSpecification' => array( array( '@type' => 'OpeningHoursSpecification', 'dayOfWeek' => array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ), 'opens' => '09:00', 'closes' => '17:00' ) ),
		);
		$graph[] = array( '@type' => 'WebSite', '@id' => $base . '/#website', 'url' => $base, 'name' => 'Nordic DEXA Scan', 'inLanguage' => 'da-DK', 'publisher' => array( '@id' => $base . '/#organization' ) );
		$h1 = preg_match( '#<h1[^>]*>(.*?)</h1>#s', $html, $hm ) ? trim( preg_replace( '/\s+/', ' ', html_entity_decode( wp_strip_all_tags( $hm[1] ), ENT_QUOTES, 'UTF-8' ) ) ) : get_the_title();
		$desc = preg_match( '#<meta name="description" content="([^"]*)"#', $html, $dm ) ? html_entity_decode( $dm[1], ENT_QUOTES, 'UTF-8' ) : '';
		$legal = in_array( get_post_field( 'post_name' ), array( 'cookies', 'privatlivspolitik' ), true );
		$page = array( '@type' => $legal ? 'WebPage' : 'MedicalWebPage', '@id' => $url . '#webpage', 'url' => $url, 'name' => $h1, 'inLanguage' => 'da-DK',
			'isPartOf' => array( '@id' => $base . '/#website' ), 'about' => array( '@id' => $base . '/#organization' ), 'dateModified' => get_the_modified_date( 'c' ),
			'breadcrumb' => array( '@id' => $url . '#breadcrumb' ) );
		if ( $desc ) { $page['description'] = $desc; }
		if ( ! $legal ) { $page['audience'] = array( '@type' => 'PeopleAudience', 'audienceType' => 'Patient' ); }
		$graph[] = $page;
	}
	if ( ! is_front_page() ) {
		$graph[] = array( '@type' => 'BreadcrumbList', '@id' => $url . '#breadcrumb', 'itemListElement' => array(
			array( '@type' => 'ListItem', 'position' => 1, 'name' => 'Forside', 'item' => $base . '/' ),
			array( '@type' => 'ListItem', 'position' => 2, 'name' => get_the_title(), 'item' => $url ),
		) );
	}
	if ( $faq ) { $graph[] = array( '@type' => 'FAQPage', '@id' => $url . '#faq', 'mainEntity' => $faq ); }
	if ( ! $graph ) { return $html; }
	$json = wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	return preg_replace( '#</head>#', '<script type="application/ld+json" class="nordic-schema">' . str_replace( '</', '<\/', $json ) . '</script>' . "\n</head>", $html, 1 );
}
add_action( 'template_redirect', function () {
	if ( is_admin() || is_feed() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || isset( $_GET['elementor-preview'] ) ) { return; }
	ob_start( 'nordic_dexa_fix' );
}, 0 );
