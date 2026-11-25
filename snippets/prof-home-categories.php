<?php
/**
 * Название:      prof-home-categories (на сайте — «Категории/подкатегории на главной + СПРЯТАННЫЕ БАДЫ»)
 * Что делает:    Шорткод [product_categories_with_subcategories]: на главной — квадратики категорий
 *                верхнего уровня (кроме «Подарочный сертификат» и «Misc»), по клику — список
 *                подкатегорий: на компьютере под квадратиками (первая категория открыта сразу),
 *                на телефоне — во всплывающем окне. Если подкатегорий нет — одна ссылка на саму категорию.
 *                С 2026-11-15 подкатегории выводятся сразу вместе со страницей (попадают в кеш главной),
 *                скрипт только показывает нужный список; раньше грузились некешируемым запросом
 *                admin-ajax ~2 с. Обработчик get_subcategories оставлен для старых копий страницы в кеше.
 *                Про «спрятанные БАДы» в коде ничего нет (на 2026-11-15 категорий БАД на сайте нет).
 * Где активен:   фронтенд, где стоит шорткод (главная); AJAX wp_ajax(_nopriv)_get_subcategories (запасной).
 * Зависимости:   WooCommerce (product_cat, миниатюры категорий).
 * Источник:      profcosmetica/snippets/prof-home-categories.php — код без изменений; убраны упоминания регионов в шапке (префиксы /ru/, /ge/ ставил prof-multiregion).
 * Обновлён:      2026-11-25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function product_categories_with_subcategories() {
    $terms = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
        'parent' => 0,
    ]);

    if (empty($terms) || is_wp_error($terms)) return '';

    ob_start();

    echo '<div class="product-category-wrapper">';

    // Верхний ряд — большие квадратные категории
    $first = true;
    $lists = ''; // списки подкатегорий для каждой категории — выводятся сразу, без запросов к серверу
    echo '<div class="product-category-cubes">';
    foreach ($terms as $term) {
        if ($term->slug === 'gift-certificate' || $term->slug === 'misc') continue;
        $active_class = $first ? 'active' : '';

        $thumbnail_id = get_term_meta($term->term_id, 'thumbnail_id', true);
        $thumbnail_url = $thumbnail_id ? wp_get_attachment_image_url($thumbnail_id, 'thumbnail') : null;

        echo '<div class="cat-cube ' . $active_class . '" data-term-id="' . $term->term_id . '">';
        if ($thumbnail_url) {
            echo '<div class="cat-icon"><img src="' . esc_url($thumbnail_url) . '" alt="' . esc_attr($term->name) . '" /></div>';
        }
        echo '<span class="cat-name">' . esc_html($term->name) . '</span>';
        echo '</div>';

        // Подкатегории этой категории (если их нет — ссылка на саму категорию)
        $children = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'parent' => $term->term_id,
        ]);
        if (empty($children) || is_wp_error($children)) {
            $children = [$term];
        }
        $lists .= '<div class="subcat-list" data-parent="' . $term->term_id . '"' . ($first ? '' : ' hidden') . '>';
        foreach ($children as $child) {
            $link = get_term_link($child);
            if (is_wp_error($link)) continue;
            $lists .= '<a href="' . esc_url($link) . '" class="cat-capsule">' . esc_html($child->name) . ' <span class="arrow">›</span></a>';
        }
        $lists .= '</div>';

        $first = false;
    }
    echo '</div>';

    // Нижний ряд — подкатегории (для десктопа): все списки сразу, виден список активной категории
    echo '<div class="subcategory-capsules">' . $lists . '</div>';

    echo '</div>'; // wrapper

    // Модалка для мобильных
    echo '<div id="subcategory-modal" class="subcategory-modal">
            <div class="subcategory-modal-content">
                <span class="modal-close">&times;</span>
                <div class="subcategory-capsules"></div>
            </div>
          </div>';
    ?>

   <style>
    .cat-cube { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 15px; border: 2px solid #eee; border-radius: 12px; cursor: pointer; transition: 0.3s; }
    @media (min-width: 999px) {
        .cat-cube.active { border-color: #763576; background: #f9f3fb; }
    }
    .cat-cube .cat-icon { width: 60px; height: 60px; margin-bottom: 8px; }
    .cat-cube .cat-icon img { max-width: 100%; max-height: 100%; object-fit: contain; }
    .spinner { display: flex; justify-content: center; align-items: center; padding: 20px; }
    .spinner:after { content: ""; width: 24px; height: 24px; border: 3px solid #ccc; border-top-color: #333; border-radius: 50%; animation: spin 0.8s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* Модалка */
    .subcategory-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); justify-content: center; align-items: center; z-index: 9999; }
    .subcategory-modal-content { background: #fff; padding: 20px; border-radius: 12px; max-width: 90%; width: 400px; max-height: 80%; overflow-y: auto; position: relative; }
    .modal-close { position: absolute; top: 10px; right: 15px; font-size: 24px; cursor: pointer; }
    /* Обёртка списка не должна влиять на раскладку: кнопки остаются прямыми «детьми» контейнера с отступами */
    .subcat-list:not([hidden]) { display: contents; }
    .cat-capsule { display: block; padding: 10px; text-decoration: none; color: #333; }
    .cat-capsule .arrow { float: right; }

    /* Мобилка: 3 квадратика в ряд */
    @media (max-width: 997px) {
        .product-category-wrapper > .subcategory-capsules { display: none !important; }

        .product-category-wrapper .product-category-cubes {
            display: grid !important;
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            gap: 8px !important;
            width: 100% !important;
        }
        .product-category-wrapper .product-category-cubes .cat-cube {
            width: auto !important;
            height: auto !important;
            min-width: 0 !important;
            max-width: none !important;
            flex: none !important;
            margin: 0 !important;
            padding: 6px !important;
            border-radius: 12px !important;
            aspect-ratio: 1 / 1 !important;
            justify-content: center !important;
        }
        .product-category-wrapper .product-category-cubes .cat-icon {
            width: 40px !important;
            height: 40px !important;
            margin-bottom: 6px !important;
        }
        .product-category-wrapper .product-category-cubes .cat-name {
            font-size: 12px !important;
            line-height: 1.2 !important;
            text-align: center !important;
            overflow-wrap: break-word;
        }
    }
</style>

    <script>
    // Подкатегории уже есть на странице (.subcat-list) — показываем нужный список без запросов к серверу
    function showSubcategories(parentId, openModal = false) {
        const desktop = document.querySelector('.product-category-wrapper > .subcategory-capsules');
        if (!desktop) return;

        desktop.querySelectorAll('.subcat-list').forEach(function(list){
            list.hidden = list.dataset.parent !== String(parentId);
        });

        if (window.innerWidth < 998 && openModal) {
            const list = desktop.querySelector('.subcat-list[data-parent="' + parentId + '"]');
            document.querySelector('#subcategory-modal .subcategory-capsules').innerHTML = list ? list.innerHTML : '';
            document.getElementById('subcategory-modal').style.display = 'flex';
        }
    }

    // Клик по категориям
    document.querySelectorAll('.cat-cube').forEach(function(cube){
        cube.addEventListener('click', function(){
            document.querySelectorAll('.cat-cube').forEach(c=>c.classList.remove('active'));
            this.classList.add('active');
            showSubcategories(this.dataset.termId, true);
        });
    });

    // Закрытие модалки
    document.querySelector('.modal-close').addEventListener('click', function(){
        document.getElementById('subcategory-modal').style.display = 'none';
    });

    document.getElementById('subcategory-modal').addEventListener('click', function(e){
        if(e.target === this){
            this.style.display = 'none';
        }
    });
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('product_categories_with_subcategories', 'product_categories_with_subcategories');

// Ajax для подкатегорий
add_action('wp_ajax_get_subcategories', 'get_subcategories_ajax');
add_action('wp_ajax_nopriv_get_subcategories', 'get_subcategories_ajax');
function get_subcategories_ajax() {
    $parent_id = intval($_GET['parent']);
    $terms = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
        'parent' => $parent_id,
    ]);

    $parent_link = get_term_link($parent_id);
    $result = [];

    if (!empty($terms) && !is_wp_error($terms)) {
        foreach ($terms as $t) {
            $result[] = [
                'name' => $t->name,
                'link' => get_term_link($t),
            ];
        }
    } else {
        $term = get_term($parent_id, 'product_cat');
        $result[] = [
            'name' => $term->name,
            'link' => $parent_link,
        ];
    }

    wp_send_json($result);
}
