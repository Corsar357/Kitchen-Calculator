<?php
defined('ABSPATH') || exit;

function kc_table($name) { global $wpdb; return $wpdb->prefix . "kc_$name"; }

function kc_db_maybe_install() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $sql = [];
    $sql[] = "CREATE TABLE " . kc_table('units') . " (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        slug VARCHAR(64) NOT NULL UNIQUE,
        label VARCHAR(191) NOT NULL,
        type ENUM('mass','volume','piece') NOT NULL,
        to_base DECIMAL(12,6) NOT NULL DEFAULT 0,
        synonyms TEXT NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY(id)
    ) $charset_collate;";

    $sql[] = "CREATE TABLE " . kc_table('categories') . " (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        slug VARCHAR(64) NOT NULL UNIQUE,
        name VARCHAR(191) NOT NULL,
        sort INT NOT NULL DEFAULT 0,
        PRIMARY KEY(id)
    ) $charset_collate;";

    $sql[] = "CREATE TABLE " . kc_table('ingredients') . " (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(191) NOT NULL,
        slug VARCHAR(64) NOT NULL UNIQUE,
        density_g_per_ml DECIMAL(12,6) NULL,
        category_id BIGINT UNSIGNED NULL,
        meta TEXT NULL,
        PRIMARY KEY(id),
        KEY cat_idx (category_id)
    ) $charset_collate;";

    $sql[] = "CREATE TABLE " . kc_table('mappings') . " (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        ingredient_id BIGINT UNSIGNED NOT NULL,
        unit_id BIGINT UNSIGNED NOT NULL,
        grams_per_unit DECIMAL(12,6) NOT NULL,
        note VARCHAR(191) NULL,
        PRIMARY KEY(id),
        UNIQUE KEY uniq_ing_unit (ingredient_id, unit_id),
        KEY unit_fk (unit_id),
        KEY ing_fk (ingredient_id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    foreach ($sql as $s) { dbDelta($s); }
}


function kc_run_migration_v3() {
    global $wpdb;
    // Rename dairy -> liquids
    $dairy = (int)$wpdb->get_var("SELECT id FROM ".kc_table('categories')." WHERE slug='dairy'");
    if ($dairy) {
        $wpdb->update(kc_table('categories'), ['slug'=>'liquids','name'=>'Рідини','sort'=>50], ['id'=>$dairy]);
    }
    // Merge flours -> grains
    $flours = (int)$wpdb->get_var("SELECT id FROM ".kc_table('categories')." WHERE slug='flours'");
    $grains = (int)$wpdb->get_var("SELECT id FROM ".kc_table('categories')." WHERE slug='grains'");
    if ($flours && $grains) {
        $wpdb->query($wpdb->prepare("UPDATE ".kc_table('ingredients')." SET category_id=%d WHERE category_id=%d", $grains, $flours));
        $wpdb->delete(kc_table('categories'), ['id'=>$flours]);
    }
    // Ensure desired set exists/labels updated
    $desired = [
        ['slug'=>'grains','name'=>'Крупи та борошно','sort'=>10],
        ['slug'=>'sugars','name'=>'Цукор','sort'=>20],
        ['slug'=>'spices','name'=>'Сіль і спеції','sort'=>30],
        ['slug'=>'oils','name'=>'Олії та жири','sort'=>40],
        ['slug'=>'liquids','name'=>'Рідини','sort'=>50],
        ['slug'=>'legumes','name'=>'Бобові та насіння','sort'=>60],
        ['slug'=>'nuts','name'=>'Горіхи та сухофрукти','sort'=>70],
        ['slug'=>'produce','name'=>'Овочі та фрукти','sort'=>80],
        ['slug'=>'baking','name'=>'Випічка та добавки','sort'=>90],
        ['slug'=>'eggs','name'=>'Яйця','sort'=>95],
        ['slug'=>'other','name'=>'Інше','sort'=>100],
    ];
    foreach ($desired as $c) {
        $id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM ".kc_table('categories')." WHERE slug=%s", $c['slug']));
        if ($id) {
            $wpdb->update(kc_table('categories'), ['name'=>$c['name'],'sort'=>$c['sort']], ['id'=>$id]);
        } else {
            $wpdb->insert(kc_table('categories'), $c);
        }
    }
}

function kc_db_maybe_upgrade() {
    $stored = get_option('kc_db_version');
    if ((int)$stored < 3 && (int)KC_DB_VERSION >= 3) { kc_run_migration_v3(); }
        if ($stored !== KC_DB_VERSION) {
        kc_db_maybe_install();
        update_option('kc_db_version', KC_DB_VERSION);
    }
}

