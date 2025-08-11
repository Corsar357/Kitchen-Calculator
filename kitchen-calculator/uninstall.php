<?php
defined('WP_UNINSTALL_PLUGIN') || exit;
if (!get_option('kc_delete_data_on_uninstall')) return;
global $wpdb; $p=$wpdb->prefix;
$wpdb->query("DROP TABLE IF EXISTS {$p}kc_mappings, {$p}kc_ingredients, {$p}kc_units, {$p}kc_categories");
delete_option('kc_db_version');
