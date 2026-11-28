<?php
/* Kerne: indstillinger, tabeller, kryptering, roller, log, tider og bookingstatus. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function nb_settings() {
	$d = array(
		'price_ore'    => 99900,
		'currency'     => 'dkk',
		'product'      => 'Basis helkrops-DEXA',
		'hold_min'     => 30,      // Stripe kræver mindst 30 minutter for en Checkout-session.
		'grace_min'    => 10,      // Ekstra ventetid før en udløbet reservation frigives (forsinkede webhooks).
		'lead_hours'   => 2,       // Tidligste booking i forhold til nu.
		'purge_days'   => 30,      // Visitation slettes dette antal dage efter scanningstidspunktet (afventer ejerens beslutning).
		'clinic_email' => 'info@nordicspecialhospital.dk',
		'address'      => "Nordic DEXA Scan\nLaurits Olsens Vej 1, 1. sal\n2650 Hvidovre",
		'phone'        => '77 34 00 36',
		'cancel_text'  => 'Afbestilling skal ske senest 48 timer før din tid.',
		'terms_url'    => '',
		'privacy_url'  => '',
		'page_url'     => '',
		'gift_page_url' => '',
	);
	return wp_parse_args( (array) get_option( 'nb_settings', array() ), $d );
}

function nb_t( $name ) { global $wpdb; return $wpdb->prefix . 'nb_' . $name; }

function nb_activate() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$c = $wpdb->get_charset_collate();
	dbDelta( 'CREATE TABLE ' . nb_t( 'slots' ) . " (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		starts_utc DATETIME NOT NULL,
		dur_min SMALLINT UNSIGNED NOT NULL DEFAULT 20,
		status VARCHAR(10) NOT NULL DEFAULT 'open',
		booking_id BIGINT UNSIGNED NULL,
		held_until DATETIME NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY starts_utc (starts_utc),
		KEY booking_id (booking_id)
	) $c;" );
	dbDelta( 'CREATE TABLE ' . nb_t( 'bookings' ) . " (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		ref CHAR(24) NOT NULL,
		slot_id BIGINT UNSIGNED NOT NULL,
		starts_utc DATETIME NOT NULL,
		status VARCHAR(20) NOT NULL DEFAULT 'hold',
		name VARCHAR(190) NOT NULL,
		email VARCHAR(190) NOT NULL,
		phone VARCHAR(40) NOT NULL,
		visit MEDIUMTEXT NULL,
		visit_at DATETIME NULL,
		cpr TEXT NULL,
		gift_id BIGINT UNSIGNED NULL,
		receipt_url VARCHAR(500) NULL,
		clinic_mail_at DATETIME NULL,
		reminded_at DATETIME NULL,
		amount INT UNSIGNED NOT NULL,
		currency CHAR(3) NOT NULL,
		session_id VARCHAR(255) NULL,
		pi_id VARCHAR(255) NULL,
		refunded INT UNSIGNED NOT NULL DEFAULT 0,
		mail_state VARCHAR(20) NOT NULL DEFAULT '',
		created_at DATETIME NOT NULL,
		updated_at DATETIME NOT NULL,
		paid_at DATETIME NULL,
		decided_at DATETIME NULL,
		decided_by BIGINT UNSIGNED NULL,
		purge_after DATETIME NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY ref (ref),
		KEY status (status),
		KEY slot_id (slot_id),
		KEY pi_id (pi_id(64)),
		KEY session_id (session_id(64))
	) $c;" );
	dbDelta( 'CREATE TABLE ' . nb_t( 'events' ) . " (
		id VARCHAR(255) NOT NULL,
		type VARCHAR(100) NOT NULL,
		received DATETIME NOT NULL,
		PRIMARY KEY  (id(191))
	) $c;" );
	dbDelta( 'CREATE TABLE ' . nb_t( 'log' ) . " (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		at DATETIME NOT NULL,
		booking_id BIGINT UNSIGNED NULL,
		user_id BIGINT UNSIGNED NULL,
		action VARCHAR(40) NOT NULL,
		detail VARCHAR(255) NOT NULL DEFAULT '',
		PRIMARY KEY  (id),
		KEY booking_id (booking_id)
	) $c;" );
	do_action( 'nb_install_tables', $c );
	add_role( 'nb_laege', 'Læge (booking)', array( 'read' => true, 'nb_manage' => true, 'nb_view_health' => true ) );
	$admin = get_role( 'administrator' );
	if ( $admin ) { $admin->add_cap( 'nb_manage' ); $admin->add_cap( 'nb_view_health' ); }
	nb_key(); // Opret nøglefil hvis den mangler.
	if ( ! wp_next_scheduled( 'nb_cron_5' ) ) { wp_schedule_event( time() + 60, 'nb_5min', 'nb_cron_5' ); }
	if ( ! wp_next_scheduled( 'nb_cron_daily' ) ) { wp_schedule_event( time() + 300, 'daily', 'nb_cron_daily' ); }
	update_option( 'nb_db_ver', NB_VER );
}

add_filter( 'cron_schedules', function ( $s ) { $s['nb_5min'] = array( 'interval' => 300, 'display' => 'Hvert 5. minut' ); return $s; } );

function nb_now() { return gmdate( 'Y-m-d H:i:s' ); }

function nb_log( $booking_id, $action, $detail = '' ) {
	global $wpdb;
	$wpdb->insert( nb_t( 'log' ), array( 'at' => nb_now(), 'booking_id' => $booking_id ?: null, 'user_id' => get_current_user_id() ?: null, 'action' => substr( $action, 0, 40 ), 'detail' => substr( (string) $detail, 0, 255 ) ) );
}

/* ---------- Kryptering af visitationen (libsodium). Nøglen ligger uden for webroden. ---------- */
function nb_key_path() { return defined( 'NB_KEY_FILE' ) ? NB_KEY_FILE : dirname( untrailingslashit( ABSPATH ) ) . '/nordic-booking.key'; }
function nb_key() {
	static $k = null;
	if ( null !== $k ) { return $k; }
	$p = nb_key_path();
	if ( ! file_exists( $p ) ) {
		$dir = dirname( $p );
		if ( is_writable( $dir ) ) { file_put_contents( $p, base64_encode( random_bytes( SODIUM_CRYPTO_SECRETBOX_KEYBYTES ) ) ); @chmod( $p, 0600 ); }
	}
	$raw = file_exists( $p ) ? base64_decode( trim( (string) file_get_contents( $p ) ), true ) : false;
	$k = ( $raw && strlen( $raw ) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES ) ? $raw : false;
	return $k;
}
function nb_encrypt( array $data ) {
	$k = nb_key(); if ( ! $k ) { return false; }
	$n = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
	return 'v1:' . base64_encode( $n . sodium_crypto_secretbox( wp_json_encode( $data ), $n, $k ) );
}
function nb_decrypt( $blob ) {
	$k = nb_key(); if ( ! $k || ! is_string( $blob ) || 0 !== strpos( $blob, 'v1:' ) ) { return null; }
	$raw = base64_decode( substr( $blob, 3 ), true ); if ( ! $raw ) { return null; }
	$p = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $k );
	return false === $p ? null : json_decode( $p, true );
}