function kc_seed_initial_data() {
    global $wpdb;

    if (!$wpdb->get_var("SELECT COUNT(1) FROM " . kc_table('units'))) {
        $units = [
            ['slug'=>'ml', 'label'=>'mL', 'type'=>'volume', 'to_base'=>1],
            ['slug'=>'liter', 'label'=>'Liter', 'type'=>'volume', 'to_base'=>1000],
            ['slug'=>'tsp', 'label'=>'tsp (4.92892 mL)', 'type'=>'volume', 'to_base'=>4.92892],
            ['slug'=>'tbsp', 'label'=>'TBSP (14.7868 mL)', 'type'=>'volume', 'to_base'=>14.7868],
            ['slug'=>'cup', 'label'=>'Cup (US 236.588 mL)', 'type'=>'volume', 'to_base'=>236.588],
            ['slug'=>'pint', 'label'=>'Pint', 'type'=>'volume', 'to_base'=>473.176],
            ['slug'=>'quart', 'label'=>'Quart', 'type'=>'volume', 'to_base'=>946.353],
            ['slug'=>'gallon', 'label'=>'Gallon', 'type'=>'volume', 'to_base'=>3785.41],
            ['slug'=>'floz', 'label'=>'Fluid Ounce', 'type'=>'volume', 'to_base'=>29.5735],
            ['slug'=>'glass200', 'label'=>'Glass 200 mL', 'type'=>'volume', 'to_base'=>200],
            ['slug'=>'glass250', 'label'=>'Glass 250 mL', 'type'=>'volume', 'to_base'=>250],
            ['slug'=>'g', 'label'=>'Gram', 'type'=>'mass', 'to_base'=>1],
            ['slug'=>'kg', 'label'=>'Kilogram', 'type'=>'mass', 'to_base'=>1000],
            ['slug'=>'oz', 'label'=>'Ounce', 'type'=>'mass', 'to_base'=>28.3495],
            ['slug'=>'lb', 'label'=>'Pound', 'type'=>'mass', 'to_base'=>453.592],
        ];
        foreach ($units as $u) { $wpdb->insert(kc_table('units'), $u); }
    }

    if (!$wpdb->get_var("SELECT COUNT(1) FROM " . kc_table('categories'))) {
        $cats = [
            ['slug'=>'grains','name'=>'Крупи та борошно','sort'=>10],
            ['slug'=>'sugars','name'=>'Цукор','sort'=>20],
            ['slug'=>'spices','name'=>'Сіль і спеції','sort'=>30],
            ['slug'=>'oils','name'=>'Олії та жири','sort'=>40],
            ['slug'=>'liquids','name'=>'Рідини','sort'=>50],
            ['slug'=>'legumes','name'=>'Бобові та насіння','sort'=>60],
            ['slug'=>'nuts','name'=>'Горіхи та сухофрукти','sort'=>70],
            ['slug'=>'produce','name'=>'Овочі та фрукти','sort'=>80],
            ['slug'=>'baking','name'=>'Випічка та добавки','sort'=>90],
            ['slug'=>'eggs','name'=>'Яйця','sort'=>95],
            ['slug'=>'other','name'=>'Інше','sort'=>100],
        ];
        foreach ($cats as $c) { $wpdb->insert(kc_table('categories'), $c); }
    }

    if (!$wpdb->get_var("SELECT COUNT(1) FROM " . kc_table('ingredients'))) {
        $wpdb->insert(kc_table('ingredients'), [
            'name'=>'Semolina', 'slug'=>'semolina', 'density_g_per_ml'=>0.8,
            'category_id' => (int)$wpdb->get_var("SELECT id FROM ".kc_table('categories')." WHERE slug='grains'")
        ]);
        $wpdb->insert(kc_table('ingredients'), [
            'name'=>'Water', 'slug'=>'water', 'density_g_per_ml'=>1.0,
            'category_id' => (int)$wpdb->get_var("SELECT id FROM ".kc_table('categories')." WHERE slug='dairy'")
        ]);
    }

    $semolina_id = (int) $wpdb->get_var("SELECT id FROM ".kc_table('ingredients')." WHERE slug='semolina'");
    $water_id    = (int) $wpdb->get_var("SELECT id FROM ".kc_table('ingredients')." WHERE slug='water'");
    $unit = fn($slug) => (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM ".kc_table('units')." WHERE slug=%s", $slug
    ));

    if ($semolina_id && !$wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM ".kc_table('mappings')." WHERE ingredient_id=%d",$semolina_id))) {
        $mappings = [
            ['ingredient_id'=>$semolina_id, 'unit_id'=>$unit('glass200'), 'grams_per_unit'=>160.0, 'note'=>'повна склянка 200 мл'],
            ['ingredient_id'=>$semolina_id, 'unit_id'=>$unit('glass250'), 'grams_per_unit'=>200.0, 'note'=>'повна склянка 250 мл'],
            ['ingredient_id'=>$semolina_id, 'unit_id'=>$unit('tbsp'),    'grams_per_unit'=>5.32,  'note'=>'столова ложка без гірки'],
        ];
        foreach ($mappings as $m) { $wpdb->insert(kc_table('mappings'), $m); }
    }
    if ($water_id && !$wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM ".kc_table('mappings')." WHERE ingredient_id=%d",$water_id))) {
        $mappings = [
            ['ingredient_id'=>$water_id, 'unit_id'=>$unit('glass200'), 'grams_per_unit'=>200.0],
            ['ingredient_id'=>$water_id, 'unit_id'=>$unit('glass250'), 'grams_per_unit'=>250.0],
        ];
        foreach ($mappings as $m) { $wpdb->insert(kc_table('mappings'), $m); }
    }
}
