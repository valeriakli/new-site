<?php
/**
 * Название:      prof-menu-brands (на сайте — «Вывод брендов в меню»)
 * Что делает:    В главном меню («Main menu») к пункту «Бренды» (ссылка содержит /brands/ или
 *                название «Бренды») добавляет подменю со всеми брендами PWB Brands по алфавиту,
 *                включая бренды без товаров. Пункты создаются на лету, в настройках меню их нет.
 * Где активен:   фронтенд, вывод меню; wp_nav_menu_objects (10).
 * Зависимости:   Perfect Brands for WooCommerce (таксономия pwb-brand), тема Blocksy (класс
 *                menu-item-has-children для подменю).
 * Источник:      profcosmetica/snippets/prof-menu-brands.php — код без изменений; убраны упоминания регионов в шапке (префиксы /ru/, /ge/ ставил prof-multiregion).
 * Обновлён:      2026-11-25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter('wp_nav_menu_objects', 'add_pwb_brands_submenu_blocksy', 10, 2);

function add_pwb_brands_submenu_blocksy($items, $args) {
    $menu_name = 'Main menu'; // Имя вашего меню

    // Получаем объект меню
    $menu_obj = wp_get_nav_menu_object($args->menu);
    if (!$menu_obj) return $items;
    if ($menu_obj->name !== $menu_name && $menu_obj->term_id != $args->menu) return $items;

    // Найдем максимальный ID
    $max_id = 0;
    foreach ($items as $item) {
        if ($item->ID > $max_id) $max_id = $item->ID;
    }

    $new_items = $items;

    foreach ($items as $item) {
        // Ищем пункт меню "Бренды"
        if (strpos($item->url, '/brands/') !== false || $item->title === 'Бренды') {
            $parent_id = $item->ID;

            // Получаем бренды PWB Brands
            $brands = get_terms([
                'taxonomy' => 'pwb-brand',
                'hide_empty' => false,
            ]);

            if (!empty($brands) && !is_wp_error($brands)) {
                // Сортировка по алфавиту без учета регистра
                usort($brands, function($a, $b) {
                    return strcasecmp($a->name, $b->name);
                });

                foreach ($brands as $brand) {
                    $max_id++;
                    $new_item = (object) [
                        'ID' => $max_id,
                        'db_id' => $max_id,
                        'menu_item_parent' => $parent_id,
                        'title' => $brand->name,
                        'url' => get_term_link($brand),
                        'menu_order' => $max_id,
                        'type' => 'custom',
                        'object' => 'custom',
                        'object_id' => $brand->term_id,
                        'classes' => [
                            'menu-item',
                            'menu-item-type-custom',
                            'menu-item-object-custom',
                        ],
                        'target' => '',
                        'attr_title' => '',
                        'description' => '',
                        'xfn' => '',
                    ];

                    $new_items[] = $new_item;
                }

                // Делаем сам пункт "Бренды" пунктом с подменю (как "О магазине")
                $item->classes[] = 'menu-item-has-children';
            }

            break; // Добавляем под Brands один раз
        }
    }

    return $new_items;
}
