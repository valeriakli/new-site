<?php
/**
 * Название:      prof-brand-description (на сайте — «Описание бренда в каталоге»)
 * Что делает:    На странице бренда (/brand/…/) и на странице фильтра WOOF по бренду
 *                (адрес с pwb-brand-…, несколько брендов через -and-) выводит логотип бренда
 *                (140 px, на телефоне 110 px) и его описание (из описания термина pwb-brand).
 *                Логотип выводится и у брендов без описания. На телефоне описание свёрнуто
 *                до 180 px с затуханием и кнопкой «Развернуть / Свернуть» (только если текст длинный);
 *                на компьютере — полностью.
 *                Стандартный баннер PWB (.pwb-brand-banner-cont) скрыт через CSS: это тот же логотип,
 *                но в исходном размере (у брендов без описания он вылезал на весь экран — до 1680 px).
 * Где активен:   фронтенд; woocommerce_archive_description (20). CSS (wp_head) и JS (wp_footer)
 *                подключаются на всех страницах.
 * Зависимости:   WooCommerce, Perfect Brands for WooCommerce (pwb-brand, мета pwb_brand_image), WOOF.
 * Источник:      profcosmetica/snippets/prof-brand-description.php — перенесён без изменений кода, кроме отмеченного.
 * Обновлён:      2026-11-25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Бренд: логотип + описание + «Развернуть»
 * Работает на страницах брендов и на страницах фильтра WOOF (swoof) по бренду.
 */
add_action( 'woocommerce_archive_description', function () {

	$brand_slugs = array();

	if ( is_tax( 'pwb-brand' ) ) {
		// Страница бренда
		$term = get_queried_object();
		if ( $term && ! empty( $term->slug ) ) {
			$brand_slugs[] = $term->slug;
		}
	} else {
		// Страница фильтра WOOF
		$current_url = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
		if ( preg_match( '#pwb-brand-([a-zA-Z0-9_-]+)#', $current_url, $matches ) ) {
			$brand_slugs = explode( '-and-', $matches[1] );
		}
	}

	if ( empty( $brand_slugs ) ) {
		return;
	}

	$output = '';

	foreach ( $brand_slugs as $slug ) {

		$term = get_term_by( 'slug', $slug, 'pwb-brand' );
		if ( ! $term ) {
			continue;
		}

		// Логотип сразу нужного размера (medium), а не оригинал
		$logo    = '';
		$logo_id = (int) get_term_meta( $term->term_id, 'pwb_brand_image', true );
		if ( $logo_id ) {
			$logo = wp_get_attachment_image( $logo_id, 'medium', false, array(
				'alt'      => $term->name,
				'sizes'    => '140px',
				'decoding' => 'async',
				'loading'  => 'eager',
			) );
		}

		$desc = trim( $term->description );

		if ( ! $logo && '' === $desc ) {
			continue;
		}

		$output .= '<div class="single-brand-description">';

		if ( $logo ) {
			$output .= '<div class="brand-logo">' . $logo . '</div>';
		}

		if ( '' !== $desc ) {
			$output .= '
			<div class="brand-text-wrapper">
				<div class="brand-text">' . wp_kses_post( $desc ) . '</div>
				<button class="brand-read-more" type="button">Развернуть</button>
			</div>';
		}

		$output .= '</div>';
	}

	if ( '' === $output ) {
		return;
	}

	echo '<div class="brand-description-after-products">' . $output . '</div>';

}, 20 );


/**
 * CSS
 */
add_action( 'wp_head', function () {
?>
<style>
/* Стандартный баннер PWB — тот же логотип в исходном размере; логотип выводит этот сниппет */
.pwb-brand-banner-cont{display:none!important}

.single-brand-description{
    display:flex;
    gap:30px;
    align-items:flex-start;
}

.brand-logo{
    flex:0 0 140px;
    max-width:140px;
}

.brand-logo img{
    display:block;
    width:100%;
    height:auto;
    max-height:140px;
    object-fit:contain;
}

.brand-text-wrapper{
    flex:1;
}

/* ---------- ДЕСКТОП ---------- */

.brand-text{
    overflow:visible;
    max-height:none;
    position:relative;
}

.brand-text::after{
    display:none;
}

.brand-read-more{
    display:none;
    margin-top:15px;
    padding:0;
    background:none;
    border:0;
    cursor:pointer;
    font-weight:600;
    color:inherit;
    text-decoration:underline;
}

/* ---------- МОБИЛЬНЫЕ ---------- */

@media (max-width:767px){

    .single-brand-description{
        display:block;
    }

    .brand-logo{
        width:110px;
        max-width:110px;
        margin:0 auto 20px;
    }

    .brand-logo img{
        max-height:110px;
    }

    .brand-text{
        overflow:hidden;
        max-height:180px;
        transition:max-height .35s ease;
    }

    .brand-text::after{
        content:"";
        display:block;
        position:absolute;
        left:0;
        right:0;
        bottom:0;
        height:70px;
        background:linear-gradient(to bottom,rgba(255,255,255,0),#fff);
        pointer-events:none;
    }

    .brand-text.expanded{
        max-height:5000px;
    }

    .brand-text.expanded::after{
        display:none;
    }

}
</style>
<?php
} );


/**
 * JS: кнопка «Развернуть» на телефоне
 */
add_action( 'wp_footer', function () {
?>
<script>
document.addEventListener('DOMContentLoaded', function () {

    document.querySelectorAll('.brand-text-wrapper').forEach(function(wrapper){

        const text = wrapper.querySelector('.brand-text');
        const button = wrapper.querySelector('.brand-read-more');

        if(!text || !button) return;

        function updateButton(){

            // На десктопе описание всегда полностью открыто
            if(window.innerWidth > 767){
                button.style.display = 'none';
                text.classList.remove('expanded');
                button.textContent = 'Развернуть';
                return;
            }

            // На мобильном показываем кнопку только если текст длинный
            if(text.scrollHeight > text.clientHeight + 5){
                button.style.display = 'inline-block';
            }else{
                button.style.display = 'none';
            }
        }

        setTimeout(updateButton,100);

        window.addEventListener('load', updateButton);
        window.addEventListener('resize', updateButton);

        button.addEventListener('click', function(){

            text.classList.toggle('expanded');

            button.textContent = text.classList.contains('expanded')
                ? 'Свернуть'
                : 'Развернуть';

        });

    });

});
</script>
<?php
} );
