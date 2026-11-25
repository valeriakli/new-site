<?php
/**
 * Название:      prof-checkout-back-reload (на сайте — «back button after payment»)
 * Что делает:    Если покупатель вернулся на оформление заказа кнопкой «Назад» (например, со
 *                страницы оплаты bePaid) и браузер показал сохранённую копию страницы
 *                (back-forward cache), страница перезагружается — чтобы корзина, итоги и форма
 *                были актуальными, а не «застывшими» с момента ухода на оплату.
 * Где активен:   фронтенд, оформление заказа (в т.ч. «Спасибо за заказ», это тоже is_checkout());
 *                wp_footer (10), событие pageshow с event.persisted.
 * Зависимости:   WooCommerce (is_checkout). Данные полей не теряются: их восстанавливает
 *                prof-checkout-form-storage.
 * Источник:      profcosmetica/snippets/prof-checkout-back-reload.php — перенесён без изменений кода, кроме отмеченного.
 * Обновлён:      2026-11-25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action('wp_footer', function() {
    if (is_checkout()) {
        ?>
        <script>
        // Фикс возврата на checkout через кнопку "Назад"
        window.addEventListener('pageshow', function(event) {
            // Если страница загружена из back-forward cache
            if (event.persisted) {
                window.location.reload();
            }
        });
        </script>
        <?php
    }
});
