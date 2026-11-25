# Второй сайт (временное имя new-site)

Интернет-магазин косметики, Беларусь, отдельное юрлицо. Структура и логика — с profcosmetica.by. Это короткий справочник, он читается в каждой сессии. План запуска — [plan.md](plan.md).

## Статус
- **Сайт ещё не создан.** Нет тарифа WordPress.com, домена и названия. Текущий этап — 0 (решения), см. [plan.md](plan.md).
- Дизайн: палитра и шрифт Manrope утверждены — [design.md](design.md).

## Напоминания
_(пока нет)_

## Стек (планируется, как на profcosmetica)
- WordPress + WooCommerce на WordPress.com (свой тариф, оформить на новое юрлицо)
- Тема **Blocksy**, конструктор **Elementor**, SEO **AIOSEO**, свой код — через Code Snippets
- **Без WCML и регионов**: только BY, валюта BYN
- Оплата — **bePaid**, свой кабинет нового юрлица

## Доступ
- Домен: —
- MCP: будет свой `.mcp.json`. Переменные Windows — с отдельными именами (например `NEWSITE_WP_API_URL`), чтобы не путать с profcosmetica.

## Сниппеты
Что переносить с profcosmetica — [snippets-migration.md](snippets-migration.md). Статус «готов» — код в репозитории; на сайт ставить эту версию после копирования (приехавшая с копией может быть старее).

| Сниппет | Что делает | Статус |
|---|---|---|
| [prof-filter-cat-order](../snippets/prof-filter-cat-order.php) | Порядок категорий верхнего уровня в фильтре WOOF | готов |
| [prof-filter-cats-api](../snippets/prof-filter-cats-api.php) | REST-дерево категорий для скрипта фильтра | готов |
| [prof-woof-settings](../snippets/prof-woof-settings.php) | Настройки WOOF: пустые варианты, подкатегории | готов |
| [prof-catalog-per-page](../snippets/prof-catalog-per-page.php) | Товаров на странице: телефон 16, компьютер 15 | готов |
| [prof-cat-bottom-text](../snippets/prof-cat-bottom-text.php) | Текст ACF внизу категории | готов |
| [prof-brand-description](../snippets/prof-brand-description.php) | Логотип и описание на странице бренда | готов |
| [prof-sale-category-sync](../snippets/prof-sale-category-sync.php) | Категория «Акции» = товары со скидкой | готов |
| [my-brand-carousel](../snippets/my-brand-carousel.php) | Шорткод карусели логотипов брендов | готов |
| [prof-description-truncate](../snippets/prof-description-truncate.php) | Длинное описание товара свёрнуто | готов |
| [prof-tab-add-to-cart](../snippets/prof-tab-add-to-cart.php) | Кнопка «В корзину» после описания товара | готов |
| [prof-cart-auto-update](../snippets/prof-cart-auto-update.php) | Корзина обновляется сама при смене количества | готов |
| [prof-coupon-rules](../snippets/prof-coupon-rules.php) | Правила процентных промокодов (без акционных, волос, исключённых брендов) | готов, убрана проверка регионов |
| [prof-checkout-fields](../snippets/prof-checkout-fields.php) | Упрощённая форма оформления | готов |
| [prof-checkout-messenger](../snippets/prof-checkout-messenger.php) | Поле мессенджера на оформлении | готов |
| [prof-checkout-errors](../snippets/prof-checkout-errors.php) | Ошибка оформления: прокрутка и плашка | готов |
| [prof-checkout-order-toggle](../snippets/prof-checkout-order-toggle.php) | Товары в «Ваш заказ» свёрнуты | готов |
| [prof-checkout-back-reload](../snippets/prof-checkout-back-reload.php) | Перезагрузка оформления после «Назад» | готов |
| [prof-checkout-form-storage](../snippets/prof-checkout-form-storage.php) | Поля оформления сохраняются в браузере 60 дней | готов, без поля СДЭК |
| [prof-shipping-label](../snippets/prof-shipping-label.php) | Подписи «Способ получения заказа», «Сумма» | готов |
| [prof-shipping-note](../snippets/prof-shipping-note.php) | Текст в скобках у способов доставки — серым | готов |
| [prof-free-shipping-min](../snippets/prof-free-shipping-min.php) | Бесплатная доставка по Минску от 130 BYN | готов; порог — уточнить у Оли |
| [prof-payment-title-order](../snippets/prof-payment-title-order.php) | Заголовок «Оплата» и порядок способов | готов |
| [prof-admin-order-shipping](../snippets/prof-admin-order-shipping.php) | Способ доставки в админке заказа | готов |
| [prof-shortcode-cache](../snippets/prof-shortcode-cache.php) | Кеш HTML тяжёлых шорткодов на 15 мин | готов, регион убран из ключа |
| [prof-elementor-dss-off](../snippets/prof-elementor-dss-off.php) | Отключает Elementor Design System Sync на фронтенде | готов |
| [prof-theme-guard](../snippets/prof-theme-guard.php) | Сторож настроек темы, виджетов и меню | готов |
| [prof-delivery-info](../snippets/prof-delivery-info.php) | Блок «Доставка и оплата»: вкладка товара, под товарами в категориях, шорткод | готов, вынесен из prof-multiregion; тексты и адрес самовывоза — уточнить у Оли |
