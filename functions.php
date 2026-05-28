<?php
/**
 * Nukta Publish functions and definitions
 */

function nukta_publish_scripts() {
    wp_enqueue_style( 'nukta-publish-style', get_stylesheet_uri(), array(), '1.0.0' );
}
add_action( 'wp_enqueue_scripts', 'nukta_publish_scripts' );

function nukta_publish_setup() {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
}
add_action( 'after_setup_theme', 'nukta_publish_setup' );
