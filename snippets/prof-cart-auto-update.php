<?php
/**
 * Название:      Автоматическое обновление корзины
 * Что делает:    В корзине при изменении количества товара (поле input.qty) сам нажимает
 *                кнопку «Обновить корзину», чтобы покупателю не нужно было делать это вручную.
 *                Ждёт 0,5 с после последнего изменения: при нескольких быстрых нажатиях «+»/«−»
 *                корзина обновляется один раз, а не на каждое нажатие.
 * Где активен:   фронтенд, только страница корзины; скрипт в wp_footer (99).
 * Зависимости:   WooCommerce (is_cart, кнопка name="update_cart"), jQuery.
 * Источник:      profcosmetica/snippets/prof-cart-auto-update.php — перенесён без изменений кода, кроме отмеченного.
 * Обновлён:      2026-11-25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function auto_update_cart_qty() {
    // Проверяем, что это страница корзины
    if ( function_exists('is_cart') && is_cart() ) {
        ?>
        <script>
        (function(){
            if ( typeof jQuery === 'undefined' ) {
                return;
            }
            jQuery(function($){
                var timer = null;
                // Обработчик на body: продолжает работать после того, как WooCommerce
                // перерисует корзину по AJAX
                $(document.body).on('change', 'div.woocommerce input.qty', function(){
                    clearTimeout(timer);
                    timer = setTimeout(function(){
                        $("[name='update_cart']").prop('disabled', false).trigger('click');
                    }, 500);
                });
            });
        })();
        </script>
        <?php
    }
}
add_action('wp_footer', 'auto_update_cart_qty', 99);
