<?php
/**
 * Plugin Name: Kitchen Calculator
 * Description: Конвертер кулінарних мір. v0.2: категорії інгредієнтів (вкладки), манка+вода, CRUD у адмінці.
 * Version:     0.2.3
 * Requires PHP: 7.4
 * Author:      You
 * Text Domain: kitchen-calculator
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

define('KC_PLUGIN_FILE', __FILE__);
define('KC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('KC_CAP', 'manage_kitchen_calculator');
define('KC_DB_VERSION', '3');

require_once KC_PLUGIN_DIR . 'inc/db.php';
require_once KC_PLUGIN_DIR . 'inc/helpers.php';
require_once KC_PLUGIN_DIR . 'inc/rest.php';
require_once KC_PLUGIN_DIR . 'admin/pages.php';

// ▼▼▼ ПОЧАТОК КОДУ ДЛЯ ЛОКАЛІЗАЦІЇ ТА POLYLANG ▼▼▼

/**
 * Завантажує файл перекладу плагіна.
 */
function kc_load_textdomain() {
    load_plugin_textdomain(
        'kitchen-calculator',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}
add_action('plugins_loaded', 'kc_load_textdomain');

/**
 * Реєструє динамічні рядки (інгредієнти, категорії, юніти) для перекладу в Polylang.
 */
function kc_polylang_register_strings() {
    if (function_exists('pll_register_string')) {
        global $wpdb;
        $group = 'Kitchen Calculator';

        // Інгредієнти
        $ingredients = $wpdb->get_results("SELECT name FROM " . kc_table('ingredients'));
        if ($ingredients) {
            foreach ($ingredients as $item) {
                pll_register_string($item->name, $item->name, $group, false);
            }
        }

        // Категорії
        $categories = $wpdb->get_results("SELECT name FROM " . kc_table('categories'));
        if ($categories) {
            foreach ($categories as $item) {
                pll_register_string($item->name, $item->name, $group, false);
            }
        }

        // Юніти
        $units = $wpdb->get_results("SELECT label FROM " . kc_table('units'));
        if ($units) {
            foreach ($units as $item) {
                pll_register_string($item->label, $item->label, $group, false);
            }
        }
    }
}
add_action('init', 'kc_polylang_register_strings', 20); // Запускаємо з пріоритетом, щоб точно після завантаження всього

// ▲▲▲ КІНЕЦЬ КОДУ ДЛЯ ЛОКАЛІЗАЦІЇ ТА POLYLANG ▲▲▲


/** Активуємо: створюємо таблиці + сид */
register_activation_hook(__FILE__, function () {
    if ($role = get_role('administrator')) { $role->add_cap('manage_kitchen_calculator'); }
    kc_db_maybe_install();
    kc_seed_initial_data();
    add_option('kc_db_version', KC_DB_VERSION);
});

/** Блок: реєстрація скриптів з залежностями + блок */
add_action('init', function () {
    wp_register_script(
        'kc-calculator-block',
        plugins_url('blocks/kc-calculator/block.js', __FILE__),
        ['wp-blocks','wp-element','wp-components','wp-editor','wp-block-editor','wp-i18n'],
        filemtime(__DIR__ . '/blocks/kc-calculator/block.js'),
        true
    );

    wp_register_script(
        'kc-calculator-frontend',
        plugins_url('blocks/kc-calculator/frontend.js', __FILE__),
        ['wp-api-fetch', 'wp-i18n'], // Додано wp-i18n як залежність
        filemtime(__DIR__ . '/blocks/kc-calculator/frontend.js'),
        true
    );
    
    // Передаємо переклади в JS для коректної роботи
    wp_set_script_translations('kc-calculator-frontend', 'kitchen-calculator', KC_PLUGIN_DIR . 'languages');

    register_block_type(__DIR__ . '/blocks/kc-calculator', [
        'editor_script'   => 'kc-calculator-block',
        'view_script'     => 'kc-calculator-frontend',
        'render_callback' => 'kc_render_calculator_block',
    ]);
});

/** Рендер блоку на фронті */
function kc_render_calculator_block($attributes) {
    $ing = esc_attr($attributes['defaultIngredient'] ?? 'semolina');
    $from = esc_attr($attributes['defaultFromUnit'] ?? 'glass200');
    $to = esc_attr($attributes['defaultToUnit'] ?? 'g');
    $precision = (int)($attributes['precision'] ?? 2);

    ob_start(); ?>
    <div class="kc-calculator-block"
         data-default-ingredient="<?php echo $ing; ?>"
         data-default-from="<?php echo $from; ?>"
         data-default-to="<?php echo $to; ?>"
         data-precision="<?php echo esc_attr($precision); ?>">

      <div class="kc-tabs"></div>

      <label>
        <span><?php _e('Ingredient', 'kitchen-calculator'); ?></span>
        <select class="kc-ingredient"></select>
      </label>

      <label>
        <span><?php _e('Amount', 'kitchen-calculator'); ?></span>
        <input type="number" class="kc-amount" value="1" step="any">
      </label>

      <label>
        <span><?php _e('From', 'kitchen-calculator'); ?></span>
        <select class="kc-from-unit"></select>
      </label>

      <label>
        <span><?php _e('To', 'kitchen-calculator'); ?></span>
        <select class="kc-to-unit"></select>
      </label>

      <button type="button" class="kc-swap" aria-label="<?php esc_attr_e('Swap', 'kitchen-calculator'); ?>">↔︎ <?php _e('Swap', 'kitchen-calculator'); ?></button>
      <div class="kc-recipe-links" aria-live="polite"></div>
      <p class="kc-result" aria-live="polite"></p>
    </div>
    <?php
    return ob_get_clean();
}

/** Адмін-меню */
add_action('admin_menu', function () {
    add_menu_page(
        __('Kitchen Calculator', 'kitchen-calculator'),
        __('Kitchen Calc', 'kitchen-calculator'),
        'manage_options',
        'kc-dashboard',
        'kc_admin_page_ingredients',
        'dashicons-calculator',
        58
    );
    add_submenu_page('kc-dashboard', __('Ingredients', 'kitchen-calculator'), __('Ingredients', 'kitchen-calculator'),
        'manage_kitchen_calculator', 'kc-ingredients', 'kc_admin_page_ingredients');
    add_submenu_page('kc-dashboard', __('Units', 'kitchen-calculator'), __('Units', 'kitchen-calculator'),
        'manage_kitchen_calculator', 'kc-units', 'kc_admin_page_units');
    add_submenu_page('kc-dashboard', __('Categories', 'kitchen-calculator'), __('Categories', 'kitchen-calculator'),
        'manage_options', 'kc-categories', 'kc_admin_page_categories');
    add_submenu_page('kc-dashboard', __('Mappings', 'kitchen-calculator'), __('Mappings', 'kitchen-calculator'),
        'manage_kitchen_calculator', 'kc-mappings', 'kc_admin_page_mappings');
    add_submenu_page(
        'kc-dashboard',
        __('Import/Export CSV', 'kitchen-calculator'),
        __('Import/Export CSV', 'kitchen-calculator'),
        KC_CAP,
        'kc-import-export',
        'kc_admin_page_import_export'
    );
    add_submenu_page(
        'kc-dashboard',
        __('Settings', 'kitchen-calculator'),
        __('Settings', 'kitchen-calculator'),
        'manage_options',
        'kc-settings',
        'kc_admin_page_settings'
    );
});

/** Conditional DB upgrade only when viewing plugin admin pages */
function kc_safe_admin_upgrade() {
    if (!is_admin()) return;
    if (!current_user_can('manage_options')) return;
    $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
    if (strpos($page, 'kc-') !== 0 && $page !== 'kc-dashboard') return;
    if (function_exists('kc_db_maybe_upgrade')) { kc_db_maybe_upgrade(); }
}
add_action('admin_init', 'kc_safe_admin_upgrade', 20);

/** Обробляє POST-запити та GET-параметри для імпорту/експорту CSV. */
function kc_handle_csv_actions() {
    if (!current_user_can(KC_CAP)) {
        return;
    }
    $action = '';
    if (isset($_POST['kc_action'])) {
        $action = sanitize_key($_POST['kc_action']);
    } elseif (isset($_GET['kc_action'])) {
        $action = sanitize_key($_GET['kc_action']);
    }
    if (empty($action)) {
        return;
    }
    $function_name = "kc_action_{$action}";
    if (function_exists($function_name)) {
        $parts = explode('_', $action);
        if (count($parts) !== 3) {
            wp_die('Invalid action format specified.');
        }
        $action_prefix = $parts[0];
        $type = $parts[1];
        $nonce_action = "kc_nonce_{$type}_{$action_prefix}";
        $nonce_field = $nonce_action . '_field';
        check_admin_referer($nonce_action, $nonce_field);
        call_user_func($function_name);
    }
}
add_action('admin_init', 'kc_handle_csv_actions');

/** Шорткод */
function kc_shortcode_callback($atts) {
    $attributes = shortcode_atts([
        'defaultIngredient' => 'semolina',
        'defaultFromUnit'   => 'glass200',
        'defaultToUnit'     => 'g',
        'precision'         => 2,
    ], $atts, 'kitchen_calculator');
    return kc_render_calculator_block($attributes);
}
add_shortcode('kitchen_calculator', 'kc_shortcode_callback');

/** Налаштування для рецептів */
function kc_register_settings() {
    register_setting(
        'kc_settings_group',
        'kc_options',
        'kc_settings_sanitize'
    );
}
add_action('admin_init', 'kc_register_settings');

function kc_settings_sanitize($input) {
    $new_input = [];
    if (isset($input['recipe_count'])) {
        $new_input['recipe_count'] = absint($input['recipe_count']);
    }
    return $new_input;
}

/** Мета-бокс для рецептів */
function kc_add_ingredient_meta_box() {
    add_meta_box(
        'kc_ingredient_link',
        __('Link to Calculator Ingredient', 'kitchen-calculator'),
        'kc_render_ingredient_meta_box',
        ['post'],
        'side',
        'low'
    );
}
add_action('add_meta_boxes', 'kc_add_ingredient_meta_box');

function kc_render_ingredient_meta_box($post) {
    global $wpdb;
    wp_nonce_field('kc_save_meta_box_data', 'kc_meta_box_nonce');
    $ingredient_slug = get_post_meta($post->ID, '_kc_ingredient_slug', true);
    $priority = get_post_meta($post->ID, '_kc_ingredient_priority', true);
    if ($priority === '') $priority = 10;
    $all_ingredients = $wpdb->get_results("SELECT slug, name FROM " . kc_table('ingredients') . " ORDER BY name");
    echo '<p><label for="kc_ingredient_slug_field">' . __('Ingredient:', 'kitchen-calculator') . '</label></p>';
    echo '<select id="kc_ingredient_slug_field" name="kc_ingredient_slug_field" style="width:100%;">';
    echo '<option value="">' . __('— None —', 'kitchen-calculator') . '</option>';
    foreach ($all_ingredients as $ing) {
        $translated_name = function_exists('pll__') ? pll__($ing->name) : $ing->name;
        echo '<option value="' . esc_attr($ing->slug) . '" ' . selected($ingredient_slug, $ing->slug, false) . '>' . esc_html($translated_name) . '</option>';
    }
    echo '</select>';
    echo '<p><label for="kc_priority_field">' . __('Priority:', 'kitchen-calculator') . '</label></p>';
    echo '<input type="number" id="kc_priority_field" name="kc_priority_field" value="' . esc_attr($priority) . '" min="0" step="1" style="width:100%;"/>';
    echo '<p class="description">' . __('Lower number means higher priority.', 'kitchen-calculator') . '</p>';
}

function kc_save_ingredient_meta_box_data($post_id) {
    if (!isset($_POST['kc_meta_box_nonce']) || !wp_verify_nonce($_POST['kc_meta_box_nonce'], 'kc_save_meta_box_data')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (isset($_POST['kc_ingredient_slug_field'])) {
        update_post_meta($post_id, '_kc_ingredient_slug', sanitize_key($_POST['kc_ingredient_slug_field']));
    }
    if (isset($_POST['kc_priority_field'])) {
        update_post_meta($post_id, '_kc_ingredient_priority', intval($_POST['kc_priority_field']));
    }
}
add_action('save_post', 'kc_save_ingredient_meta_box_data');