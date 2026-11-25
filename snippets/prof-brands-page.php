<?php
/**
 * Название:      prof-brands-page (на сайте — «Страница брендов: поиск и фильтры»)
 * Что делает:    Заменяет вывод шорткода Perfect Brands [pwb-all-brands] на странице /brands/
 *                своей сеткой брендов (тот же шорткод, править страницу в Elementor не нужно;
 *                также работает [prof_brands]). Над сеткой: поиск по названию (сразу при вводе,
 *                понимает ввод в русской раскладке: «зфгдф» → paula), алфавитный указатель
 *                (только буквы, на которые есть бренды), фильтр направления «Лицо / Волосы / Тело /
 *                Мужчинам» (по категориям товаров бренда, считается автоматически) и «Есть акции».
 *                На карточке: логотип, название, число товаров, страна (поле ACF
 *                strana-proizvoditel у бренда, если заполнено), значок «Есть акции».
 *                «Акции» — товары бренда в категории «Акции» (ярлык sales), как в фильтре каталога,
 *                а не все товары со сниженной ценой. Когда включён фильтр «Есть акции», карточка
 *                ведёт на акционные товары бренда: /brand/<бренд>/swoof/product_cat-sales/
 *                (фильтр WOOF; canonical этой страницы — страница бренда).
 *                Порядок брендов — как в таксономии (Taxonomy Terms Order), как и раньше.
 *                Логотипы сразу нужного размера (размер medium, без обрезки), поэтому старый скрипт
 *                в HTML-виджете страницы, менявший ?resize= на ?fit=, больше не нужен.
 *                Выбранные фильтры запоминаются на время сессии (возврат «Назад» со страницы бренда).
 *                CSS снимает max-height с контейнера Elementor вокруг блока: старый HTML-виджет страницы
 *                (77e3c24) фиксировал высоту при загрузке, и после смены фильтра бренды обрезались.
 *                На ширине до 767px блок страницы сдвинут на 28px вниз: шапка Blocksy закреплена поверх
 *                содержимого и срезала заголовок «Бренды».
 *                Новые бренды появляются на странице сами: кеш данных сбрасывается при создании бренда.
 *                2026-11-15: клик по карточке бренда больше не выбирает букву (скрипт искал кнопки букв
 *                по data-letter, а этот атрибут есть и у карточек).
 * Где активен:   фронтенд; шорткод переопределяется на wp_loaded. Данные (бренды, количества,
 *                направления, акции) кешируются в transient prof_brands_page_v2 на 6 ч и
 *                сбрасываются при правке товаров, брендов и категорий.
 *                CSS и JS выводятся вместе с шорткодом, на других страницах их нет.
 * Зависимости:   WooCommerce, Perfect Brands for WooCommerce (pwb-brand, мета pwb_brand_image),
 *                категории верхнего уровня face, hair-care, body-care, for-man и категория sales;
 *                WOOF (адрес фильтра swoof/product_cat-sales/).
 *                Выключить сниппет — вернётся стандартный вывод Perfect Brands.
 * Источник:      profcosmetica/snippets/prof-brands-page.php — код без изменений; убраны упоминания регионов в шапке (префиксы /ru/, /ge/ ставил prof-multiregion).
 * Обновлён:      2026-11-25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Направления: ярлык категории верхнего уровня => подпись в фильтре */
function prof_brands_page_dirs() {
	return array(
		'face'      => 'Лицо',
		'hair-care' => 'Волосы',
		'body-care' => 'Тело',
		'for-man'   => 'Мужчинам',
	);
}

/* Ярлык категории «Акции» — по ней считается значок и фильтр «Есть акции» */
if ( ! defined( 'PROF_BRANDS_SALE_CAT' ) ) define( 'PROF_BRANDS_SALE_CAT', 'sales' );

/* Подмена шорткода Perfect Brands */
add_action( 'wp_loaded', function () {
	remove_shortcode( 'pwb-all-brands' );
	add_shortcode( 'pwb-all-brands', 'prof_brands_page_shortcode' );
	add_shortcode( 'prof_brands', 'prof_brands_page_shortcode' );
} );

