<?php
/**
 * Название:      prof-checkout-errors
 * Что делает:    Понятные ошибки на оформлении заказа. WooCommerce при ошибке (не отмечено согласие с правилами,
 *                пустой email и т.п.) прокручивает страницу к самому верху формы — на телефоне это выглядит как
 *                «страница обновилась», а галочка «правила» остаётся далеко внизу у кнопки.
 *                Теперь: прокрутка к первому неправильному полю (подсвечивается), а над кнопкой
 *                «Подтвердить заказ» — красная плашка с текстом ошибки. Плашка вверху формы остаётся как была.
 *                Если ошибка не про поле (например, оплата) — прокрутка как в WooCommerce, плашка у кнопки тоже есть.
 * Где активен:   только фронтенд, страница оформления заказа (без страницы «Заказ получен»): wp_footer (99),
 *                JS на событии checkout_error. Сервер и проверку заказа не меняет.
 * Зависимости:   WooCommerce (classic checkout, jQuery, checkout.min.js); тема Blocksy (липкая шапка — отступ 140px).
 * Источник:      profcosmetica/snippets/prof-checkout-errors.php — перенесён без изменений кода, кроме отмеченного.
 * Обновлён:      2026-11-25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_footer',
	function () {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() || is_checkout_pay_page() ) {
			return;
		}
		?>
<style id="prof-checkout-errors-css">
.prof-co-hint{background:#fdecee;border:1px solid #e2445c;border-left-width:4px;color:#b3122e;border-radius:6px;padding:10px 14px;margin:0 0 14px;font-size:15px;line-height:1.4}
.prof-co-hint ul{margin:0;padding:0;list-style:none}
.prof-co-hint li+li{margin-top:6px}
form.checkout .woocommerce-terms-and-conditions-wrapper .woocommerce-invalid{background:#fdecee;outline:2px solid #e2445c;outline-offset:4px;border-radius:4px;animation:prof-co-pulse .6s ease 2}
form.checkout .woocommerce-terms-and-conditions-wrapper .woocommerce-invalid label,
form.checkout .woocommerce-terms-and-conditions-wrapper .woocommerce-invalid .woocommerce-terms-and-conditions-checkbox-text{color:#b3122e}
@keyframes prof-co-pulse{50%{outline-color:transparent}}
</style>
<script id="prof-checkout-errors-js">
(function ($) {
	if (!$) { return; }
	var OFFSET = 140; // липкая шапка Blocksy

	function clearHint() { $('.prof-co-hint').remove(); }

	function messages(html) {
		var list = [], seen = {};
		$('<div>').html(html).find('li, .woocommerce-error:not(:has(li))').each(function () {
			var t = $.trim($(this).text()).replace(/\s+/g, ' ');
			if (t && !seen[t]) { seen[t] = 1; list.push(t); }
		});
		return list;
	}

	function showHint(list) {
		clearHint();
		var $btn = $('#place_order');
		if (!list.length || !$btn.length) { return; }
		var $box = $('<div class="prof-co-hint" role="alert"><ul></ul></div>');
		$.each(list, function (i, t) { $('<li>').text(t).appendTo($box.find('ul')); });
		$box.insertBefore($btn);
	}

	function firstInvalid() {
		var $rows = $('form.checkout .form-row.woocommerce-invalid:visible');
		return $rows.length ? $rows.first() : null;
	}

	$(document.body).on('checkout_error', function (e, html) {
		if (!$('form.checkout').length) { return; } // ошибки не оформления (купон и т.п.)
		showHint(messages(html));
		var $row = firstInvalid();
		if (!$row) { return; } // ошибка не про поле — оставляем прокрутку WooCommerce
		$('html, body').stop(true);
		$('html, body').animate({ scrollTop: Math.max(0, $row.offset().top - OFFSET) }, 400);
		var input = $row.find('input, select, textarea').get(0);
		if (input && input.type !== 'checkbox') {
			try { input.focus({ preventScroll: true }); } catch (err) {}
		}
	});

	// Новая попытка или пересчёт — убираем старую плашку; отметили галочку — убираем подсветку.
	$(document.body).on('update_checkout', clearHint);
	$('form.checkout').on('checkout_place_order', function () { clearHint(); return true; });
	$(document.body).on('change', '#terms', function () {
		if (this.checked) {
			$(this).closest('.form-row').removeClass('woocommerce-invalid woocommerce-invalid-required-field');
			if (!firstInvalid()) { clearHint(); }
		}
	});
})(window.jQuery);
</script>
		<?php
	},
	99
);