/* ---------- Tider ---------- */
function nb_tz() { return new DateTimeZone( 'Europe/Copenhagen' ); }
function nb_local( $utc, $fmt = 'Y-m-d H:i' ) { $d = new DateTime( $utc, new DateTimeZone( 'UTC' ) ); $d->setTimezone( nb_tz() ); return $d->format( $fmt ); }
function nb_human( $utc ) {
	$d = new DateTime( $utc, new DateTimeZone( 'UTC' ) ); $d->setTimezone( nb_tz() );
	$days = array( 'søndag', 'mandag', 'tirsdag', 'onsdag', 'torsdag', 'fredag', 'lørdag' );
	$mon = array( '', 'januar', 'februar', 'marts', 'april', 'maj', 'juni', 'juli', 'august', 'september', 'oktober', 'november', 'december' );
	return $days[ (int) $d->format( 'w' ) ] . ' ' . (int) $d->format( 'j' ) . '. ' . $mon[ (int) $d->format( 'n' ) ] . ' ' . $d->format( 'Y' ) . ' kl. ' . $d->format( 'H:i' );
}

/* Ledige tider i et datointerval (lokal dato). */
function nb_free_slots( $from, $to ) {
	global $wpdb; $s = nb_settings();
	$a = new DateTime( $from . ' 00:00', nb_tz() ); $a->setTimezone( new DateTimeZone( 'UTC' ) );
	$b = new DateTime( $to . ' 23:59:59', nb_tz() ); $b->setTimezone( new DateTimeZone( 'UTC' ) );
	$min = gmdate( 'Y-m-d H:i:s', time() + (int) $s['lead_hours'] * 3600 );
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, starts_utc FROM ' . nb_t( 'slots' ) . " WHERE status='open' AND booking_id IS NULL AND starts_utc BETWEEN %s AND %s AND starts_utc > %s ORDER BY starts_utc", $a->format( 'Y-m-d H:i:s' ), $b->format( 'Y-m-d H:i:s' ), $min ), ARRAY_A );
	return array_map( function ( $r ) { return array( 'id' => (int) $r['id'], 'date' => nb_local( $r['starts_utc'], 'Y-m-d' ), 'time' => nb_local( $r['starts_utc'], 'H:i' ) ); }, $rows );
}

