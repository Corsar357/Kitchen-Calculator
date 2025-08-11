<?php
defined('ABSPATH') || exit;

/** INGREDIENTS */
function kc_admin_page_ingredients() {
    if (!current_user_can('manage_kitchen_calculator')) wp_die('Forbidden');
    global $wpdb;

    // categories for UI
    $cats = $wpdb->get_results("SELECT id, name, slug FROM ".kc_table('categories')." ORDER BY sort,name");
    $current_cat = sanitize_key($_GET['cat'] ?? '');

    // Handle create/update/delete
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        check_admin_referer('kc_ing_save');
        if (isset($_POST['create'])) {
            $wpdb->insert(kc_table('ingredients'), [
                'name'=>sanitize_text_field($_POST['name']),
                'slug'=>sanitize_key($_POST['slug']),
                'density_g_per_ml'=>($_POST['density']!==''? floatval($_POST['density']): null),
                'category_id'=>($_POST['category_id']!=='')? intval($_POST['category_id']) : null,
            ]);
            echo '<div class="notice notice-success"><p>' . __('Ingredient created.', 'kitchen-calculator') . '</p></div>';
        }
        if (isset($_POST['update'])) {
            $wpdb->update(kc_table('ingredients'), [
                'name'=>sanitize_text_field($_POST['name']),
                'density_g_per_ml'=>($_POST['density']!==''? floatval($_POST['density']): null),
                'category_id'=>($_POST['category_id']!=='')? intval($_POST['category_id']) : null,
            ], ['id'=>intval($_POST['id'])]);
            echo '<div class="notice notice-success"><p>' . __('Ingredient updated.', 'kitchen-calculator') . '</p></div>';
        }
        if (isset($_POST['delete'])) {
            $wpdb->delete(kc_table('ingredients'), ['id'=>intval($_POST['id'])]);
            echo '<div class="notice notice-success"><p>' . __('Ingredient deleted.', 'kitchen-calculator') . '</p></div>';
        }
    }

    // Tabs
    echo '<div class="wrap"><h1>' . __('Ingredients', 'kitchen-calculator') . '</h1>';
    echo '<h2 class="nav-tab-wrapper">';
    echo '<a class="nav-tab '.($current_cat===''?'nav-tab-active':'').'" href="?page=kc-ingredients">' . __('All', 'kitchen-calculator') . '</a>';
    foreach ($cats as $c) {
        $active = $current_cat===$c->slug ? ' nav-tab-active' : '';
        $translated_cat_name = function_exists('pll__') ? pll__($c->name) : $c->name;
        echo '<a class="nav-tab'.$active.'" href="?page=kc-ingredients&cat='.$c->slug.'">'.esc_html($translated_cat_name).'</a>';
    }
    echo '</h2>';

    echo '<h2>' . __('Add new', 'kitchen-calculator') . '</h2>';
    ?>
    <form method="post"><?php wp_nonce_field('kc_ing_save'); ?>
      <input type="text" name="name" placeholder="<?php esc_attr_e('Name', 'kitchen-calculator'); ?>" required>
      <input type="text" name="slug" placeholder="<?php esc_attr_e('Slug (unique)', 'kitchen-calculator'); ?>" required>
      <input type="number" step="0.0001" name="density" placeholder="<?php esc_attr_e('Density g/mL (optional)', 'kitchen-calculator'); ?>">
      <select name="category_id">
        <option value=""><?php _e('— Category —', 'kitchen-calculator'); ?></option>
        <?php foreach($cats as $c):
            $translated_cat_name = function_exists('pll__') ? pll__($c->name) : $c->name;
        ?>
          <option value="<?php echo esc_attr($c->id); ?>"><?php echo esc_html($translated_cat_name); ?></option>
        <?php endforeach; ?>
      </select>
      <button class="button button-primary" name="create" value="1"><?php _e('Add', 'kitchen-calculator'); ?></button>
    </form>
    <?php

    $where = '';
    if ($current_cat) {
        $where = $wpdb->prepare(" WHERE category_id = (SELECT id FROM ".kc_table('categories')." WHERE slug=%s)", $current_cat);
    }
    $rows = $wpdb->get_results("SELECT * FROM ".kc_table('ingredients').$where." ORDER BY name");

    echo '<h2>' . __('Existing', 'kitchen-calculator') . '</h2>'; ?>
    <table class="widefat striped">
      <thead><tr><th>ID</th><th><?php _e('Name', 'kitchen-calculator'); ?></th><th><?php _e('Slug', 'kitchen-calculator'); ?></th><th><?php _e('Density g/mL', 'kitchen-calculator'); ?></th><th><?php _e('Category', 'kitchen-calculator'); ?></th><th><?php _e('Actions', 'kitchen-calculator'); ?></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <form method="post"><?php wp_nonce_field('kc_ing_save'); ?>
            <td><?php echo esc_html($r->id); ?><input type="hidden" name="id" value="<?php echo esc_attr($r->id); ?>"></td>
            <td><input type="text" name="name" value="<?php echo esc_attr($r->name); ?>"></td>
            <td><?php echo esc_html($r->slug); ?></td>
            <td><input type="number" step="0.0001" name="density" value="<?php echo esc_attr($r->density_g_per_ml); ?>"></td>
            <td>
              <select name="category_id">
                <option value="">—</option>
                <?php foreach($cats as $c):
                    $translated_cat_name = function_exists('pll__') ? pll__($c->name) : $c->name;
                ?>
                  <option value="<?php echo esc_attr($c->id); ?>" <?php selected($r->category_id, $c->id); ?>>
                    <?php echo esc_html($translated_cat_name); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </td>
            <td>
              <button class="button" name="update" value="1"><?php _e('Save', 'kitchen-calculator'); ?></button>
              <button class="button button-link-delete" name="delete" value="1" onclick="return confirm('<?php esc_attr_e('Delete?', 'kitchen-calculator'); ?>')"><?php _e('Delete', 'kitchen-calculator'); ?></button>
            </td>
          </form>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php
}