/* ---------- Данные о брендах (кеш 6 ч) ---------- */

function prof_brands_page_data() {
	$data = get_transient( 'prof_brands_page_v2' );
	if ( is_array( $data ) ) {
		return $data;
	}

	$brands = get_terms( array(
		'taxonomy'   => 'pwb-brand',
		'hide_empty' => false,
	) );
	if ( empty( $brands ) || is_wp_error( $brands ) ) {
		return array();
	}

	// Родитель каждой категории — чтобы подняться до категории верхнего уровня
	$cat_parent = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'fields' => 'id=>parent' ) );
	$cat_slug   = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'fields' => 'id=>slug' ) );
	if ( is_wp_error( $cat_parent ) ) $cat_parent = array();
	if ( is_wp_error( $cat_slug ) )   $cat_slug   = array();
	$dirs = prof_brands_page_dirs();

	$top_slug = function ( $id ) use ( $cat_parent, $cat_slug ) {
		$guard = 0;
		while ( ! empty( $cat_parent[ $id ] ) && $guard++ < 10 ) {
			$id = (int) $cat_parent[ $id ];
		}
		return isset( $cat_slug[ $id ] ) ? $cat_slug[ $id ] : '';
	};

	// Товары, видимые в каталоге
	$not_in = array( 'exclude-from-catalog' );
	if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
		$not_in[] = 'outofstock';
	}
	$ids = get_posts( array(
		'post_type'        => 'product',
		'post_status'      => 'publish',
		'numberposts'      => -1,
		'fields'           => 'ids',
		'no_found_rows'    => true,
		'suppress_filters' => true,
		'tax_query'        => array( array(
			'taxonomy' => 'product_visibility',
			'field'    => 'name',
			'terms'    => $not_in,
			'operator' => 'NOT IN',
		) ),
	) );

	// Бренды и категории всех товаров одним запросом
	$by_product = array();
	if ( $ids ) {
		$rels = wp_get_object_terms( $ids, array( 'pwb-brand', 'product_cat' ), array( 'fields' => 'all_with_object_id' ) );
		if ( ! is_wp_error( $rels ) ) {
			foreach ( $rels as $t ) {
				$by_product[ $t->object_id ][ $t->taxonomy ][] = (int) $t->term_id;
			}
		}
	}

	$stats = array();
	foreach ( $by_product as $pid => $tax ) {
		if ( empty( $tax['pwb-brand'] ) ) continue;
		$p_dirs  = array();
		$p_sale  = false;
		foreach ( isset( $tax['product_cat'] ) ? $tax['product_cat'] : array() as $cid ) {
			$s = $top_slug( $cid );
			if ( isset( $dirs[ $s ] ) ) $p_dirs[ $s ] = true;
			if ( isset( $cat_slug[ $cid ] ) && PROF_BRANDS_SALE_CAT === $cat_slug[ $cid ] ) $p_sale = true;
		}
		foreach ( $tax['pwb-brand'] as $bid ) {
			if ( ! isset( $stats[ $bid ] ) ) $stats[ $bid ] = array( 'count' => 0, 'dirs' => array(), 'sale' => false );
			$stats[ $bid ]['count']++;
			$stats[ $bid ]['dirs'] += $p_dirs;
			if ( $p_sale ) $stats[ $bid ]['sale'] = true;
		}
	}

	$data = array();
	foreach ( $brands as $b ) {
		$st      = isset( $stats[ $b->term_id ] ) ? $stats[ $b->term_id ] : array( 'count' => 0, 'dirs' => array(), 'sale' => false );
		$country = get_term_meta( $b->term_id, 'strana-proizvoditel', true );
		if ( is_array( $country ) ) $country = implode( ', ', array_filter( $country, 'is_string' ) );

		$name   = html_entity_decode( $b->name, ENT_QUOTES, 'UTF-8' );
		$first  = mb_strtoupper( mb_substr( $name, 0, 1, 'UTF-8' ), 'UTF-8' );
		$letter = preg_match( '/^\p{L}$/u', $first ) ? $first : '#';

		$data[] = array(
			'id'      => (int) $b->term_id,
			'name'    => $name,
			'slug'    => $b->slug,
			'logo'    => (int) get_term_meta( $b->term_id, 'pwb_brand_image', true ),
			'country' => is_string( $country ) ? trim( $country ) : '',
			'letter'  => $letter,
			'count'   => $st['count'],
			'dirs'    => array_keys( $st['dirs'] ),
			'sale'    => $st['sale'],
		);
	}

	set_transient( 'prof_brands_page_v2', $data, 6 * HOUR_IN_SECONDS );
	return $data;
}

