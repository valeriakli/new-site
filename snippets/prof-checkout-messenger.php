<?php
/**
 * Название:      prof-checkout-messenger (на сайте — «Мессенджер для связи при оформлении»)
 * Что делает:    1) Добавляет на оформление необязательное поле «Как с вами связаться в мессенджере?»
 *                   (billing_messenger, после телефона, перед email).
 *                2) При создании заказа сохраняет его в мету _billing_messenger и дописывает
 *                   в примечание покупателя строку «📱 Мессенджер для связи: …» (видно в письмах и заказе).
 *                3) Показывает мессенджер в админке заказа под платёжным адресом.
 *                4) Проверка: нельзя написать только название мессенджера («телеграм», «viber»…);
 *                   нужен @ник или номер (не меньше 7 цифр).
 * Где активен:   оформление и админка заказа; woocommerce_checkout_fields (10),
 *                woocommerce_checkout_create_order (10), woocommerce_admin_order_data_after_billing_address (10),
 *                woocommerce_after_checkout_validation (10).
 * Зависимости:   WooCommerce. Поле сохраняет prof-checkout-form-storage; форму упрощает
 *                prof-checkout-fields (это поле не трогает).
 * Источник:      profcosmetica/snippets/prof-checkout-messenger.php — перенесён без изменений кода, кроме отмеченного.
 * Обновлён:      2026-11-25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 1. Добавляем поле "Мессенджер" в форму чекаута
add_filter( 'woocommerce_checkout_fields', 'add_messenger_checkout_field' );
function add_messenger_checkout_field( $fields ) {
    $fields['billing']['billing_messenger'] = array(
        'type'        => 'text',
        'label'       => 'Как с вами связаться в мессенджере?',
        'placeholder' => 'Например: Telegram @ivanov или +375291234567',
        'description' => 'Укажите мессенджер для связи, ваш никнейм или номер телефона, по которому вас можно найти.',
        'required'    => false,
        'class'       => array( 'form-row-wide' ),
        'priority'    => 105,
    );
    return $fields;
}

// 2. Сохраняем поле и вшиваем его в заметку заказа до сохранения в БД
add_action( 'woocommerce_checkout_create_order', 'save_messenger_and_add_to_note', 10, 2 );
function save_messenger_and_add_to_note( $order, $data ) {
    if ( ! empty( $_POST['billing_messenger'] ) ) {
        $messenger = sanitize_text_field( $_POST['billing_messenger'] );

        // Сохраняем в метаданные заказа
        $order->update_meta_data( '_billing_messenger', $messenger );

        // Добавляем в заметку покупателя (customer note)
        $existing_note = $order->get_customer_note();
        $separator = $existing_note ? "\n\n---\n" : "";
        $order->set_customer_note( $existing_note . $separator . "📱 Мессенджер для связи: " . $messenger );
    }
}

// 3. Отображаем поле в админке WordPress (в деталях заказа)
add_action( 'woocommerce_admin_order_data_after_billing_address', 'display_messenger_in_admin_order', 10, 1 );
function display_messenger_in_admin_order( $order ) {
    $messenger = $order->get_meta( '_billing_messenger' );
    if ( $messenger ) {
        echo '<p><strong>Мессенджер:</strong> ' . esc_html( $messenger ) . '</p>';
    }
}

// 4. Валидация введенных данных
add_action( 'woocommerce_after_checkout_validation', 'validate_messenger_checkout_field', 10, 2 );
function validate_messenger_checkout_field( $data, $errors ) {
    if ( empty( $data['billing_messenger'] ) ) {
        return;
    }

    $messenger = trim( $data['billing_messenger'] );
    $lower     = mb_strtolower( $messenger );

    // Проверка на ввод одного лишь названия мессенджера
    $invalid_values = array(
        'tg', 'тг', 'telegram', 'телеграм', 'телеграмм', 'telegramm',
        'viber', 'вайбер', 'whatsapp', 'ватсап', 'вацап', 'watsapp',
    );

    if ( in_array( $lower, $invalid_values, true ) ) {
        $errors->add(
            'billing_messenger',
            'Пожалуйста, укажите ваш никнейм (@username) или номер телефона в мессенджере. Недостаточно написать только название мессенджера.'
        );
        return;
    }

    // Если нет ни '@', ни хотя бы 7 цифр
    $digits = preg_replace( '/\D/', '', $messenger );

    if ( strpos( $messenger, '@' ) === false && strlen( $digits ) < 7 ) {
        $errors->add(
            'billing_messenger',
            'Укажите, пожалуйста, ваш @username или номер телефона, по которому вас можно найти в мессенджере.'
        );
    }
}
