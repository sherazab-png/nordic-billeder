<?php
/**
 * Plugin Name: Nordic site
 * Description: Små tillæg til Nordic-sitet: sidetitel og metabeskrivelse fra udkastet, dansk skiplink og CSS, som Elementors stil-editor ikke kan gemme. Indeholder også en midlertidig importfunktion, som kun er aktiv, mens filen plan.enc findes.
 * Version: 1.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* Sidetitel og metabeskrivelse (gemt pr. side i _nordic_seo) */
add_filter( 'pre_get_document_title', function ( $t ) {
	if ( is_singular() ) { $s = get_post_meta( get_queried_object_id(), '_nordic_seo', true ); if ( ! empty( $s['title'] ) ) { return $s['title']; } }
	return $t;
}, 20 );
add_action( 'wp_head', function () {
	if ( is_singular() ) { $s = get_post_meta( get_queried_object_id(), '_nordic_seo', true ); if ( ! empty( $s['meta'] ) ) { echo '<meta name="description" content="' . esc_attr( $s['meta'] ) . '">' . "\n"; } }
}, 1 );

/* Dansk skiplink og mål for skiplinket */
add_filter( 'gettext', function ( $tr, $text ) { return ( 'Skip to main content' === $text || 'Skip to content' === $text ) ? 'Spring til indhold' : $tr; }, 20, 2 );
add_action( 'wp_footer', function () {
	echo "<script>(function(){var m=document.getElementById('hovedindhold');if(m){m.setAttribute('tabindex','-1');document.querySelectorAll('a.skip-link,a[href=\"#content\"]').forEach(function(a){a.setAttribute('href','#hovedindhold')});}})();</script>\n";
}, 99 );

/* CSS, som Elementors stil-editor ikke kan gemme (nummererede lister, finderens pil, formularens udseende) */
add_action( 'wp_head', function () {
	$f = '.frm-fluent-form';
	$css = '.nx-list-decimal{list-style-type:decimal}'
		. '.ns-fd-opt-h>:first-child{flex:1 1 auto;min-width:0}.ns-fd-opt-h>:last-child{flex:0 0 auto;width:auto;min-width:0}'
		. "$f{font-family:Manrope;color:#1C2B45}"
		. "$f fieldset{display:grid!important;grid-template-columns:repeat(auto-fit,minmax(min(100%,240px),1fr));column-gap:14px}"
		. "$f fieldset>*{grid-column:1/-1}$f fieldset>.ff-el-group:has(input[name=navn]),$f fieldset>.ff-el-group:has(input[name=telefon]){grid-column:auto}"
		. "$f .ff-el-group{margin-bottom:26px}"
		. "$f .ff-el-input--label{padding:0;margin:0 0 12px}$f .ff-el-input--label label{margin:0}"
		. "$f .ff-el-input--label label,$f .nsh-ff-legend{font-size:16px;font-weight:700;color:#102A4C;line-height:1.6}"
		. "$f .nsh-ff-legend{margin:0}$f .ff-custom_html:has(.nsh-ff-legend){margin-bottom:12px}"
		. "$f .ff-el-group:has(.ff-el-form-control){margin-bottom:14px}$f .ff-el-group:has(input[type=email]){margin-bottom:26px}"
		. "$f .ff-el-group:has(.ff-el-form-control) .ff-el-input--label{margin-bottom:6px}$f .ff-el-group:has(.ff-el-form-control) .ff-el-input--label label{font-size:14px;font-weight:600;color:#1C2B45}"
		. "$f .ff_list_inline .ff-el-input--content{display:flex;flex-wrap:wrap;gap:10px}"
		. "$f .ff_list_inline .ff-el-form-check{margin:0;display:block}"
		. "$f .ff_list_inline .ff-el-form-check-label{position:relative;display:inline-flex;align-items:center;min-height:44px;padding:11px 16px;border:1px solid #CBD6E4;border-radius:100px;background:#FFFFFF;color:#1C2B45;font-size:14.5px;font-weight:600;cursor:pointer;line-height:1.4}"
		. "$f .ff_list_inline .ff-el-form-check-label input{position:absolute;opacity:0;width:1px;height:1px;margin:0}"
		. "$f .ff_list_inline .ff-el-form-check-label:has(input:checked){background:#183A70;border-color:#183A70;color:#FFFFFF}"
		. "$f .ff_list_inline .ff-el-form-check-label:has(input:focus-visible){outline:2px solid #183A70;outline-offset:2px}"
		. "$f .ff-el-form-control{width:100%;box-sizing:border-box;border:1px solid #CBD6E4;border-radius:12px;padding:14px 16px;font:inherit;font-size:15.5px;color:#1C2B45;background:#FFFFFF}"
		. "$f .ff-el-form-control:focus{outline:2px solid #183A70;outline-offset:1px;border-color:#183A70}"
		. "$f .nsh-ff-note{margin:0;font-size:14px;line-height:1.6;color:#8A4B2A;background:#FBF4EC;border:1px solid #EAD7C0;border-radius:12px;padding:12px 14px}"
		. "$f .ff-el-tc{font-size:14px;color:#3A4A63}$f .ff-el-tc a{color:#183A70}"
		. "$f .ff-btn-submit{background:#183A70!important;color:#FFFFFF!important;border:0;padding:17px 30px;border-radius:100px;font-family:Manrope;font-weight:700;font-size:15px}"
		. "$f .ff-btn-submit:focus-visible{outline:2px solid #183A70;outline-offset:2px}"
		. "$f .ff_submit_btn_wrapper{margin-bottom:0}";
	$css .= (string) get_option( 'nordic_css', '' );
	echo '<style id="nordic-tillaeg">' . $css . "</style>\n";
}, 99 );

/* Interaktive dele (risikotest og T-score), kun på sites, der har filen */
if ( is_readable( __DIR__ . '/interaktiv.php' ) ) { require_once __DIR__ . '/interaktiv.php'; }

if ( ! is_readable( __DIR__ . '/plan.enc' ) ) { return; }

/* Midlertidig forhåndsvisning bag vedligeholdelsestilstand: kun med cookien nordic_preview = nøglen (kun mens plan.enc findes). */
add_action( 'plugins_loaded', function () {
	if ( empty( $_COOKIE['nordic_preview'] ) ) { return; }
	$enc = json_decode( (string) file_get_contents( __DIR__ . '/plan.enc' ), true );
	if ( ! is_array( $enc ) || ! hash_equals( (string) $enc['check'], hash( 'sha256', (string) $_COOKIE['nordic_preview'] ) ) ) { return; }
	add_action( 'init', function () {
		$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'orderby' => 'ID' ) );
		if ( $admins ) { wp_set_current_user( $admins[0]->ID ); }
	}, 0 );
	add_filter( 'show_admin_bar', '__return_false', 99 );
}, 1 );

/*
 * Kaldes med POST til /?nordic_import=1. Brødteksten er nøglen (hex). Planen (plan.enc) er krypteret med AES-256-CBC.
 * Hvert kald udfører trin, til der er gået ca. 20 sekunder, og svarer med status som JSON.
 * Tilstand gemmes i optionen nordic_import_state. POST /?nordic_import=reset nulstiller tilstanden.
 */
