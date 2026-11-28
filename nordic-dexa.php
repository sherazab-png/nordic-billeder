<?php
/**
 * Plugin Name: Nordic DEXA optimering
 * Description: Hastighed og tilgængelighed for nordicdexascan.dk uden at ændre synlig tekst: forudindlæsning af hero-billede, hovedindhold-landmark og skiplink-mål, navne på logo-links og statusbjælker, ingen doven indlæsning af logo.
 * Version: 1.23
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* Testside til online booking (kun synlig for indloggede redaktører). */
require_once __DIR__ . '/booking-demo.php';

/* Gratis-tilstand: aktiv kun når Elementor Pro er slået fra. */
require_once __DIR__ . '/fri-tilstand.php';


/* Engelsk side: sidens egen H1 bruges, temaets sidetitel vises ikke (én H1). */
add_filter( 'hello_elementor_page_title', function ( $show ) { return is_page( 'en' ) ? false : $show; } );

/* Engelsk side (/en/): sproglinks (hreflang) og enkel styling. */
add_action( 'wp_head', function () {
	$en = get_page_by_path( 'en' );
	if ( ! $en || 'publish' !== $en->post_status ) { return; }
	if ( is_front_page() || is_page( $en->ID ) ) {
		echo '<link rel="alternate" hreflang="da" href="' . esc_url( home_url( '/' ) ) . '">' . "\n";
		echo '<link rel="alternate" hreflang="en" href="' . esc_url( get_permalink( $en ) ) . '">' . "\n";
		echo '<link rel="alternate" hreflang="x-default" href="' . esc_url( home_url( '/' ) ) . '">' . "\n";
	}
	if ( is_page( $en->ID ) ) {
		echo '<style>.nds-en{max-width:960px;margin:0 auto;padding:48px 20px 64px;font-family:Manrope,system-ui,sans-serif;color:#1C2B45;line-height:1.6}.nds-en h1{font-family:"Cormorant Garamond",Georgia,serif;font-weight:600;color:#102A4C;font-size:clamp(36px,5vw,56px);line-height:1.05;margin:0 0 14px}.nds-en h2{font-family:"Cormorant Garamond",Georgia,serif;font-weight:600;color:#102A4C;font-size:30px;margin:36px 0 10px}.nds-en-kicker{text-transform:uppercase;letter-spacing:.08em;font-size:13px;color:#66758A;margin:0 0 8px}.nds-en-lead{font-size:18px;max-width:60ch}.nds-en-price{display:inline-block;background:#EEF4FB;border-radius:12px;padding:6px 16px;font-size:28px;font-weight:700;color:#102A4C}.nds-en-cta{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}.nds-en-btn{display:inline-block;background:#102A4C;color:#fff!important;border-radius:999px;padding:12px 22px;font-weight:700;text-decoration:none}.nds-en-btn2{background:#fff;color:#102A4C!important;border:1px solid #102A4C}.nds-en-list{padding-left:20px}.nds-en-table{width:100%;border-collapse:collapse;font-variant-numeric:tabular-nums}.nds-en-table th,.nds-en-table td{text-align:left;padding:10px 8px;border-bottom:1px solid #E3EAF3}.nds-en-table td:last-child,.nds-en-table th:last-child{text-align:right;white-space:nowrap}.nds-en-note{font-size:14px;color:#66758A}.nds-en a{color:#183A70}</style>' . "\n";
	}
}, 2 );

/* WordPress laver ' - ' om til lange tankestreger ved visning. Sitet bruger almindelig bindestreg. */
add_filter( 'run_wptexturize', '__return_false' );

