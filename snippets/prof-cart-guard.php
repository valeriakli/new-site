<?php
/**
 * Название:      prof-cart-guard (защита корзины от ботов)
 * Что делает:    Запрещает добавление в корзину и оформление заказа для стран из чёрного списка (сейчас US)
 *                и ограничивает частоту добавлений в корзину с одного IP (30 за 10 минут) — только для посетителей
 *                не из Беларуси и не для администраторов (у мобильных операторов сотни людей на одном IP).
 *                Закрывает все пути: обычная кнопка и ссылка ?add-to-cart=, AJAX (wc-ajax), Store API
 *                (блочные корзина/оформление), классическое и блочное оформление заказа.
 *                Заменяет блокировку всего сайта плагином IP2Location Country Blocker, который отключал кеш страниц.
 *                Проверка страны: /?prof_geo_test=pc7319 — показывает, какую страну видит сайт (для диагностики).
 * Где активен:   woocommerce_add_to_cart_validation (10, 20), woocommerce_store_api_validate_add_to_cart,
 *                woocommerce_after_checkout_validation, woocommerce_store_api_checkout_update_order_from_request,
 *                template_redirect (только тестовый адрес).
 * Зависимости:   WooCommerce (WC_Geolocation). Заменяет старый сниппет «запрет корзины для США + лимит».
 * Источник:      profcosmetica/snippets/prof-cart-guard.php — без лимита остаются только посетители из BY (было BY / RU / GE).
 * Обновлён:      2026-11-25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Страны, которым запрещены корзина и заказ (ISO-коды). */
function prof_cart_guard_blocked_countries() {
	return array( 'US' );
}

/** IP посетителя (с учётом прокси WP.com). */
function prof_cart_guard_ip() {
	return class_exists( 'WC_Geolocation' ) ? (string) WC_Geolocation::get_ip_address() : '';
}

/** Страна посетителя; результат кешируется на сутки, чтобы не определять заново при каждом действии. */
function prof_cart_guard_country( $ip = null ) {
	if ( ! class_exists( 'WC_Geolocation' ) ) {
		return '';
	}
	$ip = null === $ip ? prof_cart_guard_ip() : $ip;
	if ( '' === $ip ) {
		return '';
	}
	$key    = 'prof_geo_' . md5( $ip );
	$cached = get_transient( $key );
	if ( false !== $cached ) {
		return (string) $cached;
	}
	$geo     = WC_Geolocation::geolocate_ip( $ip, false, true );
	$country = ! empty( $geo['country'] ) ? strtoupper( $geo['country'] ) : '';
	set_transient( $key, $country, DAY_IN_SECONDS );
	return $country;
}

function prof_cart_guard_is_blocked() {
	return in_array( prof_cart_guard_country(), prof_cart_guard_blocked_countries(), true );
}

/** Страны, на которые лимит частоты не действует (свои покупатели; мобильные операторы дают сотням людей один IP). */
function prof_cart_guard_trusted_countries() {
	return array( 'BY' );
}

/** Сообщение покупателю при превышении лимита. */
function prof_cart_guard_limit_msg() {
	return 'Слишком много добавлений в корзину подряд. Пожалуйста, подождите несколько минут и попробуйте снова.';
}

/**
 * Лимит добавлений в корзину: true, если лимит превышен.
 * Не действует для администраторов/менеджеров магазина и посетителей из BY.
 * Окно фиксированное: отсчёт от первого добавления, а не продлевается с каждым.
 */
function prof_cart_guard_rate_limited() {
	if ( current_user_can( 'manage_woocommerce' ) ) {
		return false;
	}
	if ( in_array( prof_cart_guard_country(), prof_cart_guard_trusted_countries(), true ) ) {
		return false;
	}
	$ip = prof_cart_guard_ip();
	if ( '' === $ip ) {
		return false;
	}
	$limit  = 30;                    // добавлений за окно с одного IP
	$window = 10 * MINUTE_IN_SECONDS; // длина окна
	$key  = 'atc_limit2_' . md5( $ip );
	$data = get_transient( $key );
	$now  = time();
	if ( ! is_array( $data ) || empty( $data['start'] ) || $now - (int) $data['start'] >= $window ) {
		$data = array(
			'start' => $now,
			'count' => 0,
		);
	}
	if ( (int) $data['count'] >= $limit ) {
		return true;
	}
	++$data['count'];
	set_transient( $key, $data, max( 60, $window - ( $now - (int) $data['start'] ) ) );
	return false;
}

