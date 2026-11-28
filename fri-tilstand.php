<?php
/**
 * Gratis-tilstand: sitet kan køre uden Elementor Pro.
 * Alt herunder er kun aktivt, når Elementor Pro IKKE er aktiv, så livesitet er uændret, indtil Pro slås fra.
 * - Header og footer: Elementor-skabeloner (gratis type "section"), id'er i option nordic_free_hf.
 * - Menu: shortcode [nordic_menu] (WordPress-menuen main-menu) i stedet for Pros menu-widget.
 * - Pros "Brugerdefineret CSS" og "sticky" er flyttet til option nordic_free_css (pr. side).
 * - Hjælpeklasser til gallerier (nd-gal) og udtalelser (nd-tgrid), som erstatter Pros galleri og karrusel.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function nordic_free_mode() { return ! defined( 'ELEMENTOR_PRO_VERSION' ) && did_action( 'elementor/loaded' ); }
function nordic_free_hf() { $o = (array) get_option( 'nordic_free_hf', array() ); return array( (int) ( $o['h'] ?? 0 ), (int) ( $o['f'] ?? 0 ) ); }

/* Menu (bruges i headeren). Mobil og tablet: knap med fold-ud-menu. */
add_shortcode( 'nordic_menu', function ( $a ) {
	$a = shortcode_atts( array( 'menu' => 'main-menu' ), $a );
	$m = wp_nav_menu( array( 'menu' => $a['menu'], 'container' => false, 'menu_class' => 'ndm__list', 'echo' => false, 'fallback_cb' => false, 'depth' => 2 ) );
	if ( ! $m ) { return ''; }
	$id = 'ndm-' . wp_unique_id();
	return '<nav class="ndm" aria-label="Hovedmenu"><button class="ndm__btn" type="button" aria-expanded="false" aria-controls="' . esc_attr( $id ) . '"><span class="screen-reader-text">Menu</span><svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></button><div class="ndm__wrap" id="' . esc_attr( $id ) . '">' . $m . '</div></nav>';
} );

add_action( 'wp', function () {
	if ( ! nordic_free_mode() || is_admin() ) { return; }
	list( $h, $f ) = nordic_free_hf();
	if ( $h || $f ) {
		add_filter( 'hello_elementor_header_footer', '__return_false' );
		// Skiplinket først, derefter klinikbjælken (prioritet 20) og headeren (30).
		add_filter( 'hello_elementor_enable_skip_link', '__return_false' );
		add_action( 'wp_body_open', function () { echo '<a class="skip-link screen-reader-text" href="#content">Spring til indhold</a>'; }, 1 );
	}
	add_action( 'wp_enqueue_scripts', function () use ( $h, $f ) {
		foreach ( array( $h, $f ) as $id ) { if ( $id && class_exists( '\Elementor\Core\Files\CSS\Post' ) ) { \Elementor\Core\Files\CSS\Post::create( $id )->enqueue(); } }
	}, 20 );
	if ( $h ) { add_action( 'wp_body_open', function () use ( $h ) { echo '<header id="site-header" class="nd-site-header">' . \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $h ) . '</header>'; }, 30 ); }
	if ( $f ) { add_action( 'wp_footer', function () use ( $f ) { echo '<footer id="site-footer" class="nd-site-footer">' . \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $f ) . '</footer>'; }, 1 ); }
} );

