<?php
/**
 * Название:      prof-free-shipping-min (на сайте — «запрет на выбор бесплатной доставки если заказ < 130»)
 * Что делает:    При нажатии «Подтвердить заказ» проверяет: если выбран способ
 *                flexible_shipping_single:20 («бесплатно по Минску в пределах МКАД (при заказе от 130 рублей)»),
 *                а стоимость товаров меньше 130, заказ не оформляется и показывается ошибка
 *                «Добавьте продуктов ещё на N BYN — и доставка по Минску станет бесплатной!».
 *                Сам способ в списке не скрывается — его можно выбрать, ошибка будет только при отправке.
 *                Сумма — подытог корзины до купонов (get_subtotal).
 * Где активен:   оформление заказа, проверка при отправке; woocommerce_after_checkout_validation (10).
 * Зависимости:   WooCommerce, Flexible Shipping (ID способа 20 — из настроек зоны доставки;
 *                если способ пересоздать, ID изменится и проверка перестанет работать).
 *                Платная доставка по Минску до 130 — способ :21.
 * Источник:      profcosmetica/snippets/prof-free-shipping-min.php — перенесён без изменений кода, кроме отмеченного.
 * Обновлён:      2026-11-25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 1️⃣ Проверка при оформлении заказа (checkout), без жёсткого возврата в корзину
add_action( 'woocommerce_after_checkout_validation', 'validate_free_shipping_min_order', 10, 2 );
function validate_free_shipping_min_order( $data, $errors ) {
    $cart = WC()->cart;
    $chosen_methods = WC()->session->get( 'chosen_shipping_methods' );

    if ( empty( $chosen_methods ) ) return;

    foreach ( $chosen_methods as $method_id ) {
        if ( $method_id === 'flexible_shipping_single:20' ) {
            $cart_total = $cart->get_subtotal();
            $min_free_shipping = 130;

            if ( $cart_total < $min_free_shipping ) {
                $remaining = $min_free_shipping - $cart_total;
                $errors->add(
                    'free_shipping_min',
                    sprintf(
                        'Добавьте продуктов ещё на <strong>%.2f BYN</strong> — и доставка по Минску станет бесплатной!',
                        $remaining
                    )
                );
            }
        }
    }
}