/** Ошибка для Store API (блочная корзина и оформление). */
function prof_cart_guard_store_api_error( $code, $message ) {
	if ( class_exists( '\Automattic\WooCommerce\StoreApi\Exceptions\RouteException' ) ) {
		throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( $code, $message, 403 );
	}
	throw new Exception( $message );
}

// 1. Обычное добавление в корзину: кнопка, ссылка ?add-to-cart=, wc-ajax add_to_cart.
add_filter(
	'woocommerce_add_to_cart_validation',
	static function ( $passed ) {
		if ( $passed && prof_cart_guard_is_blocked() ) {
			wc_add_notice( 'Unavailable.', 'error' );
			return false;
		}
		return $passed;
	},
	10
);
add_filter(
	'woocommerce_add_to_cart_validation',
	static function ( $passed ) {
		if ( $passed && prof_cart_guard_rate_limited() ) {
			wc_add_notice( prof_cart_guard_limit_msg(), 'error' );
			return false;
		}
		return $passed;
	},
	20
);

// 2. Store API (блочная корзина): добавление товара.
add_action(
	'woocommerce_store_api_validate_add_to_cart',
	static function () {
		if ( prof_cart_guard_is_blocked() ) {
			prof_cart_guard_store_api_error( 'prof_cart_blocked', 'Unavailable.' );
		}
		if ( prof_cart_guard_rate_limited() ) {
			prof_cart_guard_store_api_error( 'prof_cart_rate_limited', prof_cart_guard_limit_msg() );
		}
	}
);

// 3. Классическое оформление заказа.
add_action(
	'woocommerce_after_checkout_validation',
	static function ( $data, $errors ) {
		if ( prof_cart_guard_is_blocked() ) {
			$errors->add( 'prof_checkout_blocked', 'Unavailable.' );
		}
	},
	10,
	2
);

// 4. Блочное оформление заказа (Store API).
add_action(
	'woocommerce_store_api_checkout_update_order_from_request',
	static function () {
		if ( prof_cart_guard_is_blocked() ) {
			prof_cart_guard_store_api_error( 'prof_checkout_blocked', 'Unavailable.' );
		}
	}
);

// 5. Тестовый адрес: какую страну и откуда видит сайт. Без кеша, IP показывается частично.
add_action(
	'template_redirect',
	static function () {
		if ( ! isset( $_GET['prof_geo_test'] ) || 'pc7319' !== $_GET['prof_geo_test'] ) {
			return;
		}
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		$ip      = prof_cart_guard_ip();
		$t       = microtime( true );
		$geo     = class_exists( 'WC_Geolocation' ) ? WC_Geolocation::geolocate_ip( $ip, false, false ) : array();
		$t_local = round( ( microtime( true ) - $t ) * 1000 );
		$t       = microtime( true );
		$geo_api = class_exists( 'WC_Geolocation' ) ? WC_Geolocation::geolocate_ip( $ip, false, true ) : array();
		$t_api   = round( ( microtime( true ) - $t ) * 1000 );

		echo 'IP: ' . preg_replace( '/[0-9a-f]+$/i', 'xxx', $ip ) . "\n";
		echo 'Страна без внешних сервисов (заголовки/MaxMind): ' . ( ! empty( $geo['country'] ) ? $geo['country'] : '—' ) . " ({$t_local} ms)\n";
		echo 'Страна с внешними сервисами: ' . ( ! empty( $geo_api['country'] ) ? $geo_api['country'] : '—' ) . " ({$t_api} ms)\n";
		echo 'Итог prof_cart_guard_country(): ' . ( prof_cart_guard_country( $ip ) ?: '—' ) . "\n";
		echo 'Заблокирован: ' . ( prof_cart_guard_is_blocked() ? 'да' : 'нет' ) . "\n";
		echo "Гео-заголовки сервера:\n";
		foreach ( $_SERVER as $k => $v ) {
			if ( preg_match( '/COUNTRY|GEO/i', $k ) ) {
				echo '  ' . $k . ' = ' . ( is_scalar( $v ) ? $v : '…' ) . "\n";
			}
		}
		exit;
	}
);
