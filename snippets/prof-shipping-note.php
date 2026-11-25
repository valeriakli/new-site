<?php
/**
 * Название:      prof-shipping-note (на сайте — «Почта (по тарифам почты) кавычки»)
 * Что делает:    В корзине и на оформлении у способов доставки Самовывоз (local_pickup:16),
 *                Европочта (flat_rate:18) и Белпочта (flat_rate:19) текст в скобках —
 *                «(по тарифам почты)», «(Минск, Братская 11…)» — выводится серым и мелким
 *                (span.shipping-note). Делается на сервере, в названии способа, без скрипта:
 *                нет мелькания и лишней работы на остальных страницах.
 *                Заменяет HTML-сниппет с JS и MutationObserver (snippets/_off/prof-shipping-note.html).
 * Где активен:   фронтенд; фильтр woocommerce_cart_shipping_method_full_label (20) —
 *                подпись способа в корзине и на оформлении, в т.ч. при AJAX-пересчёте;
 *                стиль — wp_head только в корзине и на оформлении.
 * Зависимости:   WooCommerce. ID способов (16, 18, 19) — из настроек зоны доставки;
 *                при пересоздании способа поправить список в prof_shipping_note_methods().
 * Источник:      profcosmetica/snippets/prof-shipping-note.php — перенесён без изменений кода, кроме отмеченного.
 * Обновлён:      2026-11-25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Способы доставки, у которых текст в скобках делаем серым.
function prof_shipping_note_methods() {
	return array( 'local_pickup:16', 'flat_rate:18', 'flat_rate:19' );
}

add_filter( 'woocommerce_cart_shipping_method_full_label', function ( $label, $method ) {
	if ( ! is_object( $method ) || ! method_exists( $method, 'get_id' ) ) {
		return $label;
	}
	if ( ! in_array( $method->get_id(), prof_shipping_note_methods(), true ) ) {
		return $label;
	}
	if ( false !== strpos( $label, 'shipping-note' ) ) {
		return $label; // уже обработано
	}
	// Текст в скобках (без вложенных тегов) → <span class="shipping-note">(…)</span>
	$new = preg_replace( '/\(([^()<>]*)\)/u', '<span class="shipping-note">($1)</span>', $label );
	return null === $new ? $label : $new;
}, 20, 2 );

add_action( 'wp_head', function () {
	if ( ! function_exists( 'is_cart' ) || ! ( is_cart() || is_checkout() ) ) {
		return;
	}
	echo '<style id="prof-shipping-note">.shipping-note{color:#999;font-size:12px}</style>' . "\n";
} );
