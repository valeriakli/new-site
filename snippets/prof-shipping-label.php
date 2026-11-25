<?php
/**
 * Название:      prof-shipping-label — понятные подписи в корзине и на оформлении заказа
 * Что делает:    1) «Доставка» → «Способ получения заказа:» (заголовок блока способов доставки).
 *                2) «Подытог» → «Стоимость товаров» в строке итогов (под списком товаров, над купоном);
 *                   в заголовке колонки с суммой по каждому товару «Подытог» → «Сумма».
 *                Сами способы доставки и их порядок не трогает
 *                (порядок задаётся в админке: WooCommerce → Настройки → Доставка → зона).
 * Где активен:   фронтенд (и AJAX-обновления корзины/оформления); фильтры woocommerce_shipping_package_name (20),
 *                gettext (20); флаг строки итогов ставят woocommerce_before_cart_totals /
 *                woocommerce_review_order_after_cart_contents, снимают woocommerce_after_cart_totals /
 *                woocommerce_review_order_after_order_total.
 * Зависимости:   WooCommerce.
 * Источник:      profcosmetica/snippets/prof-shipping-label.php — перенесён без изменений кода, кроме отмеченного.
 * Обновлён:      2026-11-25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 1. Заголовок блока доставки.
add_filter( 'woocommerce_shipping_package_name', function ( $name, $i, $package ) {
	// Если корзина делится на несколько посылок, WooCommerce называет их «Доставка 2» и т.д. —
	// такие названия не трогаем, меняем только основное.
	if ( 0 === (int) $i ) {
		return 'Способ получения заказа:';
	}
	return $name;
}, 20, 3 );

// 2. «Подытог». В шаблонах WooCommerce одна и та же строка «Subtotal» стоит и в итогах,
// и в заголовке колонки товаров, поэтому отмечаем, когда выводится блок итогов.
$GLOBALS['prof_in_totals'] = false;

$prof_totals_on  = function () { $GLOBALS['prof_in_totals'] = true; };
$prof_totals_off = function () { $GLOBALS['prof_in_totals'] = false; };

add_action( 'woocommerce_before_cart_totals', $prof_totals_on );                 // корзина: блок «Сумма заказа»
add_action( 'woocommerce_after_cart_totals', $prof_totals_off );
add_action( 'woocommerce_review_order_after_cart_contents', $prof_totals_on );   // оформление: после списка товаров
add_action( 'woocommerce_review_order_after_order_total', $prof_totals_off );

add_filter( 'gettext', function ( $translation, $text, $domain ) {
	if ( 'woocommerce' !== $domain || 'Subtotal' !== $text ) {
		return $translation;
	}
	if ( is_admin() && ! wp_doing_ajax() ) {
		return $translation; // админку не трогаем
	}
	return ! empty( $GLOBALS['prof_in_totals'] ) ? 'Стоимость товаров' : 'Сумма';
}, 20, 3 );
