<?php
defined('ABSPATH') || exit;

function kc_cache_get($key) { if (function_exists('get_transient')) return get_transient($key); return false; }
function kc_cache_set($key, $value, $ttl=300) { if (function_exists('set_transient')) set_transient($key, $value, $ttl); }
function kc_cache_del($key) { if (function_exists('delete_transient')) delete_transient($key); }

// ▼▼▼ ЗМІНА 1: Додано допоміжну функцію для перекладу ▼▼▼
/**
 * Допоміжна функція для перекладу результатів з бази даних.
 */
function kc_translate_results($results, $field_to_translate) {
    if (function_exists('pll__') && !empty($results)) {
        foreach ($results as $result) {
            if (isset($result->{$field_to_translate})) {
                $result->{$field_to_translate} = pll__($result->{$field_to_translate});
            }
        }
    }
    return $results;
}


function kc_register_rest_routes() {

    // ▼▼▼ ЗМІНА 2: Модифіковано ендпоінт /categories ▼▼▼
    register_rest_route('kc/v1', '/categories', [
        'methods' => 'GET',
        'callback' => function() {
            global $wpdb; $cache_key='kc_rest_categories_v1';
            $cached = kc_cache_get($cache_key);
            if ($cached === false) {
                 $cached = $wpdb->get_results("SELECT slug,name,sort FROM ".kc_table('categories')." ORDER BY sort,name");
                 kc_cache_set($cache_key, $cached, 300);
            }
            return kc_translate_results($cached, 'name');
        },
        'permission_callback' => '__return_true'
    ]);

    // ▼▼▼ ЗМІНА 3: Модифіковано ендпоінт /ingredients ▼▼▼
    register_rest_route('kc/v1', '/ingredients', [
        'methods'  => 'GET',
        'callback' => function( WP_REST_Request $req ) {
            global $wpdb;
            $cat = sanitize_key($req->get_param('category'));
            if ($cat) {
                $results = $wpdb->get_results($wpdb->prepare(
                    "SELECT slug,name FROM ".kc_table('ingredients')."
                     WHERE category_id = (SELECT id FROM ".kc_table('categories')." WHERE slug=%s)
                     ORDER BY name", $cat
                ));
            } else {
                $results = $wpdb->get_results("SELECT slug,name FROM ".kc_table('ingredients')." ORDER BY name");
            }
            return kc_translate_results($results, 'name');
        },
        'permission_callback' => '__return_true'
    ]);

    // ▼▼▼ ЗМІНА 4: Модифіковано ендпоінт /units ▼▼▼
    register_rest_route('kc/v1', '/units', [
        'methods'  => 'GET',
        'callback' => function() {
            global $wpdb;
            $results = $wpdb->get_results("SELECT slug,label FROM ".kc_table('units')." WHERE active=1 ORDER BY label");
            return kc_translate_results($results, 'label');
        },
        'permission_callback' => '__return_true'
    ]);

    register_rest_route('kc/v1', '/convert', [
        'methods'  => 'POST',
        'callback' => function (WP_REST_Request $req) {
            $ing = sanitize_key($req['ingredient']);
            $from= sanitize_key($req['from']);
            $to  = sanitize_key($req['to']);
            $amt = floatval($req['amount']);

            $res = kc_convert($ing, $from, $to, $amt);
            if (is_wp_error($res)) {
                return new WP_REST_Response(['error'=>$res->get_error_message()], 400);
            }
            return $res;
        },
        'permission_callback' => '__return_true',
        'args' => [
            'ingredient'=>['required'=>true],
            'from'=>['required'=>true],
            'to'=>['required'=>true],
            'amount'=>['required'=>true,'type'=>'number','minimum'=>0]
        ]
    ]);

    // Ендпоінт для рецептів залишається без змін
    register_rest_route('kc/v1', '/recipes', [
        'methods'  => 'GET',
        'callback' => function (WP_REST_Request $req) {
            $ingredient_slug = sanitize_key($req->get_param('ingredient'));
            if (empty($ingredient_slug)) {
                return [];
            }
            $options = get_option('kc_options', ['recipe_count' => 3]);
            $recipe_count = (int) $options['recipe_count'];
            if ($recipe_count === 0) {
                return [];
            }
            $args = [
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'posts_per_page' => $recipe_count,
                'meta_query'     => [
                    [
                        'key'   => '_kc_ingredient_slug',
                        'value' => $ingredient_slug,
                    ]
                ],
                'orderby' => [
                    'meta_value_num' => 'ASC',
                    'date'           => 'DESC'
                ],
                'meta_key' => '_kc_ingredient_priority'
            ];
            $query = new WP_Query($args);
            $recipes = [];
            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $recipes[] = [
                        'title' => get_the_title(),
                        'link'  => get_permalink(),
                    ];
                }
            }
            wp_reset_postdata();
            return $recipes;
        },
        'permission_callback' => '__return_true',
        'args' => [
            'ingredient' => ['required' => true, 'type' => 'string'],
        ]
    ]);
}
add_action('rest_api_init', 'kc_register_rest_routes');


