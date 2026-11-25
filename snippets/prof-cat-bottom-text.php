<?php
/**
 * Название:      Текст внизу подкатегории
 * Что делает:    На странице категории товаров после списка товаров выводит текст из поля ACF
 *                «subcategory_bottom_text» этой категории, обёрнутый в <div class="term-description">.
 *                Если поле пустое или ACF выключен, ничего не выводится.
 * Где активен:   фронтенд, страницы категорий товаров; woocommerce_after_shop_loop (15).
 * Зависимости:   WooCommerce, ACF (get_field, поле subcategory_bottom_text у таксономии product_cat).
 * Источник:      profcosmetica/snippets/prof-cat-bottom-text.php — перенесён без изменений кода, кроме отмеченного.
 * Обновлён:      2026-11-25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Вывод кастомного текста подкатегории после списка товаров
add_action( 'woocommerce_after_shop_loop', function() {
    // Без ACF (плагин выключен) ничего не выводим, чтобы категории не падали с ошибкой
    if ( ! function_exists( 'get_field' ) ) {
        return;
    }

    if ( is_product_category() ) {
        $term_id    = get_queried_object_id();
        $custom_txt = get_field( 'subcategory_bottom_text', 'product_cat_' . $term_id );

        if ( $custom_txt ) {
            echo '<div class="term-description">';
            echo wp_kses_post( $custom_txt );
            echo '</div>';
        }
    }
}, 15 );
