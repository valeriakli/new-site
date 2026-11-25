<?php
/**
 * Название:      prof-sale-category-sync (на сайте — «Категория Акции по скидке»)
 * Что делает:    Держит категорию «Акции» (ярлык sales) в соответствии со скидками: товар со
 *                скидкой (цена со скидкой действует сейчас, для вариативного — хотя бы у одной
 *                вариации) попадает в «Акции», без скидки — убирается из неё. Так фильтр «Акции»
 *                в каталоге, значок «Акция» на картинке и «Есть акции» на странице брендов
 *                всегда совпадают. Вручную вести категорию «Акции» больше не нужно: достаточно
 *                поставить или убрать цену со скидкой (в том числе по датам).
 *                Остальные категории товара не трогаются.
 * Где активен:   при сохранении товара или вариации (woocommerce_new_product, woocommerce_update_product,
 *                …_variation; это же срабатывает при быстром и массовом редактировании и при
 *                начале / окончании скидки по датам — крон WooCommerce сохраняет товар);
 *                плюс полная сверка раз в сутки (крон prof_sale_cat_sync_daily) и один раз сразу
 *                после включения сниппета (при первом заходе в админку).
 *                Ручной запуск сверки: в админке открыть адрес /wp-admin/?prof_sale_cat_sync=1.
 * Зависимости:   WooCommerce; категория с ярлыком sales. После изменений сбрасывает кеш
 *                prof-brands-page (prof_brands_page_flush) и prof-shortcode-cache (prof_sc_bump_version),
 *                если они есть.
 * Источник:      profcosmetica/snippets/prof-sale-category-sync.php — перенесён без изменений кода, кроме отмеченного.
 * Обновлён:      2026-11-25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'PROF_SALE_CAT_SLUG' ) ) define( 'PROF_SALE_CAT_SLUG', 'sales' );

function prof_sale_cat_term_id() {
	$term = get_term_by( 'slug', PROF_SALE_CAT_SLUG, 'product_cat' );
	return $term ? (int) $term->term_id : 0;
}

function prof_sale_cat_after_change() {
	if ( function_exists( 'prof_brands_page_flush' ) ) prof_brands_page_flush();
	if ( function_exists( 'prof_sc_bump_version' ) ) prof_sc_bump_version();
}

/* Один товар: добавить в «Акции» или убрать. Возвращает true, если что-то изменилось. */
function prof_sale_cat_sync_product( $product_id ) {
	$term_id = prof_sale_cat_term_id();
	if ( ! $term_id ) return false;

	$product = wc_get_product( $product_id );
	if ( ! $product || $product->is_type( 'variation' ) ) return false;

	$on_sale = $product->is_on_sale( 'edit' );
	$in_cat  = has_term( $term_id, 'product_cat', $product_id );

	if ( $on_sale && ! $in_cat ) {
		wp_set_object_terms( $product_id, array( $term_id ), 'product_cat', true );
		return true;
	}
	if ( ! $on_sale && $in_cat ) {
		wp_remove_object_terms( $product_id, array( $term_id ), 'product_cat' );
		return true;
	}
	return false;
}

/* Сохранение товара / вариации. Откладываем до конца запроса: на странице товара
   категории из формы сохраняются раньше, а цены вариаций — позже. */
function prof_sale_cat_queue( $product_id ) {
	global $prof_sale_cat_queue;
	$post_type = get_post_type( $product_id );
	if ( 'product_variation' === $post_type ) {
		$product_id = wp_get_post_parent_id( $product_id );
	} elseif ( 'product' !== $post_type ) {
		return;
	}
	if ( ! $product_id ) return;
	if ( empty( $prof_sale_cat_queue ) ) {
		$prof_sale_cat_queue = array();
		add_action( 'shutdown', 'prof_sale_cat_run_queue', 1 );
	}
	$prof_sale_cat_queue[ $product_id ] = true;
}
function prof_sale_cat_run_queue() {
	global $prof_sale_cat_queue;
	if ( empty( $prof_sale_cat_queue ) ) return;
	$changed = false;
	foreach ( array_keys( $prof_sale_cat_queue ) as $id ) {
		wc_delete_product_transients( $id ); // свежий расчёт скидки вариативного товара
		if ( prof_sale_cat_sync_product( $id ) ) $changed = true;
	}
	$prof_sale_cat_queue = array();
	if ( $changed ) prof_sale_cat_after_change();
}
add_action( 'woocommerce_new_product', 'prof_sale_cat_queue' );
add_action( 'woocommerce_update_product', 'prof_sale_cat_queue' );
add_action( 'woocommerce_new_product_variation', 'prof_sale_cat_queue' );
add_action( 'woocommerce_update_product_variation', 'prof_sale_cat_queue' );

/* Полная сверка всех товаров */
function prof_sale_cat_sync_all() {
	$term_id = prof_sale_cat_term_id();
	if ( ! $term_id ) return array( 'added' => array(), 'removed' => array() );

	delete_transient( 'wc_products_onsale' );
	$on_sale = array();
	foreach ( wc_get_product_ids_on_sale() as $id ) {
		$id = (int) $id;
		if ( 'product' === get_post_type( $id ) ) $on_sale[ $id ] = true;
	}
	$in_cat = array();
	$objs   = get_objects_in_term( $term_id, 'product_cat' );
	if ( ! is_wp_error( $objs ) ) {
		foreach ( $objs as $id ) $in_cat[ (int) $id ] = true;
	}

	$added = $removed = array();
	// Проверяем каждого кандидата по самому товару (список WooCommerce может отставать)
	foreach ( array_keys( $on_sale + $in_cat ) as $id ) {
		$had = isset( $in_cat[ $id ] );
		if ( prof_sale_cat_sync_product( $id ) ) {
			if ( $had ) $removed[] = $id; else $added[] = $id;
		}
	}
	if ( $added || $removed ) prof_sale_cat_after_change();
	update_option( 'prof_sale_cat_last_sync', array(
		'time'    => current_time( 'mysql' ),
		'added'   => $added,
		'removed' => $removed,
	), false );
	return array( 'added' => $added, 'removed' => $removed );
}

/* Ежедневная сверка */
add_action( 'prof_sale_cat_sync_daily', 'prof_sale_cat_sync_all' );
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'prof_sale_cat_sync_daily' ) ) {
		wp_schedule_event( strtotime( 'tomorrow 01:30' ) - (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ), 'daily', 'prof_sale_cat_sync_daily' );
	}
} );

/* Первая сверка после включения и ручной запуск (только администратор) */
add_action( 'admin_init', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) return;
	$manual = isset( $_GET['prof_sale_cat_sync'] );
	if ( ! $manual && get_option( 'prof_sale_cat_sync_v1' ) ) return;

	$res = prof_sale_cat_sync_all();
	update_option( 'prof_sale_cat_sync_v1', 1, false );

	add_action( 'admin_notices', function () use ( $res ) {
		$names = function ( $ids ) {
			return implode( ', ', array_map( function ( $id ) { return get_the_title( $id ) . ' (' . $id . ')'; }, $ids ) );
		};
		echo '<div class="notice notice-success is-dismissible"><p><strong>Категория «Акции» сверена со скидками.</strong> ';
		echo 'Добавлено: ' . count( $res['added'] ) . ( $res['added'] ? ' — ' . esc_html( $names( $res['added'] ) ) : '' ) . '. ';
		echo 'Убрано: ' . count( $res['removed'] ) . ( $res['removed'] ? ' — ' . esc_html( $names( $res['removed'] ) ) : '' ) . '.</p></div>';
	} );
} );
