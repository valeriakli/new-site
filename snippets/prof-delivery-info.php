<?php
/**
 * Название:      Блок «Доставка и оплата» на товарах и в категориях
 * Что делает:    Выводит короткий блок об условиях доставки и оплаты по Минску и Беларуси:
 *                на странице товара — отдельной вкладкой «Доставка и оплата» после «Описание» и «Бренд»,
 *                в категориях, брендах и магазине — под списком товаров (кроме поиска),
 *                на страницах Elementor — шорткодом [prof_delivery_info] (старое имя [prof_region_info] тоже работает).
 *                В тексте подставляется название товара или раздела — так у каждой страницы свой текст для поиска.
 *                Текст сразу есть в HTML страницы (вкладка только свёрнута), его видят Google и кеш.
 *                Тексты и ссылка — в prof_delivery_info_data() ниже. При смене условий доставки или оплаты
 *                править здесь вместе со страницей /delivery-payment/ и prof-free-shipping-min.
 * Где активен:   фронтенд; woocommerce_product_tabs (98, вкладка с priority 50),
 *                woocommerce_after_shop_loop (50), wp_head (20) — стили, init — шорткоды.
 * Где проверено: ещё не проверен на сайте (сайта нет). Проверить: товар → вкладка «Доставка и оплата»;
 *                категория и бренд → блок под товарами; поиск — блока нет.
 * Зависимости:   WooCommerce. Пока активен prof-multiregion (он выводит такой же блок), этот сниппет
 *                ничего не выводит — можно вставить заранее, заработает после выключения prof-multiregion.
 * Источник:      раздел 12 prof-multiregion v3.1 (profcosmetica) — только тексты для Беларуси,
 *                без регионов RU / GE. Канонический адрес (canonical) для BY prof-multiregion не менял —
 *                его задаёт AIOSEO, переносить нечего.
 * Обновлён:      2026-11-25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Пока активен prof-multiregion, он сам выводит этот блок — не дублируем.
 * Проверяется в момент вывода, а не при загрузке: порядок загрузки сниппетов не гарантирован.
 */
function prof_delivery_info_off() {
	return function_exists( 'prof_get_region' );
}

/**
 * Тексты блока. ПРОВЕРИТЬ С ОЛЕЙ перед запуском: пороги и цены доставки, адрес самовывоза
 * (здесь адрес profcosmetica), способы оплаты по договору bePaid нового юрлица.
 */
function prof_delivery_info_data() {
	return array(
		'title'   => 'Доставка и оплата по Минску и Беларуси',
		// %s — название товара или раздела.
		'intro'   => 'Заказать %s с доставкой по Минску и всей Беларуси можно онлайн, заказы обрабатываем круглосуточно.',
		// Если названия нет (магазин, шорткод на обычной странице).
		'subject' => 'косметику',
		'items'   => array(
			'Курьером по Минску — на следующий рабочий день. Бесплатно при заказе от 130 BYN, при заказе до 130 BYN — 10 BYN.',
			'Самовывоз в Минске: ул. Братская, 11, ЖК «Минск-Мир», в согласованное время.',
			'По Беларуси — Белпочта и Европочта, от 2 до 5 дней, отправка ежедневно, трек-номер по SMS.',
			'Оплата при получении (наличными, ЕРИП, картой) или онлайн: банковской картой, Google Pay, Samsung Pay, картой рассрочки «Халва».',
			'Цена указана в белорусских рублях (BYN).',
		),
		'link'    => 'delivery-payment',
	);
}

function prof_delivery_info_html( $subject = '' ) {
	$d = prof_delivery_info_data();
	if ( '' === $subject ) {
		$subject = $d['subject'];
	}

	$html  = '<section class="prof-region-info" aria-label="' . esc_attr( $d['title'] ) . '">';
	$html .= '<h2 class="prof-region-info__title">' . esc_html( $d['title'] ) . '</h2>';
	$html .= '<p>' . esc_html( sprintf( $d['intro'], $subject ) ) . '</p>';
	$html .= '<ul>';
	foreach ( $d['items'] as $item ) {
		$html .= '<li>' . esc_html( $item ) . '</li>';
	}
	$html .= '</ul>';
	$html .= '<p><a href="' . esc_url( home_url( user_trailingslashit( $d['link'] ) ) ) . '">Подробнее о доставке и оплате</a></p>';
	$html .= '</section>';

	return $html;
}

// Товар: вкладка «Доставка и оплата» после «Описание» (10) и «Бренд».
add_filter(
	'woocommerce_product_tabs',
	function ( $tabs ) {
		if ( prof_delivery_info_off() ) {
			return $tabs;
		}
		$tabs['prof_delivery'] = array(
			'title'    => 'Доставка и оплата',
			'priority' => 50,
			'callback' => function () {
				global $product;
				$name = ( $product instanceof WC_Product ) ? '«' . wp_strip_all_tags( $product->get_name() ) . '»' : '';
				echo prof_delivery_info_html( $name ); // phpcs:ignore WordPress.Security.EscapeOutput
			},
		);
		return $tabs;
	},
	98
);

// Категории, бренды, магазин: под списком товаров.
add_action(
	'woocommerce_after_shop_loop',
	function () {
		if ( prof_delivery_info_off() || is_search() ) {
			return;
		}
		$name = '';
		if ( is_product_taxonomy() ) {
			$term = get_queried_object();
			if ( $term && ! empty( $term->name ) ) {
				$name = 'товары из раздела «' . wp_strip_all_tags( $term->name ) . '»';
			}
		}
		echo prof_delivery_info_html( $name ); // phpcs:ignore WordPress.Security.EscapeOutput
	},
	50
);

// Шорткод для страниц Elementor. [prof_region_info] — старое имя с profcosmetica,
// оно может стоять на скопированных страницах.
function prof_delivery_info_shortcode() {
	$name = '';
	if ( function_exists( 'is_product' ) && is_product() ) {
		$product = wc_get_product( get_the_ID() );
		if ( $product ) {
			$name = '«' . wp_strip_all_tags( $product->get_name() ) . '»';
		}
	}
	return prof_delivery_info_html( $name );
}
add_action(
	'init',
	function () {
		add_shortcode( 'prof_delivery_info', 'prof_delivery_info_shortcode' );
		if ( ! prof_delivery_info_off() ) {
			add_shortcode( 'prof_region_info', 'prof_delivery_info_shortcode' );
		}
	}
);

// Стили. Классы те же, что на profcosmetica, — если в CSS темы есть правила для них, они сработают.
add_action(
	'wp_head',
	function () {
		if ( prof_delivery_info_off() ) {
			return;
		}
		?>
		<style id="prof-delivery-info-css">
		.prof-region-info{margin:24px 0;padding:16px 20px;border:1px solid rgba(0,0,0,.1);border-radius:8px;font-size:14px;line-height:1.5}
		.prof-region-info__title{font-size:16px;margin:0 0 8px}
		.prof-region-info ul{margin:8px 0 8px 18px;padding:0}
		.prof-region-info li{margin:4px 0}
		.prof-region-info p{margin:6px 0}
		/* Во вкладке товара — без рамки, как обычный текст вкладки */
		.woocommerce-Tabs-panel .prof-region-info{margin:0;padding:0;border:0;border-radius:0;font-size:inherit}
		.woocommerce-Tabs-panel .prof-region-info__title{font-size:1.25em}
		</style>
		<?php
	},
	20
);
