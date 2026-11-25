<?php
/**
 * Название:      prof-catalog-per-page (на сайте — «кол-во товаров в каталоге»)
 * Что делает:    Сколько товаров на одной странице каталога: на телефоне 16 (ровно в 2 колонки),
 *                на компьютере 15. Действует в каталоге /products/ и на страницах таксономий
 *                товаров (категории, бренды, метки, атрибуты).
 * Где активен:   фронтенд, основной запрос; pre_get_posts (10).
 * Зависимости:   WooCommerce. Устройство — wp_is_mobile() (по User-Agent). Кеш страниц WP.com
 *                хранит отдельные копии для телефонов и компьютеров (проверено 2026-11-15:
 *                из кеша телефону 16, компьютеру 15), поэтому кеш это не ломает.
 * Источник:      profcosmetica/snippets/prof-catalog-per-page.php — перенесён без изменений кода, кроме отмеченного.
 * Обновлён:      2026-11-25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'pre_get_posts', function( $query ) {
    // Проверяем, что это не админка и основной запрос
    if ( is_admin() || ! $query->is_main_query() ) return;

    // Получаем объект текущего запроса
    $queried_object = get_queried_object();

    // Проверяем: архив товаров или таксономия для товаров
    if ( $query->is_post_type_archive( 'product' ) || ( isset( $queried_object->taxonomy ) && is_tax() ) ) {

        // Проверяем, что таксономия относится к продуктам
        if ( isset( $queried_object->taxonomy ) ) {
            $taxonomy_obj = get_taxonomy( $queried_object->taxonomy );
            if ( ! isset( $taxonomy_obj->object_type ) || ! in_array( 'product', $taxonomy_obj->object_type ) ) {
                return; // Если таксономия не для продуктов — выходим
            }
        }

        // Устанавливаем количество товаров в зависимости от устройства
        if ( wp_is_mobile() ) {
            $query->set( 'posts_per_page', 16 );
        } else {
            $query->set( 'posts_per_page', 15 );
        }
    }
});