/* Hero-billedet på forsiden er en CSS-baggrund, som browseren først finder sent. Forudindlæs det. */
add_action( 'wp_head', function () {
	if ( is_front_page() ) {
		// Mobil får et udsnit af hero-billedet (det synlige område), computer det fulde billede.
		echo '<link rel="preload" as="image" href="' . esc_url( content_url( '/uploads/2026/10/hero_q90.webp' ) ) . '" media="(min-width: 768px)" fetchpriority="high">' . "\n";
		echo '<link rel="preload" as="image" href="' . esc_url( plugins_url( 'hero-mobil.webp', __FILE__ ) ) . '" media="(max-width: 767px)" fetchpriority="high">' . "\n";
		echo '<style id="nordic-hero-mobil">@media (max-width:767px){.elementor-1445 .elementor-element.elementor-element-ffb8bd4:not(.elementor-motion-effects-element-type-background){background-image:url(' . esc_url( plugins_url( 'hero-mobil.webp', __FILE__ ) ) . ') !important;background-position:-45px 0 !important}}'
			. '</style>' . "\n";
	}
	// Skrifttyperne (latin, variable) hentes straks, så overskrifter ikke ombrydes om, når skriften skifter (layoutskift).
	foreach ( array( 'manrope-58ee1998', 'cormorantgaramond-abcaa8b4' ) as $f ) {
		if ( file_exists( WP_CONTENT_DIR . '/uploads/elementor/google-fonts/fonts/' . $f . '.woff2' ) ) {
			echo '<link rel="preload" as="font" type="font/woff2" href="' . esc_url( content_url( '/uploads/elementor/google-fonts/fonts/' . $f . '.woff2' ) ) . '" crossorigin>' . "\n";
		}
	}
}, 1 );

/* Kontrast (runde 3 T4, godkendt 2026-11-21): guld #C79A5B har kun 2,3-2,6:1 mod lys baggrund.
   Lille guldtekst, mærket "Populær" og knappen "Ring og book tid" får den mørkere guld #8A6A3C (ca. 5:1).
   Links i brødtekst understreges. Prisens "kr" på Premium-kortet var navy på navy og bliver hvid. */
add_action( 'wp_head', function () {
	$txt = array( 'fd25936', '20a4e4e', '7789ad9', '9265062', '88360b7', '2dfd635', '672b8b7', 'a3483e4', 'bd24855', 'f329de1', 'c585852', '002fb18', 'cde3469', '80007dd', '1a92139' );
	$pill = array( '444816e', 'd4b5217', '5ac88d2', 'd804b00', 'dc0704d' );
	$btn = array( '89cdfff', 'd09d130', 'cec0694', 'e5f51ea', 'ab13ffb', 'ac093f7', '8b2cd2c', 'f336752', '298999c', 'cb34bf2', '6a7a627', 'e9f5609' );
	$m = function ( $ids, $suf ) { return implode( ',', array_map( function ( $i ) use ( $suf ) { return '.elementor .elementor-element.elementor-element-' . $i . $suf; }, $ids ) ); };
	echo '<style id="nordic-kontrast">'
		. $m( $txt, ' .elementor-heading-title' ) . ',' . $m( $txt, ' .elementor-widget-container' ) . ',' . $m( $txt, ' p' ) . ',' . $m( $txt, ' .elementor-heading-title span' ) . '{color:#8A6A3C!important}'
		. $m( $pill, '' ) . '{background-color:#8A6A3C!important}'
		. $m( $btn, ' .elementor-button' ) . '{background-color:#8A6A3C!important}'
		. '.elementor .elementor-element.elementor-element-744004b,.elementor .elementor-element.elementor-element-744004b *{color:#fff!important}'
		. '#content p a:not(.elementor-button),#content li a:not(.elementor-button),.page-content p a,.page-content li a{text-decoration:underline;text-underline-offset:2px}'
		. '</style>' . "\n";
}, 99 );

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
	echo '<style id="nordic-menu">.elementor-nav-menu--main .nordic-kun-mobil{display:none!important}.elementor-element-6a7a627 .elementor-button,.elementor-element-e9f5609 .elementor-button{white-space:nowrap}@media(max-width:1279px){[data-elementor-type=header] .elementor-element-6a7a627{display:none}}.elementor-element-d1d6203 .elementor-button,.elementor-element-b84d909 .elementor-button,.elementor-element-d0d9596 .elementor-button{white-space:nowrap}.e-con:has(>.elementor-element-b84d909){flex-wrap:wrap}.elementor-element-1362762{align-items:center!important}.elementor-element-1362762>.elementor-element-88367be,.elementor-element-1362762>.elementor-element-4182c0a{margin-left:0!important;margin-right:0!important;align-self:center!important}.elementor-element-4182c0a .elementor-icon-list-items{justify-content:center}</style>' . "\n";
	echo '<style id="nordic-h4-udseende">' . $sel . '{font-family:"Manrope",Sans-serif;font-size:14px;letter-spacing:1px}@media(max-width:1024px){' . $sel . '{font-size:12px}}@media(max-width:767px){' . $sel . '{letter-spacing:0}}</style>' . "\n";
}, 99 );