/* Сброс кеша при изменениях */
function prof_brands_page_flush() {
	delete_transient( 'prof_brands_page_v2' );
}
add_action( 'save_post_product', 'prof_brands_page_flush' );
add_action( 'trashed_post', 'prof_brands_page_flush' );
add_action( 'woocommerce_product_set_stock_status', 'prof_brands_page_flush' );
foreach ( array( 'pwb-brand', 'product_cat' ) as $prof_bp_tax ) {
	add_action( "created_{$prof_bp_tax}", 'prof_brands_page_flush' );
	add_action( "edited_{$prof_bp_tax}", 'prof_brands_page_flush' );
	add_action( "delete_{$prof_bp_tax}", 'prof_brands_page_flush' );
}
unset( $prof_bp_tax );

function prof_brands_page_plural( $n, $one, $few, $many ) {
	$n  = abs( (int) $n ) % 100;
	$n1 = $n % 10;
	if ( $n > 10 && $n < 20 ) return $many;
	if ( $n1 > 1 && $n1 < 5 ) return $few;
	if ( 1 === $n1 ) return $one;
	return $many;
}

/* ---------- Вывод ---------- */

function prof_brands_page_shortcode() {
	$data = prof_brands_page_data();
	if ( ! $data ) {
		return '';
	}
	$dirs = prof_brands_page_dirs();

	// Какие направления, буквы и акции реально есть
	$used_dirs = array();
	$letters   = array();
	$has_sale  = false;
	foreach ( $data as $b ) {
		foreach ( $b['dirs'] as $d ) $used_dirs[ $d ] = true;
		$letters[ $b['letter'] ] = true;
		if ( $b['sale'] ) $has_sale = true;
	}
	$letters = array_keys( $letters );
	usort( $letters, function ( $a, $b ) {
		$ca = preg_match( '/^[A-Z]$/', $a ) ? 0 : ( '#' === $a ? 2 : 1 );
		$cb = preg_match( '/^[A-Z]$/', $b ) ? 0 : ( '#' === $b ? 2 : 1 );
		return $ca !== $cb ? $ca - $cb : strcmp( $a, $b );
	} );

	ob_start();
	?>
<div class="prof-brands" data-prof-brands>
	<div class="prof-brands__tools">
		<div class="prof-brands__search">
			<input type="search" placeholder="Найти бренд" aria-label="Поиск бренда" autocomplete="off" spellcheck="false">
		</div>
		<div class="prof-brands__chips" role="group" aria-label="Направление">
			<button type="button" class="prof-brands__chip is-active" data-dir="" aria-pressed="true">Все</button>
			<?php foreach ( $dirs as $slug => $label ) : if ( empty( $used_dirs[ $slug ] ) ) continue; ?>
			<button type="button" class="prof-brands__chip" data-dir="<?php echo esc_attr( $slug ); ?>" aria-pressed="false"><?php echo esc_html( $label ); ?></button>
			<?php endforeach; ?>
			<?php if ( $has_sale ) : ?>
			<button type="button" class="prof-brands__chip prof-brands__chip--sale" data-sale-toggle aria-pressed="false">Есть акции</button>
			<?php endif; ?>
		</div>
		<?php if ( count( $letters ) > 1 ) : ?>
		<div class="prof-brands__letters" role="group" aria-label="Первая буква">
			<button type="button" class="prof-brands__letter is-active" data-letter="" aria-pressed="true">Все</button>
			<?php foreach ( $letters as $l ) : ?>
			<button type="button" class="prof-brands__letter" data-letter="<?php echo esc_attr( $l ); ?>" aria-pressed="false"><?php echo esc_html( $l ); ?></button>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>
	</div>
	<p class="prof-brands__status" aria-live="polite"></p>
	<ul class="prof-brands__grid">
		<?php foreach ( $data as $i => $b ) :
			$link = get_term_link( $b['id'], 'pwb-brand' );
			if ( is_wp_error( $link ) ) continue;
			$meta = array();
			if ( $b['count'] > 0 ) {
				$meta[] = $b['count'] . ' ' . prof_brands_page_plural( $b['count'], 'товар', 'товара', 'товаров' );
			}
			if ( '' !== $b['country'] ) {
				$meta[] = $b['country'];
			}
			$img = $b['logo'] ? wp_get_attachment_image( $b['logo'], 'medium', false, array(
				'alt'      => $b['name'],
				'loading'  => $i < 12 ? 'eager' : 'lazy',
				'decoding' => 'async',
				'sizes'    => '120px',
				'class'    => 'prof-brands__img',
			) ) : '';
			?>
		<li class="prof-brands__item"
			data-search="<?php echo esc_attr( $b['name'] . ' ' . str_replace( '-', '', $b['slug'] ) ); ?>"
			data-letter="<?php echo esc_attr( $b['letter'] ); ?>"
			data-dirs="<?php echo esc_attr( implode( ' ', $b['dirs'] ) ); ?>"
			<?php if ( $b['sale'] ) : ?>data-sale="1" data-sale-href="<?php echo esc_url( trailingslashit( $link ) . 'swoof/product_cat-' . PROF_BRANDS_SALE_CAT . '/' ); ?>"<?php endif; ?>>
			<a class="prof-brands__card" href="<?php echo esc_url( $link ); ?>">
				<?php if ( $b['sale'] ) : ?><span class="prof-brands__badge">Есть акции</span><?php endif; ?>
				<span class="prof-brands__logo"><?php echo $img ? $img : '<span class="prof-brands__noimg">' . esc_html( $b['name'] ) . '</span>'; ?></span>
				<span class="prof-brands__name"><?php echo esc_html( $b['name'] ); ?></span>
				<?php if ( $meta ) : ?><span class="prof-brands__meta"><?php echo esc_html( implode( ' · ', $meta ) ); ?></span><?php endif; ?>
			</a>
		</li>
		<?php endforeach; ?>
	</ul>
	<div class="prof-brands__empty" hidden>
		Ничего не нашлось. <button type="button" class="prof-brands__reset">Сбросить фильтры</button>
	</div>
</div>
<style>
/* Старый HTML-виджет страницы фиксировал max-height контейнера по высоте при загрузке — с фильтрами бренды обрезались */
.e-con:has([data-prof-brands]){max-height:none!important}
/* Шапка Blocksy закреплена поверх страницы без отступа под неё: на телефоне и планшете она срезала заголовок «Бренды» */
@media (max-width:767px){.e-con:has([data-prof-brands]){padding-top:28px!important}}
.prof-brands{--pb-accent:var(--color-2,#8250ab);--pb-text:var(--color-4,#1a1a1a);--pb-muted:#6b6b6b;--pb-line:#e6e1ec;margin:0 auto;max-width:1200px}
.prof-brands__tools{display:flex;flex-direction:column;gap:18px;margin-bottom:8px}
.prof-brands__search input{width:100%;max-width:420px;height:44px;padding:0 14px;border:1px solid var(--pb-line);border-radius:22px;font-size:15px;background:#fff;color:var(--pb-text)}
.prof-brands__search input:focus{outline:none;border-color:var(--pb-accent);box-shadow:0 0 0 3px rgba(130,80,171,.15)}
.prof-brands__chips,.prof-brands__letters{display:flex;flex-wrap:wrap;gap:10px 6px}
.prof-brands .prof-brands__chip,.prof-brands .prof-brands__letter{min-height:34px;padding:0 14px;border:1px solid var(--pb-line);border-radius:17px;background:#fff;color:var(--pb-text);font-size:14px;font-weight:500;line-height:1;cursor:pointer;max-width:none;transition:background-color .2s,border-color .2s,color .2s}
.prof-brands .prof-brands__letter{min-width:34px;padding:0 8px;font-size:13px}
.prof-brands .prof-brands__chip:hover,.prof-brands .prof-brands__letter:hover{border-color:var(--pb-accent);color:var(--pb-accent)}
.prof-brands .prof-brands__chip.is-active,.prof-brands .prof-brands__letter.is-active{background:var(--pb-accent);border-color:var(--pb-accent);color:#fff}
.prof-brands .prof-brands__chip--sale:not(.is-active){border-color:#e7b5c4;color:#b23a5c}
.prof-brands__status{margin:6px 0 14px;font-size:13px;color:var(--pb-muted);min-height:1em}
.prof-brands__grid{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:18px}
.prof-brands__item{margin:0;padding:0}
.prof-brands__item[hidden]{display:none}
.prof-brands__card{position:relative;display:flex;flex-direction:column;align-items:center;gap:6px;height:100%;padding:18px 12px 14px;background:#fff;border:1px solid var(--pb-line);border-radius:12px;box-shadow:0 1px 5px var(--theme-palette-color-5,rgba(0,0,0,.08));text-align:center;text-decoration:none;color:var(--pb-text);transition:transform .25s,box-shadow .25s}
.prof-brands__card:hover{transform:translateY(-4px);box-shadow:0 6px 20px rgba(0,0,0,.13);color:var(--pb-text)}
.prof-brands__logo{display:flex;align-items:center;justify-content:center;width:100%;height:96px;margin-bottom:4px}
.prof-brands__logo img{max-width:120px;max-height:96px;width:auto;height:auto;object-fit:contain}
.prof-brands__noimg{font-size:18px;font-weight:700;color:var(--pb-accent)}
.prof-brands__name{font-weight:600;font-size:15px;line-height:1.25}
.prof-brands__card:hover .prof-brands__name{color:var(--pb-accent)}
.prof-brands__meta{font-size:12.5px;color:var(--pb-muted);line-height:1.3}
.prof-brands__badge{position:absolute;top:8px;right:8px;padding:3px 8px;border-radius:10px;background:#fbe9ef;color:#b23a5c;font-size:11px;font-weight:600;line-height:1.2}
.prof-brands__empty{padding:30px 0;text-align:center;color:var(--pb-muted)}
.prof-brands .prof-brands__reset{background:none;border:0;padding:0;color:var(--pb-accent);text-decoration:underline;cursor:pointer;font:inherit;max-width:none}
@media (max-width:768px){
.prof-brands__grid{grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px}
.prof-brands__logo{height:72px}
.prof-brands__logo img{max-width:100px;max-height:72px}
.prof-brands__letters{flex-wrap:nowrap;overflow-x:auto;padding:2px 0 12px;-webkit-overflow-scrolling:touch;scrollbar-width:thin}
.prof-brands .prof-brands__letter{flex:0 0 auto}
}
@media (max-width:480px){
.prof-brands__grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.prof-brands__card{padding:16px 8px 12px}
.prof-brands__name{font-size:14px}
.prof-brands__badge{position:static;order:-1;margin-bottom:-2px}
}
</style>
<script>
(function () {
	function init() {
		var root = document.querySelector('[data-prof-brands]');
		if (!root || root.dataset.ready) return;
		root.dataset.ready = '1';

		var input   = root.querySelector('.prof-brands__search input');
		var items   = [].slice.call(root.querySelectorAll('.prof-brands__item'));
		// Только кнопки: у карточек брендов тоже есть data-letter, и без этого клик по карточке выбирал букву
		var dirBtns = [].slice.call(root.querySelectorAll('.prof-brands__chip[data-dir]'));
		var letBtns = [].slice.call(root.querySelectorAll('.prof-brands__letter[data-letter]'));
		var saleBtn = root.querySelector('[data-sale-toggle]');
		var status  = root.querySelector('.prof-brands__status');
		var empty   = root.querySelector('.prof-brands__empty');
		var KEY     = 'prof_brands_state';
		var state   = { q: '', dir: '', letter: '', sale: false };

		var RU = 'йцукенгшщзхъфывапролджэячсмитьбю';
		var EN = 'qwertyuiop[]asdfghjkl;\'zxcvbnm,.';
		function norm(s) {
			return String(s || '').toLowerCase().replace(/ё/g, 'е').replace(/[^a-z0-9а-я]+/g, '');
		}
		function toLatin(s) {
			var out = '';
			s = String(s || '').toLowerCase();
			for (var i = 0; i < s.length; i++) {
				var k = RU.indexOf(s[i]);
				out += k > -1 ? EN[k] : s[i];
			}
			return out;
		}
		items.forEach(function (li) {
			li._s = norm(li.getAttribute('data-search'));
			li._d = ' ' + (li.getAttribute('data-dirs') || '') + ' ';
			li._a = li.querySelector('.prof-brands__card');
			li._href = li._a ? li._a.getAttribute('href') : '';
			li._saleHref = li.getAttribute('data-sale-href') || '';
		});

		function plural(n, a, b, c) {
			var m = n % 100, m1 = n % 10;
			if (m > 10 && m < 20) return c;
			if (m1 > 1 && m1 < 5) return b;
			return m1 === 1 ? a : c;
		}
		function mark(btns, attr, val) {
			btns.forEach(function (b) {
				var on = b.getAttribute(attr) === val;
				b.classList.toggle('is-active', on);
				b.setAttribute('aria-pressed', on ? 'true' : 'false');
			});
		}
		function save() {
			try { sessionStorage.setItem(KEY, JSON.stringify(state)); } catch (e) {}
		}
		function apply() {
			var q1 = norm(state.q), q2 = norm(toLatin(state.q)), shown = 0;
			items.forEach(function (li) {
				var ok = (!q1 || li._s.indexOf(q1) > -1 || (q2 && li._s.indexOf(q2) > -1))
					&& (!state.dir || li._d.indexOf(' ' + state.dir + ' ') > -1)
					&& (!state.letter || li.getAttribute('data-letter') === state.letter)
					&& (!state.sale || li.hasAttribute('data-sale'));
				li.hidden = !ok;
				if (ok) shown++;
				// С фильтром «Есть акции» — сразу на акционные товары бренда
				if (li._a && li._saleHref) li._a.setAttribute('href', state.sale ? li._saleHref : li._href);
			});
			mark(dirBtns, 'data-dir', state.dir);
			mark(letBtns, 'data-letter', state.letter);
			if (saleBtn) {
				saleBtn.classList.toggle('is-active', state.sale);
				saleBtn.setAttribute('aria-pressed', state.sale ? 'true' : 'false');
			}
			var filtered = state.q || state.dir || state.letter || state.sale;
			status.textContent = filtered && shown ? 'Найдено: ' + shown + ' ' + plural(shown, 'бренд', 'бренда', 'брендов') : '';
			empty.hidden = shown > 0;
			save();
		}

		try {
			var saved = JSON.parse(sessionStorage.getItem(KEY) || 'null');
			if (saved && typeof saved === 'object') {
				state.q = typeof saved.q === 'string' ? saved.q : '';
				state.dir = dirBtns.some(function (b) { return b.getAttribute('data-dir') === saved.dir; }) ? saved.dir : '';
				state.letter = letBtns.some(function (b) { return b.getAttribute('data-letter') === saved.letter; }) ? saved.letter : '';
				state.sale = !!(saved.sale && saleBtn);
				if (input) input.value = state.q;
			}
		} catch (e) {}

		if (input) input.addEventListener('input', function () {
			state.q = input.value;
			if (state.q) state.letter = '';
			apply();
		});
		dirBtns.forEach(function (b) {
			b.addEventListener('click', function () { state.dir = b.getAttribute('data-dir'); apply(); });
		});
		letBtns.forEach(function (b) {
			b.addEventListener('click', function () {
				state.letter = b.getAttribute('data-letter');
				if (state.letter && input) { input.value = ''; state.q = ''; }
				apply();
			});
		});
		if (saleBtn) saleBtn.addEventListener('click', function () { state.sale = !state.sale; apply(); });
		root.querySelector('.prof-brands__reset').addEventListener('click', function () {
			state = { q: '', dir: '', letter: '', sale: false };
			if (input) input.value = '';
			apply();
		});
		apply();
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
</script>
	<?php
	return ob_get_clean();
}