/* Atomisk reservation af en tid: kun én forespørgsel kan få rækken. Det forhindrer dobbeltbooking. */
function nb_claim_slot( $slot_id, $booking_id, $held_until ) {
	global $wpdb; $s = nb_settings();
	$min = gmdate( 'Y-m-d H:i:s', time() + (int) $s['lead_hours'] * 3600 );
	return 1 === (int) $wpdb->query( $wpdb->prepare( 'UPDATE ' . nb_t( 'slots' ) . " SET booking_id=%d, held_until=%s WHERE id=%d AND status='open' AND booking_id IS NULL AND starts_utc > %s", $booking_id, $held_until, $slot_id, $min ) );
}
function nb_release_slot( $booking ) {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( 'UPDATE ' . nb_t( 'slots' ) . ' SET booking_id=NULL, held_until=NULL WHERE id=%d AND booking_id=%d', $booking->slot_id, $booking->id ) );
}
function nb_fix_slot( $booking ) {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( 'UPDATE ' . nb_t( 'slots' ) . ' SET held_until=NULL WHERE id=%d AND booking_id=%d', $booking->slot_id, $booking->id ) );
}

/* ---------- Bookinger ---------- */
function nb_get( $field, $val ) {
	global $wpdb;
	if ( ! in_array( $field, array( 'id', 'ref', 'session_id', 'pi_id' ), true ) || '' === (string) $val ) { return null; }
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . nb_t( 'bookings' ) . " WHERE $field=%s", $val ) );
}
/* Statusskift kun fra tilladte statusser (optimistisk lås mod samtidige webhooks og klik). */
function nb_transition( $b, array $from, $to, array $extra = array() ) {
	global $wpdb;
	$set = array_merge( $extra, array( 'status' => $to, 'updated_at' => nb_now() ) );
	$cols = array(); $vals = array();
	foreach ( $set as $k => $v ) { $cols[] = null === $v ? "`$k`=NULL" : "`$k`=%s"; if ( null !== $v ) { $vals[] = $v; } }
	$in = implode( ',', array_fill( 0, count( $from ), '%s' ) );
	$vals = array_merge( $vals, array( $b->id ), $from );
	$n = $wpdb->query( $wpdb->prepare( 'UPDATE ' . nb_t( 'bookings' ) . ' SET ' . implode( ',', $cols ) . " WHERE id=%d AND status IN ($in)", $vals ) );
	if ( 1 === (int) $n ) { nb_log( $b->id, 'status', $b->status . ' -> ' . $to ); return nb_get( 'id', $b->id ); }
	return false;
}

function nb_status_label( $st ) {
	$l = array( 'hold' => 'Reserveret (betaling i gang)', 'paid' => 'Betalt - afventer læge', 'approved' => 'Godkendt', 'rejected' => 'Afvist og refunderet', 'cancelled' => 'Afbestilt', 'refunded' => 'Refunderet', 'expired' => 'Udløbet (ikke betalt)', 'completed' => 'Gennemført', 'conflict' => 'Konflikt - refunderet', 'refund_failed' => 'Refusion fejlede' );
	return $l[ $st ] ?? $st;
}

