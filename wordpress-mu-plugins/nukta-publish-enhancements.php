<?php
/**
 * Plugin Name: Nukta Publish Enhancements
 * Description: SEO, contributor auth URLs, and login/register UX for publish.nukta.co.tz
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Nukta_Publish_Enhancements {

    const CONTRIBUTOR_SLUG = 'contributor';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_rewrites']);
        add_filter('document_title_parts', [__CLASS__, 'filter_document_title'], 20);
        add_action('wp_head', [__CLASS__, 'output_seo_meta'], 1);
        add_action('wp_head', [__CLASS__, 'output_json_ld'], 5);
        add_filter('pre_get_document_title', [__CLASS__, 'filter_pre_get_document_title'], 20);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_filter('login_redirect', [__CLASS__, 'contributor_login_redirect'], 10, 3);
        add_filter('register_url', [__CLASS__, 'contributor_register_url']);
        add_filter('login_url', [__CLASS__, 'contributor_login_url'], 10, 3);
        add_action('user_register', [__CLASS__, 'ensure_contributor_role']);
    }

    public static function contributor_page_url(array $args = []): string {
        $base = home_url('/' . self::CONTRIBUTOR_SLUG . '/');
        return $args ? add_query_arg($args, $base) : $base;
    }

    public static function register_url(): string {
        return self::contributor_page_url(['register' => '1']);
    }

    public static function register_rewrites(): void {
        // Pretty /contributor/ alias is handled by the WordPress page slug.
    }

    public static function is_front(): bool {
        return is_front_page() || is_page('home');
    }

    public static function seo_context(): array {
        $site_name = get_bloginfo('name');
        $tagline   = get_bloginfo('description');
        $url       = home_url('/');
        $logo      = 'https://publish.nukta.co.tz/wp-content/uploads/2026/05/nukta-publish-logo.png';

        if (self::is_front()) {
            return [
                'title'       => 'Nukta Publish | Submit & Publish Your Work — Tanzania Writers & Creators',
                'description' => 'Nukta Publish is Tanzania\'s digital publishing platform. Submit manuscripts, pass editorial review, and reach readers across East Africa. Register free as a Contributor.',
                'keywords'    => 'Nukta Publish, Tanzania publishing, submit manuscript, freelance writers Tanzania, contributor registration, digital publishing East Africa, Nukta, writer platform',
                'canonical'   => $url,
                'og_type'     => 'website',
                'image'       => $logo,
            ];
        }

        if (is_page(self::CONTRIBUTOR_SLUG)) {
            return [
                'title'       => 'Contributor Sign In & Registration | Nukta Publish',
                'description' => 'Sign in or register as a Nukta Publish Contributor. Submit your work, track editorial review, and publish to Tanzanian and global readers.',
                'keywords'    => 'Nukta Publish login, contributor register, writer sign in Tanzania',
                'canonical'   => self::contributor_page_url(),
                'og_type'     => 'website',
                'image'       => $logo,
            ];
        }

        return [
            'title'       => wp_get_document_title(),
            'description' => $tagline ?: 'Nukta Publish — Tanzania\'s platform for writers and creators.',
            'keywords'    => 'Nukta Publish, Tanzania, publishing, writers',
            'canonical'   => get_permalink() ?: $url,
            'og_type'     => is_singular('post') ? 'article' : 'website',
            'image'       => $logo,
        ];
    }

    public static function filter_document_title(array $parts): array {
        $ctx = self::seo_context();
        if (!empty($ctx['title']) && (self::is_front() || is_page(self::CONTRIBUTOR_SLUG))) {
            $parts['title'] = $ctx['title'];
            unset($parts['tagline'], $parts['site']);
        }
        return $parts;
    }

    public static function filter_pre_get_document_title(string $title): string {
        $ctx = self::seo_context();
        if (self::is_front() || is_page(self::CONTRIBUTOR_SLUG)) {
            return $ctx['title'];
        }
        return $title;
    }

    public static function output_seo_meta(): void {
        $ctx = self::seo_context();
        $title = esc_attr($ctx['title']);
        $desc  = esc_attr($ctx['description']);
        $keys  = esc_attr($ctx['keywords']);
        $canon = esc_url($ctx['canonical']);
        $image = esc_url($ctx['image']);
        $site  = esc_attr(get_bloginfo('name'));
        $locale = esc_attr(str_replace('_', '-', get_locale()));

        echo "\n<!-- Nukta Publish SEO -->\n";
        echo '<meta name="description" content="' . $desc . '" />' . "\n";
        echo '<meta name="keywords" content="' . $keys . '" />' . "\n";
        echo '<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1" />' . "\n";
        echo '<link rel="canonical" href="' . $canon . '" />' . "\n";
        echo '<meta name="author" content="Nukta Publish" />' . "\n";
        echo '<meta name="geo.region" content="TZ" />' . "\n";
        echo '<meta name="geo.placename" content="Tanzania" />' . "\n";

        // Open Graph
        echo '<meta property="og:locale" content="' . $locale . '" />' . "\n";
        echo '<meta property="og:type" content="' . esc_attr($ctx['og_type']) . '" />' . "\n";
        echo '<meta property="og:title" content="' . $title . '" />' . "\n";
        echo '<meta property="og:description" content="' . $desc . '" />' . "\n";
        echo '<meta property="og:url" content="' . $canon . '" />' . "\n";
        echo '<meta property="og:site_name" content="' . $site . '" />' . "\n";
        echo '<meta property="og:image" content="' . $image . '" />' . "\n";
        echo '<meta property="og:image:alt" content="Nukta Publish — Tanzania publishing platform" />' . "\n";

        // Twitter
        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
        echo '<meta name="twitter:title" content="' . $title . '" />' . "\n";
        echo '<meta name="twitter:description" content="' . $desc . '" />' . "\n";
        echo '<meta name="twitter:image" content="' . $image . '" />' . "\n";

        if (self::is_front()) {
            echo '<meta name="application-name" content="Nukta Publish" />' . "\n";
            echo '<link rel="alternate" hreflang="en-TZ" href="' . esc_url(home_url('/')) . '" />' . "\n";
            echo '<link rel="alternate" hreflang="sw-TZ" href="' . esc_url(home_url('/')) . '" />' . "\n";
            echo '<link rel="alternate" hreflang="x-default" href="' . esc_url(home_url('/')) . '" />' . "\n";
        }
    }

    public static function output_json_ld(): void {
        $url  = home_url('/');
        $name = get_bloginfo('name');
        $desc = self::seo_context()['description'];

        $organization = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Organization',
            'name'        => $name,
            'url'         => $url,
            'description' => $desc,
            'logo'        => self::seo_context()['image'],
            'sameAs'      => [
                'https://nukta.co.tz',
                'https://ai.nukta.co.tz',
            ],
            'areaServed'  => [
                '@type' => 'Country',
                'name'  => 'Tanzania',
            ],
        ];

        $website = [
            '@context'        => 'https://schema.org',
            '@type'           => 'WebSite',
            'name'            => $name,
            'url'             => $url,
            'description'     => $desc,
            'inLanguage'      => ['en-TZ', 'sw-TZ'],
            'publisher'       => ['@id' => $url . '#organization'],
            'potentialAction' => [
                '@type'       => 'RegisterAction',
                'target'      => self::register_url(),
                'name'        => 'Register as Contributor',
                'description' => 'Create a Nukta Publish contributor account to submit manuscripts.',
            ],
        ];

        if (self::is_front()) {
            $webpage = [
                '@context'    => 'https://schema.org',
                '@type'       => 'WebPage',
                'name'        => self::seo_context()['title'],
                'url'         => $url,
                'description' => $desc,
                'isPartOf'    => ['@id' => $url . '#website'],
                'about'       => [
                    '@type' => 'Thing',
                    'name'  => 'Digital publishing for Tanzanian writers',
                ],
            ];
            $website['@id'] = $url . '#website';
            $organization['@id'] = $url . '#organization';

            echo '<script type="application/ld+json">' . wp_json_encode([$organization, $website, $webpage], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
            return;
        }

        echo '<script type="application/ld+json">' . wp_json_encode([$organization, $website], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }

    public static function enqueue_assets(): void {
        wp_register_style('nukta-publish-enhancements', false);
        wp_enqueue_style('nukta-publish-enhancements');
        wp_add_inline_style('nukta-publish-enhancements', '
            .elementor-element-74068c0 .elementor-element-61ba7db,
            .elementor-element-74068c0 .elementor-element-f4e8b2a1 {
                display: inline-block;
                vertical-align: middle;
            }
            .elementor-element-74068c0 .elementor-element-f4e8b2a1 .elementor-button {
                margin-left: 12px;
            }
            @media (max-width: 767px) {
                .elementor-element-74068c0 .elementor-element-61ba7db,
                .elementor-element-74068c0 .elementor-element-f4e8b2a1 {
                    display: block;
                    width: 100%;
                }
                .elementor-element-74068c0 .elementor-element-f4e8b2a1 .elementor-button {
                    margin-left: 0;
                    margin-top: 12px;
                }
            }
        ');

        if (!is_page(self::CONTRIBUTOR_SLUG)) {
            return;
        }

        wp_register_script('nukta-publish-contributor-auth', false, ['jquery'], '1.0.0', true);
        wp_enqueue_script('nukta-publish-contributor-auth');
        wp_add_inline_script('nukta-publish-contributor-auth', '
            jQuery(function ($) {
                var params = new URLSearchParams(window.location.search);
                if (params.get("register") === "1" || window.location.hash === "#register") {
                    var $wrapper = $(".advgb-lores-form-wrapper").first();
                    if ($wrapper.length) {
                        $wrapper.find(".advgb-login-form-wrapper").hide();
                        $wrapper.find(".advgb-register-form-wrapper").show();
                    }
                }
            });
        ');
    }

    public static function contributor_login_redirect($redirect_to, $requested, $user) {
        if (is_wp_error($user) || !($user instanceof WP_User)) {
            return $redirect_to;
        }
        if (in_array('contributor', (array) $user->roles, true)) {
            return admin_url('edit.php');
        }
        return $redirect_to;
    }

    public static function contributor_register_url(string $url): string {
        return self::register_url();
    }

    public static function contributor_login_url(string $url, string $redirect, bool $force_reauth): string {
        if ($redirect) {
            return add_query_arg('redirect_to', urlencode($redirect), self::contributor_page_url());
        }
        return self::contributor_page_url();
    }

    public static function ensure_contributor_role(int $user_id): void {
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }
        if (empty(array_intersect(['administrator', 'editor', 'author'], (array) $user->roles))) {
            $user->set_role('contributor');
        }
    }
}

Nukta_Publish_Enhancements::init();
