<?php
/* Administration: bookinger, godkendelse/afvisning, refusion, tider, indstillinger og log. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'admin_menu', function () {
	add_menu_page( 'Booking', 'Booking', 'nb_manage', 'nb-bookings', 'nb_admin_bookings', 'dashicons-calendar-alt', 26 );
	add_submenu_page( 'nb-bookings', 'Bookinger', 'Bookinger', 'nb_manage', 'nb-bookings', 'nb_admin_bookings' );
	add_submenu_page( 'nb-bookings', 'Tider', 'Tider', 'nb_manage', 'nb-slots', 'nb_admin_slots' );
	add_submenu_page( 'nb-bookings', 'Indstillinger', 'Indstillinger', 'manage_options', 'nb-settings', 'nb_admin_settings' );
	add_submenu_page( 'nb-bookings', 'Log', 'Log', 'nb_manage', 'nb-log', 'nb_admin_log' );
	if ( nb_mock() ) { add_submenu_page( 'nb-bookings', 'Test', 'Test', 'manage_options', 'nb-test', 'nb_admin_test' ); }
} );

add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'nb_manage' ) ) { return; }
	global $wpdb;
	$n = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . nb_t( 'bookings' ) . " WHERE status='paid'" );
	$f = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . nb_t( 'bookings' ) . " WHERE mail_state LIKE 'fejl:%' OR status='refund_failed'" );
	if ( $n ) { echo '<div class="notice notice-warning"><p><a href="' . esc_url( admin_url( 'admin.php?page=nb-bookings&st=paid' ) ) . '">' . (int) $n . ' betalte bookinger afventer lægefaglig vurdering.</a></p></div>'; }
	if ( $f ) { echo '<div class="notice notice-error"><p><a href="' . esc_url( admin_url( 'admin.php?page=nb-bookings&st=problem' ) ) . '">' . (int) $f . ' bookinger har en fejlet e-mail eller refusion.</a></p></div>'; }
	if ( ! nb_key() ) { echo '<div class="notice notice-error"><p>Booking: krypteringsnøglen mangler. Online booking er lukket.</p></div>'; }
	if ( nb_live_key() && ! ( defined( 'NORDIC_BOOKING_LIVE' ) && NORDIC_BOOKING_LIVE ) ) { echo '<div class="notice notice-error"><p>Booking: der er indsat en livenøgle, men livebetaling er ikke slået til. Online booking er lukket.</p></div>'; }
} );

/* Handlinger (POST med nonce). */
add_action( 'admin_post_nb_act', function () {
	if ( ! current_user_can( 'nb_manage' ) ) { wp_die( 'Ingen adgang' ); }
	$id = (int) ( $_POST['b'] ?? 0 );
	check_admin_referer( 'nb_act_' . $id );
	$b = nb_get( 'id', $id ); if ( ! $b ) { wp_die( 'Ukendt booking' ); }
	$act = sanitize_key( $_POST['act'] ?? '' );
	$amt = isset( $_POST['amount'] ) && '' !== $_POST['amount'] ? (int) round( (float) str_replace( ',', '.', (string) $_POST['amount'] ) * 100 ) : null;
	$msg = nb_do_action( $b, $act, $amt );
	wp_safe_redirect( add_query_arg( array( 'page' => 'nb-bookings', 'b' => $id, 'msg' => rawurlencode( $msg ) ), admin_url( 'admin.php' ) ) ); exit;
} );


