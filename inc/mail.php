<?php
/* E-mails. Ingen helbredsoplysninger i nogen e-mail. Fejl logges og vises i admin, og kan sendes igen. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function nb_send( $b, $kind, $to, $subject, $body ) {
	global $wpdb;
	$ok = wp_mail( $to, $subject, $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
	if ( $b ) {
		$wpdb->update( nb_t( 'bookings' ), array( 'mail_state' => $ok ? 'ok:' . $kind : 'fejl:' . $kind ), array( 'id' => $b->id ) );
		nb_log( $b->id, $ok ? 'mail_ok' : 'mail_fejl', $kind );
	}
	return $ok;
}
function nb_visit_link( $b ) { $s = nb_settings(); return add_query_arg( array( 'nb' => 'visit', 'ref' => $b->ref ), $s['page_url'] ?: home_url( '/' ) ); }
function nb_sig() { $s = nb_settings(); return "\n\nVenlig hilsen\nNordic DEXA Scan\n" . $s['address'] . "\nTlf. " . $s['phone']; }

function nb_mail_paid( $b ) {
	$s = nb_settings();
	return nb_send( $b, 'paid', $b->email, 'Vi har modtaget din booking - ' . nb_human( $b->starts_utc ),
		"Hej " . $b->name . "\n\nTak for din booking af " . $s['product'] . " " . nb_human( $b->starts_utc ) . ".\n\nVi har modtaget din betaling.\n\n" . ( $b->visit_at ? "En læge gennemgår nu dit spørgeskema, fordi en DEXA-scanning skal være berettiget. Du får en bekræftelse, når det er sket." : "Udfyld venligst det korte spørgeskema, hvis du ikke allerede har gjort det. En DEXA-scanning skal være berettiget, og en læge skal godkende den, før din tid er endeligt bekræftet:\n" . nb_visit_link( $b ) ) . "\n\nKan scanningen ikke godkendes, refunderer vi hele beløbet.\n\nDin kvittering kommer i en separat e-mail fra vores betalingsudbyder Stripe.\n\nBookingnummer: " . $b->ref . nb_sig() );
}
function nb_mail_clinic_new( $b ) {
	$s = nb_settings();
	$ok = nb_send( null, 'klinik', $s['clinic_email'], 'Ny online booking afventer lægefaglig vurdering',
		"Betalt booking med udfyldt visitation " . nb_human( $b->starts_utc ) . ".\n\nGennemgå visitationen her:\n" . admin_url( 'admin.php?page=nb-bookings&b=' . (int) $b->id ) . "\n\n(E-mailen indeholder bevidst ingen patient- eller helbredsoplysninger.)" );
	nb_log( $b->id, $ok ? 'mail_ok' : 'mail_fejl', 'klinik' );
	return $ok;
}
function nb_mail_approved( $b ) {
	$s = nb_settings();
	return nb_send( $b, 'approved', $b->email, 'Bekræftelse: DEXA-scanning ' . nb_human( $b->starts_utc ),
		"Hej " . $b->name . "\n\nDin scanning er godkendt, og din tid er bekræftet:\n\n" . nb_human( $b->starts_utc ) . "\n" . $s['address'] . "\n\nForberedelse: ingen særlig forberedelse. Kom gerne i let tøj uden metal. Selve scanningen tager 10-20 minutter.\n\nDu får din skriftlige rapport inden for 24 timer.\n\nAfbestilling: " . $s['cancel_text'] . "\n\nBookingnummer: " . $b->ref . nb_sig() );
}
function nb_mail_rejected( $b ) {
	return nb_send( $b, 'rejected', $b->email, 'Din booking af DEXA-scanning',
		"Hej " . $b->name . "\n\nEfter lægefaglig vurdering kan vi desværre ikke tilbyde dig scanningen på det foreliggende grundlag. Ring gerne til os, hvis du har spørgsmål.\n\nHele beløbet er refunderet. Det kan tage 5-10 bankdage, før pengene står på din konto.\n\nBookingnummer: " . $b->ref . nb_sig() );
}
function nb_mail_cancelled( $b, $amount ) {
	return nb_send( $b, 'cancelled', $b->email, 'Din booking er afbestilt',
		"Hej " . $b->name . "\n\nDin booking " . nb_human( $b->starts_utc ) . " er afbestilt.\n\n" . ( $amount > 0 ? 'Vi har refunderet ' . number_format( $amount / 100, 2, ',', '.' ) . " kr. Det kan tage 5-10 bankdage, før pengene står på din konto.\n" : '' ) . "\nBookingnummer: " . $b->ref . nb_sig() );
}
function nb_mail_conflict( $b ) {
	nb_mail_admin( 'Booking-konflikt refunderet', 'En betaling kom ind, efter at tiden var givet til en anden. Betalingen er refunderet automatisk. Booking ' . $b->ref . '.' );
	return nb_send( $b, 'conflict', $b->email, 'Din booking kunne ikke gennemføres',
		"Hej " . $b->name . "\n\nDin betaling blev gennemført, efter at reservationen af tiden var udløbet, og tiden var i mellemtiden blevet booket af en anden. Vi har derfor refunderet hele beløbet.\n\nBook gerne en ny tid, eller ring til os.\n\nBookingnummer: " . $b->ref . nb_sig() );
}
function nb_mail_admin( $subject, $body ) { $s = nb_settings(); return wp_mail( $s['clinic_email'], '[Booking] ' . $subject, $body ); }

/* Gensend den sidst fejlede e-mail for en booking. */
function nb_mail_resend( $b ) {
	$kind = preg_replace( '/^(ok|fejl):/', '', (string) $b->mail_state );
	switch ( $kind ) {
		case 'paid': return nb_mail_paid( $b );
		case 'approved': return nb_mail_approved( $b );
		case 'rejected': return nb_mail_rejected( $b );
		case 'cancelled': return nb_mail_cancelled( $b, (int) $b->refunded );
		case 'conflict': return nb_mail_conflict( $b );
	}
	return false;
}
