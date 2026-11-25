<?php
/**
 * Название:      prof-tab-add-to-cart (на сайте — «Кнопка добавить в корзину после описания товара»)
 * Что делает:    На странице товара добавляет в конец открытой вкладки (Описание / Бренд /
 *                Доставка и оплата) кнопку «В корзину». В «Описании» — перед блоком похожих записей
 *                Jetpack (#jp-relatedposts), если он есть. Кнопка нажимает основную кнопку
 *                «В корзину» товара (с выбранным количеством). При переключении вкладки кнопка
 *                переносится в новую вкладку.
 *                Видна всегда, в т.ч. при свёрнутом описании (prof-description-truncate, с 2026-11-15).
 *                Кнопка ставится только в панель верхнего уровня: если в тексте описания есть
 *                скопированная разметка вкладок, второй кнопки не будет (с 2026-11-15).
 * Где активен:   фронтенд, страница товара; wp_footer (20), jQuery.
 * Зависимости:   WooCommerce (вкладки .woocommerce-Tabs-panel, .summary form.cart), jQuery,
 *                prof-description-truncate (сворачивание описания; кнопка стоит после «Показать полностью»).
 * Источник:      profcosmetica/snippets/prof-tab-add-to-cart.php — перенесён без изменений кода, кроме отмеченного.
 * Обновлён:      2026-11-25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function move_add_to_cart_script() {

    if (!is_product()) return;
    ?>
    <script>
    jQuery(function($){

        function addTabButton() {

            // удалить старые кнопки
            $('.custom-tab-add-to-cart').remove();

            // только панели верхнего уровня: в описании некоторых товаров есть
            // скопированная разметка вкладок (вложенные .woocommerce-Tabs-panel)
            const $activeTab = $('.woocommerce-Tabs-panel:visible')
                .not('.woocommerce-Tabs-panel .woocommerce-Tabs-panel')
                .first();

            if (!$activeTab.length) {
                return;
            }

            const $button = $(`
                <div class="custom-tab-add-to-cart" style="margin-top:20px;">
                    <button type="button" class="button alt">
                        В корзину
                    </button>
                </div>
            `);

            if ($activeTab.is('#tab-description')) {

                const $relatedPosts = $('#jp-relatedposts');

                if ($relatedPosts.length) {
                    $relatedPosts.before($button);
                } else {
                    $activeTab.append($button);
                }

            } else {
                $activeTab.append($button);
            }
        }

        // клик по кнопке во вкладке
        $(document).on('click', '.custom-tab-add-to-cart button', function(e){

            e.preventDefault();

            const qty = $('.summary form.cart .qty').val();

            const $origQty = $('.summary form.cart .qty');

            if ($origQty.length && qty) {
                $origQty.val(qty).trigger('change');
            }

            $('.summary form.cart .single_add_to_cart_button')
                .first()
                .trigger('click');
        });

        addTabButton();

        $(document).on('click', '.wc-tabs a', function(){
            setTimeout(addTabButton, 200);
        });

    });
    </script>
    <?php
}
add_action('wp_footer', 'move_add_to_cart_script', 20);