/* Fælles handlinger (bruges af admin og af testværktøjet). */
function nb_do_action( $b, $act, $amt = null ) {
	$msg = ''; $id = (int) $b->id;
	if ( 'approve' === $act && ! $b->visit_at ) { return 'Kan ikke godkendes: de supplerende spørgsmål er ikke besvaret endnu.'; }
	if ( 'approve' === $act ) {
		$nb = nb_transition( $b, array( 'paid' ), 'approved', array( 'decided_at' => nb_now(), 'decided_by' => get_current_user_id() ) );
		$msg = $nb ? ( nb_mail_approved( $nb ) ? 'Godkendt, og bekræftelse sendt.' : 'Godkendt, men e-mailen fejlede.' ) : 'Kunne ikke godkende (status er ændret).';
	} elseif ( 'reject' === $act ) {
		$nb = nb_transition( $b, array( 'paid' ), 'rejected', array( 'decided_at' => nb_now(), 'decided_by' => get_current_user_id() ) );
		if ( $nb ) {
			$ok = nb_refund( $nb, null, 'afvist' );
			if ( $ok && $nb->gift_id ) { nb_gift_mark_refunded( $nb->gift_id, 'afvist' ); }
			if ( ! $ok ) { nb_transition( $nb, array( 'rejected' ), 'refund_failed' ); }
			nb_release_slot( $nb );
			$msg = $ok ? ( nb_mail_rejected( nb_get( 'id', $id ) ) ? 'Afvist, refunderet og patienten informeret.' : 'Afvist og refunderet, men e-mailen fejlede.' ) : 'Afvist, men refusionen fejlede. Se loggen og refunder i ' . nb_pay_name() . '.';
		} else { $msg = 'Kunne ikke afvise (status er ændret).'; }
	} elseif ( 'cancel' === $act ) {
		$nb = nb_transition( $b, array( 'paid', 'approved' ), 'cancelled', array( 'decided_at' => nb_now(), 'decided_by' => get_current_user_id() ) );
		if ( $nb ) {
			$ok = 0 === $amt ? true : nb_refund( $nb, $amt, 'afbestilt' );
			if ( $nb->gift_id ) { if ( 0 === $amt ) { nb_gift_reactivate( $nb ); } elseif ( $ok && null === $amt ) { nb_gift_mark_refunded( $nb->gift_id, 'afbestilt' ); } }
			if ( ! $ok ) { nb_transition( $nb, array( 'cancelled' ), 'refund_failed' ); }
			nb_release_slot( $nb );
			$nb = nb_get( 'id', $id );
			nb_mail_cancelled( $nb, (int) $nb->refunded );
			$msg = $ok ? 'Afbestilt. Refunderet: ' . number_format( $nb->refunded / 100, 2, ',', '.' ) . ' kr.' : 'Afbestilt, men refusionen fejlede. Se loggen.';
		} else { $msg = 'Kunne ikke afbestille (status er ændret).'; }
	} elseif ( 'retry_refund' === $act && 'refund_failed' === $b->status ) {
		$ok = nb_refund( $b, null, 'nyt forsøg' );
		$msg = $ok ? 'Refusion gennemført.' : 'Refusionen fejlede igen.';
		if ( $ok ) { nb_transition( $b, array( 'refund_failed' ), 'refunded' ); }
	} elseif ( 'complete' === $act ) {
		$msg = nb_transition( $b, array( 'approved' ), 'completed' ) ? 'Markeret som gennemført.' : 'Kunne ikke ændre status.';
	} elseif ( 'resend' === $act ) {
		$msg = nb_mail_resend( $b ) ? 'E-mail sendt igen.' : 'E-mailen fejlede igen.';
	}
	return $msg ?: 'Ukendt handling.';
}

function nb_btn( $b, $act, $label, $cls = 'button', $extra = '', $confirm = '' ) {
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin:0 6px 6px 0"' . ( $confirm ? ' onsubmit="return confirm(' . esc_attr( wp_json_encode( $confirm ) ) . ')"' : '' ) . '>';
	wp_nonce_field( 'nb_act_' . $b->id );
	echo '<input type="hidden" name="action" value="nb_act"><input type="hidden" name="b" value="' . (int) $b->id . '"><input type="hidden" name="act" value="' . esc_attr( $act ) . '">' . $extra . '<button class="' . esc_attr( $cls ) . '">' . esc_html( $label ) . '</button></form>';
}

