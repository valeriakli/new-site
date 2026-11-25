<?php
/**
 * Название:      prof-description-truncate (на сайте — «Обрезка описания товара»)
 * Что делает:    На странице товара во вкладке «Описание» сворачивает длинный текст до 200 px
 *                (снизу плавное затухание) и добавляет кнопку «Показать полностью» / «Скрыть».
 *                После «Скрыть» страница прокручивается обратно к описанию, если его начало ушло вверх.
 *                Высота перепроверяется после загрузки картинок, поэтому длинные описания с фото тоже
 *                сворачиваются. Короткие описания не трогает.
 *                Кнопка «В корзину» из prof-tab-add-to-cart стоит после «Показать полностью» и видна всегда.
 * Где активен:   фронтенд, страница товара; woocommerce_after_single_product_summary (5), JS + CSS.
 * Зависимости:   WooCommerce (вкладка #tab-description), тема Blocksy (цвет кнопки, белый фон
 *                страницы — под него затухание), prof-tab-add-to-cart (.custom-tab-add-to-cart).
 * Источник:      profcosmetica/snippets/prof-description-truncate.php — перенесён без изменений кода, кроме отмеченного.
 * Обновлён:      2026-11-25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function blocksy_conditional_show_more_description() {
	global $post;

	// Пустое описание — ничего не выводим. Проверка без сборки описания целиком:
	// текст без тегов или хотя бы одна картинка.
	if ( ! $post ) {
		return;
	}
	$raw = (string) $post->post_content;
	if ( '' === trim( wp_strip_all_tags( $raw ) ) && false === stripos( $raw, '<img' ) ) {
		return;
	}

	?>
	<script>
	document.addEventListener("DOMContentLoaded", function () {
		const tabDesc = document.querySelector("#tab-description");
		if (!tabDesc) return;

		// Если кнопка обрезки уже создана — выходим, чтобы не плодить обертки
		if (tabDesc.querySelector(".container-show-more")) return;

		const targetTab = tabDesc.querySelector("#tab-description") || tabDesc;
		const maxHeight = 200; // Высота видимой части в px

		// Оборачиваем текст описания (кнопку «В корзину», если она уже есть, оставляем снаружи)
		let wrapper = targetTab.querySelector(".descr-wrapper");
		if (!wrapper) {
			const addToCartBtn = targetTab.querySelector(".custom-tab-add-to-cart");
			wrapper = document.createElement("div");
			wrapper.classList.add("descr-wrapper");

			Array.from(targetTab.childNodes)
				.filter(node => node !== addToCartBtn)
				.forEach(node => wrapper.appendChild(node));

			if (addToCartBtn) {
				targetTab.insertBefore(wrapper, addToCartBtn);
			} else {
				targetTab.appendChild(wrapper);
			}
		}

		let btn = null;

		function collapse() {
			wrapper.classList.remove("expanded");
			targetTab.classList.remove("is-expanded");
			btn.innerText = "Показать полностью";

			// Если начало описания ушло вверх за экран — возвращаемся к нему
			const top = targetTab.getBoundingClientRect().top;
			if (top < 0) {
				window.scrollTo({ top: window.scrollY + top - 100, behavior: "smooth" });
			}
		}

		function expand() {
			wrapper.classList.add("expanded");
			targetTab.classList.add("is-expanded");
			btn.innerText = "Скрыть";
		}

		// Сворачиваем, если текст выше 200 px. Вызывается сразу и повторно после загрузки картинок.
		function check() {
			if (btn) return; // уже свёрнуто
			if (wrapper.scrollHeight <= maxHeight) return;

			wrapper.classList.add("js-description-truncated");

			const container = document.createElement("div");
			container.classList.add("container-show-more");

			btn = document.createElement("button");
			btn.type = "button";
			btn.classList.add("show-more-btn");
			btn.innerText = "Показать полностью";
			container.append(btn);

			// Сразу после текста — то есть перед кнопкой «В корзину», где бы она ни была
			wrapper.after(container);

			btn.addEventListener("click", function () {
				if (wrapper.classList.contains("expanded")) {
					collapse();
				} else {
					expand();
				}
			});
		}

		check();

		// Картинки в описании могут догрузиться позже и увеличить высоту
		wrapper.querySelectorAll("img").forEach(function (img) {
			if (!img.complete) {
				img.addEventListener("load", check, { once: true });
			}
		});
		window.addEventListener("load", check, { once: true });
	});
	</script>
	<style>
	/* Перебиваем display: none для вложенных табов, если они есть */
	#tab-description #tab-description {
		display: block !important;
	}

	/* Обрезаем ТОЛЬКО ТЕКСТ, оставляя кнопки снаружи */
	.descr-wrapper.js-description-truncated {
		max-height: 200px;
		overflow: hidden;
		position: relative;
		transition: max-height 0.3s ease;
	}

	.descr-wrapper.js-description-truncated.expanded {
		max-height: none !important;
	}

	/* Плавное затухание внизу свёрнутого текста (фон страницы белый) */
	.descr-wrapper.js-description-truncated:not(.expanded)::after {
		content: "";
		position: absolute;
		left: 0;
		right: 0;
		bottom: 0;
		height: 60px;
		background: linear-gradient(to bottom, rgba(255, 255, 255, 0), #fff);
		pointer-events: none;
	}

	/* Кнопка 'В корзину' видна всегда — и в свёрнутом, и в развёрнутом описании */

	/* Стили кнопки 'Показать полностью' */
	.show-more-btn {
		color: var(--theme-button-background-initial-color, #000);
		text-decoration: underline;
		font-weight: bold;
		background: transparent;
		border: none;
		cursor: pointer;
		padding: 0;
	}

	.show-more-btn:hover {
		color: purple;
	}

	.container-show-more {
		margin-top: 15px;
		margin-bottom: 15px;
		max-width: var(--wp--style--global--content-size);
	}
	</style>
	<?php
}
add_action( 'woocommerce_after_single_product_summary', 'blocksy_conditional_show_more_description', 5 );
