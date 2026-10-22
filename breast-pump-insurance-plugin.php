<?php
/**
 * Plugin Name: Breast Pump Insurance Guide
 * Description: Mobile-first BabyBrands guide that helps parents find breast pump coverage on their own insurer website. Shortcode: [bbgc_insurance_guide]
 * Version: 1.0.0
 * Author: BabyBrands
 * Text Domain: breast-pump-insurance-plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BBGC_VERSION', '1.0.0');
define('BBGC_PLUGIN_FILE', __FILE__);
define('BBGC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BBGC_PLUGIN_PATH', plugin_dir_path(__FILE__));

require_once BBGC_PLUGIN_PATH . 'includes/directory.php';
require_once BBGC_PLUGIN_PATH . 'includes/install.php';
require_once BBGC_PLUGIN_PATH . 'includes/rest.php';
require_once BBGC_PLUGIN_PATH . 'includes/admin.php';

add_action('plugins_loaded', 'bbgc_maybe_install');
register_activation_hook(BBGC_PLUGIN_FILE, 'bbgc_install');
register_deactivation_hook(BBGC_PLUGIN_FILE, 'bbgc_deactivate');

function bbgc_deactivate()
{
    wp_clear_scheduled_hook('bbgc_retention_daily');
}

/**
 * @param mixed $value
 */
function bbgc_truthy($value)
{
    $value = strtolower(trim((string) $value));

    return in_array($value, ['yes', 'y', 'true', '1', 'on'], true);
}

function bbgc_register_assets()
{
    wp_register_style(
        'bbgc-guide',
        BBGC_PLUGIN_URL . 'assets/css/guide.css',
        [],
        BBGC_VERSION
    );

    wp_register_script(
        'bbgc-guide',
        BBGC_PLUGIN_URL . 'assets/js/guide.js',
        [],
        BBGC_VERSION,
        true
    );
}

add_action('wp_enqueue_scripts', 'bbgc_register_assets');

/**
 * Allowlisted traffic source from the entry URL.
 */
function bbgc_request_source()
{
    if (!isset($_GET['source'])) {
        return 'direct';
    }

    $source = sanitize_key(wp_unslash($_GET['source']));

    if (in_array($source, ['bbgc_registration', 'qr', 'direct'], true)) {
        return $source;
    }

    return 'direct';
}

/**
 * Optional campaign code. Stored with the journey, never shown as identity.
 */
function bbgc_request_campaign()
{
    if (!isset($_GET['campaign'])) {
        return '';
    }

    $campaign = sanitize_text_field(wp_unslash($_GET['campaign']));

    if (preg_match('/^[A-Za-z0-9_-]{1,40}$/', $campaign)) {
        return $campaign;
    }

    return '';
}

/**
 * @param array<string, string> $atts
 */
function bbgc_render_shortcode($atts)
{
    $atts = shortcode_atts(
        [
            'return_url' => home_url('/'),
            'start' => '',
        ],
        $atts,
        'bbgc_insurance_guide'
    );

    $returnUrl = esc_url_raw($atts['return_url']);
    $savedReturn = esc_url_raw((string) get_option('bbgc_return_url', ''));
    if ($savedReturn !== '') {
        $returnUrl = $savedReturn;
    }
    if ($returnUrl === '') {
        $returnUrl = home_url('/');
    }

    $source = bbgc_request_source();
    $start = sanitize_key($atts['start']);
    if (!in_array($start, ['invitation', 'page1'], true)) {
        $start = $source === 'bbgc_registration' ? 'invitation' : 'page1';
    }

    $view = '';
    if (isset($_GET['bbgc_view'])) {
        $requested = sanitize_key(wp_unslash($_GET['bbgc_view']));
        if (in_array($requested, ['invitation', 'page1', 'page2', 'results', 'thanks'], true)) {
            $view = $requested;
        }
    }

    wp_enqueue_style('bbgc-guide');
    wp_enqueue_script('bbgc-guide');

    wp_localize_script('bbgc-guide', 'BBGC_GUIDE', [
        'logoUrl' => BBGC_PLUGIN_URL . 'assets/images/babybrands-logo.svg',
        'returnUrl' => $returnUrl,
        'source' => $source,
        'campaign' => bbgc_request_campaign(),
        'start' => $start,
        'view' => $view,
        'restUrl' => esc_url_raw(rest_url('bbgc/v1')),
        'noticeVersion' => BBGC_NOTICE_VERSION,
        'notice' => __('When you finish, BabyBrands will store your answers to improve this guide. Your answers are self-reported, not verified by BabyBrands.', 'breast-pump-insurance-plugin'),
        'insurers' => bbgc_public_directory(),
        'privacy' => __('This guide asks only for the answers you choose to enter. Insurance logins, member numbers, prescriptions and medical documents are not collected here. Browsing the guide is not a signup for marketing.', 'breast-pump-insurance-plugin'),
        'about' => __('BabyBrands helps you navigate your insurer\'s website. It does not verify benefits or submit claims. Section names vary by plan; check eligibility and conditions with your insurer.', 'breast-pump-insurance-plugin'),
        'decline' => __('You can leave this optional guide and continue. Your registration is unchanged, and no insurance answers are required.', 'breast-pump-insurance-plugin'),
    ]);

    ob_start();
    ?>
    <div class="bbgc-shell" id="bbgc-guide">
        <div id="bbgc-view">
            <header class="bbgc-header">
                <img class="bbgc-logo" src="<?php echo esc_url(BBGC_PLUGIN_URL . 'assets/images/babybrands-logo.svg'); ?>" alt="<?php esc_attr_e('BabyBrands Gift Club', 'breast-pump-insurance-plugin'); ?>" width="204" height="75">
                <div class="bbgc-caption"><?php esc_html_e('Breast Pump Insurance Guide', 'breast-pump-insurance-plugin'); ?></div>
                <span class="bbgc-accent"></span>
            </header>
            <main class="bbgc-page">
                <p class="bbgc-intro"><?php esc_html_e('Loading the guide…', 'breast-pump-insurance-plugin'); ?></p>
            </main>
        </div>
        <dialog id="bbgc-notice">
            <h2 id="bbgc-notice-title"></h2>
            <p id="bbgc-notice-body"></p>
            <button type="button" class="bbgc-btn bbgc-btn-dark" id="bbgc-close-notice"><?php esc_html_e('Close', 'breast-pump-insurance-plugin'); ?></button>
        </dialog>
    </div>
    <?php

    return ob_get_clean();
}

add_shortcode('bbgc_insurance_guide', 'bbgc_render_shortcode');