function nb_admin_bookings() {
	global $wpdb;
	if ( ! empty( $_GET['b'] ) ) { nb_admin_detail( (int) $_GET['b'] ); return; }
	$st = sanitize_key( $_GET['st'] ?? '' );
	$where = "status<>'expired'";
	if ( 'paid' === $st ) { $where = "status='paid'"; }
	elseif ( 'problem' === $st ) { $where = "(mail_state LIKE 'fejl:%' OR status='refund_failed')"; }
	elseif ( 'all' === $st ) { $where = '1=1'; }
	$rows = $wpdb->get_results( 'SELECT id, ref, starts_utc, status, name, mail_state, refunded, amount, visit_at FROM ' . nb_t( 'bookings' ) . " WHERE $where ORDER BY starts_utc DESC LIMIT 300" );
	echo '<div class="wrap"><h1>Bookinger</h1><p>';
	foreach ( array( '' => 'Aktuelle', 'paid' => 'Afventer læge', 'problem' => 'Fejl', 'all' => 'Alle' ) as $k => $l ) { echo '<a class="button' . ( $st === $k ? ' button-primary' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=nb-bookings' . ( $k ? '&st=' . $k : '' ) ) ) . '">' . esc_html( $l ) . '</a> '; }
	echo '</p><table class="widefat striped"><thead><tr><th>Tid</th><th>Navn</th><th>Status</th><th>Betaling</th><th>E-mail</th><th></th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		echo '<tr><td>' . esc_html( nb_local( $r->starts_utc, 'd.m.Y H:i' ) ) . '</td><td>' . esc_html( $r->name ) . '</td><td>' . esc_html( nb_status_label( $r->status ) ) . ( 'paid' === $r->status && ! $r->visit_at ? ' <i>(svar mangler)</i>' : '' ) . '</td><td>' . esc_html( number_format( $r->amount / 100, 0, ',', '.' ) ) . ' kr.' . ( $r->refunded ? ' (refunderet ' . esc_html( number_format( $r->refunded / 100, 2, ',', '.' ) ) . ')' : '' ) . '</td><td>' . ( 0 === strpos( (string) $r->mail_state, 'fejl' ) ? '<b style="color:#b32d2e">Fejlet</b>' : 'OK' ) . '</td><td><a href="' . esc_url( admin_url( 'admin.php?page=nb-bookings&b=' . (int) $r->id ) ) . '">Åbn</a></td></tr>';
	}
	if ( ! $rows ) { echo '<tr><td colspan="6">Ingen bookinger.</td></tr>'; }
	echo '</tbody></table></div>';
}

function nb_admin_detail( $id ) {
	global $wpdb;
	$b = nb_get( 'id', $id ); if ( ! $b ) { echo '<div class="wrap"><p>Ukendt booking.</p></div>'; return; }
	echo '<div class="wrap"><h1>Booking ' . esc_html( nb_local( $b->starts_utc, 'd.m.Y H:i' ) ) . '</h1><p><a href="' . esc_url( admin_url( 'admin.php?page=nb-bookings' ) ) . '">&larr; Alle bookinger</a></p>';
	if ( ! empty( $_GET['msg'] ) ) { echo '<div class="notice notice-info"><p>' . esc_html( wp_unslash( rawurldecode( $_GET['msg'] ) ) ) . '</p></div>'; }
	echo '<table class="form-table"><tr><th>Status</th><td><b>' . esc_html( nb_status_label( $b->status ) ) . '</b></td></tr>';
	echo '<tr><th>Tid</th><td>' . esc_html( nb_human( $b->starts_utc ) ) . '</td></tr>';
	echo '<tr><th>Navn</th><td>' . esc_html( $b->name ) . '</td></tr><tr><th>E-mail</th><td>' . esc_html( $b->email ) . '</td></tr><tr><th>Telefon</th><td>' . esc_html( $b->phone ) . '</td></tr>';
	if ( current_user_can( 'nb_view_health' ) ) { $c = nb_decrypt( $b->cpr ); echo '<tr><th>Fødselsdato (CPR)</th><td>' . esc_html( $c['cpr'] ?? ( $b->cpr ? 'kan ikke dekrypteres' : 'slettet' ) ) . '</td></tr>'; }
	echo ( $b->receipt_url ? '<tr><th>Kvittering</th><td><a href="' . esc_url( $b->receipt_url ) . '" target="_blank" rel="noopener">Kvittering</a></td></tr>' : '' ) . '<tr><th>Klinikmail</th><td>' . esc_html( $b->clinic_mail_at ? nb_local( $b->clinic_mail_at, 'd.m.Y H:i' ) : 'ikke sendt' ) . '</td></tr>';
	echo '<tr><th>Betaling</th><td>' . esc_html( number_format( $b->amount / 100, 2, ',', '.' ) ) . ' kr.' . ( $b->refunded ? ', refunderet ' . esc_html( number_format( $b->refunded / 100, 2, ',', '.' ) ) . ' kr.' : '' ) . ( $b->pi_id ? '<br><code>' . esc_html( $b->pi_id ) . '</code>' : '' ) . '</td></tr>';
	echo '<tr><th>Bookingnummer</th><td><code>' . esc_html( $b->ref ) . '</code></td></tr>';
	echo '<tr><th>E-mail-status</th><td>' . esc_html( $b->mail_state ?: '-' ) . '</td></tr>';
	echo '<tr><th>Supplerende spørgsmål<br><span class="description">(visitation)</span></th><td>';
	if ( ! current_user_can( 'nb_view_health' ) ) { echo 'Kun synlig for lægerollen.'; }
	elseif ( ! $b->visit_at ) { echo '<b>Ikke besvaret endnu.</b> Patienten har fået et link i betalingsmailen' . ( $b->reminded_at ? ' og en påmindelse ' . esc_html( nb_local( $b->reminded_at, 'd.m H:i' ) ) : '' ) . '.'; }
	elseif ( null === $b->visit ) { echo 'Slettet efter udløb af opbevaringsfristen.'; }
	else {
		$v = nb_decrypt( $b->visit );
		nb_log( $b->id, 'view_health', '' );
		if ( ! $v ) { echo '<b style="color:#b32d2e">Kan ikke dekrypteres (nøglen mangler eller er ændret).</b>'; }
		else {
			$f = array( 'kost' => 'Kostomlægning eller vægttab', 'sport' => 'Optimering af sportspræstation', 'opf' => 'Opfølgning efter sygdom eller behandling', 'andet' => 'Andet' );
			echo 'Formål: <b>' . esc_html( $f[ $v['formaal'] ] ?? $v['formaal'] ) . '</b><br>Gravid: ' . esc_html( $v['gravid'] ) . '<br>Over 197 cm eller 159 kg: ' . esc_html( $v['graense'] ) . '<br>Bemærkning: ' . ( '' !== $v['note'] ? nl2br( esc_html( $v['note'] ) ) : '<i>ingen</i>' );
			echo '<p class="description">Opbevares krypteret og slettes ' . esc_html( $b->purge_after ? nb_local( $b->purge_after, 'd.m.Y' ) : '-' ) . '. Overfør berettigelsen til journalen inden da.</p>';
		}
	}
	echo '</td></tr></table><h2>Handlinger</h2>';
	if ( 'paid' === $b->status ) {
		if ( $b->visit_at ) { nb_btn( $b, 'approve', 'Godkend scanningen', 'button button-primary' ); } else { echo '<p><i>Godkend bliver tilgængelig, når patienten har besvaret de supplerende spørgsmål.</i></p>'; }
		nb_btn( $b, 'reject', 'Afvis og refunder hele beløbet', 'button', '', 'Afvis og refunder hele beløbet? Patienten får besked.' );
	}
	if ( in_array( $b->status, array( 'paid', 'approved' ), true ) ) {
		nb_btn( $b, 'cancel', 'Afbestil og refunder', 'button', '<input type="text" name="amount" placeholder="' . ( $b->gift_id ? 'Tom = refunder køber, 0 = gavekort gyldigt igen' : 'Beløb i kr. (tom = alt)' ) . '" style="width:' . ( $b->gift_id ? '300' : '170' ) . 'px;margin-right:4px">', 'Afbestil bookingen og refunder det angivne beløb?' );
	}
	if ( 'approved' === $b->status ) { nb_btn( $b, 'complete', 'Marker som gennemført' ); }
	if ( 'refund_failed' === $b->status ) { nb_btn( $b, 'retry_refund', 'Prøv refusion igen', 'button button-primary' ); }
	if ( 0 === strpos( (string) $b->mail_state, 'fejl' ) ) { nb_btn( $b, 'resend', 'Send e-mail igen' ); }
	echo '<h2>Historik</h2><table class="widefat striped"><tbody>';
	foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . nb_t( 'log' ) . ' WHERE booking_id=%d ORDER BY id DESC LIMIT 100', $b->id ) ) as $l ) {
		$u = $l->user_id ? get_userdata( $l->user_id ) : null;
		echo '<tr><td>' . esc_html( nb_local( $l->at, 'd.m.Y H:i:s' ) ) . '</td><td>' . esc_html( $l->action ) . '</td><td>' . esc_html( $l->detail ) . '</td><td>' . esc_html( $u ? $u->user_login : 'system' ) . '</td></tr>';
	}
	echo '</tbody></table></div>';
}