/* Betaling modtaget (fra webhook eller oprydning). Idempotent. */
function nb_mark_paid( $b, $session ) {
	if ( in_array( $b->status, array( 'paid', 'approved', 'completed', 'rejected', 'cancelled', 'refunded', 'conflict' ), true ) ) { return $b; }
	$pi = is_array( $session['payment_intent'] ?? null ) ? ( $session['payment_intent']['id'] ?? '' ) : (string) ( $session['payment_intent'] ?? '' );
	$s = nb_settings();
	$purge = gmdate( 'Y-m-d H:i:s', strtotime( $b->starts_utc . ' UTC' ) + (int) $s['purge_days'] * DAY_IN_SECONDS );
	if ( 'expired' === $b->status ) {
		// Betalt efter frigivelse: tag tiden igen hvis den er ledig, ellers refunder.
		global $wpdb;
		$ok = 1 === (int) $wpdb->query( $wpdb->prepare( 'UPDATE ' . nb_t( 'slots' ) . ' SET booking_id=%d, held_until=NULL WHERE id=%d AND booking_id IS NULL', $b->id, $b->slot_id ) );
		if ( ! $ok ) {
			$nb = nb_transition( $b, array( 'expired' ), 'conflict', array( 'pi_id' => $pi, 'paid_at' => nb_now(), 'purge_after' => $purge ) );
			if ( $nb ) { nb_refund( $nb, null, 'conflict' ); nb_mail_conflict( $nb ); }
			return $nb ?: nb_get( 'id', $b->id );
		}
	}
	$nb = nb_transition( $b, array( 'hold', 'expired' ), 'paid', array( 'pi_id' => $pi, 'paid_at' => nb_now(), 'purge_after' => $purge ) );
	if ( $nb ) {
		nb_fix_slot( $nb );
		// Kvitteringslink fra Stripe til klinikmailen.
		$pi_obj = $pi ? nb_stripe( 'GET', 'payment_intents/' . rawurlencode( $pi ), array( 'expand' => array( 'latest_charge' ) ) ) : null;
		if ( is_array( $pi_obj ) && ! empty( $pi_obj['latest_charge']['receipt_url'] ) ) { $GLOBALS['wpdb']->update( nb_t( 'bookings' ), array( 'receipt_url' => substr( $pi_obj['latest_charge']['receipt_url'], 0, 500 ) ), array( 'id' => $nb->id ) ); $nb = nb_get( 'id', $nb->id ); }
		nb_mail_paid( $nb );
		if ( $nb->visit_at ) { nb_mail_clinic_new( $nb ); }
	}
	return $nb ?: nb_get( 'id', $b->id );
}

function nb_expire( $b, $why ) {
	$nb = nb_transition( $b, array( 'hold' ), 'expired' );
	if ( $nb ) { nb_release_slot( $nb ); nb_log( $nb->id, 'expired', $why ); }
	return $nb;
}