/** UNITS */
function kc_admin_page_units() {
    if (!current_user_can('manage_kitchen_calculator')) wp_die('Forbidden');
    global $wpdb;

    if ($_SERVER['REQUEST_METHOD']==='POST') {
        check_admin_referer('kc_unit_save');
        if (isset($_POST['create'])) {
            $wpdb->insert(kc_table('units'), [
                'slug'=>sanitize_key($_POST['slug']),
                'label'=>sanitize_text_field($_POST['label']),
                'type'=>in_array($_POST['type'],['mass','volume','piece'],true)?$_POST['type']:'volume',
                'to_base'=>floatval($_POST['to_base']),
                'active'=>1
            ]);
            echo '<div class="notice notice-success"><p>' . __('Unit created.', 'kitchen-calculator') . '</p></div>';
        }
        if (isset($_POST['update'])) {
            $wpdb->update(kc_table('units'), [
                'label'=>sanitize_text_field($_POST['label']),
                'type'=>in_array($_POST['type'],['mass','volume','piece'],true)?$_POST['type']:'volume',
                'to_base'=>floatval($_POST['to_base']),
                'active'=>isset($_POST['active'])?1:0
            ], ['id'=>intval($_POST['id'])]);
          echo '<div class="notice notice-success"><p>' . __('Unit updated.', 'kitchen-calculator') . '</p></div>';

        }
        if (isset($_POST['delete'])) {
            $wpdb->delete(kc_table('units'), ['id'=>intval($_POST['id'])]);
            echo '<div class="notice notice-success"><p>' . __('Unit deleted.', 'kitchen-calculator') . '</p></div>';
        }
    }

    $rows = $wpdb->get_results("SELECT * FROM ".kc_table('units')." ORDER BY type,label");
    ?>
    <div class="wrap">
      <h1><?php _e('Units', 'kitchen-calculator'); ?></h1>
      <h2><?php _e('Add new', 'kitchen-calculator'); ?></h2>
      <form method="post"><?php wp_nonce_field('kc_unit_save'); ?>
        <input type="text" name="slug" placeholder="<?php esc_attr_e('Slug (unique)', 'kitchen-calculator'); ?>" required>
        <input type="text" name="label" placeholder="<?php esc_attr_e('Label', 'kitchen-calculator'); ?>" required>
        <select name="type">
          <option value="volume"><?php _e('volume (mL)', 'kitchen-calculator'); ?></option>
          <option value="mass"><?php _e('mass (g)', 'kitchen-calculator'); ?></option>
          <option value="piece"><?php _e('piece', 'kitchen-calculator'); ?></option>
        </select>
        <input type="number" step="0.000001" name="to_base" placeholder="<?php esc_attr_e('to base (mL or g)', 'kitchen-calculator'); ?>" required>
        <button class="button button-primary" name="create" value="1"><?php _e('Add', 'kitchen-calculator'); ?></button>
      </form>

      <h2><?php _e('Existing', 'kitchen-calculator'); ?></h2>
      <table class="widefat striped">
        <thead><tr><th>ID</th><th><?php _e('Slug', 'kitchen-calculator'); ?></th><th><?php _e('Label', 'kitchen-calculator'); ?></th><th><?php _e('Type', 'kitchen-calculator'); ?></th><th><?php _e('to_base', 'kitchen-calculator'); ?></th><th><?php _e('Active', 'kitchen-calculator'); ?></th><th><?php _e('Actions', 'kitchen-calculator'); ?></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <form method="post"><?php wp_nonce_field('kc_unit_save'); ?>
              <td><?php echo esc_html($r->id); ?><input type="hidden" name="id" value="<?php echo esc_attr($r->id); ?>"></td>
              <td><?php echo esc_html($r->slug); ?></td>
              <td><input type="text" name="label" value="<?php echo esc_attr($r->label); ?>"></td>
              <td>
                <select name="type">
                  <option value="volume" <?php selected($r->type,'volume'); ?>><?php _e('volume', 'kitchen-calculator'); ?></option>
                  <option value="mass" <?php selected($r->type,'mass'); ?>><?php _e('mass', 'kitchen-calculator'); ?></option>
                  <option value="piece" <?php selected($r->type,'piece'); ?>><?php _e('piece', 'kitchen-calculator'); ?></option>
                </select>
              </td>
              <td><input type="number" step="0.000001" name="to_base" value="<?php echo esc_attr($r->to_base); ?>"></td>
              <td><label><input type="checkbox" name="active" <?php checked($r->active,1); ?>> <?php _e('active', 'kitchen-calculator'); ?></label></td>
              <td>
                <button class="button" name="update" value="1"><?php _e('Save', 'kitchen-calculator'); ?></button>
                <button class="button button-link-delete" name="delete" value="1" onclick="return confirm('<?php esc_attr_e('Delete?', 'kitchen-calculator'); ?>')"><?php _e('Delete', 'kitchen-calculator'); ?></button>
              </td>
            </form>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
}

