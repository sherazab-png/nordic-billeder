<?php
/**
 * Plugin Name: Nordic testmiljø-lås
 * Description: Lukket testkopi: kræver login, skjult for søgemaskiner, sender ingen e-mails (de logges i stedet).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
add_action( 'send_headers', function () { header( 'X-Robots-Tag: noindex, nofollow', true ); } );
add_filter( 'pre_option_blog_public', function () { return '0'; } );
add_action( 'init', function () {
	if ( wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) || is_user_logged_in() ) { return; }
	// Testadgang uden login (kun til automatiske tests): ?nordic_dev=<kode> sætter en cookie i 8 timer.
	if ( defined( 'NORDIC_DEV_PASS' ) && NORDIC_DEV_PASS ) {
		if ( isset( $_GET['nordic_dev'] ) && hash_equals( NORDIC_DEV_PASS, (string) $_GET['nordic_dev'] ) ) { setcookie( 'nordic_dev', hash_hmac( 'sha256', 'ok', NORDIC_DEV_PASS ), time() + 8 * 3600, '/', '', true, true ); return; }
		if ( isset( $_COOKIE['nordic_dev'] ) && hash_equals( hash_hmac( 'sha256', 'ok', NORDIC_DEV_PASS ), (string) $_COOKIE['nordic_dev'] ) ) { return; }
	}
	$u = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
	if ( preg_match( '#^/(wp-login\.php|wp-admin/admin-ajax\.php|wp-json/nordic-booking/)#', $u ) || false !== strpos( $u, 'nordic_import=1' ) ) { return; }
	if ( is_admin() ) { return; }
	auth_redirect();
}, 0 );
// E-mails sendes ikke fra testmiljøet. De gemmes, så de kan kontrolleres. Med option nordic_dev_mail_fail=1 simuleres en fejl.
add_filter( 'pre_wp_mail', function ( $r, $a ) {
	$log = (array) get_option( 'nordic_dev_mail', array() );
	$fail = (bool) get_option( 'nordic_dev_mail_fail' );
	array_unshift( $log, array( 'at' => gmdate( 'c' ), 'to' => $a['to'], 'subject' => $a['subject'], 'body' => $a['message'], 'fail' => $fail, 'att' => array_map( function ( $f ) { return basename( $f ) . ': ' . ( file_exists( $f ) ? substr( (string) file_get_contents( $f ), 0, 400 ) : 'MANGLER' ); }, (array) ( $a['attachments'] ?? array() ) ) ) );
	update_option( 'nordic_dev_mail', array_slice( $log, 0, 100 ), false );
	return ! $fail;
}, 1, 2 );
add_action( 'wp_body_open', function () {
	echo '<div style="background:#B42318;color:#fff;text-align:center;padding:6px;font:600 14px/1.4 system-ui;position:relative;z-index:99999">TESTMILJØ - dev.nordicdexascan.dk - ingen rigtige bookinger eller betalinger</div>';
} );
