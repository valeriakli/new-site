<?php
/**
 * Название:      prof-checkout-order-toggle (на сайте — «Аккордеон товары оформление заказа»)
 * Что делает:    На оформлении заказа прячет список товаров в таблице «Ваш заказ» (видны только итоги)
 *                и выводит над ней ссылку «Показать товары» / «Скрыть товары». Открыт список или нет,
 *                запоминается: после пересчёта оформления (смена доставки, купон) он остаётся как был.
 * Где активен:   фронтенд, оформление; woocommerce_checkout_before_order_review (10), CSS и JS внутри.
 * Зависимости:   WooCommerce (таблица .woocommerce-checkout-review-order-table, событие updated_checkout),
 *                jQuery (только для события пересчёта), цвет темы var(--color-2) (Blocksy).
 * Источник:      profcosmetica/snippets/prof-checkout-order-toggle.php — перенесён без изменений кода, кроме отмеченного.
 * Обновлён:      2026-11-25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action('woocommerce_checkout_before_order_review', 'wrap_order_review_in_toggle');
function wrap_order_review_in_toggle() {
    ?>
    <div class="order-review-toggle-wrapper">
        <span class="toggle-order-details" role="button" tabindex="0" style="
            cursor: pointer;
            color: var(--color-2);
            display: inline-block;
            margin-bottom: 10px;
            font-weight: 600;
            text-decoration: underline;
        ">Показать товары</span>
    </div>

    <style>
        .woocommerce-checkout-review-order-table tbody {
            display: none;
        }
        .woocommerce-checkout-review-order-table.show tbody{
            display: table-row-group;
        }
        .toggle-order-details:hover {
            color: #217dbb;
        }
    </style>

    <script type="text/javascript">
    (function() {
        // Открыт ли список товаров. Хранится здесь, а не в таблице: WooCommerce
        // заменяет таблицу заново при каждом пересчёте оформления.
        var isOpen = false;

        function apply() {
            var table = document.querySelector('.woocommerce-checkout-review-order-table');
            if (table) {
                table.classList.toggle('show', isOpen);
            }
            document.querySelectorAll('.toggle-order-details').forEach(function(btn) {
                btn.textContent = isOpen ? 'Скрыть товары' : 'Показать товары';
                btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });
        }

        function toggle() {
            isOpen = !isOpen;
            apply();
        }

        // Один обработчик на весь документ: не дублируется при пересчётах
        document.addEventListener('click', function(e) {
            if (e.target.closest && e.target.closest('.toggle-order-details')) {
                toggle();
            }
        });
        document.addEventListener('keydown', function(e) {
            if ((e.key === 'Enter' || e.key === ' ') && e.target.closest && e.target.closest('.toggle-order-details')) {
                e.preventDefault();
                toggle();
            }
        });

        // После пересчёта оформления таблица новая — возвращаем ей прежнее состояние
        if (typeof jQuery !== 'undefined') {
            jQuery(document.body).on('updated_checkout', apply);
        }
        document.addEventListener('DOMContentLoaded', apply);
    })();
    </script>
    <?php
}
