<?php
/**
 * Plugin Name: Nordic Booking
 * Description: Online booking og betaling af Basis helkrops-DEXA via Stripe Checkout. Forløb: tid, oplysninger, betaling, supplerende spørgsmål. Visitationen krypteres og vises kun for lægerollen. Lægen godkender; afvisning refunderes.
 * Version: 0.4.1
 * Requires PHP: 7.4
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'NB_VER', '0.4.1' );
define( 'NB_DIR', __DIR__ );

require_once NB_DIR . '/inc/core.php';
require_once NB_DIR . '/inc/stripe.php';
require_once NB_DIR . '/inc/mail.php';
require_once NB_DIR . '/inc/rest.php';
require_once NB_DIR . '/inc/front.php';
require_once NB_DIR . '/inc/admin.php';

register_activation_hook( __FILE__, 'nb_activate' );
register_deactivation_hook( __FILE__, function () { wp_clear_scheduled_hook( 'nb_cron_5' ); wp_clear_scheduled_hook( 'nb_cron_daily' ); } );
add_action( 'plugins_loaded', function () { if ( get_option( 'nb_db_ver' ) !== NB_VER ) { nb_activate(); } } );