/* Refusion (fuld når $amount er null). Returnerer true/false. */
function nb_refund( $b, $amount, $reason ) {
	if ( ! $b->pi_id ) { nb_log( $b->id, 'refund_error', 'mangler betalings-id' ); return false; }
	$left = (int) $b->amount - (int) $b->refunded;
	$amt = null === $amount ? $left : min( (int) $amount, $left );
	if ( $amt <= 0 ) { return true; }
	$n = (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT COUNT(*) FROM ' . nb_t( 'log' ) . " WHERE booking_id=%d AND action='refund'", $b->id ) );
	$r = nb_stripe( 'POST', 'refunds', array( 'payment_intent' => $b->pi_id, 'amount' => $amt, 'metadata' => array( 'nb_ref' => $b->ref ) ), 'nb-rf-' . $b->ref . '-' . $n . '-' . $amt );
	if ( is_wp_error( $r ) ) { nb_log( $b->id, 'refund_error', $r->get_error_message() ); return false; }
	// Kumulativt beløb (samme tal som Stripes amount_refunded), så en webhook, der når frem først, ikke giver dobbelttælling.
	$GLOBALS['wpdb']->query( $GLOBALS['wpdb']->prepare( 'UPDATE ' . nb_t( 'bookings' ) . ' SET refunded=LEAST(amount, GREATEST(refunded, %d)), updated_at=%s WHERE id=%d', (int) $b->refunded + $amt, nb_now(), $b->id ) );
	nb_log( $b->id, 'refund', $amt . ' (' . $reason . ') ' . ( $r['id'] ?? '' ) );
	return true;
}

/* ---------- Cron ---------- */
add_action( 'nb_cron_5', 'nb_cleanup_holds' );
add_action( 'nb_cron_5', 'nb_remind' );
/* Påmindelse: betalt, men de supplerende spørgsmål er ikke besvaret efter 4 timer (eller tidligere, hvis scanningen er inden for 24 timer). Sendes én gang. */
function nb_remind() {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . nb_t( 'bookings' ) . " WHERE status='paid' AND visit_at IS NULL AND reminded_at IS NULL AND starts_utc > %s AND ( paid_at < %s OR ( starts_utc < %s AND paid_at < %s ) ) LIMIT 20", nb_now(), gmdate( 'Y-m-d H:i:s', time() - 4 * HOUR_IN_SECONDS ), gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ), gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) ) );
	foreach ( $rows as $b ) {
		if ( 1 !== (int) $wpdb->query( $wpdb->prepare( 'UPDATE ' . nb_t( 'bookings' ) . ' SET reminded_at=%s WHERE id=%d AND reminded_at IS NULL', nb_now(), $b->id ) ) ) { continue; }
		nb_mail_reminder( $b );
		// Klinikken får bookingen nu, så tiden kan sættes i kalenderen, selv om svarene mangler.
		if ( ! $b->clinic_mail_at ) { nb_mail_clinic_new( nb_get( 'id', $b->id ) ); }
	}
}
function nb_cleanup_holds() {
	global $wpdb; $s = nb_settings();
	$cut = gmdate( 'Y-m-d H:i:s', time() - (int) $s['grace_min'] * 60 );
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT b.* FROM ' . nb_t( 'bookings' ) . ' b JOIN ' . nb_t( 'slots' ) . " s ON s.id=b.slot_id AND s.booking_id=b.id WHERE b.status='hold' AND s.held_until < %s LIMIT 50", $cut ) );
	foreach ( $rows as $b ) {
		// Spørg Stripe, før tiden frigives: en betaling kan være gennemført, selv om webhooken ikke er kommet.
		if ( $b->session_id ) {
			$cs = nb_stripe( 'GET', 'checkout/sessions/' . rawurlencode( $b->session_id ) );
			if ( is_wp_error( $cs ) ) { nb_log( $b->id, 'cleanup_error', $cs->get_error_message() ); continue; }
			if ( 'complete' === ( $cs['status'] ?? '' ) && 'paid' === ( $cs['payment_status'] ?? '' ) ) { nb_mark_paid( $b, $cs ); continue; }
			if ( 'open' === ( $cs['status'] ?? '' ) ) { nb_stripe( 'POST', 'checkout/sessions/' . rawurlencode( $b->session_id ) . '/expire', array(), 'nb-exp-' . $b->ref ); }
		}
		nb_expire( $b, 'reservation udløbet' );
	}
}
add_action( 'nb_cron_daily', 'nb_purge' );
function nb_purge() {
	global $wpdb;
	$n = $wpdb->query( $wpdb->prepare( 'UPDATE ' . nb_t( 'bookings' ) . ' SET visit=NULL, cpr=NULL WHERE ( visit IS NOT NULL OR cpr IS NOT NULL ) AND purge_after IS NOT NULL AND purge_after < %s', nb_now() ) );
	if ( $n ) { nb_log( null, 'purge_visit', (string) $n ); }
	// Ikke-betalte reservationer slettes helt efter 7 dage (dataminimering).
	$old = gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS );
	$m = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . nb_t( 'bookings' ) . " WHERE status='expired' AND updated_at < %s", $old ) );
	if ( $m ) { nb_log( null, 'purge_expired', (string) $m ); }
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . nb_t( 'events' ) . ' WHERE received < %s', gmdate( 'Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS ) ) );
}