/* Tider: opret serier, luk/åbn og slet ledige tider. */
function nb_admin_slots() {
	global $wpdb; $msg = '';
	if ( 'POST' === $_SERVER['REQUEST_METHOD'] && check_admin_referer( 'nb_slots' ) ) {
		if ( isset( $_POST['gen'] ) ) {
			$from = sanitize_text_field( $_POST['from'] ); $to = sanitize_text_field( $_POST['to'] );
			$t1 = sanitize_text_field( $_POST['t1'] ); $t2 = sanitize_text_field( $_POST['t2'] ); $step = max( 10, (int) $_POST['step'] );
			$days = array_map( 'intval', (array) ( $_POST['wd'] ?? array() ) ); $n = 0;
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) && preg_match( '/^\d{2}:\d{2}$/', $t1 ) && preg_match( '/^\d{2}:\d{2}$/', $t2 ) && strtotime( $to ) - strtotime( $from ) <= 366 * DAY_IN_SECONDS ) {
				for ( $d = new DateTime( $from, nb_tz() ); $d->format( 'Y-m-d' ) <= $to; $d->modify( '+1 day' ) ) {
					if ( ! in_array( (int) $d->format( 'N' ), $days, true ) ) { continue; }
					$a = new DateTime( $d->format( 'Y-m-d' ) . ' ' . $t1, nb_tz() ); $e = new DateTime( $d->format( 'Y-m-d' ) . ' ' . $t2, nb_tz() );
					for ( ; $a < $e; $a->modify( '+' . $step . ' minutes' ) ) {
						$u = clone $a; $u->setTimezone( new DateTimeZone( 'UTC' ) );
						$n += (int) $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO ' . nb_t( 'slots' ) . " (starts_utc, dur_min, status) VALUES (%s, %d, 'open')", $u->format( 'Y-m-d H:i:s' ), $step ) );
					}
				}
				nb_log( null, 'slots_add', $n . ' tider ' . $from . ' - ' . $to ); $msg = $n . ' nye tider oprettet.';
			} else { $msg = 'Ugyldige værdier.'; }
		}
		if ( isset( $_POST['toggle'] ) ) {
			$id = (int) $_POST['toggle'];
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . nb_t( 'slots' ) . " SET status=IF(status='open','closed','open') WHERE id=%d AND booking_id IS NULL", $id ) );
			$msg = 'Tid ændret.';
		}
		if ( isset( $_POST['delday'] ) ) {
			$day = sanitize_text_field( $_POST['delday'] );
			$a = new DateTime( $day . ' 00:00', nb_tz() ); $a->setTimezone( new DateTimeZone( 'UTC' ) ); $e = clone $a; $e->modify( '+1 day' );
			$n = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . nb_t( 'slots' ) . ' WHERE booking_id IS NULL AND starts_utc >= %s AND starts_utc < %s AND id NOT IN (SELECT slot_id FROM ' . nb_t( 'bookings' ) . ')', $a->format( 'Y-m-d H:i:s' ), $e->format( 'Y-m-d H:i:s' ) ) );
			nb_log( null, 'slots_del', $day . ': ' . (int) $n ); $msg = (int) $n . ' ledige tider slettet.';
		}
	}
	echo '<div class="wrap"><h1>Tider</h1>' . ( $msg ? '<div class="notice notice-info"><p>' . esc_html( $msg ) . '</p></div>' : '' );
	echo '<h2>Opret tider</h2><form method="post">'; wp_nonce_field( 'nb_slots' );
	echo '<p>Fra <input type="date" name="from" required> til <input type="date" name="to" required> &nbsp; kl. <input type="time" name="t1" value="08:30" required> - <input type="time" name="t2" value="15:00" required> &nbsp; hvert <input type="number" name="step" value="20" min="10" style="width:60px"> min.</p><p>';
	foreach ( array( 1 => 'Man', 2 => 'Tir', 3 => 'Ons', 4 => 'Tor', 5 => 'Fre', 6 => 'Lør', 7 => 'Søn' ) as $k => $l ) { echo '<label style="margin-right:10px"><input type="checkbox" name="wd[]" value="' . $k . '"' . ( $k <= 5 ? ' checked' : '' ) . '> ' . $l . '</label>'; }
	echo '</p><p><button class="button button-primary" name="gen" value="1">Opret tider</button> <span class="description">Eksisterende tider springes over.</span></p></form>';
	echo '<h2>Næste 30 dage</h2>';
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT s.*, b.status AS bst, b.name FROM ' . nb_t( 'slots' ) . ' s LEFT JOIN ' . nb_t( 'bookings' ) . ' b ON b.id=s.booking_id WHERE s.starts_utc BETWEEN %s AND %s ORDER BY s.starts_utc', nb_now(), gmdate( 'Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS ) ) );
	$by = array(); foreach ( $rows as $r ) { $by[ nb_local( $r->starts_utc, 'Y-m-d' ) ][] = $r; }
	foreach ( $by as $day => $list ) {
		echo '<form method="post" style="margin:12px 0">'; wp_nonce_field( 'nb_slots' );
		echo '<b>' . esc_html( nb_local( $list[0]->starts_utc, 'l d.m.Y' ) ) . '</b> <button class="button-link" name="delday" value="' . esc_attr( $day ) . '" onclick="return confirm(\'Slet alle ledige tider denne dag?\')">slet ledige</button><br>';
		foreach ( $list as $r ) {
			$lab = nb_local( $r->starts_utc, 'H:i' );
			if ( $r->booking_id ) { echo '<span class="button disabled" title="' . esc_attr( nb_status_label( $r->bst ) ) . '" style="margin:2px">' . esc_html( $lab ) . ' ' . esc_html( $r->name ) . '</span>'; }
			else { echo '<button class="button' . ( 'closed' === $r->status ? '' : ' button-primary' ) . '" name="toggle" value="' . (int) $r->id . '" style="margin:2px" title="' . ( 'closed' === $r->status ? 'Lukket - klik for at åbne' : 'Åben - klik for at lukke' ) . '">' . esc_html( $lab ) . ( 'closed' === $r->status ? ' (lukket)' : '' ) . '</button>'; }
		}
		echo '</form>';
	}
	if ( ! $by ) { echo '<p>Ingen tider de næste 30 dage.</p>'; }
	echo '</div>';
}

