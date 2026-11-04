<?php
/**
 * Plugin Name: Nordic kvalitet
 * Description: SEO- og kvalitetstillæg for Nordic-sites: strukturerede data (klinik, brødkrumme, FAQ), Open Graph, billedattributter, brødkrumme-navigation, sikkerhedsheaders, sitemap og oprydning i WordPress-standarder. Ændrer ingen synlig tekst.
 * Version: 1.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* Klinikdata pr. domæne (fra udkastene). */
function nordic_k_site() {
	$h = strtolower( (string) ( $_SERVER['HTTP_HOST'] ?? '' ) );
	$base = array(
		'telephone' => '+4577340036', 'email' => 'info@nordicspecialhospital.dk',
		'address' => array( '@type' => 'PostalAddress', 'streetAddress' => 'Laurits Olsens Vej 1, 1. sal', 'postalCode' => '2650', 'addressLocality' => 'Hvidovre', 'addressCountry' => 'DK' ),
		'hours' => array( array( array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday' ), '08:00', '17:00' ), array( array( 'Friday' ), '08:00', '15:30' ) ),
	);
	if ( false !== strpos( $h, 'nordicreumatologi' ) ) { return $base + array( 'name' => 'Nordic Reumatologi', 'og' => 'og-reumatologi.png', 'specialty' => 'Rheumatologic' ); }
	if ( false !== strpos( $h, 'nordicosteoporose' ) ) { return $base + array( 'name' => 'Nordic Osteoporose', 'og' => 'og-osteoporose.png', 'specialty' => '' ); }
	return $base + array( 'name' => 'Nordic Specialklinik', 'og' => 'og-specialklinik.png', 'specialty' => '' );
}

/* Sidens metabeskrivelse (fra "Nordic site"-plugin'et eller Specialklinikkens snippet). */
function nordic_k_desc() {
	if ( function_exists( 'nordic_seo_current' ) ) { $s = nordic_seo_current(); if ( $s ) { return $s[1]; } }
	if ( is_singular() ) { $s = get_post_meta( get_queried_object_id(), '_nordic_seo', true ); if ( ! empty( $s['meta'] ) ) { return $s['meta']; } }
	return '';
}

function nordic_k_url() {
	if ( is_front_page() ) { return home_url( '/' ); }
	if ( is_singular() ) { return get_permalink( get_queried_object_id() ); }
	return home_url( add_query_arg( array() ) );
}

/* Open Graph, Twitter og klinikkens strukturerede data */
add_action( 'wp_head', function () {
	if ( is_admin() || ! ( is_singular() || is_front_page() ) || ! empty( $GLOBALS['nordic_k_is404'] ) ) { return; }
	$s = nordic_k_site();
	$title = wp_get_document_title();
	$desc = nordic_k_desc();
	$url = nordic_k_url();
	$img = home_url( '/nordic-media/og/' . $s['og'] );
	$m = array(
		'og:type' => 'website', 'og:site_name' => $s['name'], 'og:locale' => 'da_DK', 'og:title' => $title, 'og:url' => $url,
		'og:image' => $img, 'og:image:width' => '1200', 'og:image:height' => '630', 'og:image:alt' => $s['name'],
	);
	if ( $desc ) { $m['og:description'] = $desc; }
	foreach ( $m as $k => $v ) { echo '<meta property="' . esc_attr( $k ) . '" content="' . esc_attr( $v ) . '">' . "\n"; }
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	$hours = array();
	foreach ( $s['hours'] as $x ) { $hours[] = array( '@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $x[0], 'opens' => $x[1], 'closes' => $x[2] ); }
	$clinic = array(
		'@type' => 'MedicalClinic', '@id' => home_url( '/#klinik' ), 'name' => $s['name'], 'url' => home_url( '/' ),
		'image' => $img, 'logo' => home_url( '/nordic-media/billeder/logo-mark-navy.png' ),
		'telephone' => $s['telephone'], 'email' => $s['email'], 'address' => $s['address'],
		'openingHoursSpecification' => $hours, 'areaServed' => 'DK', 'availableLanguage' => 'da',
		'parentOrganization' => array( '@type' => 'MedicalOrganization', 'name' => 'Nordic Specialhospital', 'url' => 'https://nordicspecialhospital.dk/' ),
	);
	if ( $s['specialty'] ) { $clinic['medicalSpecialty'] = $s['specialty']; }
	if ( is_front_page() && $desc ) { $clinic['description'] = $desc; }
	// Placering og Google-profil (koordinater og place_id fra Google Maps for Laurits Olsens Vej 1, Hvidovre)
	$clinic['geo'] = array( '@type' => 'GeoCoordinates', 'latitude' => 55.6634844, 'longitude' => 12.4770292 );
	$clinic['hasMap'] = 'https://www.google.com/maps/place/?q=place_id:ChIJ9bwYyo9RUkYRD2x-2cOCUH0';
	$clinic['isAcceptingNewPatients'] = true;
	$sister = array( 'https://nordicspecialhospital.dk/', 'https://nordicreumatologi.dk/', 'https://nordicosteoporose.dk/', 'https://nordicdexascan.dk/' );
	$clinic['parentOrganization']['subOrganization'] = array();
	foreach ( $sister as $u ) { if ( home_url( '/' ) !== $u ) { $clinic['parentOrganization']['subOrganization'][] = array( '@type' => 'MedicalClinic', 'url' => $u ); } }
	$graph = array( $clinic, array( '@type' => 'WebSite', '@id' => home_url( '/#websted' ), 'url' => home_url( '/' ), 'name' => $s['name'], 'inLanguage' => 'da-DK', 'publisher' => array( '@id' => home_url( '/#klinik' ) ) ) );
	echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "</script>\n";
}, 5 );

/* Efterbehandling af den færdige side: billedattributter, brødkrumme, FAQ- og brødkrumme-data, sikre links. */
function nordic_k_sizes() { return array('dexa-scanner-med-leje-i-et-lyst-undersoegelsesrum.webp'=>array(600,900), 'google-ikon.png'=>array(94,96), 'ikon-0fc7e1f50c.svg'=>array(17,17), 'ikon-1e6dfd3500.svg'=>array(22,22), 'ikon-208e749951.svg'=>array(20,20), 'ikon-26f9ac88ca.svg'=>array(24,24), 'ikon-27c494020c.svg'=>array(24,24), 'ikon-2cea039906.svg'=>array(22,22), 'ikon-335df1ba49.svg'=>array(16,16), 'ikon-43ba532814.svg'=>array(22,22), 'ikon-4d7899ddcb.svg'=>array(16,16), 'ikon-508761df0c.svg'=>array(22,22), 'ikon-54fb8344f2.svg'=>array(24,24), 'ikon-54fdae5c8c.svg'=>array(15,15), 'ikon-5f9c06fb54.svg'=>array(22,22), 'ikon-6c3c706d1a.svg'=>array(24,24), 'ikon-786e8d3acd.svg'=>array(22,22), 'ikon-8e6eb26c56.svg'=>array(24,24), 'ikon-930f295ee5.svg'=>array(14,14), 'ikon-a2d7d7d6f1.svg'=>array(200,400), 'ikon-c9ff983112.svg'=>array(16,16), 'ikon-df6f7a4d4c.svg'=>array(18,18), 'ikon-e9828ae22b.svg'=>array(18,18), 'ikon-f10e5612ce.svg'=>array(22,22), 'ikon-f8fb4c11b5.svg'=>array(200,360), 'ikon-telefon-guld.svg'=>array(24,24), 'ikon-telefon-navy.svg'=>array(24,24), 'illustration-af-blodtryksmaaling-ved-laegeundersoegelse.webp'=>array(1440,990), 'illustration-af-dexa-scanning.webp'=>array(1440,990), 'illustration-af-et-lyst-venteomraade-i-nordisk-stil.webp'=>array(1440,1080), 'illustration-af-laegefaglig-raadgivning-til-medarbejdere.webp'=>array(1440,990), 'illustration-af-moderne-klinikindretning.webp'=>array(1440,1080), 'illustration-af-samtale-om-medicinsk-vaegtbehandling.webp'=>array(1440,990), 'kvinde-gaar-en-tur-paa-en-kyststi.webp'=>array(600,900), 'kvinde-ved-skrivebord-der-tager-noter.webp'=>array(1400,788), 'laege-gennemgaar-resultater-paa-en-tablet-sammen-med-en-aeld.webp'=>array(1536,1152), 'laege-og-patient-ser-paa-et-roentgenbillede-af-en-haand.webp'=>array(1000,667), 'laege-undersoeger-leddene-i-en-patients-haand.webp'=>array(600,900), 'laege-viser-resultater-paa-tablet.webp'=>array(750,1000), 'logo-mark-hvid.png'=>array(160,160), 'logo-mark-navy.png'=>array(160,160), 'mand-bruger-sin-telefon.webp'=>array(1440,1080), 'mand-loeber-en-tur-paa-en-kyststi.webp'=>array(1400,788), 'og-osteoporose.png'=>array(1200,630), 'og-reumatologi.png'=>array(1200,630), 'og-specialklinik.png'=>array(1200,630), 'par-paa-gaatur-i-skoven.webp'=>array(1400,788)); }

function nordic_k_fix( $html ) {
	if ( false === stripos( $html, '<html' ) ) { return $html; }
	$sizes = nordic_k_sizes();
	$first = true;
	$main = stripos( $html, 'id="hovedindhold"' );
	if ( false === $main ) { $main = stripos( $html, 'id="content"' ); } // fx Specialklinikkens forside
	$html = preg_replace_callback( '/<img\b[^>]*>/i', function ( $m ) use ( $sizes, &$first, $main, &$html ) {
		$t = $m[0];
		$t = preg_replace( '/\sid="\d+"/', '', $t ); // Elementor giver billeder vedhæftnings-id som HTML-id (dubletter)
		if ( ! preg_match( '/\swidth=/i', $t ) && preg_match( '/\ssrc="([^"]+)"/i', $t, $s ) ) {
			$f = basename( strtok( $s[1], '?' ) );
			$wh = isset( $sizes[ $f ] ) ? $sizes[ $f ] : null;
			if ( ! $wh && false !== strpos( $s[1], '/wp-content/uploads/' ) ) {
				$id = attachment_url_to_postid( $s[1] );
				if ( $id ) { $src = wp_get_attachment_image_src( $id, 'full' ); if ( $src ) { $wh = array( $src[1], $src[2] ); } }
			}
			if ( $wh ) { $t = preg_replace( '/^<img\b/i', '<img width="' . (int) $wh[0] . '" height="' . (int) $wh[1] . '"', $t ); }
		}
		if ( ! preg_match( '/\sdecoding=/i', $t ) ) { $t = preg_replace( '/^<img\b/i', '<img decoding="async"', $t ); }
		return $t;
	}, $html );
	// Første indholdsbillede indlæses straks (LCP), resten dovent.
	if ( false !== $main ) {
		$pos = $main;
		// Første rasterbillede (ikke SVG-ikoner) er typisk sidens LCP-billede.
		if ( preg_match( '/<img\b(?![^>]*\.svg["\s?])[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE, $pos ) ) {
			$t = $m[0][0];
			$n = preg_replace( '/\sloading="lazy"/i', '', $t );
			$n = preg_replace( '/^<img\b/i', '<img fetchpriority="high"', $n );
			$html = substr_replace( $html, $n, $m[0][1], strlen( $t ) );
			$after = $m[0][1] + strlen( $n );
			$html = substr( $html, 0, $after ) . preg_replace_callback( '/<img\b[^>]*>/i', function ( $x ) {
				return preg_match( '/\sloading=/i', $x[0] ) ? $x[0] : preg_replace( '/^<img\b/i', '<img loading="lazy"', $x[0] );
			}, substr( $html, $after ) );
		}
	}
	// Eksterne links i nyt vindue: rel="noopener"
	$html = preg_replace_callback( '/<a\b[^>]*target="_blank"[^>]*>/i', function ( $m ) {
		return preg_match( '/\srel=/i', $m[0] ) ? $m[0] : preg_replace( '/^<a\b/i', '<a rel="noopener"', $m[0] );
	}, $html );
	// Temaets <main id="content"> omkring siden giver to main-landmarks: gør det til en div, når siden har sit eget <main>.
	if ( preg_match( '/<main\b[^>]*id="hovedindhold"/', $html ) && preg_match( '/<main id="content"([^>]*)>/', $html ) ) {
		$html = preg_replace( '/<main id="content"([^>]*)>/', '<div id="content"$1>', $html, 1 );
		$p = strrpos( $html, '</main>' );
		if ( false !== $p ) { $html = substr_replace( $html, '</div>', $p, 7 ); }
	}
	// Sider uden <main>: sidens indhold (#hovedindhold) får main-rollen
	if ( ! preg_match( '/<main\b/i', $html ) ) {
		$html = preg_replace( '/(<div\b(?![^>]*\brole=)[^>]*\bid="hovedindhold")/', '$1 role="main"', $html, 1 );
	}
	// Navigationsnavne fra udkastet (header og footer)
	$labels = array( '74678d6b' => 'Klinikker i Nordic Specialhospital', '527d3265' => 'Hovedmenu', '5dada8e0' => 'Specialklinikker', '5dada8e0o' => 'Specialklinikker' );
	if ( 'Nordic Osteoporose' === nordic_k_site()['name'] ) { $labels['5dada8e0'] = 'Osteoporose'; }
	foreach ( $labels as $id => $lab ) {
		$html = preg_replace( '/(<nav\b(?![^>]*aria-label)[^>]*\bdata-id="' . $id . '")/', '$1 aria-label="' . $lab . '"', $html, 1 );
	}
	// Pingback-link (XML-RPC er slået fra)
	$html = preg_replace( '/<link rel="pingback"[^>]*>\s*/i', '', $html );
	// Temaets forældede profil-link
	$html = preg_replace( '/<link rel="profile" href="http:\/\/gmpg\.org\/xfn\/11"\s*\/?>\s*/i', '', $html );
	// Brødkrumme: navigationsnavn, aktuel side og strukturerede data
	if ( false !== $main && class_exists( 'DOMDocument' ) ) {
		$ld = array();
		$doc = new DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8"?>' . $html );
		libxml_clear_errors();
		$xp = new DOMXPath( $doc );
		$mainEl = $xp->query( '//*[@id="hovedindhold"]' )->item( 0 );
		$mods = array();
		if ( $mainEl ) {
			foreach ( $xp->query( './/nav', $mainEl ) as $nav ) {
				$txt = trim( preg_replace( '/\s+/u', ' ', $nav->textContent ) );
				if ( 0 !== strpos( $txt, 'Forside /' ) ) { continue; }
				if ( ! $nav->hasAttribute( 'aria-label' ) && $nav->getAttribute( 'data-id' ) ) { $mods[ $nav->getAttribute( 'data-id' ) ] = ' aria-label="Brødkrumme"'; }
				$items = array(); $i = 1; $last = null;
				foreach ( $xp->query( './/a|.//span', $nav ) as $el ) {
					$t = trim( preg_replace( '/\s+/u', ' ', $el->textContent ) );
					if ( '' === $t || '/' === $t ) { continue; }
					if ( 'a' === strtolower( $el->nodeName ) ) {
						$items[] = array( '@type' => 'ListItem', 'position' => $i++, 'name' => $t, 'item' => esc_url_raw( home_url( wp_make_link_relative( $el->getAttribute( 'href' ) ) ) ) );
					} else { $last = array( $el, $t ); }
				}
				if ( $last ) {
					$items[] = array( '@type' => 'ListItem', 'position' => $i, 'name' => $last[1], 'item' => nordic_k_url() );
					if ( ! $last[0]->hasAttribute( 'aria-current' ) && $last[0]->getAttribute( 'data-interaction-id' ) ) { $mods[ $last[0]->getAttribute( 'data-interaction-id' ) ] = ' aria-current="page"'; }
				}
				if ( count( $items ) > 1 ) { $ld[] = array( '@type' => 'BreadcrumbList', 'itemListElement' => $items ); }
				break;
			}
			// FAQ: <details> i sektioner med overskriften "Ofte stillede spørgsmål"
			$qa = array();
			foreach ( $xp->query( './/section|.//*[contains(concat(" ",@class," ")," e-con ")][h2]', $mainEl ) as $sec ) {
				$hit = false;
				foreach ( $xp->query( './/h2', $sec ) as $h2 ) { if ( false !== mb_stripos( $h2->textContent, 'Ofte stillede spørgsmål' ) ) { $hit = true; } }
				if ( ! $hit ) { continue; }
				foreach ( $xp->query( './/details', $sec ) as $d ) {
					$sum = $xp->query( './/summary', $d )->item( 0 );
					if ( ! $sum ) { continue; }
					$q = trim( preg_replace( '/\s+/u', ' ', $sum->textContent ) );
					$a = trim( preg_replace( '/\s+/u', ' ', substr( $d->textContent, strlen( $sum->textContent ) ) ) );
					if ( $q && $a ) { $qa[ $q ] = array( '@type' => 'Question', 'name' => $q, 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $a ) ); }
				}
			}
			if ( $qa ) { $ld[] = array( '@type' => 'FAQPage', 'mainEntity' => array_values( $qa ) ); }
			// Medicinsk side: overskrift, sprog, målgruppe, klinik som udgiver og "Senest opdateret" fra sidens egen tekst.
			$h1 = $xp->query( './/h1', $mainEl )->item( 0 );
			if ( $h1 && ! is_front_page() && empty( $GLOBALS['nordic_k_is404'] ) ) {
				$name = trim( preg_replace( '/\s+/u', ' ', $h1->textContent ) );
				$page = array( '@type' => 'MedicalWebPage', '@id' => nordic_k_url() . '#side', 'url' => nordic_k_url(), 'name' => $name,
					'inLanguage' => 'da-DK', 'isPartOf' => array( '@id' => home_url( '/#websted' ) ), 'publisher' => array( '@id' => home_url( '/#klinik' ) ),
					'audience' => array( '@type' => 'MedicalAudience', 'audienceType' => 'Patient' ) );
				$si = nordic_k_site();
				if ( $si['specialty'] ) { $page['specialty'] = $si['specialty']; }
				$months = array( 'januar' => '01', 'februar' => '02', 'marts' => '03', 'april' => '04', 'maj' => '05', 'juni' => '06', 'juli' => '07', 'august' => '08', 'september' => '09', 'oktober' => '10', 'november' => '11', 'december' => '12' );
				if ( preg_match( '/Senest opdateret:?\s*(' . implode( '|', array_keys( $months ) ) . ')\s+(\d{4})/iu', $mainEl->textContent, $mm ) ) {
					$page['lastReviewed'] = $mm[2] . '-' . $months[ mb_strtolower( $mm[1] ) ];
				}
				$path = (string) wp_parse_url( nordic_k_url(), PHP_URL_PATH );
				if ( in_array( $path, array( '/cookies/', '/privatlivspolitik/', '/anmod-om-tid/' ), true ) ) { $page['@type'] = 'WebPage'; unset( $page['audience'], $page['specialty'] ); }
				if ( '/kontakt/' === $path ) { $page['@type'] = 'ContactPage'; unset( $page['audience'], $page['specialty'] ); }
				if ( preg_match( '#^/(sygdomme|idraetsmedicin)/[^/]+/$#', $path ) || in_array( $path, array( '/osteoporose/', '/osteopeni/', '/osteoporose-hos-maend/' ), true ) ) {
					$page['about'] = array( '@type' => 'MedicalCondition', 'name' => $name );
				}
				$pdesc = nordic_k_desc();
				if ( $pdesc ) { $page['description'] = $pdesc; }
				$ld[] = $page;
			}
		}
		// Kun læsning med DOM; ændringer lægges ind i de oprindelige tags (siden genskrives ikke).
		foreach ( $mods as $id => $attr ) {
			$html = preg_replace( '/(<(?:nav|span|a)\b[^>]*\bdata-(?:id|interaction-id)="' . preg_quote( $id, '/' ) . '")/', '$1' . $attr, $html, 1 );
		}
		if ( $ld ) {
			$json = '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $ld ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "</script>\n";
			$html = preg_replace( '/<\/head>/i', $json . '</head>', $html, 1 );
		}
	}
	// Tegnsætning lige efter et link i løbende tekst: fjern mellemrummet mellem </a> og punktummet/kommaet (som i udkastet).
	$html = preg_replace( '/\s*<\/a>\s*(<span\b[^>]*\bclass="[^"]*e-paragraph-base[^"]*"[^>]*>[.,;:!?]{1,3}<\/span>)/', '</a>$1', $html );
	return $html;
}
add_action( 'template_redirect', function () {
	if ( is_admin() || is_feed() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || isset( $_GET['elementor-preview'] ) ) { return; }
	ob_start( 'nordic_k_fix' );
}, 0 );

/* Sikkerhedsheaders. HSTS uden includeSubDomains (rammer kun det enkelte domæne).
   CSP: alt fra eget domæne; inline er tilladt, fordi Elementor og Fluent Forms bruger inline-kode.
   CSP sendes som Report-Only: håndhævet CSP fik Googles Lighthouse til at fejle (robots.txt-hentning, Speed Index 15 s -> 2 s, SEO 92 -> 100).
   CSP sendes ikke til indloggede brugere, så Elementor-editoren og adminbjælken ikke påvirkes. */
add_action( 'send_headers', function () {
	if ( is_admin() ) { return; }
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	header( 'X-Frame-Options: SAMEORIGIN' );
	header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()' );
	if ( is_ssl() ) { header( 'Strict-Transport-Security: max-age=31536000' ); }
	if ( ! is_user_logged_in() && ! isset( $_GET['elementor-preview'] ) ) {
		header( "Content-Security-Policy-Report-Only: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src 'self'; frame-src 'self'; worker-src 'self' blob:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'" );
	}
} );

/* Oprydning: ingen XML-RPC, ingen versionsnummer, ingen emoji-scripts, ingen brugeropremsning */
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'wp_headers', function ( $h ) { unset( $h['X-Pingback'] ); return $h; } );
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'wp_shortlink_wp_head' );
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
add_filter( 'emoji_svg_url', '__return_false' );
add_filter( 'rest_endpoints', function ( $e ) {
	if ( ! is_user_logged_in() ) { unset( $e['/wp/v2/users'], $e['/wp/v2/users/(?P<id>[\d]+)'] ); }
	return $e;
} );
add_action( 'template_redirect', function () {
	if ( isset( $_GET['author'] ) && ! is_user_logged_in() ) { wp_safe_redirect( home_url( '/' ), 301 ); exit; }
	if ( is_author() || is_attachment() ) { wp_safe_redirect( home_url( '/' ), 301 ); exit; }
}, 1 );

/* Almindelig bindestreg i sidetitler (fx 404-siden) i stedet for lang tankestreg */
add_filter( 'document_title_separator', function () { return '-'; } );
add_filter( 'document_title', function ( $t ) { return str_replace( array( '&#8211;', '&#8212;', '–', '—' ), '-', $t ); }, 99 );

/* Sitemap: kun sider */
add_filter( 'wp_sitemaps_add_provider', function ( $p, $name ) { return in_array( $name, array( 'users', 'taxonomies' ), true ) ? false : $p; }, 10, 2 );
add_filter( 'wp_sitemaps_post_types', function ( $t ) { return array_intersect_key( $t, array( 'page' => 1 ) ); } );
add_filter( 'wp_sitemaps_posts_entry', function ( $e, $post ) { $e['lastmod'] = get_post_modified_time( 'c', true, $post ); return $e; }, 10, 2 );

/* LiteSpeed: kombinér CSS til én fil (testet: identisk layout på 1440 og 390). Sættes én gang; kan slås fra i LiteSpeed > Sideoptimering. */
add_action( 'init', function () {
	if ( get_option( 'nordic_k_csscomb' ) ) { return; }
	update_option( 'litespeed.conf.optm-css_comb', true );
	update_option( 'nordic_k_csscomb', 1, false );
} );

/* 404-side i klinikkens design: indholdet redigeres i Elementor på siden "Siden findes ikke" (slug side-ikke-fundet eller siden-findes-ikke). */
function nordic_k_404_page() {
	$p = get_page_by_path( 'side-ikke-fundet' );
	if ( ! $p ) { $p = get_page_by_path( 'siden-findes-ikke' ); }
	return ( $p && 'publish' === $p->post_status ) ? $p : null;
}
/* 301-omdirigeringer fra de gamle sites' adresser (godkendt af Sheraz 2026-11-04). Gælder kun adresser, der ellers ville give 404. */
function nordic_k_redirects() {
	$s = 'https://nordicspecialhospital.dk';
	$maps = array(
		'nordicspecialhospital.dk' => array(
			'/fortrolighedspolitik/' => '/privatlivspolitik/',
			'/information/' => '/patientinformation/', '/offentlig-patient/' => '/patientinformation/',
			'/forsikringspatient/' => '/patientinformation/', '/privat-patient/' => '/patientinformation/',
			'/specialer/' => '/#klinikker',
			'/overvaegt/' => '/medicinsk-vaegtbehandling/', '/overvaegt-og-hormonsygdomme-2/' => '/medicinsk-vaegtbehandling/',
			'/reumatologi-og-idraetsmedicin/' => 'https://nordicreumatologi.dk/',
			'/knogleskorhed/' => 'https://nordicosteoporose.dk/',
			'/dexa/' => 'https://nordicdexascan.dk/',
			'/flyvemedicin/' => '/laegeattester/', '/flyvemedicin-class-2/' => '/laegeattester/', '/flyvemedicin-cabin-crew/' => '/laegeattester/',
			'/kardiologi/' => '/helbredsundersogelse/',
			'/extra-sections/' => '/', '/maintenance-page/' => '/',
		),
		'nordicreumatologi.dk' => array(
			'/about/' => '/om-os/',
			'/reumatologi-og-idraetsmedicin/' => '/reumatologi/',
			'/offentlig-patient/' => '/patientinformation/', '/forsikringspatient/' => '/patientinformation/',
			'/privat-patient/' => '/patientinformation/', '/praktisk/' => '/patientinformation/',
			'/privatlivspolitik/' => $s . '/privatlivspolitik/',
			'/helbredsundersogelse/' => $s . '/helbredsundersogelse/',
			'/overvaegt-og-hormonsygdomme/' => $s . '/medicinsk-vaegtbehandling/',
			'/dexa-skanning/' => 'https://nordicdexascan.dk/',
			'/neurologi/' => '/', '/allergi/' => '/',
			'/elementskit-content/*' => '/', '/elementor-hf/*' => '/',
		),
	);
	$h = preg_replace( '/^(www\.|dev\.)/', '', strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) );
	return $maps[ $h ] ?? array();
}
add_action( 'template_redirect', function () {
	if ( ! is_404() ) { return; }
	$p = (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH ); // phpcs:ignore
	$p = '/' . trim( strtolower( $p ), '/' ) . '/';
	foreach ( nordic_k_redirects() as $from => $to ) {
		$hit = ( '/*' === substr( $from, -2 ) ) ? 0 === strpos( $p, substr( $from, 0, -1 ) ) : $p === $from;
		if ( $hit ) {
			wp_redirect( 0 === strpos( $to, 'http' ) ? $to : home_url( $to ), 301, 'Nordic' ); // phpcs:ignore
			exit;
		}
	}
}, 0 );

// Ved 404 vises siden som en almindelig Elementor-side (så dens stilark indlæses), men med status 404.
add_action( 'template_redirect', function () {
	if ( ! is_404() ) { return; }
	$p = nordic_k_404_page();
	if ( ! $p ) { return; }
	global $wp_query, $wp_the_query, $post;
	$wp_query = new WP_Query( array( 'page_id' => $p->ID ) ); // phpcs:ignore
	$wp_the_query = $wp_query; // phpcs:ignore
	$post = $p; // phpcs:ignore
	setup_postdata( $post );
	$GLOBALS['nordic_k_is404'] = true;
	remove_action( 'wp_head', 'rel_canonical' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
	status_header( 404 );
	nocache_headers();
}, 1 );
add_filter( 'pre_get_document_title', function ( $t ) {
	return empty( $GLOBALS['nordic_k_is404'] ) ? $t : 'Siden findes ikke - ' . nordic_k_site()['name'];
}, 99 );
add_filter( 'wp_robots', function ( $r ) {
	if ( is_page( array( 'side-ikke-fundet', 'siden-findes-ikke' ) ) ) { $r['noindex'] = true; }
	return $r;
} );
add_filter( 'wp_sitemaps_posts_query_args', function ( $args ) {
	$p = nordic_k_404_page();
	if ( $p ) { $args['post__not_in'] = array_merge( (array) ( $args['post__not_in'] ?? array() ), array( $p->ID ) ); }
	return $args;
} );

/* Hastighed: egne skrifttyper (kun de brugte vægte, font-display: swap) i stedet for Google Fonts med alle vægte,
   og ingen ikon- og widget-styles, som siderne ikke bruger. */
function nordic_k_fonts() {
	return array( array( 'Manrope', 'manrope', array( 400, 500, 600, 700 ) ), array( 'Montserrat', 'montserrat', array( 400, 500, 600 ) ), array( 'Cormorant Garamond', 'cormorant-garamond', array( 500, 600, 700 ) ) );
}
add_filter( 'elementor/frontend/print_google_fonts', '__return_false' );
add_action( 'wp_enqueue_scripts', function () {
	foreach ( array( 'hfe-elementor-icons', 'hfe-icons-list', 'hfe-social-icons', 'hfe-social-share-icons-brands', 'hfe-social-share-icons-fontawesome', 'hfe-nav-menu-icons', 'elementor-icons', 'fluent-form-styles', 'fluentform-elementor-widget' ) as $h ) { wp_dequeue_style( $h ); }
	wp_dequeue_script( 'hello-theme-frontend' );
	wp_dequeue_script( 'fluentform-elementor' );
}, 999 );
add_action( 'wp_print_styles', function () {
	global $wp_styles;
	if ( empty( $wp_styles->queue ) ) { return; }
	foreach ( $wp_styles->queue as $h ) { if ( 0 === strpos( $h, 'elementor-gf-' ) ) { wp_dequeue_style( $h ); } }
}, 1 );
add_action( 'wp_default_scripts', function ( $scripts ) {
	if ( ! is_admin() && isset( $scripts->registered['jquery'] ) ) { $scripts->registered['jquery']->deps = array_diff( $scripts->registered['jquery']->deps, array( 'jquery-migrate' ) ); }
} );
add_action( 'wp_head', function () {
	// Faneikon og ikon til hjemmeskærm (logomærket), medmindre der er valgt et webstedsikon i WordPress.
	if ( ! has_site_icon() ) {
		$ik = home_url( '/nordic-media/ikoner/' );
		echo '<link rel="icon" href="' . esc_url( $ik . 'favicon.ico' ) . '" sizes="any">' . "\n";
		echo '<link rel="icon" href="' . esc_url( $ik . 'favicon-32.png' ) . '" type="image/png" sizes="32x32">' . "\n";
		echo '<link rel="apple-touch-icon" href="' . esc_url( $ik . 'apple-touch-icon.png' ) . '">' . "\n";
	}
	// Link i samtykketeksten understreges som i udkastet (tydeligt link i tekst).
	echo '<style id="nordic-kvalitet-css">.frm-fluent-form .ff_t_c a,.frm-fluent-form .ff-el-tc a{text-decoration:underline}'
		// Meget smalle skærme (under 380 px) som udkastets header: mindre logo, ingen prikker i klinikbjælken
		. '@media (max-width:379px){.elementor-element-2306273f{gap:8px!important}[data-interaction-id="75138d6e"]{width:32px!important;height:32px!important}[data-interaction-id="7920e6e1"]{font-size:16px!important}.elementor-element-74678d6b a>.e-con:first-child{display:none!important}}</style>' . "\n";
	$base = home_url( '/nordic-media/fonts/' );
	foreach ( array( 'manrope-latin-400-normal', 'manrope-latin-700-normal', 'cormorant-garamond-latin-700-normal' ) as $f ) {
		echo '<link rel="preload" href="' . esc_url( $base . $f . '.woff2' ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
	}
	$css = '';
	foreach ( nordic_k_fonts() as $f ) {
		foreach ( $f[2] as $w ) {
			$css .= '@font-face{font-family:"' . $f[0] . '";font-style:normal;font-weight:' . $w . ';font-display:swap;src:url(' . $base . $f[1] . '-latin-' . $w . '-normal.woff2) format("woff2");unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}';
		}
	}
	echo '<style id="nordic-skrift">' . $css . "</style>\n";
}, 2 );

/* Menuer i headeren (dropdown, stor menu, mobilmenu): Esc og klik udenfor lukker, og kun én er åben ad gangen. */
add_action( 'wp_footer', function () {
	echo "<script>(function(){var h=document.querySelector('[data-elementor-type=\"header\"],header');if(!h)return;var D=function(){return h.querySelectorAll('details[open]')};"
		. "h.addEventListener('toggle',function(e){var t=e.target;if(t.tagName==='DETAILS'&&t.open){D().forEach(function(x){if(x!==t&&!x.contains(t)&&!t.contains(x))x.open=false})}},true);"
		. "document.addEventListener('keydown',function(e){if(e.key==='Escape'){D().forEach(function(x){x.open=false;var s=x.querySelector('summary');if(s&&x.contains(document.activeElement))s.focus()})}});"
		. "document.addEventListener('click',function(e){if(!h.contains(e.target))D().forEach(function(x){x.open=false})});})();</script>\n";
}, 99 );

/* Cache-opvarmning: efter tømning af LiteSpeed-cachen (og dagligt) hentes alle sider én ad gangen,
   så besøgende og Google altid får en cachet side, og databasen ikke belastes af mange samtidige kald. */
function nordic_k_warm() {
	$urls = array( home_url( '/' ) );
	foreach ( get_pages( array( 'post_status' => 'publish', 'number' => 200 ) ) as $p ) {
		if ( in_array( $p->post_name, array( 'side-ikke-fundet', 'siden-findes-ikke' ), true ) ) { continue; }
		$urls[] = get_permalink( $p );
	}
	foreach ( array_unique( $urls ) as $u ) {
		wp_remote_get( $u, array( 'timeout' => 20, 'redirection' => 0, 'user-agent' => 'Nordic cache warmer', 'sslverify' => true ) );
		usleep( 400000 );
	}
}
add_action( 'nordic_k_warm_event', 'nordic_k_warm' );
add_action( 'litespeed_purged_all', function () {
	if ( ! wp_next_scheduled( 'nordic_k_warm_event' ) ) { wp_schedule_single_event( time() + 30, 'nordic_k_warm_event' ); }
} );
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'nordic_k_warm_daily' ) ) { wp_schedule_event( time() + 3600, 'daily', 'nordic_k_warm_daily' ); }
} );
add_action( 'nordic_k_warm_daily', 'nordic_k_warm' );
