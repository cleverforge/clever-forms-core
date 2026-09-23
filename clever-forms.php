<?php
/**
 * Plugin Name: Clever Forms
 * Plugin URI: https://cleverforge.ai/clever-forms/
 * Description: Build responsive forms with logic, entries, signatures, uploads, PDFs, notifications, webhooks, templates, and add-ons.
 * Version: 0.9.4-dev
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Author: CleverForge and AI for Social Change, LLC
 * Author URI: https://cleverforge.ai/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: clever-forms
 * Domain Path: /languages
 */
if (!defined('ABSPATH')) { exit; }
define('CLEVER_FORMS_VERSION', '0.9.4-dev');
define('CLEVER_FORMS_FILE', __FILE__);
define('CLEVER_FORMS_DIR', plugin_dir_path(__FILE__));
define('CLEVER_FORMS_URL', plugin_dir_url(__FILE__));
require_once CLEVER_FORMS_DIR . 'includes/class-clever-addons.php';
require_once CLEVER_FORMS_DIR . 'includes/class-clever-forms-pdf.php';
require_once CLEVER_FORMS_DIR . 'includes/class-clever-forms-security.php';
require_once CLEVER_FORMS_DIR . 'includes/class-clever-forms.php';

add_action('init', static function (): void {
	load_plugin_textdomain('clever-forms', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

register_activation_hook(__FILE__, ['Clever_Forms', 'activate']);
Clever_Forms_Security::boot();
Clever_Forms::instance();
do_action('clever_forms_loaded', CLEVER_FORMS_VERSION);