add_action( 'init', function () {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! isset( $_GET['nordic_import'] ) ) { return; }
	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	$key_hex = trim( (string) file_get_contents( 'php://input' ) );
	$file = __DIR__ . '/plan.enc';
	if ( ! is_readable( $file ) ) { echo wp_json_encode( array( 'error' => 'ingen plan' ) ); exit; }
	$enc = json_decode( file_get_contents( $file ), true );
	if ( ! is_array( $enc ) || ! hash_equals( (string) $enc['check'], hash( 'sha256', $key_hex ) ) ) { status_header( 403 ); echo '{"error":"forkert noegle"}'; exit; }
	$plain = openssl_decrypt( base64_decode( $enc['ct'] ), 'aes-256-cbc', hex2bin( $key_hex ), OPENSSL_RAW_DATA, base64_decode( $enc['iv'] ) );
	$plan = json_decode( (string) $plain, true );
	if ( ! is_array( $plan ) ) { echo '{"error":"plan kan ikke laeses"}'; exit; }
	if ( 'reset' === $_GET['nordic_import'] ) { delete_option( 'nordic_import_state' ); echo '{"reset":true}'; exit; }
	if ( 'export' === $_GET['nordic_import'] ) {
		// Header/footer med klasser og variabler, til kopiering til et andet Nordic-site (samme format som Specialklinikkens eksport).
		$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'orderby' => 'ID' ) );
		wp_set_current_user( $admins[0]->ID );
		$st0 = get_option( 'nordic_import_state', array() );
		$o = array( 'classes' => nordic_imp_ability( 'elementor/read-resource', array( 'uri' => 'elementor://global-classes' ) ),
			'vars' => nordic_imp_ability( 'elementor/read-resource', array( 'uri' => 'elementor://global-variables' ) ), 'posts' => array() );
		foreach ( array( 'hf:type_header', 'hf:type_footer' ) as $k ) {
			if ( empty( $st0['vars'][ $k ] ) ) { continue; }
			$pid = (int) $st0['vars'][ $k ]; $m = array();
			foreach ( array( '_elementor_data', '_elementor_page_settings', '_elementor_template_type', '_elementor_edit_mode', '_elementor_version', '_wp_page_template', 'ehf_target_include_locations', 'ehf_target_exclude_locations', 'ehf_target_user_roles', 'ehf_template_type' ) as $mk ) { $m[ $mk ] = get_post_meta( $pid, $mk, true ); }
			$o['posts'][ $pid ] = array( 'post_title' => get_the_title( $pid ), 'meta' => $m );
		}
		echo wp_json_encode( $o ); exit;
	}
	if ( 'dump' === $_GET['nordic_import'] ) {
		// Viser header/footer-data, så rettelser kan beregnes præcist.
		$st0 = get_option( 'nordic_import_state', array() );
		$o = array();
		foreach ( (array) ( $st0['vars'] ?? array() ) as $k => $v ) { if ( 0 === strpos( $k, 'hf:' ) ) { $o[ $k ] = get_post_meta( (int) $v, '_elementor_data', true ); } }
		echo wp_json_encode( $o ); exit;
	}

	$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'orderby' => 'ID' ) );
	if ( ! $admins ) { echo '{"error":"ingen administrator"}'; exit; }
	wp_set_current_user( $admins[0]->ID );
	@set_time_limit( 120 );

	$st = get_option( 'nordic_import_state', array( 'done' => array(), 'vars' => array(), 'log' => array() ) );
	$start = microtime( true );
	$ran = array();
	foreach ( $plan['ops'] as $op ) {
		if ( isset( $st['done'][ $op['id'] ] ) ) { continue; }
		if ( microtime( true ) - $start > 20 ) { break; }
		try {
			$res = nordic_imp_run( $op, $st );
		} catch ( Throwable $e ) {
			$res = array( 'error' => $e->getMessage() );
		}
		if ( isset( $res['error'] ) ) {
			$st['log'][] = $op['id'] . ': ' . ( is_string( $res['error'] ) ? $res['error'] : wp_json_encode( $res['error'] ) );
			update_option( 'nordic_import_state', $st, false );
			echo wp_json_encode( array( 'stopped' => $op['id'], 'error' => $res['error'], 'done' => count( $st['done'] ), 'total' => count( $plan['ops'] ), 'ran' => $ran ) );
			exit;
		}
		$st['done'][ $op['id'] ] = $res['summary'] ?? true;
		$ran[] = $op['id'];
		update_option( 'nordic_import_state', $st, false );
	}
	echo wp_json_encode( array( 'done' => count( $st['done'] ), 'total' => count( $plan['ops'] ), 'ran' => $ran, 'res' => array_intersect_key( $st['done'], array_flip( $ran ) ), 'vars' => $st['vars'], 'log' => array_slice( $st['log'], -10 ) ) );
	exit;
}, 1 );

function nordic_imp_ability( $name, $input ) {
	$a = wp_get_ability( $name );
	if ( ! $a ) { return new WP_Error( 'noability', 'mangler ability ' . $name ); }
	return $a->execute( $input );
}

/* Erstatter {{navn}} i strenge med værdier fra tilstanden (fx side-id'er). */
function nordic_imp_subst( $v, $vars ) {
	if ( is_string( $v ) ) {
		if ( preg_match( '/^\{\{([\w.:-]+)\}\}$/', $v, $m ) && isset( $vars[ $m[1] ] ) ) { return $vars[ $m[1] ]; }
		return preg_replace_callback( '/\{\{([\w.:-]+)\}\}/', function ( $m ) use ( $vars ) { return isset( $vars[ $m[1] ] ) ? $vars[ $m[1] ] : $m[0]; }, $v );
	}
	if ( is_array( $v ) ) { foreach ( $v as $k => $x ) { $v[ $k ] = nordic_imp_subst( $x, $vars ); } }
	return $v;
}

function nordic_imp_ids( $resolved ) {
	preg_match_all( '/configuration-id="([^"]+)" id="([^"]+)"/', (string) $resolved, $m, PREG_SET_ORDER );
	$o = array();
	foreach ( $m as $x ) { $o[ $x[1] ] = $x[2]; }
	return $o;
}

function nordic_imp_build( $post_id, $parent, $part, $mode = 'append' ) {
	$in = array( 'post_id' => (int) $post_id, 'xml_structure' => $part['xml_structure'], 'parent_id' => (string) $parent, 'mode' => $mode );
	foreach ( array( 'element_config', 'style', 'classes' ) as $k ) { if ( ! empty( $part[ $k ] ) ) { $in[ $k ] = $part[ $k ]; } }
	$r = nordic_imp_ability( 'elementor/build-composition', $in );
	if ( is_wp_error( $r ) ) { return $r; }
	if ( empty( $r['success'] ) ) { return new WP_Error( 'build', wp_json_encode( $r ) ); }
	// En udgivet side gemmer ændringer som autosave; udgiv straks, så næste kald ser dem.
	if ( 'publish' === get_post_status( $post_id ) ) { nordic_imp_ability( 'elementor/publish-document', array( 'post_id' => (int) $post_id ) ); }
	return nordic_imp_ids( $r['resolved_xml'] ?? '' );
}

/* Ny kopi af et element med nye id'er (id + endelse), også for lokale stil-id'er. */
function nordic_imp_clone( $e, $suf ) {
	$e['id'] = substr( $e['id'] . $suf, 0, 12 );
	if ( ! empty( $e['styles'] ) ) {
		$ns = array();
		foreach ( $e['styles'] as $sid => $sv ) {
			$nid = $sid . $suf; $sv['id'] = $nid; $ns[ $nid ] = $sv;
			if ( isset( $e['settings']['classes']['value'] ) ) {
				foreach ( $e['settings']['classes']['value'] as $i => $c ) { if ( $c === $sid ) { $e['settings']['classes']['value'][ $i ] = $nid; } }
			}
		}
		$e['styles'] = $ns;
	}
	foreach ( (array) ( $e['elements'] ?? array() ) as $i => $c ) { $e['elements'][ $i ] = nordic_imp_clone( $c, $suf ); }
	return $e;
}

function nordic_imp_walk( &$list, $o, &$hit ) {
	foreach ( $list as $i => &$e ) {
		if ( $e['id'] === $o[1] ) {
			$hit = true;
			switch ( $o[0] ) {
				case 'text': $e['settings'][ $o[2] ] = array( '$$type' => 'escaped-html', 'value' => $o[3] ); break;
				case 'set': $e['settings'][ $o[2] ] = $o[3]; break;
				case 'url': $e['settings']['link']['value']['destination'] = array( '$$type' => 'url', 'value' => $o[2] ); break;
				case 'img':
					$src = array( 'url' => array( '$$type' => 'url', 'value' => $o[2] ) );
					if ( isset( $e['settings']['image']['value']['src']['value']['alt'] ) ) { $src['alt'] = $e['settings']['image']['value']['src']['value']['alt']; }
					$e['settings']['image']['value']['src'] = array( '$$type' => 'image-src', 'value' => $src ); break;
				case 'unclass':
					$e['settings']['classes']['value'] = array_values( array_diff( $e['settings']['classes']['value'], array( $o[2] ) ) );
					unset( $e['styles'][ $o[2] ] ); break;
				case 'style': // [style, id, stil-id, stilobjekt]
					$e['styles'][ $o[2] ] = $o[3];
					$e['settings']['classes']['value'][] = $o[2]; break;
				case 'dup': array_splice( $list, $i + 1, 0, array( nordic_imp_clone( $e, $o[2] ) ) ); return;
			}
			return;
		}
		if ( ! empty( $e['elements'] ) ) { nordic_imp_walk( $e['elements'], $o, $hit ); if ( $hit ) { return; } }
	}
}

/* ---- Lancering: dev-site -> live-domæne ----
 * sr       : søg/erstat i databasen (dry=true tæller kun). Serialiserede værdier håndteres korrekt.
 * golive   : backup af alle tabeller (prefix bk_), søg/erstat, Elementor-CSS ryddes, synlig for søgemaskiner,
 *            og til sidst byttes mapperne: live public_html -> public_html_gammel_<tid>, dev public_html -> live public_html.
 * rollback : bytter mapperne tilbage og gendanner tabellerne fra bk_ (køres fra live-domænet efter golive).
 */