/* Én fælles privatlivspolitik for alle Nordic-klinikker (ejerens beslutning 2026-10-25). */
add_action( 'template_redirect', function () {
	$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
	// Osteoporose samlet på nordicosteoporose.dk (godkendt 2026-11-15).
	if ( 'osteoporose-knoglesundhed' === $path && ! is_user_logged_in() ) {
		wp_redirect( 'https://nordicosteoporose.dk/udredning/', 301, 'Nordic DEXA' );
		exit;
	}
	if ( 'privatlivspolitik' === $path && ! is_user_logged_in() ) {
		wp_redirect( 'https://nordicspecialhospital.dk/privatlivspolitik/', 301, 'Nordic DEXA' );
		exit;
	}
}, 0 );
add_filter( 'rank_math/sitemap/entry', function ( $url, $type, $obj ) {
	return ( 'post' === $type && isset( $obj->post_name ) && in_array( $obj->post_name, array( 'privatlivspolitik', 'osteoporose-knoglesundhed' ), true ) ) ? false : $url;
}, 10, 3 );

/* Klinikbjælke øverst som på de øvrige Nordic-sites, i DEXA-sitets farver (mørkere navy over headeren, guldlinje). */
add_action( 'wp_body_open', function () {
	if ( is_admin() ) { return; }
	$k = array(
		array( 'https://nordicspecialhospital.dk', 'Specialklinik', '#C79A5B', false ),
		array( 'https://nordicreumatologi.dk', 'Reumatologi', '#3F7CC0', false ),
		array( 'https://nordicosteoporose.dk', 'Osteoporose', '#2F8F83', false ),
		array( home_url( '/' ), 'DEXA Scan', '#7A68C2', true ),
	);
	echo '<div class="ndx-bar"><div class="ndx-bar__in"><a class="ndx-bar__brand" href="https://nordicspecialhospital.dk/#klinikker">Nordic Specialhospital</a><nav class="ndx-bar__nav" aria-label="Klinikker i Nordic Specialhospital">';
	foreach ( $k as $x ) {
		echo '<a href="' . esc_url( $x[0] ) . '"' . ( $x[3] ? ' class="is-active" aria-current="page" style="--ndx:' . $x[2] . '"' : '' ) . '><i style="background:' . $x[2] . '" aria-hidden="true"></i><span><span class="ndx-bar__n">Nordic </span>' . esc_html( $x[1] ) . '</span></a>';
	}
	echo '</nav></div></div>' . "\n";
}, 20 );
add_action( 'wp_head', function () {
	echo '<style id="nordic-klinikbjaelke">'
		. '.ndx-bar{background:#131E33;box-shadow:inset 0 -2px 0 #C79A5B;font-family:"Manrope",sans-serif;position:relative;z-index:20}'
		. '.ndx-bar__in{max-width:1320px;margin:0 auto;padding:0 0 2px;min-height:44px;display:flex;align-items:center;justify-content:space-between;gap:16px;box-sizing:border-box}'
		. '.ndx-bar__brand{color:#E9C894!important;font-size:11.5px;font-weight:700;letter-spacing:1.6px;text-transform:uppercase;text-decoration:none!important;white-space:nowrap}'
		. '.ndx-bar__brand:hover,.ndx-bar__brand:focus-visible{color:#fff!important}'
		. '.ndx-bar__nav{display:flex;align-items:center;gap:28px}'
		. '.ndx-bar__nav a{display:inline-flex;align-items:center;gap:7px;padding:10px 0 8px;color:rgba(227,234,243,.82)!important;font-size:12.5px;font-weight:500;line-height:1.5;text-decoration:none!important;white-space:nowrap;transition:color .15s}'
		. '.ndx-bar__nav a:hover,.ndx-bar__nav a:focus-visible{color:#fff!important}'
		. '.ndx-bar__nav a:focus-visible,.ndx-bar__brand:focus-visible{outline:2px solid #E9C894;outline-offset:2px;border-radius:2px}'
		. '.ndx-bar__nav a.is-active{color:#fff!important;font-weight:700;box-shadow:inset 0 -2px 0 var(--ndx)}'
		. '.ndx-bar__nav i{width:6px;height:6px;border-radius:50%;flex:none}'
		. '@media(max-width:1379px){.ndx-bar__in{padding:0 20px 2px}}'
		. '@media(max-width:767px){.ndx-bar__in{justify-content:center;padding:0 8px 2px}.ndx-bar__brand,.ndx-bar__n{display:none}.ndx-bar__nav{gap:12px}.ndx-bar__nav a{gap:5px;font-size:11.5px}}'
		. '@media(max-width:359px){.ndx-bar__nav{gap:8px}.ndx-bar__nav a{font-size:11px}}'
		. '</style>' . "\n";
}, 98 );

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
			$img = preg_replace( '/\ssrcset="[^"]*"/i', ' srcset="' . $u . '/logo2-520.webp 520w, ' . $u . '/logo2-780.webp 780w"', $img );
			$img = preg_replace( '/\ssrc="[^"]*"/i', ' src="' . $u . '/logo2-520.webp"', $img );
		}
		$img = preg_replace( '/\salt=""/i', ' alt="Nordic DEXA Scan"', $img );
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
	// Skærmlæsertekster på dansk (runde 3 T2).
	$html = preg_replace_callback( '/aria-label="Rated ([0-9.]+) out of ([0-9]+)"/', function ( $m ) { return 'aria-label="Bedømt ' . str_replace( '.', ',', $m[1] ) . ' ud af ' . $m[2] . '"'; }, $html );
	$html = preg_replace( '/(role="group" aria-roledescription="slide" aria-label=")([0-9]+) of ([0-9]+)"/', '$1$2 af $3"', $html );
	$html = str_replace(
		array( 'aria-label="Menu Toggle"', 'aria-label="Carousel"', 'aria-label="Accordion. Open links with Enter or Space, close with Escape, and navigate with Arrow Keys"', 'aria-roledescription="carousel"', 'aria-roledescription="slide"' ),
		array( 'aria-label="Menu"', 'aria-label="Billedkarrusel"', 'aria-label="Spørgsmål og svar. Åbn med Enter eller mellemrum, luk med Escape, og gå mellem punkterne med piletasterne"', 'aria-roledescription="karrusel"', 'aria-roledescription="dias"' ),
		$html
	);
	// Billedlinks uden tekst får billedets beskrivelse som navn (runde 3 T5).
	$html = preg_replace_callback( '/<a\b((?![^>]*aria-label)[^>]*\be-gallery-item\b[^>]*)>(\s*<div\b[^>]*\baria-label="([^"]+)")/i', function ( $m ) { return '<a' . $m[1] . ' aria-label="Vis større billede: ' . $m[3] . '">' . $m[2]; }, $html );
	$html = preg_replace_callback( '/<a\b((?![^>]*aria-label)[^>]*)>(\s*<img\b[^>]*\balt="([^"]+)"[^>]*>\s*)<\/a>/i', function ( $m ) { return '<a' . $m[1] . ' aria-label="' . $m[3] . '">' . $m[2] . '</a>'; }, $html );
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