/** MAPPINGS */
function kc_admin_page_mappings() {
    if (!current_user_can('manage_kitchen_calculator')) wp_die('Forbidden');
    global $wpdb;

    if ($_SERVER['REQUEST_METHOD']==='POST') {
        check_admin_referer('kc_map_save');
        if (isset($_POST['create'])) {
            $wpdb->insert(kc_table('mappings'), [
                'ingredient_id'=>intval($_POST['ingredient_id']),
                'unit_id'=>intval($_POST['unit_id']),
                'grams_per_unit'=>floatval($_POST['grams_per_unit']),
                'note'=>sanitize_text_field($_POST['note'])
            ]);
            echo '<div class="notice notice-success"><p>' . __('Mapping created.', 'kitchen-calculator') . '</p></div>';
        }
        if (isset($_POST['update'])) {
            $wpdb->update(kc_table('mappings'), [
                'grams_per_unit'=>floatval($_POST['grams_per_unit']),
                'note'=>sanitize_text_field($_POST['note'])
            ], ['id'=>intval($_POST['id'])]);
            echo '<div class="notice notice-success"><p>' . __('Mapping updated.', 'kitchen-calculator') . '</p></div>';
        }
        if (isset($_POST['delete'])) {
            $wpdb->delete(kc_table('mappings'), ['id'=>intval($_POST['id'])]);
            echo '<div class="notice notice-success"><p>' . __('Mapping deleted.', 'kitchen-calculator') . '</p></div>';
        }
    }

    $ingredients = $wpdb->get_results("SELECT id,name FROM ".kc_table('ingredients')." ORDER BY name");
    $units = $wpdb->get_results("SELECT id,label FROM ".kc_table('units')." WHERE active=1 ORDER BY type,label");
    $rows = $wpdb->get_results("SELECT m.*, i.name as ing_name, u.label as unit_label
        FROM ".kc_table('mappings')." m
        JOIN ".kc_table('ingredients')." i ON i.id=m.ingredient_id
        JOIN ".kc_table('units')." u ON u.id=m.unit_id
        ORDER BY i.name, u.label");

    ?>
    <div class="wrap">
      <h1><?php _e('Mappings (Ingredient &times; Unit &rarr; grams per unit)', 'kitchen-calculator'); ?></h1>
      <h2><?php _e('Add new', 'kitchen-calculator'); ?></h2>
      <form method="post"><?php wp_nonce_field('kc_map_save'); ?>
        <select name="ingredient_id" required>
          <option value=""><?php _e('Ingredient…', 'kitchen-calculator'); ?></option>
          <?php foreach ($ingredients as $i):
            $translated_name = function_exists('pll__') ? pll__($i->name) : $i->name;
          ?>
            <option value="<?php echo esc_attr($i->id); ?>"><?php echo esc_html($translated_name); ?></option>
          <?php endforeach; ?>
        </select>
        <select name="unit_id" required>
          <option value=""><?php _e('Unit…', 'kitchen-calculator'); ?></option>
          <?php foreach ($units as $u):
            $translated_label = function_exists('pll__') ? pll__($u->label) : $u->label;
          ?>
            <option value="<?php echo esc_attr($u->id); ?>"><?php echo esc_html($translated_label); ?></option>
          <?php endforeach; ?>
        </select>
        <input type="number" step="0.0001" name="grams_per_unit" placeholder="<?php esc_attr_e('grams per 1 unit', 'kitchen-calculator'); ?>" required>
        <input type="text" name="note" placeholder="<?php esc_attr_e('note (optional)', 'kitchen-calculator'); ?>">
        <button class="button button-primary" name="create" value="1"><?php _e('Add', 'kitchen-calculator'); ?></button>
      </form>

      <h2><?php _e('Existing', 'kitchen-calculator'); ?></h2>
      <table class="widefat striped">
        <thead><tr><th>ID</th><th><?php _e('Ingredient', 'kitchen-calculator'); ?></th><th><?php _e('Unit', 'kitchen-calculator'); ?></th><th><?php _e('grams/unit', 'kitchen-calculator'); ?></th><th><?php _e('Note', 'kitchen-calculator'); ?></th><th><?php _e('Actions', 'kitchen-calculator'); ?></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r):
            $translated_ing_name = function_exists('pll__') ? pll__($r->ing_name) : $r->ing_name;
            $translated_unit_label = function_exists('pll__') ? pll__($r->unit_label) : $r->unit_label;
        ?>
          <tr>
            <form method="post"><?php wp_nonce_field('kc_map_save'); ?>
              <td><?php echo esc_html($r->id); ?><input type="hidden" name="id" value="<?php echo esc_attr($r->id); ?>"></td>
              <td><?php echo esc_html($translated_ing_name); ?></td>
              <td><?php echo esc_html($translated_unit_label); ?></td>
              <td><input type="number" step="0.0001" name="grams_per_unit" value="<?php echo esc_attr($r->grams_per_unit); ?>"></td>
              <td><input type="text" name="note" value="<?php echo esc_attr($r->note); ?>"></td>
              <td>
                <button class="button" name="update" value="1"><?php _e('Save', 'kitchen-calculator'); ?></button>
                <button class="button button-link-delete" name="delete" value="1" onclick="return confirm('<?php esc_attr_e('Delete?', 'kitchen-calculator'); ?>')"><?php _e('Delete', 'kitchen-calculator'); ?></button>
              </td>
            </form>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
}

