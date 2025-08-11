<?php
defined('ABSPATH') || exit;

function kc_get_unit_by_slug(string $slug) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM ".kc_table('units')." WHERE slug=%s", $slug));
}

function kc_get_mapping($ingredient_id, $unit_id) {
    global $wpdb;
    return $wpdb->get_var($wpdb->prepare(
        "SELECT grams_per_unit FROM ".kc_table('mappings')." WHERE ingredient_id=%d AND unit_id=%d",
        $ingredient_id, $unit_id
    ));
}

function kc_convert($ingredient_slug, $from_unit_slug, $to_unit_slug, $amount) {
    global $wpdb;

    $ingredient = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM ".kc_table('ingredients')." WHERE slug=%s", $ingredient_slug
    ));
    if (!$ingredient) return new WP_Error('kc_no_ing', 'Ingredient not found');

    $from = kc_get_unit_by_slug($from_unit_slug);
    $to   = kc_get_unit_by_slug($to_unit_slug);
    if (!$from || !$to) return new WP_Error('kc_no_unit', 'Unit not found');

    $warnings = [];

    // -> grams
    if ($from->type === 'mass') {
        $grams = (float)$amount * (float)$from->to_base;
    } else {
        $map_g = kc_get_mapping($ingredient->id, $from->id);
        if ($map_g !== null) {
            $grams = (float)$amount * (float)$map_g;
        } else {
            if ($from->type !== 'volume' || $ingredient->density_g_per_ml === null) {
                return new WP_Error('kc_need_mapping', 'No mapping or density for this conversion');
            }
            $grams = (float)$amount * (float)$from->to_base * (float)$ingredient->density_g_per_ml;
            $warnings[] = 'Used density instead of explicit mapping.';
        }
    }

    // grams ->
    if ($to->type === 'mass') {
        $result = $grams / (float)$to->to_base;
    } else {
        $map_g_to = kc_get_mapping($ingredient->id, $to->id);
        if ($map_g_to !== null) {
            $result = $grams / (float)$map_g_to;
        } else {
            if ($to->type !== 'volume' || $ingredient->density_g_per_ml === null) {
                return new WP_Error('kc_need_mapping_to', 'No mapping or density for target unit');
            }
            $ml = $grams / (float)$ingredient->density_g_per_ml;
            $result = $ml / (float)$to->to_base;
            $warnings[] = 'Used density for target unit.';
        }
    }

    return ['result' => $result, 'warnings' => $warnings];
}
