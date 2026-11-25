<?php
/**
 * Название:      prof-checkout-fields (на сайте — «Форма оформления заказа»)
 * Что делает:    Упрощает форму оформления заказа: убирает индекс, компанию, вторую строку адреса,
 *                фамилию, страну, область и город (для оплаты и доставки). Поле «Имя» становится
 *                «Имя и фамилия», адрес — одно поле «Адрес» с подсказкой «Населенный пункт, улица,
 *                дом квартира». Те же поля убираются и из стандартных полей адреса WooCommerce.
 * Где активен:   везде, где WooCommerce собирает поля адреса (оформление, «Мой аккаунт», админка);
 *                woocommerce_checkout_fields (9999), woocommerce_default_address_fields (20).
 * Зависимости:   WooCommerce. На оставшиеся поля (billing_first_name, billing_address_1, billing_phone,
 *                billing_email) опираются prof-checkout-form-storage и prof-checkout-phone-mask.
 * Источник:      profcosmetica/snippets/prof-checkout-fields.php — перенесён без изменений кода, кроме отмеченного.
 * Обновлён:      2026-11-25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'woocommerce_checkout_fields', 'custom_checkout_fields', 9999 );
function custom_checkout_fields( $fields ) {
    // Удаляем ненужные поля
    unset( $fields['billing']['billing_postcode'] );
    unset( $fields['shipping']['shipping_postcode'] );

    unset( $fields['billing']['billing_company'] );
    unset( $fields['shipping']['shipping_company'] );

    unset( $fields['billing']['billing_address_2'] );
    unset( $fields['shipping']['shipping_address_2'] );

    // Удаляем фамилию
    unset( $fields['billing']['billing_last_name'] );
    unset( $fields['shipping']['shipping_last_name'] );

    // Убираем страна, область, город
    unset( $fields['billing']['billing_country'] );
    unset( $fields['shipping']['shipping_country'] );

    unset( $fields['billing']['billing_state'] );
    unset( $fields['shipping']['shipping_state'] );

    unset( $fields['billing']['billing_city'] );
    unset( $fields['shipping']['shipping_city'] );

    // Меняем имя → Имя и фамилия
    if ( isset( $fields['billing']['billing_first_name'] ) ) {
        $fields['billing']['billing_first_name']['label'] = 'Имя и фамилия';
        $fields['billing']['billing_first_name']['placeholder'] = 'Введите имя и фамилию';
    }
    if ( isset( $fields['shipping']['shipping_first_name'] ) ) {
        $fields['shipping']['shipping_first_name']['label'] = 'Имя и фамилия';
        $fields['shipping']['shipping_first_name']['placeholder'] = 'Введите имя и фамилию';
    }

    // Новый плейсхолдер для адреса
    $pl = 'Населенный пункт, улица, дом квартира';

    if ( isset( $fields['billing']['billing_address_1'] ) ) {
        $fields['billing']['billing_address_1']['placeholder'] = $pl;
        $fields['billing']['billing_address_1']['label'] = 'Адрес';
        $fields['billing']['billing_address_1']['custom_attributes']['data-placeholder'] = $pl;
    }
    if ( isset( $fields['shipping']['shipping_address_1'] ) ) {
        $fields['shipping']['shipping_address_1']['placeholder'] = $pl;
        $fields['shipping']['shipping_address_1']['label'] = 'Адрес';
        $fields['shipping']['shipping_address_1']['custom_attributes']['data-placeholder'] = $pl;
    }

    return $fields;
}

// Подстрахуем WooCommerce default address fields
add_filter( 'woocommerce_default_address_fields', 'custom_default_address_fields', 20 );
function custom_default_address_fields( $address_fields ) {
    // Меняем плейсхолдер
    $address_fields['address_1']['placeholder'] = 'Населенный пункт, улица, дом квартира';

    // Удаляем страна / область / город
    unset( $address_fields['country'] );
    unset( $address_fields['state'] );
    unset( $address_fields['city'] );
    unset( $address_fields['postcode'] );
    unset( $address_fields['address_2'] );

    return $address_fields;
}
