<?php
/**
 * Название:      prof-checkout-form-storage (на сайте — «сохранение формы при оформлении после купона»)
 * Что делает:    Сохраняет в localStorage браузера покупателя (ключ checkout_form_data) поля оформления:
 *                мессенджер, имя, адрес, телефон, email, комментарий, пункт Европочты — и подставляет
 *                их обратно в пустые поля после перезагрузки страницы (например, после применения купона).
 *                Если заказ не прошёл проверку, поля остаются. После успешного заказа (страница «Спасибо»,
 *                order-received) стирается только комментарий; контакты, адрес и пункт выдачи остаются
 *                для следующего заказа, но не дольше 60 дней с последнего ввода (метка времени _t).
 *                Отличие от profcosmetica: убрано поле пункта СДЭК (cdek_point).
 * Где активен:   Code Snippets, обычный PHP-сниппет («Только на сайте» / везде). Выводит <script> в wp_footer
 *                (приоритет 50) только на оформлении заказа и странице «Спасибо» (is_checkout()).
 * Зависимости:   WooCommerce (classic checkout, jQuery, событие updated_checkout).
 * Источник:      profcosmetica/snippets/prof-checkout-form-storage.php — перенесён без изменений кода, кроме отмеченного.
 * Обновлён:      2026-11-25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_footer', function () {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return;
	}
	?>
	<script>
	(function () {

	    var STORAGE_KEY = 'checkout_form_data';

	    var fields = [
	        'billing_messenger',
	        'billing_first_name',
	        'billing_address_1',
	        'billing_phone',
	        'billing_email',
	        'order_comments',
	        'europochta_point'
	    ];

	    // сколько хранить данные с последнего ввода
	    var MAX_AGE = 60 * 24 * 60 * 60 * 1000; // 60 дней

	    function storageGet() {
	        try {
	            var data = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}') || {};

	            // устарели — удаляем
	            if (data._t && Date.now() - data._t > MAX_AGE) {
	                localStorage.removeItem(STORAGE_KEY);
	                return {};
	            }

	            return data;
	        } catch (e) {
	            return {};
	        }
	    }

	    function storageSet(data) {
	        try {
	            localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
	        } catch (e) {}
	    }

	    // после заказа: стираем только комментарий, остальное оставляем
	    function clearComment() {
	        var data = storageGet();

	        if (data.order_comments === undefined) return;

	        delete data.order_comments;
	        storageSet(data);
	    }

	    function saveData() {
	        var data = storageGet();

	        fields.forEach(function (id) {
	            var field = document.getElementById(id);

	            if (field) {
	                data[id] = field.value;
	            }
	        });

	        data._t = Date.now();
	        storageSet(data);
	    }

	    function restoreData() {
	        var data = storageGet();

	        fields.forEach(function (id) {
	            var field = document.getElementById(id);

	            if (
	                field &&
	                data[id] !== undefined &&
	                data[id] !== '' &&
	                field.value === ''
	            ) {
	                field.value = data[id];

	                // чтобы WooCommerce понял, что поле изменилось
	                field.dispatchEvent(new Event('change', { bubbles: true }));
	                field.dispatchEvent(new Event('input', { bubbles: true }));
	            }
	        });
	    }

	    function bindListeners() {
	        fields.forEach(function (id) {
	            var field = document.getElementById(id);

	            if (!field || field.dataset.storageBound) return;

	            field.dataset.storageBound = '1';

	            field.addEventListener('input', saveData);
	            field.addEventListener('change', saveData);
	        });
	    }

	    function init() {
	        // Заказ оформлен — страница «Спасибо за заказ»: стираем комментарий
	        if (
	            document.body.classList.contains('woocommerce-order-received') ||
	            document.querySelector('.woocommerce-order-received, .woocommerce-thankyou-order-received')
	        ) {
	            clearComment();
	            return;
	        }

	        // Не оформление заказа — ничего не делаем
	        if (!document.querySelector('form.checkout')) return;

	        bindListeners();
	        restoreData();

	        if (window.jQuery) {
	            window.jQuery(document.body).on('updated_checkout', function () {
	                bindListeners();

	                // небольшая задержка, чтобы WooCommerce успел вставить новые поля
	                setTimeout(restoreData, 50);
	            });
	        }
	    }

	    if (document.readyState === 'loading') {
	        document.addEventListener('DOMContentLoaded', init);
	    } else {
	        init();
	    }

	})();
	</script>
	<?php
}, 50 );
