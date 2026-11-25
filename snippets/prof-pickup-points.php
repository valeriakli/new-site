<?php
/**
 * Название:      prof-pickup-points (на profcosmetica — «[reg] Почта пункт выдачи»)
 * Что делает:    Поле «Пункт выдачи Европочта» на оформлении (в блоке примечаний к заказу) — видно только
 *                при выбранной Европочте (flat_rate:18) и для неё обязательно (ошибка при «Подтвердить заказ»).
 *                Значение сохраняется в мету заказа europochta_point, выводится в админке заказа и
 *                отдельной строкой в письмах (в примечание покупателя не дописывается, чтобы не было повтора).
 *                Для Белпочты (flat_rate:19) поля нет — отправка на адрес из поля «Адрес».
 * Где активен:   оформление, админка заказа, письма; woocommerce_after_order_notes, wp_footer (JS),
 *                woocommerce_checkout_process, woocommerce_checkout_create_order (20),
 *                woocommerce_admin_order_data_after_shipping_address, woocommerce_email_order_meta (20).
 * Где проверено: ещё не проверен на сайте. Проверить: выбрать Европочту — поле появилось, без него заказ
 *                не оформляется; пункт виден в админке заказа и в письме.
 * Зависимости:   WooCommerce, jQuery; ID способа доставки flat_rate:18 (Copy Site сохраняет ID — сверить
 *                в WooCommerce → Настройки → Доставка, если способ пересоздавали).
 * Источник:      profcosmetica/snippets/prof-pickup-points.php — убраны поля СДЭК (RU) и EMS (GE).
 * Обновлён:      2026-11-25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'PROF_EUROPOCHTA_METHOD' ) ) {
	define( 'PROF_EUROPOCHTA_METHOD', 'flat_rate:18' );
}

// Поле на оформлении заказа.
add_action(
	'woocommerce_after_order_notes',
	function ( $checkout ) {
		echo '<div id="europochta_field_wrapper" style="display:none;">';
		woocommerce_form_field(
			'europochta_point',
			array(
				'type'        => 'text',
				'class'       => array( 'form-row-wide' ),
				'label'       => 'Пункт выдачи Европочта',
				'placeholder' => 'Укажите пункт выдачи Европочта',
				'required'    => false,
			),
			$checkout->get_value( 'europochta_point' )
		);
		echo '</div>';
	}
);

// Показываем поле только при выбранной Европочте.
add_action(
	'wp_footer',
	function () {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}
		?>
		<script>
		jQuery(function($){
			var METHOD = <?php echo wp_json_encode( PROF_EUROPOCHTA_METHOD ); ?>;

			function getShippingMethod(){
				var $checked = $('input[name^="shipping_method"]:checked');
				return $checked.length ? $checked.val() : $('input[name^="shipping_method"]').val();
			}

			function togglePickupField(){
				if ((getShippingMethod() || '') === METHOD) {
					$('#europochta_field_wrapper').slideDown();
				} else {
					$('#europochta_field_wrapper').hide();
				}
			}

			togglePickupField();
			$(document).on('change', 'input[name^="shipping_method"]', togglePickupField);
			$(document.body).on('updated_checkout', togglePickupField);
		});
		</script>
		<?php
	}
);

/** Выбранный способ доставки (первая посылка) или ''. */
function prof_pickup_chosen_method() {
	$chosen = WC()->session ? WC()->session->get( 'chosen_shipping_methods' ) : array();
	return empty( $chosen[0] ) ? '' : (string) $chosen[0];
}

// Проверка: для Европочты пункт обязателен.
add_action(
	'woocommerce_checkout_process',
	function () {
		if ( PROF_EUROPOCHTA_METHOD === prof_pickup_chosen_method() && empty( $_POST['europochta_point'] ) ) {
			wc_add_notice( 'Пожалуйста, укажите пункт выдачи Европочта.', 'error' );
		}
	}
);

// Сохранение в заказ.
add_action(
	'woocommerce_checkout_create_order',
	function ( $order, $data ) {
		if ( PROF_EUROPOCHTA_METHOD === prof_pickup_chosen_method() && ! empty( $_POST['europochta_point'] ) ) {
			$order->update_meta_data( 'europochta_point', sanitize_text_field( wp_unslash( $_POST['europochta_point'] ) ) );
		}
	},
	20,
	2
);

// Админка заказа.
add_action(
	'woocommerce_admin_order_data_after_shipping_address',
	function ( $order ) {
		$point = $order->get_meta( 'europochta_point' );
		if ( $point ) {
			echo '<p><strong>Пункт Европочта:</strong> ' . esc_html( $point ) . '</p>';
		}
	}
);

// Письма.
add_action(
	'woocommerce_email_order_meta',
	function ( $order, $sent_to_admin, $plain_text ) {
		$point = $order->get_meta( 'europochta_point' );
		if ( ! $point ) {
			return;
		}
		if ( $plain_text ) {
			echo "\nПункт Европочта: " . wp_strip_all_tags( $point ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		} else {
			echo '<p><strong>Пункт Европочта:</strong> ' . esc_html( $point ) . '</p>';
		}
	},
	20,
	3
);