/* ---------------------------------------------------------------------------
 * Cookie-samtykke og Google Analytics 4 (godkendt af Sheraz 2026-11-15).
 * - Måle-ID ligger i option nordic_ga_id (fx G-XXXXXXX). Uden ID: intet banner, ingen GA.
 * - GA (gtag.js) indlæses KUN efter "Accepter statistik". Før det: ingen kald til Google.
 * - Valget gemmes i cookien nordic_samtykke (statistik | noedvendige) i 180 dage.
 * - "Cookie-indstillinger" indsættes ved cookie-linket i footeren, så valget kan ændres.
 * - Hændelser: indsendt formular (generate_lead), klik på telefon og e-mail.
 * ------------------------------------------------------------------------- */
if ( ! function_exists( 'nordic_samtykke_id' ) ) {
	function nordic_samtykke_id() {
		if ( isset( $_GET['nsk_preview'] ) ) { return 'G-PREVIEW00'; } // kun visning af banneret til test; indlæser ikke GA
		$id = trim( (string) get_option( 'nordic_ga_id', '' ) );
		return preg_match( '/^G-[A-Z0-9]{4,20}$/', $id ) ? $id : '';
	}
	add_action( 'wp_head', function () {
		if ( is_admin() || is_user_logged_in() || ! nordic_samtykke_id() ) { return; }
		echo '<style id="nordic-samtykke-css">'
			. '.nsk{position:fixed;left:0;right:0;bottom:0;z-index:99999;background:#102A4C;color:#fff;box-shadow:0 -4px 16px rgba(16,42,76,.18);padding:8px 16px;display:flex;gap:8px 16px;align-items:center;justify-content:center;flex-wrap:wrap;font:400 13px/1.4 "Manrope",system-ui,sans-serif}'
			. '.nsk[hidden]{display:none}'
			. '.nsk p{margin:0}'
			. '.nsk a{color:#fff;text-decoration:underline;text-underline-offset:2px}'
			. '.nsk__b{display:flex;gap:8px}'
			. '.nsk button{font:600 13px/1 "Manrope",system-ui,sans-serif;padding:8px 14px;border-radius:999px;border:1px solid #fff;cursor:pointer;min-height:36px}'
			. '.nsk__ja{background:#fff;color:#102A4C}.nsk__nej{background:transparent;color:#fff}'
			. '.nsk button:hover{filter:brightness(1.1)}'
			. '.nsk button:focus-visible,.nsk a:focus-visible,.nsk-indst:focus-visible{outline:2px solid #C79A5B;outline-offset:2px}'
			. '.nsk-indst{background:none;border:0;padding:0;margin:0;font:inherit;color:inherit;text-decoration:underline;cursor:pointer}'
			. '@media(max-width:600px){.nsk{padding:8px 12px;font-size:12px}.nsk p{text-align:center}.nsk button{min-height:40px;padding:8px 12px}}'
			. '</style>' . "\n";
	}, 98 );
	add_action( 'wp_footer', function () {
		if ( is_admin() || is_user_logged_in() ) { return; }
		$id = nordic_samtykke_id();
		if ( ! $id ) { return; }
		$cookies = esc_url( home_url( '/cookies/' ) );
		echo '<div class="nsk" id="nsk" role="dialog" aria-live="polite" aria-label="Cookies" hidden>'
			. '<p>Vi bruger kun statistik-cookies (Google Analytics), hvis du siger ja. <a href="' . $cookies . '">Læs mere</a></p>'
			. '<div class="nsk__b"><button type="button" class="nsk__nej" data-nsk="noedvendige">Kun nødvendige</button><button type="button" class="nsk__ja" data-nsk="statistik">Accepter statistik</button></div>'
			. '</div>' . "\n";
		echo '<script id="nordic-samtykke-js">(function(){'
			. 'var ID=' . wp_json_encode( $id ) . ',K="nordic_samtykke",box=document.getElementById("nsk"),loaded=false;'
			. 'function get(){var m=document.cookie.match(/(?:^|; )nordic_samtykke=([^;]*)/);return m?m[1]:"";}'
			. 'function set(v){document.cookie=K+"="+v+"; Max-Age="+(180*86400)+"; Path=/; SameSite=Lax; Secure";}'
			. 'function ga(){if(loaded)return;loaded=true;window.dataLayer=window.dataLayer||[];window.gtag=function(){dataLayer.push(arguments);};gtag("js",new Date());gtag("set","linker",{domains:["nordicspecialhospital.dk","nordicreumatologi.dk","nordicosteoporose.dk","nordicdexascan.dk"]});gtag("config",ID);if(ID==="G-PREVIEW00")return;var s=document.createElement("script");s.async=true;s.src="https://www.googletagmanager.com/gtag/js?id="+encodeURIComponent(ID);document.head.appendChild(s);}'
			. 'function slet(){document.cookie.split("; ").forEach(function(c){var n=c.split("=")[0];if(/^_ga/.test(n)){var h=location.hostname.replace(/^www\./,"");["",h,"."+h].forEach(function(d){document.cookie=n+"=; Max-Age=0; Path=/"+(d?"; Domain="+d:"");});}});}'
			. 'function vaelg(v){set(v);box.hidden=true;if(v==="statistik"){ga();}else{if(loaded){location.reload();}slet();}}'
			. 'box.addEventListener("click",function(e){var b=e.target.closest("[data-nsk]");if(b)vaelg(b.getAttribute("data-nsk"));});'
			. 'var v=get();if(v==="statistik"){ga();}else if(!v){box.hidden=false;}'
			// Link "Cookie-indstillinger" ved footerens cookie-link.
			. 'var fl=Array.prototype.slice.call(document.querySelectorAll("footer a[href*=\"/cookies\"],[data-elementor-type=footer] a[href*=\"/cookies\"]"));var a=fl[fl.length-1];'
			. 'if(a){var b=document.createElement("button");b.type="button";b.className="nsk-indst";b.textContent="Cookie-indstillinger";b.addEventListener("click",function(){box.hidden=false;var f=box.querySelector("button");if(f)f.focus();});var sp=document.createTextNode(" | ");a.parentNode.insertBefore(sp,a.nextSibling);a.parentNode.insertBefore(b,sp.nextSibling);}'
			// Hændelser (sendes kun, når GA er indlæst efter samtykke).
			. 'function ev(n,p){if(loaded&&window.gtag)gtag("event",n,p||{});}'
			. 'document.addEventListener("click",function(e){var l=e.target.closest("a[href]");if(!l)return;var h=l.getAttribute("href");if(/^tel:/i.test(h))ev("phone_click",{link_url:h});else if(/^mailto:/i.test(h))ev("email_click",{link_url:h});},true);'
			. 'if(window.jQuery){jQuery(document).on("fluentform_submission_success",function(){ev("generate_lead",{form:"anmod_om_tid"});});}'
			. 'document.addEventListener("fluentform_submission_success",function(){ev("generate_lead",{form:"anmod_om_tid"});});'
			. '})();</script>' . "\n";
	}, 99 );
}

/* Fejlsiden hed "Page Not Found" (runde 3 T6). */
add_filter( 'rank_math/frontend/title', function ( $t ) { return is_404() ? 'Siden findes ikke - Nordic DEXA Scan' : $t; }, 99 );
add_filter( 'pre_get_document_title', function ( $t ) { return is_404() ? 'Siden findes ikke - Nordic DEXA Scan' : $t; }, 99 );