add_action( 'wp_head', function () {
	if ( ! nordic_free_mode() ) { return; }
	$css = '.ndm{position:relative;font-family:Manrope,sans-serif}'
		. '.ndm__btn{display:none;background:transparent;border:0;padding:6px;color:var(--e-global-color-2e286aa,#183A70);cursor:pointer;line-height:0}'
		. '.ndm__list{list-style:none;margin:0;padding:0;display:flex;flex-wrap:wrap;gap:15px;align-items:center}'
		. '.ndm__list li{position:relative;margin:0}'
		. '.ndm__list a{display:block;padding:10px 1px;font-size:15px;font-weight:400;color:var(--e-global-color-2e286aa,#183A70);text-decoration:none}'
		. '.ndm__list a:hover,.ndm__list .current-menu-item>a{opacity:.8}'
		. '.ndm__list .sub-menu{display:none;position:absolute;left:0;top:100%;min-width:220px;list-style:none;margin:0;padding:6px 0;background:var(--e-global-color-accent,#C79A5B);z-index:50;box-shadow:0 8px 24px rgba(16,42,76,.12)}'
		. '.ndm__list li:hover>.sub-menu,.ndm__list li:focus-within>.sub-menu{display:block}'
		. '.ndm__list .sub-menu a{padding:10px 16px;font-size:14px;font-weight:500}'
		. '.ndm__list .sub-menu a:hover{background:var(--e-global-color-text,#1C2B45);color:#fff;opacity:1}'
		. '@media(max-width:1024px){.ndm__btn{display:block;margin-left:auto}.ndm__wrap{display:none;position:absolute;right:0;top:100%;width:min(92vw,360px);background:var(--e-global-color-accent,#C79A5B);z-index:60;box-shadow:0 10px 30px rgba(16,42,76,.18)}.ndm.is-open .ndm__wrap{display:block}.ndm__list{flex-direction:column;align-items:stretch;gap:0}.ndm__list a{padding:12px 18px;font-size:14px;font-weight:500}.ndm__list .sub-menu{display:block;position:static;box-shadow:none;padding:0 0 0 12px;background:transparent}}'
		/* Galleri (erstatter Pros galleri). */
		. '.nd-gal{display:grid!important;gap:10px;grid-template-columns:repeat(var(--nd-c,3),minmax(0,1fr))}'
		. '.nd-gal>.elementor-widget-image{width:100%;max-width:100%;margin:0}.nd-gal img{width:100%!important;height:auto;display:block;object-fit:cover;aspect-ratio:var(--nd-ar,auto)}'
		. '.nd-c1{--nd-c:1}.nd-c2{--nd-c:2}.nd-c3{--nd-c:3}.nd-c4{--nd-c:4}.nd-ar-1-1{--nd-ar:1/1}.nd-ar-3-2{--nd-ar:3/2}.nd-ar-4-3{--nd-ar:4/3}.nd-ar-16-9{--nd-ar:16/9}'
		. '@media(max-width:1024px){.nd-ct1{--nd-c:1}.nd-ct2{--nd-c:2}.nd-ct3{--nd-c:3}.nd-ct4{--nd-c:4}}'
		. '@media(max-width:767px){.nd-cm1{--nd-c:1}.nd-cm2{--nd-c:2}.nd-cm3{--nd-c:3}}'
		/* Udtalelser (erstatter Pros karrusel): 3 / 2 / 1 kolonner. */
		. '.nd-tgrid{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr));gap:20px}.nd-tgrid>.e-con{width:auto!important;max-width:none!important}'
		. '@media(max-width:1024px){.nd-tgrid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:767px){.nd-tgrid{grid-template-columns:1fr}}';
	$pp = (array) get_option( 'nordic_free_css', array() );
	$extra = (string) ( $pp['global'] ?? '' );
	if ( is_singular() ) { $extra .= (string) ( $pp[ get_queried_object_id() ] ?? '' ); }
	echo '<style id="nordic-fri-tilstand">' . $css . $extra . '</style>' . "\n";
}, 30 );

add_action( 'wp_footer', function () {
	if ( ! nordic_free_mode() ) { return; }
	echo "<script>document.addEventListener('click',function(e){var b=e.target.closest('.ndm__btn');if(b){var n=b.closest('.ndm'),o=n.classList.toggle('is-open');b.setAttribute('aria-expanded',o?'true':'false');return;}document.querySelectorAll('.ndm.is-open').forEach(function(n){if(!n.contains(e.target)){n.classList.remove('is-open');n.querySelector('.ndm__btn').setAttribute('aria-expanded','false');}});});document.addEventListener('keydown',function(e){if(e.key==='Escape'){document.querySelectorAll('.ndm.is-open').forEach(function(n){n.classList.remove('is-open');var b=n.querySelector('.ndm__btn');b.setAttribute('aria-expanded','false');b.focus();});}});</script>\n";
}, 99 );