function nb_admin_settings() {
	if ( 'POST' === $_SERVER['REQUEST_METHOD'] && check_admin_referer( 'nb_settings' ) ) {
		$s = nb_settings();
		$s['purge_days'] = max( 1, (int) $_POST['purge_days'] );
		$s['clinic_email'] = sanitize_email( $_POST['clinic_email'] ) ?: $s['clinic_email'];
		$s['cancel_text'] = sanitize_textarea_field( wp_unslash( $_POST['cancel_text'] ) );
		$s['terms_url'] = esc_url_raw( $_POST['terms_url'] );
		$s['privacy_url'] = esc_url_raw( $_POST['privacy_url'] );
		$s['lead_hours'] = max( 0, (int) $_POST['lead_hours'] );
		update_option( 'nb_settings', $s ); nb_log( null, 'settings', '' );
		echo '<div class="notice notice-success"><p>Gemt.</p></div>';
	}
	$s = nb_settings();
	echo '<div class="wrap"><h1>Booking - indstillinger</h1><form method="post">'; wp_nonce_field( 'nb_settings' );
	echo '<table class="form-table">';
	echo '<tr><th>Pris</th><td>' . esc_html( number_format( $s['price_ore'] / 100, 2, ',', '.' ) ) . ' kr. (' . esc_html( $s['product'] ) . ') <span class="description">Ændres i koden for at undgå fejl.</span></td></tr>';
	echo '<tr><th>Slet visitation efter</th><td><input type="number" name="purge_days" value="' . (int) $s['purge_days'] . '" min="1"> dage efter scanningen</td></tr>';
	echo '<tr><th>Tidligste booking</th><td><input type="number" name="lead_hours" value="' . (int) $s['lead_hours'] . '" min="0"> timer før</td></tr>';
	echo '<tr><th>Klinikkens e-mail</th><td><input type="email" name="clinic_email" value="' . esc_attr( $s['clinic_email'] ) . '" class="regular-text"></td></tr>';
	echo '<tr><th>Afbestillingsregler</th><td><textarea name="cancel_text" rows="4" class="large-text">' . esc_textarea( $s['cancel_text'] ) . '</textarea></td></tr>';
	echo '<tr><th>Handelsbetingelser (URL)</th><td><input type="url" name="terms_url" value="' . esc_attr( $s['terms_url'] ) . '" class="regular-text"></td></tr>';
	echo '<tr><th>Privatlivspolitik (URL)</th><td><input type="url" name="privacy_url" value="' . esc_attr( $s['privacy_url'] ) . '" class="regular-text"></td></tr>';
	echo '</table><p><button class="button button-primary">Gem</button></p></form>';
	echo '<h2>Status</h2><ul>';
	echo '<li>Betaling: ' . ( nb_mock() ? 'testtilstand uden betalingsudbyder' : ( nb_payments_ready() ? ( nb_live_key() ? '<b>LIVE</b> (' . nb_pay_name() . ')' : nb_pay_name() . ' testkonto' ) : '<b>lukket</b> (' . nb_pay_name() . '-nøgler mangler)' ) ) . '</li>';
	echo '<li>Krypteringsnøgle: ' . ( nb_key() ? 'ok (uden for webroden)' : '<b>mangler</b>' ) . '</li>';
	echo '<li>Betalingsudbyder: ' . esc_html( nb_pay_name() ) . '</li>';
	echo 'sumup' === nb_provider() ? '<li>SumUp-webhook: sættes automatisk på hver betaling</li>' : '<li>Webhook-adresse: <code>' . esc_html( rest_url( 'nordic-booking/v1/stripe' ) ) . '</code></li>';
	echo '<li>Næste oprydning: ' . esc_html( wp_next_scheduled( 'nb_cron_5' ) ? nb_local( gmdate( 'Y-m-d H:i:s', wp_next_scheduled( 'nb_cron_5' ) ), 'd.m H:i' ) : 'ikke planlagt' ) . '</li></ul></div>';
}

