<?php
/**
 * Название:      prof-shortcode-cache — кеш готового HTML тяжёлых шорткодов ([products], [my_brand_carousel])
 * Что делает:    Сохраняет готовый вывод шорткода на 15 минут и при следующих сборках страницы отдаёт его
 *                из кеша вместо повторного рендера (на главной два [products] по 4 товара занимали
 *                ~190–310 мс, карусель брендов ~65–85 мс на каждой некешированной сборке, замер
 *                prof-speed-diag v3, 2026-11-07).
 *                Ключ кеша: шорткод + атрибуты + валюта + собственный счётчик версии
 *                (опция prof_sc_ver). Счётчик увеличивается при сохранении/изменении товара, остатков,
 *                брендов (pwb-brand, в т. ч. логотипа) и категорий товаров — тогда весь кеш сбрасывается.
 *                Остальное («Бестселлеры», остатки при заказах) обновляется не реже раза в 15 минут.
 *                (Общие «версии» WordPress wp_cache_get_last_changed() не используются: на сайте они
 *                меняются почти на каждом запросе, и кеш не находился.)
 *                В кеш кладёт только с адреса БЕЗ параметров (ссылки «В корзину» строятся из текущего URL,
 *                иначе ?prof_diag / ?utm_ попали бы в ссылки у всех); отдаёт из кеша при любом адресе.
 *                Чистые адреса собирает заново кеш страниц WP.com примерно раз в 5 минут — тогда блок и
 *                заполняется.
 *                Не кеширует: для вошедших пользователей (свои цены/скидки по ролям), в админке, AJAX,
 *                REST, cron, в редакторе и предпросмотре Elementor.
 *                Побочный эффект: товары из закешированного блока не попадают в события аналитики
 *                «просмотр списка товаров» (view_item_list), которые плагины собирают во время рендера.
 *                Отладка: при cookie wordpress_logged_in_profscdebug=1 (такие запросы WP.com не кеширует)
 *                в вывод шорткода добавляется комментарий <!-- prof-sc: … --> (попадание/промах/сохранение).
 *                Список шорткодов — в PROF_SC_CACHE_TAGS ниже. Добавлять только шорткоды, которые
 *                не подключают свои скрипты/стили при выводе (у [my_brand_carousel] Swiper подключается
 *                отдельно на wp_enqueue_scripts — кешировать можно).
 *                Отличие от profcosmetica: регион убран из ключа кеша и из prof_sc_request_uri().
 * Где активен:   только фронтенд; фильтры pre_do_shortcode_tag / do_shortcode_tag (приоритет 20);
 *                сброс — хуки сохранения товаров, остатков и терминов (работают и в админке).
 * Зависимости:   WooCommerce.
 *                Постоянный объектный кеш WP.com (memcached) — transients хранятся в нём.
 *                С 2026-11-15: кешируется и [product_categories_with_subcategories] (категории на главной);
 *                кеш сбрасывается и при сохранении настроек темы (customize_save_after).
 * Источник:      profcosmetica/snippets/prof-shortcode-cache.php — перенесён без изменений кода, кроме отмеченного.
 * Обновлён:      2026-11-25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'PROF_SC_CACHE_TAGS' ) ) {
	// product_categories_with_subcategories — квадратики категорий на главной (prof-home-categories):
	// подкатегории всех категорий выводятся сразу, свои стили/скрипт — внутри вывода, кешировать можно.
	define( 'PROF_SC_CACHE_TAGS', array( 'products', 'my_brand_carousel', 'product_categories_with_subcategories' ) );
}
if ( ! defined( 'PROF_SC_CACHE_TTL' ) ) {
	define( 'PROF_SC_CACHE_TTL', 15 * MINUTE_IN_SECONDS );
}

function prof_sc_debug_on() {
	return isset( $_COOKIE['wordpress_logged_in_profscdebug'] );
}

function prof_sc_debug_note( $text ) {
	return prof_sc_debug_on() ? '<!-- prof-sc: ' . esc_html( $text ) . ' -->' : '';
}

/**
 * Можно ли в этом запросе пользоваться кешем. Возвращает '' (можно) или причину отказа.
 */
function prof_sc_skip_reason( $tag ) {
	if ( ! in_array( $tag, PROF_SC_CACHE_TAGS, true ) ) {
		return 'not-in-list';
	}
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return 'admin/ajax/cron/rest';
	}
	if ( is_user_logged_in() ) {
		return 'logged-in';
	}
	if ( isset( $_GET['elementor-preview'] ) || isset( $_GET['preview'] ) ) {
		return 'preview';
	}
	if ( ! function_exists( 'get_woocommerce_currency' ) ) {
		return 'no-woocommerce';
	}
	return '';
}

