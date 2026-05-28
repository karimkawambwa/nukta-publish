<?php
/**
 * One-shot server-side updater for publish.nukta.co.tz
 * Run: wp eval-file scripts/apply-publish-updates.php
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "Run via WP-CLI inside WordPress root.\n");
    exit(1);
}

$home_id = (int) get_option('page_on_front');
$contributor_page_id = 39;

// Rename login/register page
wp_update_post([
    'ID'         => $contributor_page_id,
    'post_title' => 'Contributor Sign In',
    'post_name'  => 'contributor',
]);

// Site tagline
update_option('blogdescription', 'Tanzania\'s publishing platform for writers — submit manuscripts, editorial review, and reach readers.');

// Home page Hostinger AI meta
if ($home_id) {
    update_post_meta($home_id, 'hostinger_ai_post_meta_title', 'Nukta Publish | Submit & Publish Your Work — Tanzania Writers Platform');
    update_post_meta($home_id, 'hostinger_ai_post_meta_description', 'Nukta Publish is Tanzania\'s digital publishing platform. Submit manuscripts, pass editorial review, and reach readers across East Africa. Register free as a Contributor.');
    update_post_meta($home_id, 'hostinger_ai_post_meta_seo_keywords', 'Nukta Publish, Tanzania publishing, submit manuscript, freelance writers Tanzania, contributor registration, digital publishing East Africa, writer platform');
}

// Patch Elementor JSON from stdin file if present
$patch_file = '/tmp/elementor-home-patched.json';
if (file_exists($patch_file) && $home_id) {
    $raw = file_get_contents($patch_file);
    $json = json_decode($raw, true);
    if (is_string($json)) {
        update_post_meta($home_id, '_elementor_data', wp_slash($json));
        delete_post_meta($home_id, '_elementor_css');
        echo "Elementor home page updated.\n";
    }
}

if (class_exists('\Elementor\Plugin')) {
    \Elementor\Plugin::$instance->files_manager->clear_cache();
}

flush_rewrite_rules(false);
wp_cache_flush();

echo "Contributor page slug: contributor\n";
echo "Default role: " . get_option('default_role') . "\n";
echo "Done.\n";