function nordic_imp_sr_val( $v, $from, $to ) {
	if ( is_string( $v ) ) {
		if ( is_serialized( $v ) && false !== ( $u = @unserialize( $v, array( 'allowed_classes' => false ) ) ) ) {
			return serialize( nordic_imp_sr_val( $u, $from, $to ) );
		}
		return str_replace( $from, $to, $v );
	}
	if ( is_array( $v ) ) { foreach ( $v as $k => $x ) { $v[ $k ] = nordic_imp_sr_val( $x, $from, $to ); } return $v; }
	return $v;
}
function nordic_imp_sr( $from, $to, $dry ) {
	global $wpdb;
	$n = array();
	$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix ) . '%' ) );
	foreach ( $tables as $t ) {
		$cols = $wpdb->get_results( "SHOW COLUMNS FROM `$t`", ARRAY_A );
		$pk = null; $txt = array();
		foreach ( $cols as $c ) {
			if ( 'PRI' === $c['Key'] && ! $pk ) { $pk = $c['Field']; }
			if ( preg_match( '/char|text|blob/i', $c['Type'] ) ) { $txt[] = $c['Field']; }
		}
		if ( ! $pk || ! $txt ) { continue; }
		foreach ( $txt as $col ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT `$pk` AS k, `$col` AS v FROM `$t` WHERE `$col` LIKE %s", '%' . $wpdb->esc_like( $from ) . '%' ), ARRAY_A );
			if ( ! $rows ) { continue; }
			$n[ "$t.$col" ] = count( $rows );
			if ( $dry ) { continue; }
			foreach ( $rows as $r ) {
				$nv = nordic_imp_sr_val( $r['v'], $from, $to );
				if ( $nv !== $r['v'] ) { $wpdb->update( $t, array( $col => $nv ), array( $pk => $r['k'] ) ); }
			}
		}
	}
	return $n;
}
function nordic_imp_golive( $fn, $op ) {
	global $wpdb;
	@set_time_limit( 300 );
	$from = $op['from']; $to = $op['to'];
	if ( 'sr' === $fn ) { return array( 'summary' => nordic_imp_sr( $from, $to, ! empty( $op['dry'] ) ) ); }
	$base = '/home/' . $op['user'] . '/domains/';
	$src = $base . $op['src_domain'] . '/public_html';
	$dst = $base . $op['dst_domain'] . '/public_html';
	$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix ) . '%' ) );
	if ( 'rollback' === $fn ) {
		$old = $op['old_dir'];
		if ( ! is_dir( $old ) ) { return array( 'error' => 'gammel mappe findes ikke: ' . $old ); }
		foreach ( $tables as $t ) {
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', 'bk_' . $t ) ) ) {
				$wpdb->query( "DELETE FROM `$t`" ); $wpdb->query( "INSERT INTO `$t` SELECT * FROM `bk_$t`" );
			}
		}
		if ( ! rename( $dst, $src ) ) { return array( 'error' => 'kunne ikke flytte live tilbage til dev' ); }
		if ( ! rename( $old, $dst ) ) { return array( 'error' => 'kunne ikke gendanne gammel live-mappe' ); }
		return array( 'summary' => 'rullet tilbage' );
	}
	// golive
	if ( ! is_dir( $src ) || ! is_dir( $dst ) ) { return array( 'error' => 'mapper mangler' ); }
	if ( realpath( ABSPATH ) !== realpath( $src ) ) { return array( 'error' => 'skal køres fra dev-sitet' ); }
	foreach ( $tables as $t ) {
		$wpdb->query( "DROP TABLE IF EXISTS `bk_$t`" );
		$wpdb->query( "CREATE TABLE `bk_$t` LIKE `$t`" );
		$wpdb->query( "INSERT INTO `bk_$t` SELECT * FROM `$t`" );
	}
	$n = nordic_imp_sr( $from, $to, false );
	update_option( 'blog_public', '1' );
	if ( class_exists( '\\Elementor\\Plugin' ) ) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }
	foreach ( array( 'wp-config.php', '.htaccess' ) as $f ) {
		$p = $src . '/' . $f;
		if ( is_writable( $p ) ) { $c = file_get_contents( $p ); if ( false !== strpos( $c, $from ) ) { file_put_contents( $p, str_replace( $from, $to, $c ) ); } }
	}
	wp_cache_flush();
	$old = $dst . '_gammel_' . gmdate( 'Ymd_His' );
	if ( ! rename( $dst, $old ) ) { return array( 'error' => 'kunne ikke flytte gammel live-mappe' ); }
	if ( ! rename( $src, $dst ) ) { rename( $old, $dst ); return array( 'error' => 'kunne ikke flytte dev til live (gammel live gendannet)' ); }
	@mkdir( $src );
	@file_put_contents( $src . '/index.php', '<?php header("Location: https://' . $to . '/", true, 301); exit;' );
	return array( 'summary' => array( 'replaced' => $n, 'old_dir' => $old, 'tables' => count( $tables ) ) );
}