function nb_admin_log() {
	global $wpdb;
	echo '<div class="wrap"><h1>Booking - log</h1><table class="widefat striped"><thead><tr><th>Tid</th><th>Booking</th><th>Handling</th><th>Detalje</th><th>Bruger</th></tr></thead><tbody>';
	foreach ( $wpdb->get_results( 'SELECT * FROM ' . nb_t( 'log' ) . ' ORDER BY id DESC LIMIT 300' ) as $l ) {
		$u = $l->user_id ? get_userdata( $l->user_id ) : null;
		echo '<tr><td>' . esc_html( nb_local( $l->at, 'd.m.Y H:i:s' ) ) . '</td><td>' . ( $l->booking_id ? '<a href="' . esc_url( admin_url( 'admin.php?page=nb-bookings&b=' . (int) $l->booking_id ) ) . '">#' . (int) $l->booking_id . '</a>' : '' ) . '</td><td>' . esc_html( $l->action ) . '</td><td>' . esc_html( $l->detail ) . '</td><td>' . esc_html( $u ? $u->user_login : 'system' ) . '</td></tr>';
	}
	echo '</tbody></table></div>';
}

/* Testværktøjer (kun testtilstand). */
function nb_admin_test() {
	if ( 'POST' === $_SERVER['REQUEST_METHOD'] && check_admin_referer( 'nb_test' ) ) {
		if ( isset( $_POST['replay'] ) ) { $l = get_option( 'nb_mock_last' ); if ( $l ) { $r = nb_webhook_process( $l['payload'], $l['sig'] ); echo '<div class="notice notice-info"><p>Gensendt: ' . esc_html( $r[0] . ' ' . $r[1] ) . '</p></div>'; } }
		if ( isset( $_POST['badsig'] ) ) { $l = get_option( 'nb_mock_last' ); if ( $l ) { $r = nb_webhook_process( $l['payload'], 't=' . time() . ',v1=' . str_repeat( 'a', 64 ) ); echo '<div class="notice notice-info"><p>Ugyldig signatur: ' . esc_html( $r[0] . ' ' . $r[1] ) . '</p></div>'; } }
		if ( isset( $_POST['mailfail'] ) ) { update_option( 'nordic_dev_mail_fail', get_option( 'nordic_dev_mail_fail' ) ? 0 : 1 ); }
		if ( isset( $_POST['cron'] ) ) { nb_cleanup_holds(); nb_purge(); echo '<div class="notice notice-info"><p>Oprydning kørt.</p></div>'; }
		if ( isset( $_POST['age'] ) ) { global $wpdb; $wpdb->query( 'UPDATE ' . nb_t( 'slots' ) . " SET held_until=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 HOUR) WHERE held_until IS NOT NULL" ); echo '<div class="notice notice-info"><p>Alle reservationer gjort udløbne.</p></div>'; }
	}
	echo '<div class="wrap"><h1>Booking - test</h1><form method="post">'; wp_nonce_field( 'nb_test' );
	echo '<p><button class="button" name="replay" value="1">Gensend sidste webhook</button> <button class="button" name="badsig" value="1">Send sidste webhook med forkert signatur</button> <button class="button" name="age" value="1">Gør reservationer udløbne</button> <button class="button" name="cron" value="1">Kør oprydning nu</button> <button class="button" name="mailfail" value="1">E-mailfejl: ' . ( get_option( 'nordic_dev_mail_fail' ) ? 'TIL' : 'fra' ) . '</button></p></form>';
	echo '<h2>Sendte e-mails (testmiljøet sender ikke)</h2><table class="widefat striped"><tbody>';
	foreach ( array_slice( (array) get_option( 'nordic_dev_mail', array() ), 0, 30 ) as $m ) {
		echo '<tr><td>' . esc_html( $m['at'] ) . '</td><td>' . esc_html( is_array( $m['to'] ) ? implode( ',', $m['to'] ) : $m['to'] ) . '</td><td><b>' . esc_html( $m['subject'] ) . '</b>' . ( $m['fail'] ? ' <b style="color:#b32d2e">(fejlet)</b>' : '' ) . '<pre style="white-space:pre-wrap;font-size:12px">' . esc_html( $m['body'] ) . '</pre></td></tr>';
	}
	echo '</tbody></table></div>';
}