/** CATEGORIES */
function kc_admin_page_categories() {
    if (!current_user_can('manage_kitchen_calculator')) wp_die('Forbidden');
    global $wpdb;

    if ($_SERVER['REQUEST_METHOD']==='POST') {
        check_admin_referer('kc_cat_save');
        if (isset($_POST['create'])) {
            $slug = sanitize_key($_POST['slug'] ?? '');
            $name = sanitize_text_field($_POST['name'] ?? '');
            $sort = intval($_POST['sort'] ?? 0);
            if ($slug && $name) { $wpdb->insert(kc_table('categories'), ['slug'=>$slug,'name'=>$name,'sort'=>$sort]); }
        } elseif (isset($_POST['update'])) {
            $id = intval($_POST['id'] ?? 0);
            $slug = sanitize_key($_POST['slug'] ?? '');
            $name = sanitize_text_field($_POST['name'] ?? '');
            $sort = intval($_POST['sort'] ?? 0);
            if ($id>0) { $wpdb->update(kc_table('categories'), ['slug'=>$slug,'name'=>$name,'sort'=>$sort], ['id'=>$id]); }
        } elseif (isset($_POST['delete'])) {
            $id = intval($_POST['id'] ?? 0);
            $cnt = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM ".kc_table('ingredients')." WHERE category_id=%d", $id));
            if ($cnt===0) { $wpdb->delete(kc_table('categories'), ['id'=>$id]); }
        }
    }

    $rows = $wpdb->get_results("SELECT id,slug,name,sort FROM ".kc_table('categories')." ORDER BY sort,name");
    ?>
    <div class="wrap">
      <h1><?php _e('Categories', 'kitchen-calculator'); ?></h1>
      <h2 class="title"><?php _e('Create', 'kitchen-calculator'); ?></h2>
      <form method="post"><?php wp_nonce_field('kc_cat_save'); ?>
        <table class="form-table"><tbody>
          <tr><th scope="row"><?php _e('Slug', 'kitchen-calculator'); ?></th><td><input name="slug" class="regular-text" required></td></tr>
          <tr><th scope="row"><?php _e('Name', 'kitchen-calculator'); ?></th><td><input name="name" class="regular-text" required></td></tr>
          <tr><th scope="row"><?php _e('Sort', 'kitchen-calculator'); ?></th><td><input name="sort" type="number" value="0"></td></tr>
        </tbody></table>
        <p><button class="button button-primary" name="create" value="1"><?php _e('Add', 'kitchen-calculator'); ?></button></p>
      </form>

      <h2><?php _e('Existing', 'kitchen-calculator'); ?></h2>
      <table class="widefat striped">
        <thead><tr><th>ID</th><th><?php _e('Slug', 'kitchen-calculator'); ?></th><th><?php _e('Name', 'kitchen-calculator'); ?></th><th><?php _e('Sort', 'kitchen-calculator'); ?></th><th><?php _e('Actions', 'kitchen-calculator'); ?></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <form method="post"><?php wp_nonce_field('kc_cat_save'); ?>
              <td><?php echo esc_html($r->id); ?><input type="hidden" name="id" value="<?php echo esc_attr($r->id); ?>"></td>
              <td><input name="slug" value="<?php echo esc_attr($r->slug); ?>"></td>
              <td><input name="name" value="<?php echo esc_attr($r->name); ?>"></td>
              <td><input name="sort" type="number" value="<?php echo esc_attr($r->sort); ?>"></td>
              <td>
                <button class="button" name="update" value="1"><?php _e('Save', 'kitchen-calculator'); ?></button>
                <button class="button button-link-delete" name="delete" value="1" onclick="return confirm('<?php esc_attr_e('Delete? Categories with ingredients cannot be deleted.', 'kitchen-calculator'); ?>')"><?php _e('Delete', 'kitchen-calculator'); ?></button>
              </td>
            </form>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
}

