<?php
/*
Plugin Name: Comment Quote
Description: Quote comments with this nifty plugin!
Author: r-a-y
Author URI: http://profiles.wordpress.org/r-a-y
Version: 0.1
License: GPLv2 or later
*/

add_filter( 'wp_list_comments_args', function( $retval ) {
	if ( ! class_exists( 'Comment_Quote' ) ) {
		require_once __DIR__ . '/comment-quote.php';
		Comment_Quote::init();
	}

	return $retval;
}, 0 );

add_filter( 'preprocess_comment', function( $retval ) {
	global $allowedtags;

	$allowedtags['blockquote']['class'] = true;
	$allowedtags['em']['class'] = true;
	$allowedtags['p'] = [];

	return $retval;
}, -999 );

/**
 * Enqueue CSS.
 *
 * Feel free to disable with the 'comment_quote_enable_css' filter and roll your
 * own in your theme's stylesheet.
 */
add_action( 'wp_enqueue_scripts', function() {
	if ( ! apply_filters( 'comment_quote_enable_css', true ) )
		return;

	if ( ! is_singular() ) {
		return;
	}

	wp_enqueue_style( 'comment-quote', plugins_url( basename( __DIR__ ) ) . '/style.css' );
} );