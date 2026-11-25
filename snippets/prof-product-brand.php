<?php
/**
 * Название:      prof-product-brand (на сайте — «Бренд в карточке»)
 * Что делает:    На странице товара в блоке с ценой и кнопкой выводит строку
 *                «Бренд: <ссылка на бренд>, <страна>» — первый бренд PWB товара и страну
 *                из поля ACF «strana-proizvoditel» этого бренда (если заполнена).
 * Где активен:   фронтенд, страница товара; woocommerce_single_product_summary (25).
 * Зависимости:   Perfect Brands for WooCommerce (таксономия pwb-brand), ACF (get_field,
 *                поле strana-proizvoditel у бренда). Без ACF страница товара упадёт с ошибкой.
 * Источник:      profcosmetica/snippets/prof-product-brand.php — код без изменений; убраны упоминания регионов в шапке (префиксы /ru/, /ge/ ставил prof-multiregion).
 * Обновлён:      2026-11-25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action('woocommerce_single_product_summary', function () {

    $brands = wp_get_post_terms(get_the_ID(), 'pwb-brand');

    if (empty($brands) || is_wp_error($brands)) {
        return;
    }

    $brand = $brands[0];
    $country = get_field('strana-proizvoditel', 'pwb-brand_' . $brand->term_id);

    echo '<div class="product-brand-country">';
    echo '<strong>Бренд:</strong> ';

    echo '<a href="' . esc_url(get_term_link($brand)) . '">';
    echo esc_html($brand->name);
    echo '</a>';

    if ($country) {
        echo ', ' . esc_html($country);
    }

    echo '</div>';

}, 25);
