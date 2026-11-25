<?php
/**
 * Название:      prof-filter-cat-order — порядок категорий в фильтре каталога
 * Что делает:    В фильтре WOOF / HUSKY («Категории товаров» в каталоге /products/ и категориях)
 *                ставит категории верхнего уровня в заданном порядке: Акции, Бестселлеры,
 *                Уход за лицом, Уход за волосами, Уход за телом, Мужчинам, Наборы, Парфюмерия,
 *                Подарочный сертификат. Категории, которых нет в списке (новые), идут после них
 *                по алфавиту. Подкатегории и бренды не трогает — остаются по алфавиту.
 *                Порядок меняется правкой списка в prof_filter_cat_order_list() (ярлыки категорий).
 * Где активен:   фронтенд; фильтр woof_sort_terms_before_out (20) — WOOF вызывает его
 *                перед выводом списка терминов (views/html_types/checkbox.php).
 * Зависимости:   WOOF / HUSKY (woocommerce-products-filter).
 * Источник:      profcosmetica/snippets/prof-filter-cat-order.php — перенесён без изменений кода, кроме отмеченного.
 * Обновлён:      2026-11-25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Ярлыки (slug) категорий верхнего уровня в нужном порядке.
function prof_filter_cat_order_list() {
	return array(
		'sales',            // Акции
		'bestsellers',      // Бестселлеры
		'face',             // Уход за лицом
		'hair-care',        // Уход за волосами
		'body-care',        // Уход за телом
		'for-man',          // Мужчинам
		'boxes',            // Наборы
		'perfumery',        // Парфюмерия
		'gift-certificate', // Подарочный сертификат
	);
}

add_filter( 'woof_sort_terms_before_out', 'prof_filter_cat_order', 20, 2 );

function prof_filter_cat_order( $terms, $type = '' ) {
	if ( ! is_array( $terms ) || count( $terms ) < 2 ) {
		return $terms;
	}

	// Только категории товаров верхнего уровня (бренды и подкатегории не трогаем).
	foreach ( $terms as $term ) {
		if ( ! is_array( $term )
			|| ( $term['taxonomy'] ?? '' ) !== 'product_cat'
			|| ! empty( $term['parent'] ) ) {
			return $terms;
		}
	}

	$rank = array_flip( prof_filter_cat_order_list() );
	$pos  = 0;
	$keys = array();
	foreach ( $terms as $key => $term ) {
		$slug         = urldecode( (string) ( $term['slug'] ?? '' ) );
		$keys[ $key ] = array(
			isset( $rank[ $slug ] ) ? 0 : 1,
			$rank[ $slug ] ?? 0,
			isset( $rank[ $slug ] ) ? '' : mb_strtolower( (string) ( $term['name'] ?? '' ) ),
			$pos++, // при равенстве — исходный порядок WOOF
		);
	}

	uksort(
		$terms,
		static function ( $a, $b ) use ( $keys ) {
			return $keys[ $a ] <=> $keys[ $b ];
		}
	);

	return $terms;
}
