<?php
/**
 * Название:      prof-order-shipping-line (на profcosmetica — «Почта письмо стоимость для Европочты/Белпочты»)
 * Что делает:    Меняет строку «Доставка» в итогах заказа (письма, страница «Спасибо за заказ»,
 *                заказ в «Моём аккаунте»):
 *                — Европочта / Белпочта: при сумме заказа от 250 BYN — «Бесплатно», иначе «по тарифам почты»;
 *                — остальные способы: стоимость доставки или «Бесплатно», если 0.
 * Где активен:   везде, где выводятся итоги заказа; woocommerce_get_order_item_totals (20).
 * Где проверено: ещё не проверен на сайте. Проверить: тестовый заказ почтой до и от 250 BYN — письмо.
 * Зависимости:   WooCommerce; названия способов доставки «Европочта» / «Белпочта» (поиск по названию).
 *                Порог 250 BYN — сверить с Олей (PROF_POST_FREE_FROM ниже).
 * Источник:      profcosmetica/snippets/prof-order-shipping-line.php (версия, ждавшая вставки) —
 *                убран комментарий про RU / GE, порог вынесен в константу.
 * Обновлён:      2026-11-25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// С какой суммы заказа доставка почтой бесплатна, BYN.
if ( ! defined( 'PROF_POST_FREE_FROM' ) ) {
	define( 'PROF_POST_FREE_FROM', 250 );
}

add_filter('woocommerce_get_order_item_totals', 'custom_final_shipping_line_display', 20, 3);

function custom_final_shipping_line_display($total_rows, $order, $tax_display) {
    // Проверяем наличие блока доставки в итоговой таблице
    if (!isset($total_rows['shipping'])) {
        return $total_rows;
    }

    $shipping_methods = $order->get_shipping_methods();
    if (empty($shipping_methods)) {
        return $total_rows;
    }

    foreach ($shipping_methods as $shipping_item) {
        $method_name   = $shipping_item->get_name();
        $method_id     = $shipping_item->get_method_id();
        $shipping_cost = (float) $shipping_item->get_total();
        $order_total   = (float) $order->get_total();

        // Нечувствительная к регистру проверка (Белпочта / Европочта)
        $is_belpochta  = (mb_stripos($method_name, 'белпочта') !== false || mb_stripos($method_id, 'belpochta') !== false);
        $is_evropochta = (mb_stripos($method_name, 'европочта') !== false || mb_stripos($method_id, 'evropochta') !== false);

        if ($is_belpochta || $is_evropochta) {
            if ($order_total >= PROF_POST_FREE_FROM) {
                $total_rows['shipping']['value'] = 'Бесплатно';
            } else {
                $total_rows['shipping']['value'] = 'по тарифам почты';
            }
        } else {
            // Для всех остальных способов
            if ($shipping_cost > 0) {
                $total_rows['shipping']['value'] = wc_price($shipping_cost, array('currency' => $order->get_currency()));
            } else {
                $total_rows['shipping']['value'] = 'Бесплатно';
            }
        }
    }

    return $total_rows;
}