/**
 * ======================================================================
 * СТОРІНКА ІНТЕРФЕЙСУ ІМПОРТУ/ЕКСПОРТУ
 * ======================================================================
 */
function kc_admin_page_import_export() {
    if (!current_user_can(KC_CAP)) wp_die('Forbidden');
    
    // Повідомлення про статус
    if (!empty($_GET['kc_status'])) {
        $status = sanitize_key($_GET['kc_status']);
        $count = isset($_GET['count']) ? intval($_GET['count']) : 0;
        if ($status === 'imported') {
            echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(__('Successfully imported %d rows.', 'kitchen-calculator'), $count) . '</p></div>';
        } elseif ($status === 'error') {
            echo '<div class="notice notice-error is-dismissible"><p>' . __('An error occurred. Please check your file format and content.', 'kitchen-calculator') . '</p></div>';
        }
    }
    ?>
    <div class="wrap">
        <h1><?php _e('Import and Export CSV', 'kitchen-calculator'); ?></h1>
        <p><?php _e('Manage your data by exporting to CSV, editing in a spreadsheet, and importing back.', 'kitchen-calculator'); ?></p>

        <?php
        $data_types = [
            'ingredients' => __('Ingredients', 'kitchen-calculator'),
            'categories'  => __('Categories', 'kitchen-calculator'),
            'units'       => __('Units', 'kitchen-calculator'),
            'mappings'    => __('Mappings', 'kitchen-calculator'),
        ];

        foreach ($data_types as $type => $label) :
            $nonce_action = "kc_nonce_{$type}_import";
            $nonce_field = $nonce_action . '_field';
            $export_nonce_action = "kc_nonce_{$type}_export";
            $export_nonce_field = $export_nonce_action . '_field';
            $export_url = add_query_arg([
                'kc_action' => "export_{$type}_csv",
                $export_nonce_field => wp_create_nonce($export_nonce_action)
            ], admin_url('admin.php'));
        ?>
            <div class="card" style="margin-top: 2rem;">
                <h2 class="title"><?php echo esc_html($label); ?></h2>
                <div style="display: flex; gap: 20px;">
                    <div style="flex: 1;">
                        <h3><?php _e('Export', 'kitchen-calculator'); ?></h3>
                        <p><?php printf(__('Export all %s to a CSV file.', 'kitchen-calculator'), strtolower($label)); ?></p>
                        <a href="<?php echo esc_url($export_url); ?>" class="button button-secondary"><?php _e('Export to CSV', 'kitchen-calculator'); ?></a>
                    </div>
                    <div style="flex: 1;">
                        <h3><?php _e('Import', 'kitchen-calculator'); ?></h3>
                        <p><?php printf(__('Import %s from a CSV file. Existing entries with the same <code>slug</code> will be updated.', 'kitchen-calculator'), strtolower($label)); ?></p>
                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="kc_action" value="import_<?php echo $type; ?>_csv" />
                            <?php wp_nonce_field($nonce_action, $nonce_field); ?>
                            <p>
                                <label for="import_file_<?php echo $type; ?>"><?php _e('Choose CSV file:', 'kitchen-calculator'); ?></label>
                                <input type="file" id="import_file_<?php echo $type; ?>" name="import_csv_file" accept=".csv" required>
                            </p>
                            <?php submit_button(__('Import from CSV', 'kitchen-calculator')); ?>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}


