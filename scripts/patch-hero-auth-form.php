<?php
/**
 * Patch Elementor home hero: replace CTA buttons with inline auth form shortcode.
 * Run: wp eval-file scripts/patch-hero-auth-form.php
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "Run via WP-CLI inside WordPress root.\n");
    exit(1);
}

$home_id = (int) get_option('page_on_front');
if (!$home_id) {
    fwrite(STDERR, "No front page set.\n");
    exit(1);
}

$raw = get_post_meta($home_id, '_elementor_data', true);
if (!$raw) {
    fwrite(STDERR, "No Elementor data on home page.\n");
    exit(1);
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    fwrite(STDERR, "Invalid Elementor JSON.\n");
    exit(1);
}

$shortcode_widget = [
    'id'         => 'c7d8e9f0',
    'elType'     => 'widget',
    'settings'   => [
        'shortcode'              => '[nukta_hero_auth]',
        '_element_width'         => 'initial',
        '_element_custom_width'  => ['unit' => 'px', 'size' => 420, 'sizes' => []],
        '_flex_align_self'       => 'center',
        'align'                  => 'center',
    ],
    'elements'   => [],
    'widgetType' => 'shortcode',
];

$remove_ids = ['61ba7db', 'f4e8b2a1'];
$patched = false;

foreach ($data as &$section) {
    if (empty($section['elements'])) {
        continue;
    }
    foreach ($section['elements'] as &$container) {
        if (($container['id'] ?? '') !== '74068c0' || empty($container['elements'])) {
            continue;
        }

        $new_elements = [];
        $inserted = false;
        foreach ($container['elements'] as $child) {
            if (in_array($child['id'] ?? '', $remove_ids, true)) {
                continue;
            }
            $new_elements[] = $child;
            if (($child['id'] ?? '') === 'e636516' && !$inserted) {
                $new_elements[] = $shortcode_widget;
                $inserted = true;
                $patched = true;
            }
        }

        if (!$inserted) {
            $new_elements[] = $shortcode_widget;
            $patched = true;
        }

        $container['elements'] = $new_elements;
    }
}
unset($section, $container);

if (!$patched) {
    fwrite(STDERR, "Hero container not found.\n");
    exit(1);
}

update_post_meta($home_id, '_elementor_data', wp_slash(wp_json_encode($data)));
delete_post_meta($home_id, '_elementor_css');

if (class_exists('\Elementor\Plugin')) {
    \Elementor\Plugin::$instance->files_manager->clear_cache();
}

wp_cache_flush();
echo "Hero auth form embedded on home page (ID {$home_id}).\n";
