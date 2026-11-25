<?php
/**
 * Название:      Маска телефона на оформлении заказа (только BY)
 * Что делает:    На странице оформления заказа приводит поле #billing_phone к виду
 *                +375 (29) 123-45-67. Префикс +375 стоит в поле сам, покупатель набирает
 *                только 9 цифр; если по привычке начать с 375, +375 или 80, лишнее
 *                отбрасывается. Вставка и автозаполнение браузера тоже приводятся к формату.
 *                Без внешних библиотек (раньше был jQuery Mask с cdnjs: он «съедал» цифры
 *                3/7/5 из набранного номера).
 * Где активен:   только фронтенд, страница checkout: wp_enqueue_scripts, свой inline-скрипт
 *                в подвале; события ловятся на document, поэтому переживают AJAX-обновления.
 * Где проверено: ещё не проверен на сайте. Проверить: набор, вставка, автозаполнение номера на оформлении.
 * Зависимости:   WooCommerce.
 * Источник:      profcosmetica/snippets/prof-checkout-phone-mask.php — убрано определение региона
 *                (на profcosmetica в RU / GE маска не подключалась), здесь маска всегда.
 * Обновлён:      2026-11-25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', 'custom_checkout_phone_mask' );
function custom_checkout_phone_mask() {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return;
	}

	wp_register_script( 'prof-phone-mask', false, array(), '2026-11-10', true );
	wp_enqueue_script( 'prof-phone-mask' );

	$js = <<<'JS'
(function () {
	var ID = 'billing_phone';
	var PREFIX = '+375 (';
	var PLACEHOLDER = '+375 (__) ___-__-__';

	// Из любого ввода — 9 цифр номера без кода страны
	function local(v) {
		var d = v.indexOf('+375') === 0 ? v.slice(4).replace(/\D/g, '') : v.replace(/\D/g, '');
		if (d.indexOf('375') === 0) d = d.slice(3);
		else if (d.indexOf('80') === 0) d = d.slice(2);
		return d.slice(0, 9);
	}

	function fmt(d) {
		var o = PREFIX + d.slice(0, 2);
		if (d.length > 2) o += ') ' + d.slice(2, 5);
		if (d.length > 5) o += '-' + d.slice(5, 7);
		if (d.length > 7) o += '-' + d.slice(7, 9);
		return o;
	}

	// Позиция курсора после n-й цифры номера
	function posFor(f, n) {
		if (n <= 0) return PREFIX.length;
		for (var i = PREFIX.length, c = 0; i < f.length; i++) {
			if (/\d/.test(f.charAt(i)) && ++c === n) return i + 1;
		}
		return f.length;
	}

	function apply(el, digitsBeforeCaret) {
		var d = local(el.value);
		var focused = document.activeElement === el;
		el.value = d.length || focused ? fmt(d) : '';
		if (focused) {
			var p = digitsBeforeCaret === undefined ? el.value.length : posFor(el.value, digitsBeforeCaret);
			try { el.setSelectionRange(p, p); } catch (e) {}
		}
	}

	function isPhone(el) {
		return el && el.id === ID;
	}

	function onInput(e) {
		var el = e.target;
		if (!isPhone(el)) return;
		var pos = el.selectionStart;
		var atEnd = pos === null || pos >= el.value.length;
		apply(el, atEnd ? undefined : local(el.value.slice(0, pos)).length);
	}

	document.addEventListener('input', onInput, true);
	document.addEventListener('change', onInput, true);

	document.addEventListener('focusin', function (e) {
		var el = e.target;
		if (!isPhone(el)) return;
		el.setAttribute('placeholder', PLACEHOLDER);
		if (!el.value) {
			el.value = PREFIX;
			setTimeout(function () { try { el.setSelectionRange(PREFIX.length, PREFIX.length); } catch (err) {} }, 0);
		} else {
			apply(el);
		}
	}, true);

	document.addEventListener('focusout', function (e) {
		var el = e.target;
		if (isPhone(el) && !local(el.value).length) el.value = '';
	}, true);

	// Backspace: префикс не стирается, скобки/пробелы/дефисы перескакиваются
	document.addEventListener('keydown', function (e) {
		var el = e.target;
		if (!isPhone(el) || e.key !== 'Backspace') return;
		var p = el.selectionStart, q = el.selectionEnd, v = el.value;
		if (p !== q) return;
		if (p <= PREFIX.length) { e.preventDefault(); return; }
		if (!/\D/.test(v.charAt(p - 1))) return;
		e.preventDefault();
		var k = p - 1;
		while (k > PREFIX.length && /\D/.test(v.charAt(k - 1))) k--;
		if (k <= PREFIX.length) return;
		var before = local(v.slice(0, k - 1)).length;
		el.value = v.slice(0, k - 1) + v.slice(p);
		apply(el, before);
	}, true);

	function init() {
		var el = document.getElementById(ID);
		if (!el) return;
		el.setAttribute('placeholder', PLACEHOLDER);
		if (el.value) apply(el);
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
	else init();
})();
JS;

	wp_add_inline_script( 'prof-phone-mask', $js );
}
