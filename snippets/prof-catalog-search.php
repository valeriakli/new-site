<?php
/**
 * Название:      prof-catalog-search (на сайте — «Поиск товаров в каталоге»)
 * Что делает:    Над товарами в каталоге /products/, категориях и брендах — поле «Поиск товаров...»
 *                с лупой и кнопкой «Найти» (на телефоне — без кнопки, поиск по лупе или Enter).
 *                Ищет внутри текущей страницы каталога (параметр ?pc_q=): в категории — по категории,
 *                в бренде — по бренду; остальные параметры адреса (фильтры) сохраняются.
 *                Есть ссылка «Сбросить поиск». Постраничная навигация сохраняет запрос.
 * Где активен:   фронтенд; woocommerce_archive_description (15) — форма, woocommerce_product_query — поиск,
 *                wp_head — стили (на всех страницах).
 * Зависимости:   WooCommerce, тема Blocksy (кнопки перебиваются !important).
 * Где проверено: на profcosmetica 2026-11-15: «маска» — 96 в каталоге, 78 в «Уход за лицом», 11 у Angiopharm;
 *                несуществующее слово — «товары не найдены». На новом сайте ещё не проверен.
 * Источник:      profcosmetica/snippets/prof-catalog-search.php — код без изменений; убраны упоминания регионов в шапке (префиксы /ru/, /ge/ ставил prof-multiregion).
 * Обновлён:      2026-11-25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 1. Поле поиска (магазин, категории, бренды)
add_action( 'woocommerce_archive_description', function () {
    if ( ! ( is_shop() || is_product_taxonomy() ) ) return;

    $params = wc_clean( wp_unslash( $_GET ) );
    unset( $params['pc_q'], $params['paged'] );
    $action = strtok( $_SERVER['REQUEST_URI'], '?' );
    $value  = isset( $_GET['pc_q'] ) ? sanitize_text_field( wp_unslash( $_GET['pc_q'] ) ) : '';
    ?>
    <form class="pc-shop-search" method="get" action="<?php echo esc_url( $action ); ?>" role="search">
        <?php foreach ( $params as $k => $v ) : if ( is_array( $v ) ) continue; ?>
            <input type="hidden" name="<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $v ); ?>">
        <?php endforeach; ?>
        <div class="pc-shop-search__row">
            <div class="pc-shop-search__field">
                <input type="search" name="pc_q" value="<?php echo esc_attr( $value ); ?>"
                       placeholder="Поиск товаров..." aria-label="Поиск товаров">
                <button type="submit" class="pc-shop-search__icon" aria-label="Найти">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2.2" stroke-linecap="round"><circle cx="10.5" cy="10.5" r="6.5"/><line x1="15.5" y1="15.5" x2="21" y2="21"/></svg>
                </button>
            </div>
            <button type="submit" class="pc-shop-search__submit">Найти</button>
        </div>
        <?php if ( $value ) : ?>
            <a class="pc-shop-search__reset" href="<?php echo esc_url( remove_query_arg( 'pc_q' ) ); ?>">Сбросить поиск</a>
        <?php endif; ?>
    </form>
    <?php
}, 15 );

// 2. Подмешиваем поиск в основной запрос товаров
add_action( 'woocommerce_product_query', function ( $q ) {
    if ( ! empty( $_GET['pc_q'] ) ) {
        $q->set( 's', sanitize_text_field( wp_unslash( $_GET['pc_q'] ) ) );
    }
} );

// 3. Стили
add_action( 'wp_head', function () { ?>
    <style>
        .pc-shop-search{
            --pc-accent:#8652ad;
            --pc-accent-hover:#6e3f93;
            margin:0 0 32px;width:100%;max-width:100%;box-sizing:border-box;
        }
        .pc-shop-search__row{display:flex;gap:12px;align-items:center}
        .pc-shop-search__field{position:relative;flex:1;min-width:0}

        .pc-shop-search__field input[type=search]{
            width:100%;height:48px;margin:0;
            padding:0 52px 0 22px;
            border:1px solid #333;border-radius:999px;
            background:#fff;color:#333;font-size:15px;
            box-shadow:none;outline:none;
            -webkit-appearance:none;appearance:none;
            transition:border-color .2s, box-shadow .2s;
        }
        .pc-shop-search__field input[type=search]::placeholder{color:#888;opacity:1}
        .pc-shop-search__field input[type=search]:focus{
            border-color:var(--pc-accent);
            box-shadow:0 0 0 3px rgba(134,82,173,.15);
        }
        .pc-shop-search__field input[type=search]::-webkit-search-cancel-button{display:none}

        /* Лупа внутри поля: центрирование без transform, чтобы тема не сдвигала */
        .pc-shop-search .pc-shop-search__icon,
        .pc-shop-search .pc-shop-search__icon:hover,
        .pc-shop-search .pc-shop-search__icon:focus{
            position:absolute !important;
            top:0 !important;bottom:0 !important;right:8px !important;
            margin:auto 0 !important;
            transform:none !important;
            width:36px !important;height:36px !important;min-height:0 !important;
            padding:0 !important;
            display:flex;align-items:center;justify-content:center;
            background:transparent !important;border:0 !important;border-radius:50%;
            box-shadow:none !important;
            color:#555 !important;cursor:pointer;
        }
        .pc-shop-search .pc-shop-search__icon:hover{color:var(--pc-accent) !important;background:rgba(134,82,173,.08) !important}

        /* Кнопка «Найти» справа */
        .pc-shop-search .pc-shop-search__submit,
        .pc-shop-search .pc-shop-search__submit:hover,
        .pc-shop-search .pc-shop-search__submit:focus{
            flex:0 0 auto;
            height:48px !important;min-height:0 !important;
            margin:0 !important;padding:0 34px !important;
            transform:none !important;
            border:0 !important;border-radius:999px !important;
            box-shadow:none !important;
            font-size:15px;font-weight:600;line-height:1;cursor:pointer;
            transition:background .2s;
        }
        .pc-shop-search .pc-shop-search__submit{background:var(--pc-accent) !important;color:#fff !important}
        .pc-shop-search .pc-shop-search__submit:hover{background:var(--pc-accent-hover) !important}

        .pc-shop-search__reset{display:inline-block;margin:10px 0 0 22px;font-size:13px;color:#888}
        .pc-shop-search__reset:hover{color:var(--pc-accent)}

        /* Мобильные: без кнопки «Найти», поиск по лупе или Enter */
        @media (max-width:689px){
            .pc-shop-search{
                margin:20px 0 20px !important;
                padding:0 4px;
            }
            .pc-shop-search .pc-shop-search__submit{display:none !important}
            .pc-shop-search__field input[type=search]{height:44px;padding-left:18px}
            .pc-shop-search__reset{margin-left:18px}
        }
    </style>
<?php } );
