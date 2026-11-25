<?php
/**
 * Название:      my_brand_carousel — карусель логотипов брендов (шорткод [my_brand_carousel])
 * Что делает:    Выводит слайдер Swiper со всеми брендами таксономии pwb-brand (Perfect Brands), у которых
 *                задан логотип (term meta pwb_brand_image), со ссылками на страницы брендов.
 *                Параметры: items, autoplay, arrows, speed, autoplay_speed. Пока Swiper не запустился,
 *                показывается спиннер. Swiper подключается отдельно на wp_enqueue_scripts, только если шорткод
 *                есть в содержимом страницы. Хэндл 'swiper' уже зарегистрирован Elementor, поэтому
 *                фактически грузится Swiper v8 из Elementor, а не v9 с unpkg (работает).
 *                Вывод кешируется сниппетом prof-shortcode-cache (15 мин, сброс при правке брендов).
 * Где активен:   фронтенд; шорткод используется на главных (виджет «Шорткод» в Elementor).
 * Зависимости:   Perfect Brands for WooCommerce (таксономия pwb-brand), Swiper (через Elementor).
 * Источник:      profcosmetica/snippets/my-brand-carousel.php — перенесён без изменений кода, кроме отмеченного.
 * Обновлён:      2026-11-25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Регистрируем Swiper только при наличии шорткода на странице
function my_brand_carousel_enqueue() {
    global $post;
    if ( isset($post->post_content) && has_shortcode( $post->post_content, 'my_brand_carousel' ) ) {
        wp_enqueue_style( 'swiper', 'https://unpkg.com/swiper@9/swiper-bundle.min.css', [], '9' );
        wp_enqueue_script( 'swiper', 'https://unpkg.com/swiper@9/swiper-bundle.min.js', [], '9', true );
    }
}
add_action( 'wp_enqueue_scripts', 'my_brand_carousel_enqueue' );

// Функция шорткода
function my_brand_carousel_func( $atts ) {
    $atts = shortcode_atts( [
        'items'          => 5,
        'autoplay'       => 'true',
        'arrows'         => 'true',
        'speed'          => 600,
        'autoplay_speed' => 600,
    ], $atts, 'my_brand_carousel' );

    $brands = get_terms( [
        'taxonomy'   => 'pwb-brand',
        'hide_empty' => false,
    ] );

    if ( empty( $brands ) || is_wp_error( $brands ) ) {
        return '';
    }

    ob_start(); ?>

    <div class="brand-slider-wrapper" style="position:relative;">
        <div class="brand-slider-spinner-wrapper" style="
            position:absolute;
            top:50%;
            left:50%;
            transform:translate(-50%, -50%);
            z-index:10;">
            <div class="brand-slider-spinner" style="
                width:50px;
                height:50px;
                border:5px solid #ccc;
                border-top-color:#000;
                border-radius:50%;
                animation: spin 1s linear infinite;">
            </div>
        </div>

        <div class="swiper brand-slider"
             data-items="<?php echo esc_attr( $atts['items'] ); ?>"
             data-autoplay="<?php echo esc_attr( $atts['autoplay'] ); ?>"
             data-arrows="<?php echo esc_attr( $atts['arrows'] ); ?>"
             data-speed="<?php echo esc_attr( $atts['speed'] ); ?>"
             data-autoplay-speed="<?php echo esc_attr( $atts['autoplay_speed'] ); ?>"
             style="visibility:hidden;">
            <div class="swiper-wrapper">
                <?php foreach ( $brands as $brand ) :
                    $thumb_id = get_term_meta( $brand->term_id, 'pwb_brand_image', true );
                    if ( ! $thumb_id ) continue;
                    $img_url = wp_get_attachment_image_url( $thumb_id, 'medium' );
                    $brand_link = get_term_link( $brand );
                    if ( $img_url && ! is_wp_error($brand_link) ) : ?>
                        <div class="swiper-slide">
                            <a href="<?php echo esc_url($brand_link); ?>">
                                <img src="<?php echo esc_url( $img_url ); ?>"
                                     alt="<?php echo esc_attr( $brand->name ); ?>"
                                     loading="lazy">
                            </a>
                        </div>
                    <?php endif;
                endforeach; ?>
            </div>
            <?php if ( $atts['arrows'] === 'true' ) : ?>
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>
            <?php endif; ?>
        </div>
    </div>

    <style>
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.brand-slider').forEach(function (el) {
            const items    = parseInt(el.dataset.items) || 5;
            const autoplay = el.dataset.autoplay === 'true';
            const arrows   = el.dataset.arrows === 'true';
            const speed    = parseInt(el.dataset.speed) || 800;
            const delay    = parseInt(el.dataset.autoplaySpeed) || 2000;
            const wrapper  = el.closest('.brand-slider-wrapper');
            const spinnerWrapper  = wrapper.querySelector('.brand-slider-spinner-wrapper');

            setTimeout(function() {
                spinnerWrapper.style.display = 'none';
                el.style.visibility = 'visible';

                new Swiper(el, {
                    slidesPerView: items,
                    spaceBetween: 20,
                    loop: true,
                    speed: speed,
                    autoplay: autoplay ? { delay: delay, disableOnInteraction: false } : false,
                    navigation: arrows ? {
                        nextEl: el.querySelector('.swiper-button-next'),
                        prevEl: el.querySelector('.swiper-button-prev'),
                    } : {},
                    breakpoints: {
                        320: { slidesPerView: Math.min(3, items)},
                        768: { slidesPerView: Math.min(3, items) },
                        1024: { slidesPerView: items },
                    },
                    lazy: true
                });
            }, 50);
        });
    });
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode( 'my_brand_carousel', 'my_brand_carousel_func' );