function nordic_imp_run( $op, &$st ) {
	$fn = $op['fn'] ?? 'ability';
	if ( 'ability' === $fn ) {
		$in = nordic_imp_subst( $op['input'], $st['vars'] );
		$r = nordic_imp_ability( $op['ability'], $in );
		if ( is_wp_error( $r ) ) { return array( 'error' => $r->get_error_message() ); }
		// Ændringer i et udgivet dokument gemmes som autosave; udgiv straks, så næste trin ser dem.
		if ( ! empty( $in['post_id'] ) && in_array( $op['ability'], array( 'elementor/manage-elements', 'elementor/build-composition' ), true ) && 'publish' === get_post_status( (int) $in['post_id'] ) ) {
			nordic_imp_ability( 'elementor/publish-document', array( 'post_id' => (int) $in['post_id'] ) );
		}
		return array( 'summary' => substr( wp_json_encode( $r ), 0, 300 ) );
	}
	if ( 'page' === $fn ) {
		$p = $op['page'];
		$vk = 'page:' . $p['key'];
		if ( empty( $st['vars'][ $vk ] ) ) {
			$r = nordic_imp_ability( 'elementor/create-page', array( 'title' => $p['title'], 'post_type' => 'page' ) );
			if ( is_wp_error( $r ) || empty( $r['id'] ) ) { return array( 'error' => is_wp_error( $r ) ? $r->get_error_message() : wp_json_encode( $r ) ); }
			$st['vars'][ $vk ] = (int) $r['id'];
			update_option( 'nordic_import_state', $st, false );
		}
		$pid = (int) $st['vars'][ $vk ];
		$r = nordic_imp_ability( 'elementor/update-page-settings', array( 'post_id' => $pid, 'settings' => $p['settings'] ) );
		if ( is_wp_error( $r ) ) { return array( 'error' => $r->get_error_message() ); }
		// Siden bygges altid forfra (replace_children), så et afbrudt forsøg ikke giver dobbelt indhold.
		$ids = nordic_imp_build( $pid, 'document', $p['root'], 'replace_children' );
		if ( is_wp_error( $ids ) ) { return array( 'error' => $ids->get_error_message() ); }
		$root = $ids['side'];
		$all = $ids;
		foreach ( $p['chunks'] as $c ) {
			$ids = nordic_imp_build( $pid, $root, nordic_imp_subst( $c, $st['vars'] ) );
			if ( is_wp_error( $ids ) ) { return array( 'error' => $ids->get_error_message() ); }
			$all = array_merge( $all, $ids );
		}
		foreach ( (array) ( $p['finders'] ?? array() ) as $f ) {
			if ( empty( $all[ $f['card'] ] ) ) { return array( 'error' => 'finderkort mangler: ' . $f['card'] ); }
			$a = nordic_imp_build( $pid, $all[ $f['card'] ], $f['A'] );
			if ( is_wp_error( $a ) ) { return array( 'error' => $a->get_error_message() ); }
			$b = nordic_imp_build( $pid, $a[ $f['spec_ct'] ], $f['B'] );
			if ( is_wp_error( $b ) ) { return array( 'error' => $b->get_error_message() ); }
		}
		$r = nordic_imp_ability( 'elementor/publish-document', array( 'post_id' => $pid ) );
		if ( is_wp_error( $r ) ) { return array( 'error' => $r->get_error_message() ); }
		$upd = array( 'ID' => $pid, 'post_name' => $p['slug'] );
		if ( ! empty( $p['parent'] ) ) {
			if ( empty( $st['vars'][ 'page:' . $p['parent'] ] ) ) { return array( 'error' => 'foraelder mangler: ' . $p['parent'] ); }
			$upd['post_parent'] = (int) $st['vars'][ 'page:' . $p['parent'] ];
		}
		if ( isset( $p['menu_order'] ) ) { $upd['menu_order'] = (int) $p['menu_order']; }
		wp_update_post( $upd );
		if ( ! empty( $p['seo'] ) ) { update_post_meta( $pid, '_nordic_seo', $p['seo'] ); }
		return array( 'summary' => $pid );
	}
	if ( 'copy_hf' === $fn ) {
		// Henter header/footer fra Nordic Specialklinik og genskaber dem her med lokale klasse- og variabel-id'er.
		$resp = wp_remote_post( $op['url'], array( 'body' => $op['secret'], 'timeout' => 60 ) );
		if ( is_wp_error( $resp ) ) { return array( 'error' => $resp->get_error_message() ); }
		$src = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( empty( $src['posts'] ) ) { return array( 'error' => 'ingen eksportdata' ); }
		$map = array();
		$here = nordic_imp_ability( 'elementor/read-resource', array( 'uri' => 'elementor://global-classes' ) );
		$here = json_decode( $here['content'] ?? '{}', true );
		$lab = array();
		foreach ( (array) ( $here['classes'] ?? array() ) as $c ) { $lab[ $c['label'] ] = $c['id']; }
		$there = json_decode( $src['classes']['content'] ?? '{}', true );
		foreach ( (array) ( $there['classes'] ?? array() ) as $c ) { if ( isset( $lab[ $c['label'] ] ) ) { $map[ $c['id'] ] = $lab[ $c['label'] ]; } }
		$vh = nordic_imp_ability( 'elementor/read-resource', array( 'uri' => 'elementor://global-variables' ) );
		$vh = json_decode( $vh['content'] ?? '{}', true );
		$vl = array();
		foreach ( (array) ( $vh['variables'] ?? array() ) as $id => $v ) { $vl[ $v['label'] ] = $id; }
		$vt = json_decode( $src['vars']['content'] ?? '{}', true );
		foreach ( (array) ( $vt['variables'] ?? array() ) as $id => $v ) { if ( isset( $vl[ $v['label'] ] ) ) { $map[ $id ] = $vl[ $v['label'] ]; } }
		$made = array();
		foreach ( $src['posts'] as $sid => $sp ) {
			$type = $sp['meta']['ehf_template_type'];
			$title = str_replace( $op['from_name'], $op['to_name'], $sp['post_title'] );
			$exist = get_posts( array( 'post_type' => 'elementor-hf', 'title' => $title, 'post_status' => 'any', 'numberposts' => 1 ) );
			$id = $exist ? $exist[0]->ID : wp_insert_post( array( 'post_type' => 'elementor-hf', 'post_title' => $title, 'post_status' => 'publish' ) );
			$data = strtr( $sp['meta']['_elementor_data'], $map );
			foreach ( $op['replace'] as $from => $to ) { $data = str_replace( $from, $to, $data ); }
			update_post_meta( $id, '_elementor_data', wp_slash( $data ) );
			foreach ( array( '_elementor_page_settings', '_elementor_template_type', '_elementor_edit_mode', '_elementor_version', '_wp_page_template', 'ehf_target_include_locations', 'ehf_target_exclude_locations', 'ehf_target_user_roles', 'ehf_template_type' ) as $k ) {
				update_post_meta( $id, $k, $sp['meta'][ $k ] );
			}
			delete_post_meta( $id, '_elementor_css' );
			delete_post_meta( $id, '_elementor_element_cache' );
			$st['vars'][ 'hf:' . $type ] = $id;
			$made[] = $id;
		}
		if ( class_exists( '\Elementor\Plugin' ) ) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }
		return array( 'summary' => $made );
	}
	if ( 'hf_patch' === $fn ) {
		// Præcise tekst-/linkrettelser i header/footer. Fejler, hvis en tekst ikke findes.
		$pid = (int) nordic_imp_subst( $op['post'], $st['vars'] );
		$data = (string) get_post_meta( $pid, '_elementor_data', true );
		foreach ( $op['pairs'] as $pr ) {
			$n = substr_count( $data, $pr[0] );
			if ( ! $n ) { return array( 'error' => 'findes ikke: ' . substr( $pr[0], 0, 80 ) ); }
			$data = str_replace( $pr[0], $pr[1], $data );
		}
		json_decode( $data );
		if ( json_last_error() ) { return array( 'error' => 'ugyldig json efter rettelse' ); }
		update_post_meta( $pid, '_elementor_data', wp_slash( $data ) );
		delete_post_meta( $pid, '_elementor_css' );
		delete_post_meta( $pid, '_elementor_element_cache' );
		if ( class_exists( '\\Elementor\\Plugin' ) ) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }
		return array( 'summary' => count( $op['pairs'] ) );
	}
	if ( 'tree' === $fn ) {
		// Rettelser i et Elementor-dokuments træ: tekst, link, billede, klasser, kopi af element.
		$pid = (int) nordic_imp_subst( $op['post'], $st['vars'] );
		$tree = json_decode( (string) get_post_meta( $pid, '_elementor_data', true ), true );
		if ( ! is_array( $tree ) ) { return array( 'error' => 'intet træ' ); }
		$miss = array();
		foreach ( $op['ops'] as $o ) {
			$hit = false;
			nordic_imp_walk( $tree, $o, $hit );
			if ( ! $hit ) { $miss[] = $o[1]; }
		}
		if ( $miss ) { return array( 'error' => 'ikke fundet: ' . implode( ',', $miss ) ); }
		update_post_meta( $pid, '_elementor_data', wp_slash( wp_json_encode( $tree ) ) );
		delete_post_meta( $pid, '_elementor_css' );
		delete_post_meta( $pid, '_elementor_element_cache' );
		if ( class_exists( '\\Elementor\\Plugin' ) ) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }
		return array( 'summary' => count( $op['ops'] ) );
	}
	if ( 'kit' === $fn ) {
		// Flet indstillinger ind i Elementors aktive kit (fx brudpunkter som på Specialklinik).
		$kid = (int) get_option( 'elementor_active_kit' );
		if ( ! $kid ) { return array( 'error' => 'intet aktivt kit' ); }
		$ps = get_post_meta( $kid, '_elementor_page_settings', true );
		if ( ! is_array( $ps ) ) { $ps = array(); }
		$ps = array_merge( $ps, $op['settings'] );
		update_post_meta( $kid, '_elementor_page_settings', $ps );
		if ( class_exists( '\\Elementor\\Plugin' ) ) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }
		return array( 'summary' => $kid );
	}
	if ( 'seo' === $fn ) {
		// Ret titel/metabeskrivelse på en side: [ ['post' => id|'{{page:X}}', 'meta' => '...', 'title' => '...'], ... ]
		$n = 0;
		foreach ( $op['items'] as $it ) {
			$pid = (int) nordic_imp_subst( $it['post'], $st['vars'] );
			if ( ! $pid || 'page' !== get_post_type( $pid ) ) { return array( 'error' => 'ukendt side: ' . $it['post'] ); }
			$s = get_post_meta( $pid, '_nordic_seo', true );
			if ( ! is_array( $s ) ) { $s = array(); }
			foreach ( array( 'title', 'meta' ) as $k ) { if ( isset( $it[ $k ] ) ) { $s[ $k ] = $it[ $k ]; } }
			update_post_meta( $pid, '_nordic_seo', $s );
			$n++;
		}
		return array( 'summary' => $n );
	}
	if ( 'options' === $fn ) {
		foreach ( $op['options'] as $k => $v ) { update_option( $k, nordic_imp_subst( $v, $st['vars'] ) ); }
		return array( 'summary' => array_keys( $op['options'] ) );
	}
	if ( 'fluentform' === $fn ) {
		global $wpdb;
		$t = $wpdb->prefix . 'fluentform_forms';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$t'" ) !== $t ) { return array( 'error' => 'Fluent Forms-tabel mangler' ); }
		$radio = function ( $key, $name, $label, $opts, $msg ) {
			$o = array();
			foreach ( $opts as $x ) { $o[] = array( 'label' => $x, 'value' => $x, 'calc_value' => '', 'image' => '' ); }
			return array( 'index' => 0, 'element' => 'input_radio', 'attributes' => array( 'type' => 'radio', 'name' => $name, 'value' => '' ),
				'settings' => array( 'label' => $label, 'container_class' => '', 'label_placement' => '', 'display_type' => '', 'help_message' => '', 'admin_field_label' => $label,
					'advanced_options' => $o, 'calc_value_status' => false, 'enable_image_input' => false, 'randomize_options' => false, 'layout_class' => 'ff_list_inline',
					'validation_rules' => array( 'required' => array( 'value' => true, 'message' => $msg ) ), 'conditional_logics' => array() ),
				'editor_options' => array( 'title' => 'Radio Field', 'icon_class' => 'ff-edit-radio', 'element' => 'input-radio', 'template' => 'inputCheckable' ), 'uniqElKey' => $key );
		};
		$text = function ( $key, $type, $name, $label, $auto, $req, $msg ) {
			$el = 'email' === $type ? 'input_email' : 'input_text';
			$rules = array( 'required' => array( 'value' => $req, 'message' => $msg ) );
			if ( 'email' === $type ) { $rules['email'] = array( 'value' => true, 'message' => 'Skriv en gyldig e-mail' ); }
			return array( 'index' => 0, 'element' => $el, 'attributes' => array( 'type' => $type, 'name' => $name, 'value' => '', 'class' => '', 'placeholder' => '', 'autocomplete' => $auto ),
				'settings' => array( 'container_class' => '', 'label' => $label, 'label_placement' => '', 'admin_field_label' => $label, 'help_message' => '', 'validation_rules' => $rules, 'conditional_logics' => array() ),
				'editor_options' => array( 'title' => $label, 'icon_class' => 'ff-edit-text', 'template' => 'inputText' ), 'uniqElKey' => $key );
		};
		$html = function ( $key, $code ) {
			return array( 'index' => 0, 'element' => 'custom_html', 'attributes' => array(), 'settings' => array( 'html_codes' => $code, 'conditional_logics' => array(), 'container_class' => '' ),
				'editor_options' => array( 'title' => 'Custom HTML', 'icon_class' => 'ff-edit-html', 'template' => 'customHTML' ), 'uniqElKey' => $key );
		};
		$fields = array(
			$radio( 'el_1', 'ydelse', '1. Hvad drejer det sig om?', $op['ydelser'], 'Vælg hvad det drejer sig om' ),
			$radio( 'el_2', 'patienttype', '2. Hvordan er du patient?', $op['patienttyper'] ?? array( 'Privat', 'Forsikring', 'Offentlig - ventetidsgaranti' ), 'Vælg hvordan du er patient' ),
			$html( 'el_h3', '<p class="nsh-ff-legend">3. Dine kontaktoplysninger</p>' ),
			$text( 'el_3', 'text', 'navn', 'Navn', 'name', true, 'Skriv dit navn' ),
			$text( 'el_4', 'tel', 'telefon', 'Telefon', 'tel', true, 'Skriv dit telefonnummer' ),
			$text( 'el_5', 'email', 'email', 'E-mail (valgfri)', 'email', false, '' ),
			$radio( 'el_6', 'tidspunkt', '4. Hvornår må vi ringe?', array( 'Formiddag', 'Eftermiddag', 'Når som helst' ), 'Vælg hvornår vi må ringe' ),
			$html( 'el_note', '<p class="nsh-ff-note">' . ( $op['note'] ?? 'Skriv ikke helbredsoplysninger her. Dem gennemgår vi med dig i telefonen.' ) . '</p>' ),
			array( 'index' => 0, 'element' => 'gdpr_agreement', 'attributes' => array( 'type' => 'checkbox', 'name' => 'samtykke', 'value' => false, 'data-type' => 'gdpr-agreement' ),
				'settings' => array( 'label' => 'Samtykke', 'tnc_html' => $op['consent_html'], 'admin_field_label' => 'Samtykke', 'has_checkbox' => true, 'container_class' => '',
					'validation_rules' => array( 'required' => array( 'value' => true, 'message' => 'Du skal acceptere for at sende' ) ), 'required_field_message' => '', 'conditional_logics' => array() ),
				'editor_options' => array( 'title' => 'GDPR Agreement', 'icon_class' => 'ff-edit-gdpr', 'template' => 'termsCheckbox' ), 'uniqElKey' => 'el_7' ),
		);
		$ff = array( 'fields' => $fields, 'submitButton' => array( 'uniqElKey' => 'el_sb', 'element' => 'button', 'attributes' => array( 'type' => 'submit', 'class' => '' ),
			'settings' => array( 'align' => 'left', 'button_style' => 'default', 'container_class' => '', 'help_message' => '', 'background_color' => '#183A70', 'button_size' => 'md', 'color' => '#ffffff',
				'button_ui' => array( 'type' => 'default', 'text' => 'Send anmodning', 'img_url' => '' ) ), 'editor_options' => array( 'title' => 'Submit Button' ) ) );
		$row = array( 'title' => $op['title'], 'status' => 'published', 'appearance_settings' => null, 'form_fields' => wp_json_encode( $ff ), 'has_payment' => 0, 'type' => 'form', 'conditions' => null,
			'created_by' => get_current_user_id(), 'created_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) );
		$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE title=%s", $op['title'] ) );
		if ( $existing ) { $wpdb->update( $t, $row, array( 'id' => $existing ) ); $id = $existing; } else { $wpdb->insert( $t, $row ); $id = (int) $wpdb->insert_id; }
		$m = $wpdb->prefix . 'fluentform_form_meta';
		$wpdb->delete( $m, array( 'form_id' => $id ) );
		$settings = array( 'confirmation' => array( 'redirectTo' => 'samePage', 'messageToShow' => $op['confirmation'], 'customPage' => null, 'samePageFormBehavior' => 'hide_form', 'customUrl' => null ),
			'restrictions' => array( 'limitNumberOfEntries' => array( 'enabled' => false ), 'scheduleForm' => array( 'enabled' => false ), 'requireLogin' => array( 'enabled' => false ), 'denyEmptySubmission' => array( 'enabled' => true, 'message' => 'Udfyld formularen' ) ),
			'layout' => array( 'labelPlacement' => 'top', 'helpMessagePlacement' => 'with_label', 'errorMessagePlacement' => 'inline', 'asteriskPlacement' => 'asterisk-right', 'cssClassName' => '' ),
			'delete_entry_on_submission' => 'no', 'appendSurveyResult' => array( 'enabled' => false ) );
		$notif = array( 'name' => 'Ny anmodning om tid', 'sendTo' => array( 'type' => 'email', 'email' => $op['receiver'], 'field' => '', 'routing' => array() ),
			'fromName' => $op['from_name'], 'fromEmail' => '', 'replyTo' => '', 'bcc' => '', 'subject' => 'Ny anmodning om tid: {inputs.ydelse}',
			'message' => '<p>Ny anmodning om tid via hjemmesiden:</p><p>{all_data}</p><p>Formularen indeholder ingen helbredsoplysninger.</p>',
			'conditionals' => array( 'status' => false, 'type' => 'all', 'conditions' => array() ), 'enabled' => true, 'email_template' => '', 'media' => '' );
		$wpdb->insert( $m, array( 'form_id' => $id, 'meta_key' => 'formSettings', 'value' => wp_json_encode( $settings ) ) );
		$wpdb->insert( $m, array( 'form_id' => $id, 'meta_key' => 'notifications', 'value' => wp_json_encode( $notif ) ) );
		$wpdb->insert( $m, array( 'form_id' => $id, 'meta_key' => 'template_name', 'value' => 'blank_form' ) );
		$g = get_option( '_fluentform_global_form_settings', array() );
		if ( ! is_array( $g ) ) { $g = array(); }
		if ( ! isset( $g['misc'] ) || ! is_array( $g['misc'] ) ) { $g['misc'] = array(); }
		$g['misc']['honeypotStatus'] = 'yes';
		update_option( '_fluentform_global_form_settings', $g, 'no' );
		$st['vars']['ff'] = $id;
		return array( 'summary' => $id );
	}
	if ( 'pageinfo' === $fn ) {
		// Oversigt over sider, Rank Math-felter og (for udvalgte sider) Elementor-elementer med tekst/overskrifter.
		$o = array( 'pages' => array() );
		foreach ( get_pages( array( 'post_status' => 'publish', 'number' => 200 ) ) as $p ) {
			$o['pages'][] = array( $p->ID, $p->post_name, $p->post_title, get_post_meta( $p->ID, 'rank_math_title', true ), get_post_meta( $p->ID, 'rank_math_description', true ), get_post_meta( $p->ID, 'rank_math_focus_keyword', true ), get_post_meta( $p->ID, '_elementor_edit_mode', true ) );
		}
		$o['front'] = (int) get_option( 'page_on_front' );
		$o['tpl'] = array();
		foreach ( get_posts( array( 'post_type' => 'elementor_library', 'numberposts' => 50, 'post_status' => 'publish' ) ) as $t ) { $o['tpl'][] = array( $t->ID, $t->post_title, get_post_meta( $t->ID, '_elementor_template_type', true ) ); }
		$o['el'] = array();
		foreach ( (array) ( $op['ids'] ?? array() ) as $pid ) {
			$data = json_decode( (string) get_post_meta( (int) $pid, '_elementor_data', true ), true );
			$list = array();
			$walk = function ( $els, $d ) use ( &$walk, &$list ) {
				foreach ( (array) $els as $e ) {
					$st = $e['settings'] ?? array();
					$txt = '';
					foreach ( array( 'title', 'editor', 'text', 'description_text', 'title_text', 'heading', 'price', 'button_text' ) as $k ) { if ( ! empty( $st[ $k ] ) && is_string( $st[ $k ] ) ) { $txt .= $k . '=' . mb_substr( wp_strip_all_tags( $st[ $k ] ), 0, 90 ) . ' '; } }
					$w = $e['widgetType'] ?? $e['elType'];
					if ( $txt || in_array( $w, array( 'image', 'heading' ), true ) ) {
						$list[] = str_repeat( ' ', $d ) . $e['id'] . ' ' . $w . ( isset( $st['header_size'] ) ? ' [' . $st['header_size'] . ']' : '' ) . ( isset( $st['image']['url'] ) ? ' img=' . basename( $st['image']['url'] ) . ' alt=' . ( $st['image']['alt'] ?? '' ) : '' ) . ' ' . $txt;
					}
					if ( ! empty( $e['elements'] ) ) { $walk( $e['elements'], $d + 1 ); }
				}
			};
			$walk( $data, 0 );
			$o['el'][ $pid ] = $list;
		}
		return array( 'summary' => $o );
	}
	if ( 'html_strip' === $fn ) {
		// Fjern et regex-mønster fra HTML-widgets i alle publicerede Elementor-sider (backup i meta _nordic_bk_<op-id>).
		global $wpdb;
		$ids = $wpdb->get_col( "SELECT p.ID FROM $wpdb->posts p JOIN $wpdb->postmeta m ON m.post_id=p.ID AND m.meta_key='_elementor_data' WHERE p.post_status='publish'" );
		$log = array();
		foreach ( $ids as $pid ) {
			$raw = (string) get_post_meta( (int) $pid, '_elementor_data', true );
			$tree = json_decode( $raw, true );
			if ( ! is_array( $tree ) ) { continue; }
			$n = 0;
			$walk = function ( &$els ) use ( &$walk, &$n, $op ) {
				foreach ( $els as &$e ) {
					if ( isset( $e['settings']['html'] ) && is_string( $e['settings']['html'] ) ) {
						$e['settings']['html'] = preg_replace( $op['re'], '', $e['settings']['html'], -1, $c );
						$n += $c;
					}
					if ( ! empty( $e['elements'] ) ) { $walk( $e['elements'] ); }
				}
			};
			$walk( $tree );
			if ( $n && empty( $op['dry'] ) ) {
				update_post_meta( (int) $pid, '_nordic_bk_' . $op['id'], wp_slash( $raw ) );
				update_post_meta( (int) $pid, '_elementor_data', wp_slash( wp_json_encode( $tree ) ) );
				delete_post_meta( (int) $pid, '_elementor_css' );
			}
			if ( $n ) { $log[ $pid ] = $n; }
		}
		if ( empty( $op['dry'] ) && class_exists( '\Elementor\Plugin' ) ) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }
		return array( 'summary' => $log );
	}
	if ( 'htag' === $fn ) {
		// Skift HTML-tag (header_size) på overskrift-widgets efter element-id. Synlig tekst og stil røres ikke. Backup i _nordic_bk_<op-id>.
		global $wpdb;
		$map = (array) $op['map'];
		$ids = $wpdb->get_col( "SELECT p.ID FROM $wpdb->posts p JOIN $wpdb->postmeta m ON m.post_id=p.ID AND m.meta_key='_elementor_data' WHERE p.post_status='publish'" );
		$log = array();
		foreach ( $ids as $pid ) {
			$raw = (string) get_post_meta( (int) $pid, '_elementor_data', true );
			$tree = json_decode( $raw, true );
			if ( ! is_array( $tree ) ) { continue; }
			$n = 0;
			$walk = function ( &$els ) use ( &$walk, &$n, $map ) {
				foreach ( $els as &$e ) {
					if ( 'heading' === ( $e['widgetType'] ?? '' ) && isset( $map[ $e['id'] ] ) ) {
						$old = $e['settings']['header_size'] ?? 'h2';
						if ( $old !== $map[ $e['id'] ] ) { $e['settings']['header_size'] = $map[ $e['id'] ]; $n++; }
					}
					if ( ! empty( $e['elements'] ) ) { $walk( $e['elements'] ); }
				}
			};
			$walk( $tree );
			if ( $n && empty( $op['dry'] ) ) {
				if ( ! metadata_exists( 'post', (int) $pid, '_nordic_bk_' . $op['id'] ) ) { add_post_meta( (int) $pid, '_nordic_bk_' . $op['id'], wp_slash( $raw ) ); }
				update_post_meta( (int) $pid, '_elementor_data', wp_slash( wp_json_encode( $tree ) ) );
				delete_post_meta( (int) $pid, '_elementor_css' );
				delete_post_meta( (int) $pid, '_elementor_element_cache' );
			}
			if ( $n ) { $log[ $pid ] = $n; }
		}
		if ( empty( $op['dry'] ) && class_exists( '\Elementor\Plugin' ) ) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }
		return array( 'summary' => $log );
	}
	if ( 'restore_bk' === $fn ) {
		// Gendan _elementor_data fra backup-nøgle for de angivne indlæg (eller alle med nøglen).
		global $wpdb;
		$key = '_nordic_bk_' . $op['bk'];
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value FROM $wpdb->postmeta WHERE meta_key=%s", $key ), ARRAY_A );
		$log = array();
		foreach ( $rows as $r ) {
			if ( ! empty( $op['posts'] ) && ! in_array( (int) $r['post_id'], array_map( 'intval', $op['posts'] ), true ) ) { continue; }
			update_post_meta( (int) $r['post_id'], '_elementor_data', wp_slash( $r['meta_value'] ) );
			delete_post_meta( (int) $r['post_id'], '_elementor_css' );
			$log[] = (int) $r['post_id'];
		}
		if ( class_exists( '\Elementor\Plugin' ) ) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }
		return array( 'summary' => $log );
	}
	if ( 'eltree' === $fn ) {
		// Kompakt elementtræ med type, tekst og links for de angivne indlæg (kun læsning).
		$o = array();
		foreach ( (array) $op['ids'] as $pid ) {
			$tree = json_decode( (string) get_post_meta( (int) $pid, '_elementor_data', true ), true );
			$list = array();
			$walk = function ( $els, $d ) use ( &$walk, &$list ) {
				foreach ( (array) $els as $e ) {
					$st = $e['settings'] ?? array(); $w = $e['widgetType'] ?? $e['elType'];
					$txt = '';
					foreach ( array( 'title', 'editor', 'text', 'title_text', 'description_text', 'button_text', 'html' ) as $k ) { if ( ! empty( $st[ $k ] ) && is_string( $st[ $k ] ) ) { $txt .= $k . '=' . mb_substr( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $st[ $k ] ) ), 0, 70 ) . ' '; } }
					if ( ! empty( $st['link']['url'] ) ) { $txt .= 'link=' . $st['link']['url'] . ' '; }
					if ( ! empty( $st['icon_list'] ) ) { foreach ( $st['icon_list'] as $li ) { $txt .= '[' . mb_substr( wp_strip_all_tags( $li['text'] ?? '' ), 0, 40 ) . ( ! empty( $li['link']['url'] ) ? '->' . $li['link']['url'] : '' ) . '] '; } }
					$list[] = str_repeat( ' ', $d ) . $e['id'] . ' ' . $w . ' ' . $txt;
					if ( ! empty( $e['elements'] ) ) { $walk( $e['elements'], $d + 1 ); }
				}
			};
			$walk( $tree, 0 );
			$o[ $pid ] = $list;
		}
		return array( 'summary' => $o );
	}
	if ( 'clone_after' === $fn ) {
		// Kopiér element src (med børn) og indsæt det efter element "after" i samme forælder. Nye id'er.
		// drop: originale børne-id'er, der udelades. set: originalt id => indstillinger, der overskrives.
		$pid = (int) $op['post'];
		$raw = (string) get_post_meta( $pid, '_elementor_data', true );
		$tree = json_decode( $raw, true );
		$find = function ( $els, $id ) use ( &$find ) { foreach ( $els as $e ) { if ( $e['id'] === $id ) { return $e; } if ( ! empty( $e['elements'] ) && ( $r = $find( $e['elements'], $id ) ) ) { return $r; } } return null; };
		$src = $find( ! empty( $op['src_post'] ) ? json_decode( (string) get_post_meta( (int) $op['src_post'], '_elementor_data', true ), true ) : $tree, $op['src'] );
		if ( ! $src ) { return array( 'error' => 'src ikke fundet' ); }
		$drop = (array) ( $op['drop'] ?? array() ); $set = (array) ( $op['set'] ?? array() ); $map = array();
		$re = function ( $e ) use ( &$re, $drop, $set, &$map ) {
			if ( isset( $set[ $e['id'] ] ) ) { $e['settings'] = array_replace_recursive( (array) $e['settings'], (array) $set[ $e['id'] ] ); }
			$nid = substr( md5( $e['id'] . wp_rand() ), 0, 7 ); $map[ $e['id'] ] = $nid; $e['id'] = $nid;
			$kids = array();
			foreach ( (array) ( $e['elements'] ?? array() ) as $k ) { if ( ! in_array( $k['id'], $drop, true ) ) { $kids[] = $re( $k ); } }
			$e['elements'] = $kids;
			return $e;
		};
		$copy = $re( $src );
		$done = false;
		$ins = function ( &$els ) use ( &$ins, $op, $copy, &$done ) {
			foreach ( $els as $i => $e ) {
				if ( $e['id'] === $op['after'] ) { array_splice( $els, $i + 1, 0, array( $copy ) ); $done = true; return; }
				if ( ! empty( $els[ $i ]['elements'] ) ) { $ins( $els[ $i ]['elements'] ); if ( $done ) { return; } }
			}
		};
		$ins( $tree );
		if ( ! $done ) { return array( 'error' => 'after ikke fundet' ); }
		if ( ! metadata_exists( 'post', $pid, '_nordic_bk_' . $op['id'] ) ) { add_post_meta( $pid, '_nordic_bk_' . $op['id'], wp_slash( $raw ) ); }
		update_post_meta( $pid, '_elementor_data', wp_slash( wp_json_encode( $tree ) ) );
		delete_post_meta( $pid, '_elementor_css' );
		if ( class_exists( '\Elementor\Plugin' ) ) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }
		return array( 'summary' => $map );
	}
	if ( 'elset' === $fn ) {
		// Overskriv indstillinger på elementer efter id i ét indlæg: set: id => settings. Backup i _nordic_bk_<op-id>.
		$pid = (int) $op['post'];
		$raw = (string) get_post_meta( $pid, '_elementor_data', true );
		$tree = json_decode( $raw, true ); $n = array();
		$walk = function ( &$els ) use ( &$walk, $op, &$n ) {
			foreach ( $els as &$e ) {
				if ( isset( $op['set'][ $e['id'] ] ) ) { $e['settings'] = array_replace_recursive( (array) $e['settings'], (array) $op['set'][ $e['id'] ] ); $n[] = $e['id']; }
				if ( ! empty( $e['elements'] ) ) { $walk( $e['elements'] ); }
			}
		};
		$walk( $tree );
		if ( ! metadata_exists( 'post', $pid, '_nordic_bk_' . $op['id'] ) ) { add_post_meta( $pid, '_nordic_bk_' . $op['id'], wp_slash( $raw ) ); }
		update_post_meta( $pid, '_elementor_data', wp_slash( wp_json_encode( $tree ) ) );
		delete_post_meta( $pid, '_elementor_css' );
		if ( class_exists( '\Elementor\Plugin' ) ) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }
		return array( 'summary' => $n );
	}
	if ( 'elrepl' === $fn ) {
		// Erstat præcis tekst i et elements tekstfelt: [post, id, felt, fra, til]. Fejler hvis "fra" ikke findes.
		$log = array();
		foreach ( $op['r'] as $r ) {
			list( $pid, $id, $field, $from, $to ) = $r;
			$raw = (string) get_post_meta( (int) $pid, '_elementor_data', true );
			$tree = json_decode( $raw, true ); $hit = 0;
			$walk = function ( &$els ) use ( &$walk, $id, $field, $from, $to, &$hit ) {
				foreach ( $els as &$e ) {
					if ( '*' === $field && ( '*' === $id || $e['id'] === $id ) ) {
						array_walk_recursive( $e['settings'], function ( &$v ) use ( $from, $to, &$hit ) { if ( is_string( $v ) && false !== strpos( $v, $from ) ) { $v = str_replace( $from, $to, $v ); $hit++; } } );
					} elseif ( $e['id'] === $id ) {
						if ( false !== strpos( $field, '.' ) ) { list( $a, $i, $b ) = explode( '.', $field ); $ref = &$e['settings'][ $a ][ (int) $i ][ $b ]; } else { $ref = &$e['settings'][ $field ]; }
						if ( is_string( $ref ) && false !== strpos( $ref, $from ) ) { $ref = str_replace( $from, $to, $ref ); $hit++; }
						unset( $ref );
					}
					if ( ! empty( $e['elements'] ) ) { $walk( $e['elements'] ); }
				}
			};
			$walk( $tree );
			if ( $hit ) {
				if ( ! metadata_exists( 'post', (int) $pid, '_nordic_bk_' . $op['id'] ) ) { add_post_meta( (int) $pid, '_nordic_bk_' . $op['id'], wp_slash( $raw ) ); }
				update_post_meta( (int) $pid, '_elementor_data', wp_slash( wp_json_encode( $tree ) ) );
				delete_post_meta( (int) $pid, '_elementor_css' );
			}
			$log[] = $pid . '/' . $id . ': ' . ( $hit ? 'ok x' . $hit : 'IKKE FUNDET' );
		}
		if ( class_exists( '\Elementor\Plugin' ) ) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }
		return array( 'summary' => $log );
	}
	if ( 'postmeta' === $fn ) {
		// Sæt metafelter: set: [ [post, nøgle, værdi], ... ]. Gammel værdi logges.
		$log = array();
		foreach ( $op['set'] as $x ) { $log[] = $x[0] . ' ' . $x[1] . ' (før: ' . mb_substr( (string) get_post_meta( (int) $x[0], $x[1], true ), 0, 80 ) . ')'; update_post_meta( (int) $x[0], $x[1], wp_slash( $x[2] ) ); }
		return array( 'summary' => $log );
	}
	if ( 'clone_page' === $fn ) {
		// Ny side som kopi af en eksisterende Elementor-side: behold udvalgte topsektioner, fjern/ret elementer, indsæt nye efter et id.
		$src = (int) $op['src'];
		$vk = 'page:' . $op['key'];
		$pid = (int) ( $st['vars'][ $vk ] ?? 0 );
		if ( ! $pid ) {
			$pid = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $op['title'], 'post_name' => $op['slug'] ) );
			if ( ! $pid ) { return array( 'error' => 'kunne ikke oprette side' ); }
			$st['vars'][ $vk ] = $pid; update_option( 'nordic_import_state', $st, false );
		}
		foreach ( array( '_elementor_edit_mode', '_elementor_template_type', '_elementor_version', '_elementor_pro_version', '_elementor_page_settings', '_wp_page_template' ) as $k ) {
			$v = get_post_meta( $src, $k, true ); if ( '' !== $v ) { update_post_meta( $pid, $k, wp_slash( $v ) ); }
		}
		$tree = json_decode( (string) get_post_meta( $src, '_elementor_data', true ), true );
		$tree = array_values( array_filter( $tree, function ( $e ) use ( $op ) { return in_array( $e['id'], $op['keep'], true ); } ) );
		$json = wp_json_encode( $op['insert'] ?? array() );
		$json = str_replace( '{{ff}}', (string) ( $st['vars']['ff'] ?? '' ), $json );
		$insert = json_decode( $json, true );
		$walk = function ( &$els ) use ( &$walk, $op, $insert ) {
			$out = array();
			foreach ( $els as $e ) {
				if ( in_array( $e['id'], (array) ( $op['drop'] ?? array() ), true ) ) { continue; }
				if ( isset( $op['set'][ $e['id'] ] ) ) { $e['settings'] = array_replace_recursive( (array) $e['settings'], (array) $op['set'][ $e['id'] ] ); }
				if ( ! empty( $e['elements'] ) ) { $walk( $e['elements'] ); }
				$out[] = $e;
				foreach ( $insert as $after => $new ) { if ( $after === $e['id'] ) { foreach ( $new as $n ) { $out[] = $n; } } }
			}
			$els = $out;
		};
		$walk( $tree );
		// Element-id'er beholdes: Elementors CSS er pr. side, så de kolliderer ikke.
		update_post_meta( $pid, '_elementor_data', wp_slash( wp_json_encode( $tree ) ) );
		delete_post_meta( $pid, '_elementor_css' );
		wp_update_post( array( 'ID' => $pid, 'post_title' => $op['title'], 'post_name' => $op['slug'], 'post_status' => 'publish' ) );
		foreach ( (array) ( $op['meta'] ?? array() ) as $k => $v ) { update_post_meta( $pid, $k, wp_slash( $v ) ); }
		if ( class_exists( '\Elementor\Plugin' ) ) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }
		return array( 'summary' => $pid );
	}
	if ( 'menu_item' === $fn ) {
		// Tilføj et link-menupunkt til den menu, som en nav-menu-widget i en skabelon bruger.
		$tree = json_decode( (string) get_post_meta( (int) $op['tpl'], '_elementor_data', true ), true );
		$find = function ( $els ) use ( &$find, $op ) { foreach ( $els as $e ) { if ( $e['id'] === $op['widget'] ) { return $e['settings']['menu'] ?? ''; } if ( ! empty( $e['elements'] ) && ( $r = $find( $e['elements'] ) ) ) { return $r; } } return ''; };
		$slug = $find( $tree );
		$menu = wp_get_nav_menu_object( $slug );
		if ( ! $menu ) { return array( 'error' => 'menu ikke fundet: ' . $slug ); }
		foreach ( (array) wp_get_nav_menu_items( $menu->term_id ) as $it ) { if ( $it->url === $op['url'] ) { return array( 'summary' => 'findes allerede ' . $it->ID ); } }
		$id = wp_update_nav_menu_item( $menu->term_id, 0, array( 'menu-item-title' => $op['title'], 'menu-item-url' => $op['url'], 'menu-item-type' => 'custom', 'menu-item-status' => 'publish', 'menu-item-classes' => $op['classes'] ?? '', 'menu-item-position' => (int) ( $op['position'] ?? 99 ) ) );
		return array( 'summary' => array( 'menu' => $slug, 'item' => $id ) );
	}
	if ( 'btnlink' === $fn ) {
		// Skift link på knapper med en bestemt tekst og et bestemt nuværende link i alle publicerede Elementor-sider.
		global $wpdb;
		$ids = $wpdb->get_col( "SELECT p.ID FROM $wpdb->posts p JOIN $wpdb->postmeta m ON m.post_id=p.ID AND m.meta_key='_elementor_data' WHERE p.post_status='publish'" );
		$log = array();
		foreach ( $ids as $pid ) {
			$raw = (string) get_post_meta( (int) $pid, '_elementor_data', true );
			$tree = json_decode( $raw, true ); if ( ! is_array( $tree ) ) { continue; }
			$n = 0;
			$walk = function ( &$els ) use ( &$walk, &$n, $op ) {
				foreach ( $els as &$e ) {
					if ( 'button' === ( $e['widgetType'] ?? '' ) && trim( (string) ( $e['settings']['text'] ?? '' ) ) === $op['text'] && ( $e['settings']['link']['url'] ?? '' ) === $op['from'] ) { $e['settings']['link']['url'] = $op['to']; $n++; }
					if ( ! empty( $e['elements'] ) ) { $walk( $e['elements'] ); }
				}
			};
			$walk( $tree );
			if ( $n ) {
				if ( ! metadata_exists( 'post', (int) $pid, '_nordic_bk_' . $op['id'] ) ) { add_post_meta( (int) $pid, '_nordic_bk_' . $op['id'], wp_slash( $raw ) ); }
				update_post_meta( (int) $pid, '_elementor_data', wp_slash( wp_json_encode( $tree ) ) );
				delete_post_meta( (int) $pid, '_elementor_css' );
				$log[ $pid ] = $n;
			}
		}
		if ( class_exists( '\Elementor\Plugin' ) ) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }
		return array( 'summary' => $log );
	}
	if ( 'grep' === $fn ) {
		// Find en tekststreng i indlæg, metadata og options (kun læsning).
		global $wpdb;
		$q = '%' . $wpdb->esc_like( $op['q'] ) . '%';
		$o = array(
			'posts' => $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_type, post_status, post_title FROM $wpdb->posts WHERE post_content LIKE %s LIMIT 20", $q ), ARRAY_A ),
			'meta'  => $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_key FROM $wpdb->postmeta WHERE meta_value LIKE %s LIMIT 20", $q ), ARRAY_A ),
			'opts'  => $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM $wpdb->options WHERE option_value LIKE %s LIMIT 20", $q ) ),
		);
		$files = array();
		foreach ( array( get_stylesheet_directory(), get_template_directory() ) as $d ) {
			foreach ( (array) glob( $d . '/*.{css,php}', GLOB_BRACE ) as $f ) { if ( false !== strpos( (string) file_get_contents( $f ), $op['q'] ) ) { $files[] = $f; } }
		}
		$o['files'] = array_values( array_unique( $files ) );
		if ( ! empty( $op['ctx_all'] ) ) {
			$o['ctx_all'] = array();
			foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT m.post_id, m.meta_value FROM $wpdb->postmeta m JOIN $wpdb->posts p ON p.ID=m.post_id AND p.post_status='publish' WHERE m.meta_key='_elementor_data' AND m.meta_value LIKE %s", $q ), ARRAY_A ) as $r ) {
				$v = $r['meta_value'];
				for ( $i = strpos( $v, $op['q'] ); false !== $i && count( $o['ctx_all'] ) < 40; $i = strpos( $v, $op['q'], $i + 1 ) ) { $o['ctx_all'][] = $r['post_id'] . ' ' . get_post_field( 'post_name', $r['post_id'] ) . ': ' . substr( $v, max( 0, $i - 220 ), 380 ); }
			}
		}
		if ( ! empty( $op['ctx_post'] ) ) {
			$v = (string) get_post_meta( (int) $op['ctx_post'], '_elementor_data', true );
			$o['ctx'] = array();
			for ( $i = strpos( $v, $op['q'] ); false !== $i && count( $o['ctx'] ) < 5; $i = strpos( $v, $op['q'], $i + 1 ) ) { $o['ctx'][] = substr( $v, max( 0, $i - 300 ), 600 ); }
		}
		return array( 'summary' => $o );
	}
	if ( 'opts' === $fn ) {
		global $wpdb;
		$o = array();
		foreach ( $op['like'] as $l ) {
			foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT option_name, LEFT(option_value,300) v FROM $wpdb->options WHERE option_name LIKE %s LIMIT 30", $l ), ARRAY_A ) as $r ) { $o[ $r['option_name'] ] = $r['v']; }
		}
		$o['_files'] = array( 'maintenance' => file_exists( ABSPATH . '.maintenance' ), 'mu' => array_values( array_diff( (array) @scandir( WP_CONTENT_DIR . '/mu-plugins' ), array( '.', '..' ) ) ) );
		return array( 'summary' => $o );
	}
	if ( 'fs' === $fn ) {
		// Filoperationer inden for kontoens domænemapper: [ ['rename', fra, til], ['rmtree', sti] ]
		$root = '/home/' . $op['user'] . '/domains/';
		$log = array();
		$rm = function ( $p ) use ( &$rm ) {
			if ( is_link( $p ) || is_file( $p ) ) { return @unlink( $p ); }
			if ( ! is_dir( $p ) ) { return true; }
			foreach ( array_diff( (array) scandir( $p ), array( '.', '..' ) ) as $f ) { $rm( $p . '/' . $f ); }
			return @rmdir( $p );
		};
		foreach ( $op['steps'] as $s ) {
			foreach ( array_slice( $s, 1 ) as $p ) { if ( 0 !== strpos( $p, $root ) || false !== strpos( $p, '..' ) ) { return array( 'error' => 'sti uden for domæner: ' . $p ); } }
			if ( 'rename' === $s[0] ) { $log[] = 'rename ' . basename( $s[1] ) . ': ' . ( @rename( $s[1], $s[2] ) ? 'ok' : 'FEJL' ); }
			if ( 'rmtree' === $s[0] ) { $log[] = 'rmtree ' . basename( $s[1] ) . ': ' . ( $rm( $s[1] ) ? 'ok' : 'FEJL' ); }
			if ( 'exists' === $s[0] ) { $log[] = 'exists ' . $s[1] . ': ' . ( file_exists( $s[1] ) ? 'ja' : 'nej' ); }
		}
		return array( 'summary' => $log );
	}
	if ( 'fscheck' === $fn ) {
		$base = '/home/' . $op['user'] . '/domains/';
		$o = array( 'abspath' => ABSPATH );
		foreach ( array( $op['src_domain'], $op['dst_domain'] ) as $d ) {
			$dir = $base . $d;
			$t1 = $dir . '/nordic_rename_test_a'; $t2 = $dir . '/nordic_rename_test_b';
			$ok = @mkdir( $t1 ) && @rename( $t1, $t2 ) && @rmdir( $t2 );
			$o[ $d ] = array( 'dir_writable' => is_writable( $dir ), 'rename_ok' => $ok, 'public_html' => is_dir( $dir . '/public_html' ),
				'entries' => array_slice( (array) @scandir( $dir . '/public_html' ), 0, 40 ), 'wpconfig_has_dev' => false !== strpos( (string) @file_get_contents( $dir . '/public_html/wp-config.php' ), 'dev.' ) );
		}
		global $wpdb;
		$o['db_mb'] = $wpdb->get_var( $wpdb->prepare( 'SELECT ROUND(SUM(data_length+index_length)/1048576,1) FROM information_schema.tables WHERE table_schema=%s', DB_NAME ) );
		return array( 'summary' => $o );
	}
	if ( 'sr' === $fn || 'golive' === $fn || 'rollback' === $fn ) {
		return nordic_imp_golive( $fn, $op );
	}
	if ( 'maildiag' === $fn ) {
		// Fejlsøgning af formular-mail: seneste indsendelser, Fluent Forms-log og én testmail med fuld fejltekst.
		global $wpdb;
		$o = array();
		$o['subs'] = $wpdb->get_results( "SELECT id, form_id, created_at, status FROM {$wpdb->prefix}fluentform_submissions ORDER BY id DESC LIMIT 5", ARRAY_A );
		$o['logs'] = $wpdb->get_results( "SELECT created_at, title, status, description FROM {$wpdb->prefix}fluentform_logs ORDER BY id DESC LIMIT 8", ARRAY_A );
		$o['notif'] = $wpdb->get_var( $wpdb->prepare( "SELECT value FROM {$wpdb->prefix}fluentform_form_meta WHERE meta_key='notifications' AND form_id=%d", (int) nordic_imp_subst( '{{ff}}', $st['vars'] ) ) );
		$o['smtp_plugins'] = array_values( array_filter( (array) get_option( 'active_plugins' ), function ( $p ) { return false !== stripos( $p, 'smtp' ) || false !== stripos( $p, 'mail' ); } ) );
		$err = null;
		add_action( 'wp_mail_failed', function ( $e ) use ( &$err ) { $err = $e->get_error_message(); } );
		if ( ! empty( $op['send_to'] ) ) {
			$o['sent'] = wp_mail( $op['send_to'], 'Test fra hjemmesiden ' . wp_parse_url( home_url(), PHP_URL_HOST ), "Teknisk test af formular-mail fra hjemmesiden. Kan slettes.\n", array( 'From: Nordic Specialhospital <' . $op['from'] . '>' ) );
			$o['err'] = $err;
		}
		return array( 'summary' => $o );
	}
	if ( 'ff_mail' === $fn ) {
		// Afsender på formularens mail: fast adresse (SPF-godkendt) + svar-til patientens e-mail, hvis udfyldt.
		global $wpdb;
		$id = ( 'all' === ( $op['form'] ?? '' ) ) ? 0 : (int) nordic_imp_subst( $op['form'] ?? '{{ff}}', $st['vars'] );
		$t = $wpdb->prefix . 'fluentform_form_meta';
		$rows = ( 'all' === ( $op['form'] ?? '' ) ) ? $wpdb->get_results( "SELECT id, value FROM $t WHERE meta_key = 'notifications'" ) : $wpdb->get_results( $wpdb->prepare( "SELECT id, value FROM $t WHERE form_id = %d AND meta_key = 'notifications'", $id ) );
		if ( ! $rows ) { return array( 'error' => 'ingen notifikation for formular ' . $id ); }
		foreach ( $rows as $r ) {
			$v = json_decode( $r->value, true );
			$v['fromEmail'] = $op['from_email'];
			$v['replyTo'] = '{inputs.email}';
			$wpdb->update( $t, array( 'value' => wp_json_encode( $v ) ), array( 'id' => $r->id ) );
		}
		return array( 'summary' => count( $rows ) );
	}
	if ( 'clear_cache' === $fn ) {
		if ( class_exists( '\Elementor\Plugin' ) ) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }
		return array( 'summary' => 'ok' );
	}
	return array( 'error' => 'ukendt trin ' . $fn );
}