/**
 * Адрес текущего запроса.
 */
function prof_sc_request_uri() {
	return (string) ( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '' );
}

function prof_sc_cache_key( $tag, $attr ) {
	return 'prof_sc3_' . md5(
		$tag . '|' . wp_json_encode( (array) $attr ) . '|' . get_woocommerce_currency()
		. '|' . (int) get_option( 'prof_sc_ver', 1 )
	);
}

// Отдать из кеша (до рендера).
add_filter(
	'pre_do_shortcode_tag',
	static function ( $return, $tag, $attr ) {
		if ( false !== $return || ! in_array( $tag, PROF_SC_CACHE_TAGS, true ) ) {
			return $return;
		}
		if ( '' !== prof_sc_skip_reason( $tag ) ) {
			return $return;
		}
		$key    = prof_sc_cache_key( $tag, $attr );
		$cached = get_transient( $key );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached . prof_sc_debug_note( 'HIT ' . $tag . ' ' . $key );
		}
		return $return;
	},
	20,
	3
);

// Сохранить в кеш (после рендера).
add_filter(
	'do_shortcode_tag',
	static function ( $output, $tag, $attr ) {
		if ( ! in_array( $tag, PROF_SC_CACHE_TAGS, true ) ) {
			return $output;
		}
		$reason = prof_sc_skip_reason( $tag );
		if ( '' === $reason && false !== strpos( prof_sc_request_uri(), '?' ) ) {
			// Ссылки «В корзину» WooCommerce строит из текущего URL — с параметрами в кеш не кладём.
			$reason = 'url-has-query';
		}
		if ( '' === $reason && ( ! is_string( $output ) || '' === trim( $output ) ) ) {
			$reason = 'empty-output';
		}
		if ( '' !== $reason ) {
			return $output . prof_sc_debug_note( 'MISS ' . $tag . ', not stored: ' . $reason );
		}
		$key = prof_sc_cache_key( $tag, $attr );
		set_transient( $key, $output, PROF_SC_CACHE_TTL );
		return $output . prof_sc_debug_note( 'MISS ' . $tag . ', stored ' . $key );
	},
	20,
	3
);

/**
 * Сброс кеша: увеличиваем счётчик версии (не чаще одного раза за запрос).
 */
function prof_sc_bump_version() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	update_option( 'prof_sc_ver', (int) get_option( 'prof_sc_ver', 1 ) + 1, true );
}
add_action( 'save_post_product', 'prof_sc_bump_version' );
add_action( 'woocommerce_update_product', 'prof_sc_bump_version' );
add_action( 'woocommerce_product_set_stock', 'prof_sc_bump_version' );
add_action( 'woocommerce_variation_set_stock', 'prof_sc_bump_version' );
add_action( 'woocommerce_product_set_stock_status', 'prof_sc_bump_version' );
// Настройки темы («Внешний вид» → «Настроить»): например, текст значка распродажи в карточках товаров.
add_action( 'customize_save_after', 'prof_sc_bump_version' );
add_action(
	'edited_term',
	static function ( $term_id, $tt_id, $taxonomy ) {
		if ( in_array( $taxonomy, array( 'pwb-brand', 'product_cat' ), true ) ) {
			prof_sc_bump_version();
		}
	},
	10,
	3
);
add_action(
	'created_term',
	static function ( $term_id, $tt_id, $taxonomy ) {
		if ( in_array( $taxonomy, array( 'pwb-brand', 'product_cat' ), true ) ) {
			prof_sc_bump_version();
		}
	},
	10,
	3
);
add_action(
	'delete_term',
	static function ( $term_id, $tt_id, $taxonomy ) {
		if ( in_array( $taxonomy, array( 'pwb-brand', 'product_cat' ), true ) ) {
			prof_sc_bump_version();
		}
	},
	10,
	3
);
// Логотип бренда хранится в мете термина pwb_brand_image.
foreach ( array( 'added_term_meta', 'updated_term_meta', 'deleted_term_meta' ) as $prof_sc_meta_hook ) {
	add_action(
		$prof_sc_meta_hook,
		static function ( $meta_id, $term_id, $meta_key ) {
			if ( 'pwb_brand_image' === $meta_key ) {
				prof_sc_bump_version();
			}
		},
		10,
		3
	);
}
