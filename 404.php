<?php
/* 404-skabelon: sitets header og footer + indholdet fra siden "Siden findes ikke" (redigeres i Elementor). */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$nordic_404 = nordic_k_404_page();
get_header();
echo \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $nordic_404->ID, true ); // phpcs:ignore
get_footer();