/**
 * ======================================================================
 * УНІВЕРСАЛЬНІ ФУНКЦІЇ ДЛЯ CSV
 * ======================================================================
 */

/** Допоміжна функція для запуску експорту */
function kc_do_csv_export($filename, $headers, $data_rows) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    fputcsv($output, $headers);
    foreach ($data_rows as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

/** Допоміжна функція для обробки завантаженого файлу імпорту */
function kc_handle_csv_import($callback) {
    if (empty($_FILES['import_csv_file']) || $_FILES['import_csv_file']['error'] !== UPLOAD_ERR_OK) {
        wp_redirect(admin_url('admin.php?page=kc-import-export&kc_status=error'));
        exit;
    }

    $file_path = $_FILES['import_csv_file']['tmp_name'];
    $file = fopen($file_path, 'r');
    if (!$file) {
        wp_redirect(admin_url('admin.php?page=kc-import-export&kc_status=error'));
        exit;
    }
    
    global $wpdb;
    $wpdb->query('START TRANSACTION');
    
    $headers = fgetcsv($file);
    $imported_count = 0;
    
    while (($row = fgetcsv($file)) !== false) {
        if (count($headers) !== count($row)) continue;
        
        $data = array_combine($headers, $row);
        if ($callback($data)) {
            $imported_count++;
        }
    }
    
    fclose($file);
    $wpdb->query('COMMIT');

    if (function_exists('kc_cache_del')) {
        kc_cache_del('kc_rest_categories_v1');
    }

    wp_redirect(admin_url('admin.php?page=kc-import-export&kc_status=imported&count=' . $imported_count));
    exit;
}

/**
 * ======================================================================
 * ЛОГІКА ДЛЯ КОНКРЕТНИХ ТИПІВ ДАНИХ
 * ======================================================================
 */

// --- ІНГРЕДІЄНТИ ---
function kc_action_export_ingredients_csv() {
    global $wpdb;
    $headers = ['name', 'slug', 'density_g_per_ml', 'category_slug'];
    $rows = $wpdb->get_results("
        SELECT i.name, i.slug, i.density_g_per_ml, c.slug as category_slug
        FROM " . kc_table('ingredients') . " i
        LEFT JOIN " . kc_table('categories') . " c ON i.category_id = c.id
        ORDER BY i.name
    ", ARRAY_N);
    kc_do_csv_export('kc-ingredients.csv', $headers, $rows);
}

function kc_action_import_ingredients_csv() {
    kc_handle_csv_import(function($data) {
        global $wpdb;
        $slug = sanitize_key($data['slug']);
        if (empty($slug) || empty($data['name'])) {
            return false;
        }

        $category_id = null;
        $category_slug = sanitize_key($data['category_slug']);

        if (!empty($category_slug)) {
            $existing_category_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM " . kc_table('categories') . " WHERE slug = %s", $category_slug));

            if ($existing_category_id) {
                $category_id = $existing_category_id;
            } else {
                $new_cat_data = [
                    'slug' => $category_slug,
                    'name' => ucwords(str_replace(['-', '_'], ' ', $category_slug)),
                    'sort' => 0,
                ];
                $wpdb->insert(kc_table('categories'), $new_cat_data);
                $category_id = $wpdb->insert_id;
            }
        }
        
        $ing_data = [
            'name'               => sanitize_text_field($data['name']),
            'density_g_per_ml'   => (isset($data['density_g_per_ml']) && $data['density_g_per_ml'] !== '') ? floatval($data['density_g_per_ml']) : null,
            'category_id'        => $category_id,
        ];

        $existing_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM " . kc_table('ingredients') . " WHERE slug = %s", $slug));

        if ($existing_id) {
            $wpdb->update(kc_table('ingredients'), $ing_data, ['id' => $existing_id]);
        } else {
            $wpdb->insert(kc_table('ingredients'), array_merge(['slug' => $slug], $ing_data));
        }
        return true;
    });
}

// --- КАТЕГОРІЇ ---
function kc_action_export_categories_csv() {
    global $wpdb;
    $headers = ['name', 'slug', 'sort'];
    $rows = $wpdb->get_results("SELECT name, slug, sort FROM " . kc_table('categories') . " ORDER BY sort, name", ARRAY_N);
    kc_do_csv_export('kc-categories.csv', $headers, $rows);
}
function kc_action_import_categories_csv() {
    kc_handle_csv_import(function($data) {
        global $wpdb;
        $slug = sanitize_key($data['slug']);
        if (empty($slug) || empty($data['name'])) return false;

        $cat_data = [
            'name' => sanitize_text_field($data['name']),
            'sort' => intval($data['sort'] ?? 0),
        ];

        $existing_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM " . kc_table('categories') . " WHERE slug = %s", $slug));
        if ($existing_id) {
            $wpdb->update(kc_table('categories'), $cat_data, ['id' => $existing_id]);
        } else {
            $wpdb->insert(kc_table('categories'), array_merge(['slug' => $slug], $cat_data));
        }
        return true;
    });
}

// --- ЮНІТИ (ОДИНИЦІ ВИМІРЮВАННЯ) ---
function kc_action_export_units_csv() {
    global $wpdb;
    $headers = ['slug', 'label', 'type', 'to_base', 'active'];
    $rows = $wpdb->get_results("SELECT slug, label, type, to_base, active FROM " . kc_table('units') . " ORDER BY type, label", ARRAY_N);
    kc_do_csv_export('kc-units.csv', $headers, $rows);
}
function kc_action_import_units_csv() {
    kc_handle_csv_import(function($data) {
        global $wpdb;
        $slug = sanitize_key($data['slug']);
        if (empty($slug) || empty($data['label'])) return false;

        $unit_data = [
            'label' => sanitize_text_field($data['label']),
            'type' => in_array($data['type'], ['mass', 'volume', 'piece'], true) ? $data['type'] : 'volume',
            'to_base' => floatval($data['to_base']),
            'active' => intval($data['active'] ?? 1),
        ];

        $existing_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM " . kc_table('units') . " WHERE slug = %s", $slug));
        if ($existing_id) {
            $wpdb->update(kc_table('units'), $unit_data, ['id' => $existing_id]);
        } else {
            $wpdb->insert(kc_table('units'), array_merge(['slug' => $slug], $unit_data));
        }
        return true;
    });
}

// --- МАПІНГИ ---
function kc_action_export_mappings_csv() {
    global $wpdb;
    $headers = ['ingredient_slug', 'unit_slug', 'grams_per_unit', 'note'];
    $rows = $wpdb->get_results("
        SELECT i.slug as ingredient_slug, u.slug as unit_slug, m.grams_per_unit, m.note
        FROM " . kc_table('mappings') . " m
        JOIN " . kc_table('ingredients') . " i ON m.ingredient_id = i.id
        JOIN " . kc_table('units') . " u ON m.unit_id = u.id
        ORDER BY i.slug, u.slug
    ", ARRAY_N);
    kc_do_csv_export('kc-mappings.csv', $headers, $rows);
}
function kc_action_import_mappings_csv() {
    kc_handle_csv_import(function($data) {
        global $wpdb;
        $ing_slug = sanitize_key($data['ingredient_slug']);
        $unit_slug = sanitize_key($data['unit_slug']);
        if (empty($ing_slug) || empty($unit_slug)) return false;

        $ingredient_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM " . kc_table('ingredients') . " WHERE slug = %s", $ing_slug));
        $unit_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM " . kc_table('units') . " WHERE slug = %s", $unit_slug));

        if (!$ingredient_id || !$unit_id) return false;

        $map_data = [
            'grams_per_unit' => floatval($data['grams_per_unit']),
            'note' => sanitize_text_field($data['note'] ?? ''),
        ];

        $existing_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . kc_table('mappings') . " WHERE ingredient_id = %d AND unit_id = %d",
            $ingredient_id, $unit_id
        ));

        if ($existing_id) {
            $wpdb->update(kc_table('mappings'), $map_data, ['id' => $existing_id]);
        } else {
            $wpdb->insert(kc_table('mappings'), array_merge(['ingredient_id' => $ingredient_id, 'unit_id' => $unit_id], $map_data));
        }
        return true;
    });
}

// Сторінка налаштувань
function kc_admin_page_settings() {
    if (!current_user_can('manage_options')) {
        wp_die('Forbidden');
    }
    ?>
    <div class="wrap">
        <h1><?php _e('Kitchen Calculator Settings', 'kitchen-calculator'); ?></h1>
        <form method="post" action="options.php">
            <?php
                settings_fields('kc_settings_group');
                $options = get_option('kc_options', ['recipe_count' => 3]);
                $recipe_count = $options['recipe_count'];
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php _e('Number of recipes to show', 'kitchen-calculator'); ?></th>
                    <td>
                        <input type="number" name="kc_options[recipe_count]" value="<?php echo esc_attr($recipe_count); ?>" min="0" step="1" />
                        <p class="description"><?php _e('How many recipe links should appear below the calculator for the selected ingredient.', 'kitchen-calculator'); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}