<?php
/**
 * Название:      prof-checkout-shipping-info (на profcosmetica — «[reg] Checkout доп информация о доставке»)
 * Что делает:    На оформлении под списком способов доставки показывает плашку со сроками
 *                для выбранного способа:
 *                — по Минску (flexible_shipping_single:20 / :21 / :22): «На следующий рабочий день»;
 *                — Европочта / Белпочта (flat_rate:18 / :19): «Отправка ежедневно, срок 2–5 дней».
 *                Для самовывоза (local_pickup:16) плашки нет. Обновляется при смене способа и пересчёте.
 * Где активен:   оформление заказа; wp_footer (10), CSS + jQuery.
 * Где проверено: ещё не проверен на сайте. Проверить: переключать способы доставки на оформлении.
 * Зависимости:   WooCommerce, jQuery; ID способов доставки из настроек зон (при пересоздании способа
 *                ID меняется — поправить проверки в render()). Сроки — сверить с Олей.
 * Источник:      profcosmetica/snippets/prof-checkout-shipping-info.php — убраны плашки СДЭК (RU) и EMS (GE).
 * Обновлён:      2026-11-25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action('wp_footer', function () {
    if (!is_checkout()) return;
    ?>

    <style>
        #shipping-dynamic-info{
            margin:10px 0 0;
        }

        .ship-box{
            font-size:12px;
            opacity:.85;
            padding:10px 12px;
            border:1px solid #eee;
            border-radius:8px;
            background:#fafafa;
        }

        .ship-title{
            font-weight:600;
            margin-bottom:6px;
        }

        .ship-note{
            margin-top:6px;
            font-size:11px;
            opacity:0.6;
            line-height:1.3;
        }
    </style>

    <script>
    jQuery(function($){

        function ensureBox(){
            if ($('#shipping-dynamic-info').length === 0) {
                $('#shipping_method').after('<div id="shipping-dynamic-info"></div>');
            }
        }

        function render(){

            let val = '';

            // если несколько способов доставки
            if ($('input[name="shipping_method[0]"]:checked').length) {
                val = $('input[name="shipping_method[0]"]:checked').val();
            } else {
                // если WooCommerce вывел hidden input
                val = $('input[name="shipping_method[0]"]').val();
            }

            if(!val) return;

            let html = '';

            // 🚚 Минск
            if (
                val.includes('flexible_shipping_single:21') ||
                val.includes('flexible_shipping_single:22') ||
                val.includes('flexible_shipping_single:20')
            ) {
                html = `
                    <div class="ship-box">
                        <div class="ship-title">🚚 Доставка по Минску</div>

                        <div>На следующий рабочий день</div>

                        <div class="ship-note">
                            При наличии товара на складе
                        </div>
                    </div>
                `;
            }

            // 📦 Беларусь
            else if (
                val.includes('flat_rate:18') ||
                val.includes('flat_rate:19')
            ) {
                html = `
                    <div class="ship-box">
                        <div class="ship-title">📦 Доставка по Беларуси</div>

                        <div>Отправка ежедневно</div>
                        <div>Срок доставки: 2–5 дней</div>

                        <div class="ship-note">
                            При наличии товара на складе
                        </div>
                    </div>
                `;
            }

            $('#shipping-dynamic-info').html(html);
        }

        function init(){
            ensureBox();
            render();
        }

        $(document).on('change', 'input[name="shipping_method[0]"]', render);
        $(document.body).on('updated_checkout', init);

        init();

    });
    </script>

    <?php
});
