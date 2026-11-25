# Перенос сниппетов с profcosmetica

Составлено 2026-11-25 по `profcosmetica/snippets/` (66 файлов) и таблице сниппетов в `profcosmetica/docs/site.md`. Нужен на этапе 2 (чистка копии): копия сайта приходит со всеми сниппетами profcosmetica, здесь — что с каждым делать.

## Общие правила
- **Copy Site сохраняет ID** товаров, страниц, способов доставки и виджетов, поэтому ID в коде (сертификат 2074, косметичка 15757, доставка `:20`, `#block-25`) на копии остаются верными. Проверять, только если товар или виджет удалили.
- **На сайт ставить версию из репозитория, а не ту, что приехала с копией.** Часть сниппетов на profcosmetica ещё ждёт вставки, и в репозитории они новее (отмечены «ждёт вставки»).
- **Контакты profcosmetica** (телефон и Telegram `+375 25 992-14-65`) в коде заменить на контакты нового сайта.
- **Цвета.** Во фронтенд-сниппетах заданы цвета profcosmetica. На этапе дизайна заменить на палитру из [design.md](design.md). Больше всего цветов в `pc-gift-cards` (69), `prof-brands-page`, `prof-home-categories`, `prof-catalog-search`.
- Перенесённый сниппет сохраняется в `new-site/snippets/` под тем же именем, с шапкой по шаблону и строкой в `docs/site.md`.

## 1. Не переносить: регионы RU / GE (12)
Выключить и удалить после копирования, вместе с WCML. Затем проверить, что на оформлении есть способы доставки: их давал `prof-region-country`. Без регионов нужно в WooCommerce → Настройки → Общие поставить «Продажа только в: Беларусь» и местоположение покупателя по умолчанию — Беларусь.

**Внимание:** `prof-multiregion` делает не только регионы. Он же выводит блок «Доставка и оплата» внизу категорий и товаров и задаёт canonical / SEO. Перед удалением посмотреть его код и перенести нужное (блок «Доставка и оплата» — отдельным сниппетом, canonical — через AIOSEO).

| Сниппет | Что делал |
|---|---|
| prof-multiregion | Регионы по URL, валюта WCML, canonical, hreflang |
| prof-region-country | Страна покупателя по региону (см. проверку выше) |
| prof-region-shipping-sync | Страна доставки при входе в регион |
| prof-region-currency-early | Валюта WCML по региону |
| prof-region-shipping-cost | Стоимость EMS / СДЭК |
| prof-region-gateways | Способы оплаты по странам |
| pc-region-payment | Описание оплаты картой для RU / GE |
| prof-header-switcher | Переключатель региона в шапке |
| prof-regions-sitemap | Карта сайта RU / GE |
| prof-footer-hide-pickup | Прятал самовывоз в футере в RU / GE |
| prof-hide-certificate-regions | Сертификаты недоступны в RU / GE |
| prof-coupons-by-only | Купоны только в BY |

## 2. Не переносить: фиды и реклама profcosmetica (4)
Выключить сразу после копирования (чек-лист в [plan.md](plan.md)). Свои фиды и рекламу заводим на этапе 6.

| Сниппет | Почему | Что потом |
|---|---|---|
| prof-gmc-feeds | Фиды Merchant Center RU / GE | Свой фид BY; если нужны описания без HTML — взять эту часть |
| prof-y4ym-feeds | Фиды Яндекса RU / GE | То же |
| prof-gla-by-links | Ссылки товаров Google на BY-версию; без регионов не нужен | — |
| prof-gads-conversion | Счётчик Google Ads profcosmetica (`AW-17774989749`) | Новый сниппет с ID рекламного кабинета нового сайта |

## 3. Не переносить: временные и служебные (4)
| Сниппет | Почему |
|---|---|
| prof-speed-diag | Диагностика скорости, держали выключенным |
| prof-as-cleanup | Разовая чистка очереди Action Scheduler |
| prof-theme-mods-restore | Восстановление темы profcosmetica из бэкапа 16.11 |
| prof-checkout-debug | Временный журнал ошибок оформления |

## 4. Перенести как есть (27)
**Сделано 2026-11-25:** 26 сниппетов сохранены в `snippets/` (prof-sale-text не переносили — лишний). В четырёх убраны остатки регионов: prof-coupon-rules, prof-checkout-form-storage, prof-shortcode-cache, prof-payment-title-order (шапка). Проверить после копирования, что работает.

**Каталог и бренды:** prof-filter-cat-order, prof-filter-cats-api, prof-woof-settings, prof-catalog-per-page, prof-cat-bottom-text, prof-brand-description, prof-sale-category-sync, my-brand-carousel, prof-description-truncate, prof-tab-add-to-cart.
**Корзина и оформление:** prof-cart-auto-update, prof-coupon-rules, prof-checkout-fields, prof-checkout-messenger, prof-checkout-errors, prof-checkout-order-toggle, prof-checkout-back-reload, prof-checkout-form-storage (ждёт вставки), prof-shipping-label, prof-shipping-note, prof-free-shipping-min, prof-payment-title-order, prof-admin-order-shipping.
**Служебные:** prof-shortcode-cache, prof-elementor-dss-off, prof-theme-guard (ждёт вставки, полезен и здесь).
**Решить:** prof-sale-text — на profcosmetica помечен «лишний», можно не переносить.

Проверить при переносе: в `prof-checkout-form-storage`, `prof-free-shipping-min`, `prof-payment-title-order`, `prof-shortcode-cache` есть упоминания регионов в комментариях или ключах кеша — убрать, если найдутся.

## 5. Перенести и упростить (14)
Убрать ветки RU / GE, функции `prof_get_region()` / `prof_build_region_url()`, префиксы `/ru/`, `/ge/`.

| Сниппет | Что упростить |
|---|---|
| prof-pickup-points | Оставить только Европочту; убрать СДЭК и EMS (ждёт вставки) |
| prof-checkout-shipping-info | Оставить сроки Минска и почты; убрать СДЭК и EMS |
| prof-order-shipping-line | Проверить регион (ждёт вставки) |
| prof-checkout-phone-mask | Только BY — убрать проверки региона |
| prof-cart-guard | Белый список стран: только BY |
| prof-menu-brands | Ссылки без префикса региона |
| prof-product-brand | Ссылка на бренд без региона |
| prof-brands-page | Без региона; цвета — новые |
| prof-home-categories | Без региона; цвета — новые |
| prof-catalog-search | Без региона; цвета — новые |
| prof-hide-certificate-catalog | Убрать проверки региона |
| prof-home-coupon-popup | Убрать проверку «только BY»; проценты и суммы — решение Оли |
| prof-gift-regions | Косметичка в подарок: убрать курсы RU / GE — если акцию оставляем |
| pc-gift-cards | Убрать регионы, заменить Telegram, цвета, название магазина в письмах |

## 6. Перенести, заменив контакты (3)
| Сниппет | Что заменить |
|---|---|
| prof-cart-telegram | Ссылка на менеджера в Telegram |
| prof-hide-price | Ссылка «Узнать цену» в Telegram |
| prof-email-post-notice | Телефон в письме (ждёт вставки) |

## Вопросы к Оле
- Подарочные сертификаты на новом сайте нужны? (`pc-gift-cards` — самый большой сниппет)
- Акция «косметичка в подарок» и попап с купонами 2 / 3 / 5 % — оставляем? С теми же условиями?
- Контакты менеджера: телефон и Telegram нового сайта.
- Бесплатная доставка по Минску от 130 BYN и бесплатная почта от 250 BYN — те же суммы?
- Скрытие цен («Цена доступна в корзине») — нужно?
