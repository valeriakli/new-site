<?php
/**
 * Название:      Метод доставки в заказе
 * Что делает:    В админке на странице заказа под адресом доставки выводит строку
 *                «Способ доставки: <название>», чтобы способ было видно сразу.
 * Где активен:   только админка, страница редактирования заказа;
 *                woocommerce_admin_order_data_after_shipping_address (10).
 * Зависимости:   WooCommerce.
 * Источник:      profcosmetica/snippets/prof-admin-order-shipping.php — перенесён без изменений кода, кроме отмеченного.
 * Обновлён:      2026-11-25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action('woocommerce_admin_order_data_after_shipping_address', function($order){
    if(!$order) return;

    $shipping_methods = $order->get_shipping_methods();

    if(!empty($shipping_methods)) {
        echo '<p class="form-field form-field-wide">';
        echo '<strong>Способ доставки:</strong> ';
        foreach($shipping_methods as $method){
            echo esc_html($method->get_name());
        }
        echo '</p>';
    }
});
