<?php
/**
 * Название:      prof-woof-settings — настройки фильтра WOOF / HUSKY
 * Что делает:    Две настройки фильтра каталога:
 *                1) woof_hide_empty_terms → false: в фильтре показываются и пустые варианты
 *                   (категории / бренды без товаров), WOOF их не прячет;
 *                2) woof_show_taxonomies_on_subcategories → true: фильтр по таксономиям
 *                   показывается и на страницах подкатегорий, а не только в каталоге и верхних категориях.
 * Где активен:   фронтенд, везде, где выводится фильтр WOOF; фильтры WOOF (10).
 * Зависимости:   WOOF / HUSKY (woocommerce-products-filter). Рядом: prof-filter-cat-order (порядок
 *                категорий в фильтре), prof-filter-cats-api (дерево категорий для скрипта фильтра).
 * Источник:      profcosmetica/snippets/prof-woof-settings.php — перенесён без изменений кода, кроме отмеченного.
 * Обновлён:      2026-11-25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter('woof_hide_empty_terms', '__return_false');
add_filter('woof_show_taxonomies_on_subcategories', '__return_true');
