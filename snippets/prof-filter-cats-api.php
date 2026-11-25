<?php
/**
 * Название:      prof-filter-cats-api (на сайте — «категории в фильтре»)
 * Что делает:    Публичный адрес REST API /wp-json/myplugin/v1/product-cats: отдаёт дерево категорий
 *                товаров (id, название, slug, число товаров, подкатегории), включая пустые.
 *                По нему скрипт фильтра на страницах категорий строит список галочек категорий
 *                (контейнер woof_container_product_cat, ссылки вида /…/slug1-and-slug2/).
 * Где активен:   REST API (rest_api_init); сам сниппет на страницы ничего не выводит.
 * Зависимости:   WooCommerce (таксономия product_cat). Нужен скрипту фильтра на категориях
 *                (отдельный сниппет / код на сайте, в репозитории пока нет). Порядок категорий
 *                в самом фильтре WOOF задаёт prof-filter-cat-order.
 * Источник:      profcosmetica/snippets/prof-filter-cats-api.php — перенесён без изменений кода, кроме отмеченного.
 * Обновлён:      2026-11-25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action('rest_api_init', function () {
    register_rest_route('myplugin/v1', '/product-cats', [
        'methods' => 'GET',
        'callback' => 'myplugin_get_product_cats',
        'permission_callback' => '__return_true', // публичный
    ]);
});

// Callback для REST API
function myplugin_get_product_cats() {
    return get_category_hierarchy(); // вызываем рекурсивную функцию
}

// Рекурсивная функция для категорий и подкатегорий
function get_category_hierarchy($parent = 0) {
    $terms = get_terms([
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
        'parent'     => $parent,
		'children'=>0
    ]);

    if (is_wp_error($terms)) return [];

    $result = [];

    foreach ($terms as $term) {
        $children = get_category_hierarchy($term->term_id); // рекурсия
        $result[] = [
            'id'       => $term->term_id,
            'name'     => $term->name,
            'slug'     => $term->slug,
            'count'    => $term->count,
            'children' => $children,
        ];
    }

    return $result;
}
