<?php
/**
 * Название:      prof-payment-title-order (на сайте — «Оплата — выберите удобный способ»)
 * Что делает:    1) На оформлении перед способами оплаты выводит заголовок
 *                   «Оплата — выберите удобный способ» с линией сверху.
 *                2) Порядок способов оплаты: при получении (cod), картой на сайте (begateway),
 *                   ЕРИП (bacs); остальные (если появятся) — после них.
 * Где активен:   оформление заказа; woocommerce_review_order_before_payment (5),
 *                woocommerce_available_payment_gateways (10).
 * Зависимости:   WooCommerce, bePaid (begateway).
 * Источник:      profcosmetica/snippets/prof-payment-title-order.php — перенесён без изменений кода, кроме отмеченного.
 * Обновлён:      2026-11-25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Заголовок перед способами оплаты + изменение порядка способов оплаты
 */
add_action('woocommerce_review_order_before_payment', function () {
    echo '<p class="checkout-payment-title" style="
        font-weight: 600;
        margin-bottom: 20px;
        padding-top: 20px;
        border-top: 1px solid var(--theme-border-color);
    ">Оплата — выберите удобный способ</p>';
}, 5);

/**
 * Меняем порядок способов оплаты:
 * 1. Оплата при получении (cod)
 * 2. Оплата картой на сайте (begateway)
 * 3. Оплата по ЕРИП (bacs)
 */
add_filter('woocommerce_available_payment_gateways', function ($gateways) {

    $order = [
        'cod',
        'begateway',
        'bacs',
    ];

    $sorted_gateways = [];

    foreach ($order as $gateway_id) {
        if (isset($gateways[$gateway_id])) {
            $sorted_gateways[$gateway_id] = $gateways[$gateway_id];
        }
    }

    // На случай, если появятся другие способы оплаты
    foreach ($gateways as $gateway_id => $gateway) {
        if (!isset($sorted_gateways[$gateway_id])) {
            $sorted_gateways[$gateway_id] = $gateway;
        }
    }

    return $sorted_gateways;
});
