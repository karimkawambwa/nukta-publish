<?php
/**
 * Plugin Name: Nukta Publish Enhancements
 * Description: SEO, contributor auth URLs, and login/register UX for publish.nukta.co.tz
 * Version: 1.4.0
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
        add_shortcode('nukta_hero_auth', [__CLASS__, 'render_hero_auth_form']);
        add_action('admin_post_nopriv_nukta_contributor_register', [__CLASS__, 'handle_contributor_register']);
        add_action('admin_post_nukta_contributor_register', [__CLASS__, 'handle_contributor_register']);
        add_filter('wp_send_new_user_notification_to_user', [__CLASS__, 'disable_new_user_password_email']);
    }

    public static function disable_new_user_password_email(): bool {
        return false;
    }

    public static function contributor_page_url(array $args = []): string {
        $base = home_url('/' . self::CONTRIBUTOR_SLUG . '/');
        return $args ? add_query_arg($args, $base) : $base;
    }

    public static function register_url(): string {
        return self::contributor_page_url(['register' => '1']);
    }

    public static function render_hero_auth_form(): string {
        if (!self::is_front() && !is_page(self::CONTRIBUTOR_SLUG)) {
            return '';
        }

        if (is_user_logged_in()) {
            return self::render_logged_in_state();
        }

        $redirect = esc_url(admin_url('edit.php'));
        $login_action = esc_url(site_url('wp-login.php', 'login_post'));
        $register_action = esc_url(admin_url('admin-post.php'));
        $lost_password = esc_url(wp_lostpassword_url());
        $register_open = isset($_GET['register']) && $_GET['register'] === '1';
        $register_error = self::registration_error_message();

        ob_start();
        ?>
        <div class="nukta-hero-auth" data-default-tab="<?php echo $register_open || $register_error ? 'register' : 'login'; ?>">
            <div class="nukta-hero-auth__tabs" role="tablist" aria-label="<?php esc_attr_e('Contributor access', 'nukta-publish'); ?>">
                <button type="button" class="nukta-hero-auth__tab<?php echo $register_open ? '' : ' is-active'; ?>" data-tab="login" role="tab" aria-selected="<?php echo $register_open ? 'false' : 'true'; ?>">
                    <?php esc_html_e('Sign in', 'nukta-publish'); ?>
                </button>
                <button type="button" class="nukta-hero-auth__tab<?php echo $register_open ? ' is-active' : ''; ?>" data-tab="register" role="tab" aria-selected="<?php echo $register_open ? 'true' : 'false'; ?>">
                    <?php esc_html_e('Register as Contributor', 'nukta-publish'); ?>
                </button>
            </div>

            <div class="nukta-hero-auth__panel<?php echo $register_open ? '' : ' is-active'; ?>" data-panel="login" role="tabpanel">
                <p class="nukta-hero-auth__lead"><?php esc_html_e('Welcome back. Sign in to submit and manage your work.', 'nukta-publish'); ?></p>
                <form class="nukta-hero-auth__form" method="post" action="<?php echo $login_action; ?>">
                    <label class="nukta-hero-auth__field">
                        <span><?php esc_html_e('Username or email', 'nukta-publish'); ?></span>
                        <input type="text" name="log" autocomplete="username" required placeholder="you@email.com" />
                    </label>
                    <label class="nukta-hero-auth__field">
                        <span><?php esc_html_e('Password', 'nukta-publish'); ?></span>
                        <input type="password" name="pwd" autocomplete="current-password" required placeholder="••••••••" />
                    </label>
                    <label class="nukta-hero-auth__remember">
                        <input type="checkbox" name="rememberme" value="forever" />
                        <span><?php esc_html_e('Remember me', 'nukta-publish'); ?></span>
                    </label>
                    <input type="hidden" name="redirect_to" value="<?php echo $redirect; ?>" />
                    <input type="hidden" name="testcookie" value="1" />
                    <button type="submit" name="wp-submit" class="nukta-hero-auth__submit"><?php esc_html_e('Sign in', 'nukta-publish'); ?></button>
                    <p class="nukta-hero-auth__meta">
                        <a href="<?php echo $lost_password; ?>"><?php esc_html_e('Forgot password?', 'nukta-publish'); ?></a>
                    </p>
                </form>
            </div>

            <div class="nukta-hero-auth__panel<?php echo ($register_open || $register_error) ? ' is-active' : ''; ?>" data-panel="register" role="tabpanel">
                <p class="nukta-hero-auth__lead"><?php esc_html_e('Create your Contributor account to submit manuscripts for review.', 'nukta-publish'); ?></p>
                <?php if ($register_error) : ?>
                    <p class="nukta-hero-auth__error" role="alert"><?php echo esc_html($register_error); ?></p>
                <?php endif; ?>
                <form class="nukta-hero-auth__form" method="post" action="<?php echo $register_action; ?>">
                    <?php wp_nonce_field('nukta_contributor_register'); ?>
                    <input type="hidden" name="action" value="nukta_contributor_register" />
                    <input type="hidden" name="nukta_register_redirect" value="<?php echo esc_url(self::current_page_url()); ?>" />
                    <label class="nukta-hero-auth__field">
                        <span><?php esc_html_e('Username', 'nukta-publish'); ?></span>
                        <input type="text" name="user_login" autocomplete="username" required placeholder="yourname" value="<?php echo esc_attr(sanitize_text_field($_POST['user_login'] ?? '')); ?>" />
                    </label>
                    <label class="nukta-hero-auth__field">
                        <span><?php esc_html_e('Email', 'nukta-publish'); ?></span>
                        <input type="email" name="user_email" autocomplete="email" required placeholder="you@email.com" value="<?php echo esc_attr(sanitize_email($_POST['user_email'] ?? '')); ?>" />
                    </label>
                    <label class="nukta-hero-auth__field">
                        <span><?php esc_html_e('Password', 'nukta-publish'); ?></span>
                        <input type="password" name="user_pass" autocomplete="new-password" required minlength="8" placeholder="At least 8 characters" />
                    </label>
                    <label class="nukta-hero-auth__field">
                        <span><?php esc_html_e('Confirm password', 'nukta-publish'); ?></span>
                        <input type="password" name="user_pass_confirm" autocomplete="new-password" required minlength="8" placeholder="Repeat password" />
                    </label>
                    <button type="submit" class="nukta-hero-auth__submit nukta-hero-auth__submit--register"><?php esc_html_e('Register as Contributor', 'nukta-publish'); ?></button>
                </form>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public static function render_logged_in_state(): string {
        $user = wp_get_current_user();
        if (!$user->exists()) {
            return '';
        }

        $logout = wp_logout_url(home_url('/'));
        $links = [
            'write' => [
                'label' => __('Start writing your posts', 'nukta-publish'),
                'url'   => admin_url('edit.php'),
                'primary' => true,
            ],
            'calendar' => [
                'label' => __('Calendar', 'nukta-publish'),
                'url'   => admin_url('admin.php?page=pp-calendar'),
            ],
            'board' => [
                'label' => __('Content Board', 'nukta-publish'),
                'url'   => admin_url('admin.php?page=pp-content-board'),
            ],
            'overview' => [
                'label' => __('Content Overview', 'nukta-publish'),
                'url'   => admin_url('admin.php?page=pp-content-overview'),
            ],
        ];

        ob_start();
        ?>
        <div class="nukta-hero-auth nukta-hero-auth--signed-in">
            <p class="nukta-hero-auth__welcome">
                <?php
                printf(
                    /* translators: %s: user display name */
                    esc_html__('Signed in as %s', 'nukta-publish'),
                    esc_html($user->display_name)
                );
                ?>
            </p>

            <div class="nukta-hero-auth__signed-in-section">
                <a class="nukta-hero-auth__submit" href="<?php echo esc_url($links['write']['url']); ?>">
                    <?php echo esc_html($links['write']['label']); ?>
                </a>
            </div>

            <div class="nukta-hero-auth__signed-in-section">
                <p class="nukta-hero-auth__section-title"><?php esc_html_e('Plan your content', 'nukta-publish'); ?></p>
                <div class="nukta-hero-auth__plan-links">
                    <a class="nukta-hero-auth__plan-link" href="<?php echo esc_url($links['calendar']['url']); ?>">
                        <?php echo esc_html($links['calendar']['label']); ?>
                    </a>
                    <a class="nukta-hero-auth__plan-link" href="<?php echo esc_url($links['board']['url']); ?>">
                        <?php echo esc_html($links['board']['label']); ?>
                    </a>
                    <a class="nukta-hero-auth__plan-link" href="<?php echo esc_url($links['overview']['url']); ?>">
                        <?php echo esc_html($links['overview']['label']); ?>
                    </a>
                </div>
            </div>

            <p class="nukta-hero-auth__signed-in-footer">
                <a class="nukta-hero-auth__link-out" href="<?php echo esc_url($logout); ?>"><?php esc_html_e('Sign out', 'nukta-publish'); ?></a>
            </p>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public static function current_page_url(): string {
        $path = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
        return home_url($path);
    }

    public static function registration_error_message(): string {
        $code = isset($_GET['nukta_error']) ? sanitize_key(wp_unslash($_GET['nukta_error'])) : '';
        $messages = [
            'username'           => __('Please enter a valid username.', 'nukta-publish'),
            'email'              => __('Please enter a valid email address.', 'nukta-publish'),
            'password_short'     => __('Password must be at least 8 characters.', 'nukta-publish'),
            'password_mismatch'  => __('Passwords do not match.', 'nukta-publish'),
            'username_exists'    => __('That username is already taken.', 'nukta-publish'),
            'email_exists'       => __('An account with that email already exists.', 'nukta-publish'),
            'registration_off'   => __('Registration is currently disabled.', 'nukta-publish'),
            'create_failed'      => __('Could not create your account. Please try again.', 'nukta-publish'),
            'login_failed'       => __('Account created but sign-in failed. Please sign in manually.', 'nukta-publish'),
        ];
        return $messages[$code] ?? '';
    }

    public static function handle_contributor_register(): void {
        if (!get_option('users_can_register')) {
            self::redirect_register_error('registration_off');
        }

        check_admin_referer('nukta_contributor_register');

        $username = sanitize_user(wp_unslash($_POST['user_login'] ?? ''), true);
        $email    = sanitize_email(wp_unslash($_POST['user_email'] ?? ''));
        $password = (string) wp_unslash($_POST['user_pass'] ?? '');
        $confirm  = (string) wp_unslash($_POST['user_pass_confirm'] ?? '');
        $fallback = esc_url_raw(wp_unslash($_POST['nukta_register_redirect'] ?? home_url('/')));

        if ($username === '' || !validate_username($username)) {
            self::redirect_register_error('username', $fallback);
        }
        if (!is_email($email)) {
            self::redirect_register_error('email', $fallback);
        }
        if (strlen($password) < 8) {
            self::redirect_register_error('password_short', $fallback);
        }
        if ($password !== $confirm) {
            self::redirect_register_error('password_mismatch', $fallback);
        }
        if (username_exists($username)) {
            self::redirect_register_error('username_exists', $fallback);
        }
        if (email_exists($email)) {
            self::redirect_register_error('email_exists', $fallback);
        }

        $user_id = wp_create_user($username, $password, $email);
        if (is_wp_error($user_id)) {
            self::redirect_register_error('create_failed', $fallback);
        }

        $user = get_userdata($user_id);
        if ($user instanceof WP_User) {
            $user->set_role('contributor');
        }

        wp_signon([
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => true,
        ], is_ssl());

        if (!is_user_logged_in()) {
            self::redirect_register_error('login_failed', $fallback);
        }

        wp_safe_redirect(admin_url('edit.php'));
        exit;
    }

    private static function redirect_register_error(string $code, string $fallback = ''): void {
        $target = $fallback ?: home_url('/');
        wp_safe_redirect(add_query_arg([
            'register'    => '1',
            'nukta_error' => $code,
        ], $target));
        exit;
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
        if (!self::is_front() && !is_page(self::CONTRIBUTOR_SLUG)) {
            return;
        }

        wp_register_style('nukta-publish-enhancements', false);
        wp_enqueue_style('nukta-publish-enhancements');
        wp_add_inline_style('nukta-publish-enhancements', '
            .nukta-hero-auth {
                width: min(100%, 420px);
                margin: 1.25rem auto 0;
                padding: 1.25rem;
                border-radius: 16px;
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(12px);
                box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
                color: #0f172a;
                text-align: left;
            }
            .nukta-hero-auth__tabs {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 8px;
                margin-bottom: 1rem;
            }
            .nukta-hero-auth__tab {
                border: 1px solid #cbd5e1;
                background: #f8fafc;
                color: #334155;
                border-radius: 999px;
                padding: 0.55rem 0.75rem;
                font-size: 0.82rem;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s ease;
            }
            .nukta-hero-auth__tab.is-active {
                background: #1a2a6c;
                border-color: #1a2a6c;
                color: #fff;
            }
            .nukta-hero-auth__panel { display: none; }
            .nukta-hero-auth__panel.is-active { display: block; }
            .nukta-hero-auth__lead {
                margin: 0 0 1rem;
                font-size: 0.9rem;
                line-height: 1.5;
                color: #475569;
            }
            .nukta-hero-auth__field {
                display: block;
                margin-bottom: 0.85rem;
            }
            .nukta-hero-auth__field span {
                display: block;
                margin-bottom: 0.35rem;
                font-size: 0.78rem;
                font-weight: 600;
                color: #334155;
            }
            .nukta-hero-auth__field input {
                width: 100%;
                border: 1px solid #cbd5e1;
                border-radius: 10px;
                padding: 0.65rem 0.75rem;
                font-size: 0.95rem;
                background: #fff;
            }
            .nukta-hero-auth__field input:focus {
                outline: 2px solid #1a2a6c;
                outline-offset: 1px;
                border-color: #1a2a6c;
            }
            .nukta-hero-auth__remember {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                margin: 0 0 1rem;
                font-size: 0.85rem;
                color: #475569;
            }
            .nukta-hero-auth__submit {
                width: 100%;
                border: 0;
                border-radius: 999px;
                padding: 0.85rem 1rem;
                font-size: 0.95rem;
                font-weight: 700;
                cursor: pointer;
                background: #1a2a6c;
                color: #fff;
            }
            .nukta-hero-auth__submit--register { background: #b21f1f; }
            .nukta-hero-auth__meta {
                margin: 0.75rem 0 0;
                text-align: center;
                font-size: 0.85rem;
            }
            .nukta-hero-auth__meta a { color: #1a2a6c; }
            .nukta-hero-auth__error {
                margin: 0 0 1rem;
                padding: 0.75rem 0.9rem;
                border-radius: 10px;
                background: #fef2f2;
                border: 1px solid #fecaca;
                color: #991b1b;
                font-size: 0.88rem;
            }
            .nukta-hero-auth--signed-in { text-align: center; }
            .nukta-hero-auth__welcome {
                margin: 0 0 1rem;
                font-size: 1.05rem;
                font-weight: 700;
                color: #0f172a;
            }
            .nukta-hero-auth__signed-in-section {
                margin-bottom: 1rem;
                text-align: left;
            }
            .nukta-hero-auth__section-title {
                margin: 0 0 0.65rem;
                font-size: 0.82rem;
                font-weight: 700;
                letter-spacing: 0.04em;
                text-transform: uppercase;
                color: #64748b;
                text-align: center;
            }
            .nukta-hero-auth__signed-in-section .nukta-hero-auth__submit {
                display: block;
                text-decoration: none;
                text-align: center;
            }
            .nukta-hero-auth__plan-links {
                display: grid;
                gap: 0.5rem;
            }
            .nukta-hero-auth__plan-link {
                display: block;
                padding: 0.7rem 0.9rem;
                border-radius: 10px;
                border: 1px solid #cbd5e1;
                background: #f8fafc;
                color: #1a2a6c;
                font-size: 0.92rem;
                font-weight: 600;
                text-decoration: none;
                text-align: center;
                transition: background 0.2s ease, border-color 0.2s ease;
            }
            .nukta-hero-auth__plan-link:hover {
                background: #eef2ff;
                border-color: #1a2a6c;
            }
            .nukta-hero-auth__signed-in-footer {
                margin: 0.75rem 0 0;
                text-align: center;
            }
            .nukta-hero-auth__link-out {
                color: #475569;
                font-size: 0.9rem;
                text-decoration: underline;
            }
            .elementor-element-c7d8e9f0 { width: 100%; }
        ');

        wp_register_script('nukta-publish-contributor-auth', false, ['jquery'], '1.1.0', true);
        wp_enqueue_script('nukta-publish-contributor-auth');
        wp_add_inline_script('nukta-publish-contributor-auth', '
            jQuery(function ($) {
                function activateTab($root, tab) {
                    $root.find(".nukta-hero-auth__tab").removeClass("is-active").attr("aria-selected", "false");
                    $root.find(".nukta-hero-auth__panel").removeClass("is-active");
                    $root.find(\'.nukta-hero-auth__tab[data-tab="\' + tab + \'"]\').addClass("is-active").attr("aria-selected", "true");
                    $root.find(\'.nukta-hero-auth__panel[data-panel="\' + tab + \'"]\').addClass("is-active");
                }
                $(".nukta-hero-auth").each(function () {
                    var $root = $(this);
                    activateTab($root, $root.data("default-tab") || "login");
                    $root.on("click", ".nukta-hero-auth__tab", function () {
                        activateTab($root, $(this).data("tab"));
                    });
                });
                if ($(".advgb-lores-form-wrapper").length) {
                    var params = new URLSearchParams(window.location.search);
                    if (params.get("register") === "1" || window.location.hash === "#register") {
                        var $wrapper = $(".advgb-lores-form-wrapper").first();
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

        self::create_publishpress_author($user_id);
    }

    /**
     * Create a PublishPress Authors profile linked to the WordPress user.
     */
    public static function create_publishpress_author(int $user_id): void {
        if (!class_exists('MultipleAuthors\\Classes\\Objects\\Author')) {
            return;
        }

        $user = get_userdata($user_id);
        if (!$user || !in_array('contributor', (array) $user->roles, true)) {
            return;
        }

        \MultipleAuthors\Classes\Objects\Author::create_from_user($user_id);
    }
}

Nukta_Publish_Enhancements::init();