// CRUD-операції для категорій залишаються без змін
add_action('rest_api_init', function(){
    register_rest_route('kc/v1', '/categories', [
        'methods'  => 'POST',
        'callback' => function( WP_REST_Request $req ) {
            if (!current_user_can('manage_kitchen_calculator')) return new WP_Error('forbidden','Forbidden', ['status'=>403]);
            global $wpdb;
            $slug = sanitize_key($req['slug']);
            $name = sanitize_text_field($req['name']);
            $sort = intval($req['sort'] ?? 0);
            if (!$slug || !$name) return new WP_Error('invalid','slug & name required', ['status'=>400]);
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM ".kc_table('categories')." WHERE slug=%s", $slug));
            if ($exists) return new WP_Error('exists','Category exists', ['status'=>409]);
            $wpdb->insert(kc_table('categories'), ['slug'=>$slug,'name'=>$name,'sort'=>$sort]);
            kc_cache_del('kc_rest_categories_v1'); return ['id'=>$wpdb->insert_id];
        },
        'permission_callback' => '__return_true'
    ]);
    register_rest_route('kc/v1', '/categories/(?P<id>\d+)', [
        'methods'  => 'PUT',
        'callback' => function( WP_REST_Request $req ) {
            if (!current_user_can('manage_kitchen_calculator')) return new WP_Error('forbidden','Forbidden', ['status'=>403]);
            global $wpdb; $id = intval($req['id']);
            $data = [];
            if ($req['slug']) $data['slug'] = sanitize_key($req['slug']);
            if ($req['name']) $data['name'] = sanitize_text_field($req['name']);
            if (isset($req['sort'])) $data['sort'] = intval($req['sort']);
            if (!$data) return new WP_Error('invalid','nothing to update', ['status'=>400]);
            $wpdb->update(kc_table('categories'), $data, ['id'=>$id]);
            kc_cache_del('kc_rest_categories_v1'); kc_cache_del('kc_rest_categories_v1'); return ['ok'=>true];
        },
        'permission_callback' => '__return_true'
    ]);
    register_rest_route('kc/v1', '/categories/(?P<id>\d+)', [
        'methods'  => 'DELETE',
        'callback' => function( WP_REST_Request $req ) {
            if (!current_user_can('manage_kitchen_calculator')) return new WP_Error('forbidden','Forbidden', ['status'=>403]);
            global $wpdb; $id = intval($req['id']);
            $cnt = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM ".kc_table('ingredients')." WHERE category_id=%d", $id));
            if ($cnt>0) return new WP_Error('conflict','Category has ingredients', ['status'=>409]);
            $wpdb->delete(kc_table('categories'), ['id'=>$id]);
            kc_cache_del('kc_rest_categories_v1'); return ['ok'=>true];
        },
        'permission_callback' => '__return_true'
    ]);
});