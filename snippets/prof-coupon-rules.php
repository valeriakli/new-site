<?php
/**
 * Название:      prof-coupon-rules (правила промокодов «2», «3», «5»)
 * Что делает:    Промокоды со скидкой в процентах действуют только на «подходящие» товары: без акции (цена
 *                распродажи), не из исключённых в купоне категорий (сейчас «Уход за волосами»), не исключённых
 *                брендов (стандартные поля WooCommerce и Perfect Brands) и не подарочный сертификат.
 *                Минимальная сумма купона (250 / 350 / 500 Br) считается только по подходящим товарам.
 *                Неподходящие товары можно оставить в корзине: купон применится, на них скидки просто не будет.
 *                Если подходящих товаров не хватает до минимума — понятная ошибка, сколько ещё добавить.
 *                Если подходящих нет совсем — понятная ошибка вместо стандартной WooCommerce.
 *                Заменяет два сниппета на сайте: «исключить категории/бренды для купонов» (запрещал купон
 *                целиком, если в корзине есть средство для волос) и безымянный «Купон не применяется к акционным
 *                товарам» (его фильтр товара отменял исключение категорий; минимум считал вместе с волосами).
 *                Их нужно выключить при включении этого.
 * Где активен:   корзина и оформление (и их AJAX): woocommerce_coupon_is_valid_for_product (9999),
 *                woocommerce_coupon_is_valid (9999), woocommerce_coupon_error (20). Кеш страниц не трогает.
 *                Купоны в ручных заказах в админке — по правилам WooCommerce (проверка минимума пропускается).
 * Зависимости:   WooCommerce; Perfect Brands (мета купона _pwb_coupon_exclude_brands, таксономия pwb-brand) —
 *                если нет, бренды просто не проверяются. Подарочные сертификаты (pc-gift-cards: купоны GC-…
 *                с метой _pcgc, тип fixed_cart) этими правилами не затрагиваются.
 * Источник:      profcosmetica/snippets/prof-coupon-rules.php — перенесён без изменений кода, кроме отмеченного.
 * Обновлён:      2026-11-25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** К каким купонам применяем правила: процентные и «на товар», кроме подарочных сертификатов. */
function prof_cr_applies( $coupon ) {
	return $coupon instanceof WC_Coupon
		&& $coupon->is_type( array( 'percent', 'fixed_product' ) )
		&& ! $coupon->get_meta( '_pcgc' );
}

/** ID товара (для вариации — родителя: категории и бренды висят на нём). */
function prof_cr_parent_id( $product ) {
	return $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
}

/** Товар «Подарочный сертификат» (слаг из pc-gift-cards). */
function prof_cr_is_certificate( $product ) {
	$slug = defined( 'PCGC_PRODUCT_SLUG' ) ? PCGC_PRODUCT_SLUG : 'gift-certificate';
	return get_post_field( 'post_name', prof_cr_parent_id( $product ) ) === $slug;
}

/** Исключённые бренды Perfect Brands из купона. */
function prof_cr_excluded_brands( $coupon ) {
	$ids = $coupon->get_meta( '_pwb_coupon_exclude_brands', true );
	if ( is_string( $ids ) ) {
		$ids = explode( ',', $ids );
	}
	return array_filter( array_map( 'intval', (array) $ids ) );
}

/** Подходит ли товар под скидку (поверх решения WooCommerce). */
add_filter(
	'woocommerce_coupon_is_valid_for_product',
	function ( $valid, $product, $coupon, $values ) {
		if ( ! $valid || ! prof_cr_applies( $coupon ) || ! $product instanceof WC_Product ) {
			return $valid; // решение WooCommerce (категории, исключения, «Только без скидок») сохраняем
		}
		if ( $product->is_on_sale() || prof_cr_is_certificate( $product ) ) {
			return false;
		}
		$brands = prof_cr_excluded_brands( $coupon );
		if ( $brands && taxonomy_exists( 'pwb-brand' ) && has_term( $brands, 'pwb-brand', prof_cr_parent_id( $product ) ) ) {
			return false;
		}
		return true;
	},
	9999,
	4
);

/** Сумма в корзине по товарам, на которые действует купон. */
function prof_cr_eligible_sum( $coupon ) {
	$cart    = WC()->cart;
	$with_tax = $cart->display_prices_including_tax();
	$sum     = 0;
	foreach ( $cart->get_cart() as $item ) {
		$product = isset( $item['data'] ) ? $item['data'] : null;
		if ( ! $product instanceof WC_Product || ! $coupon->is_valid_for_product( $product, $item ) ) {
			continue;
		}
		$sum += (float) $item['line_subtotal'] + ( $with_tax ? (float) $item['line_subtotal_tax'] : 0 );
	}
	return $sum;
}

/** «товары без акции, кроме раздела «Уход за волосами»» — по настройкам купона. */
function prof_cr_rule_text( $coupon ) {
	$names = array();
	foreach ( $coupon->get_excluded_product_categories() as $cat_id ) {
		$term = get_term( (int) $cat_id, 'product_cat' );
		if ( $term && ! is_wp_error( $term ) ) {
			$names[] = '«' . wp_strip_all_tags( $term->name ) . '»';
		}
	}
	$text = 'товары без акции';
	if ( $names ) {
		$text .= ', кроме ' . ( count( $names ) > 1 ? 'разделов ' : 'раздела ' ) . implode( ', ', $names );
	}
	return $text;
}

function prof_cr_money( $amount ) {
	return html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' );
}

/** Минимальная сумма — только по подходящим товарам. */
add_filter(
	'woocommerce_coupon_is_valid',
	function ( $valid, $coupon, $discounts = null ) {
		if ( ! $valid || ! prof_cr_applies( $coupon ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $valid;
		}
		if ( $discounts instanceof WC_Discounts && $discounts->get_object() instanceof WC_Order ) {
			return $valid; // ручной заказ в админке
		}
		$min = (float) $coupon->get_minimum_amount();
		if ( $min <= 0 ) {
			return $valid;
		}
		$sum = prof_cr_eligible_sum( $coupon );
		if ( $sum >= $min ) {
			return $valid;
		}
		$code = wc_strtoupper( $coupon->get_code() );
		$rule = prof_cr_rule_text( $coupon );
		if ( $sum <= 0 ) {
			$msg = sprintf( 'Купон «%1$s» действует при заказе от %2$s на %3$s. Таких товаров в корзине пока нет.', $code, prof_cr_money( $min ), $rule );
		} else {
			$msg = sprintf( 'Купон «%1$s» действует при заказе от %2$s на %3$s. Таких товаров в корзине на %4$s — добавьте ещё на %5$s.', $code, prof_cr_money( $min ), $rule, prof_cr_money( $sum ), prof_cr_money( $min - $sum ) );
		}
		throw new Exception( $msg, 108 ); // 108 — «не набрана минимальная сумма»
	},
	9999,
	3
);

/** В корзине нет ни одного подходящего товара — вместо стандартного «купон не применим к выбранным товарам». */
add_filter(
	'woocommerce_coupon_error',
	function ( $message, $err_code, $coupon ) {
		if ( 109 !== (int) $err_code || ! prof_cr_applies( $coupon ) ) {
			return $message;
		}
		return sprintf( 'Купон «%1$s» действует только на %2$s. Таких товаров в корзине нет.', wc_strtoupper( $coupon->get_code() ), prof_cr_rule_text( $coupon ) );
	},
	20,
	3
);
