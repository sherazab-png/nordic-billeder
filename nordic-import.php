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
	echo "<script>(function(){var m=document.querySelector('[data-elementor-type=\"wp-page\"]');if(m&&!document.getElementById('content')){m.id='content';m.setAttribute('role','main');m.setAttribute('tabindex','-1');}})();</script>\n";
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

if ( ! is_readable( __DIR__ . '/plan.enc' ) ) { return; }

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
	echo wp_json_encode( array( 'done' => count( $st['done'] ), 'total' => count( $plan['ops'] ), 'ran' => $ran, 'vars' => $st['vars'], 'log' => array_slice( $st['log'], -10 ) ) );
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

function nordic_imp_run( $op, &$st ) {
	$fn = $op['fn'] ?? 'ability';
	if ( 'ability' === $fn ) {
		$r = nordic_imp_ability( $op['ability'], nordic_imp_subst( $op['input'], $st['vars'] ) );
		if ( is_wp_error( $r ) ) { return array( 'error' => $r->get_error_message() ); }
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
			$radio( 'el_2', 'patienttype', '2. Hvordan er du patient?', array( 'Privat', 'Forsikring', 'Offentlig - ventetidsgaranti' ), 'Vælg hvordan du er patient' ),
			$html( 'el_h3', '<p class="nsh-ff-legend">3. Dine kontaktoplysninger</p>' ),
			$text( 'el_3', 'text', 'navn', 'Navn', 'name', true, 'Skriv dit navn' ),
			$text( 'el_4', 'tel', 'telefon', 'Telefon', 'tel', true, 'Skriv dit telefonnummer' ),
			$text( 'el_5', 'email', 'email', 'E-mail (valgfri)', 'email', false, '' ),
			$radio( 'el_6', 'tidspunkt', '4. Hvornår må vi ringe?', array( 'Formiddag', 'Eftermiddag', 'Når som helst' ), 'Vælg hvornår vi må ringe' ),
			$html( 'el_note', '<p class="nsh-ff-note">Skriv ikke helbredsoplysninger her. Dem gennemgår vi med dig i telefonen.</p>' ),
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
	if ( 'clear_cache' === $fn ) {
		if ( class_exists( '\Elementor\Plugin' ) ) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }
		return array( 'summary' => 'ok' );
	}
	return array( 'error' => 'ukendt trin ' . $fn );
}
