# Плагины после копирования

Составлено 2026-11-25 по `profcosmetica/docs/plugins-audit.md` (черновик аудита от 2026-11-08) и CHANGELOG profcosmetica. Нужен на этапе 2 (чистка копии) вместе с [snippets-migration.md](snippets-migration.md).

**Список неполный.** На profcosmetica 46 активных плагинов, а в аудите названо около 30: полный список так и не сверили. Чтобы дополнить, нужен скриншот или список страницы «Плагины» из админки profcosmetica (или с копии в день копирования).

## Как выключать
- **Сначала отменить задачи плагина в Action Scheduler, потом выключать.** Иначе его повторяющиеся задачи бесконечно перепланируются с ошибкой (на profcosmetica так было с Image Optimization и фидами, см. `profcosmetica/docs/speed.md`). WooCommerce → Статус → Запланированные действия → фильтр по хуку плагина → отменить pending.
- По одному-два плагина за раз → проверить главную, товар, каталог с фильтром, корзину, оформление.
- Выключенный и проверенный плагин — удалить, а не оставлять выключенным.

## 1. Удалить сразу после копирования
| Плагин | Почему |
|---|---|
| WooCommerce Multilingual (WCML) | Регионы и валюты RU / GE; у нас одна валюта BYN. Удалять вместе со сниппетами регионов (группа 1 в [snippets-migration.md](snippets-migration.md)) |
| Query Monitor | Только для отладки, нагружает каждый запрос |
| WPForms Lite | На profcosmetica форм нет ни на одной странице |
| Gravatar Enhanced | Аватары в магазине не нужны |
| Gutenberg (плагин) | Сайт на Elementor, редактор блоков есть в ядре WordPress. Если WordPress.com не даёт выключить — оставить |

## 2. Оставить, но перенастроить на новый сайт
Данные profcosmetica здесь использовать нельзя: свой счётчик, свой кабинет, своя почта.

| Плагин | Что сделать |
|---|---|
| bePaid (begateway) | Сразу после копирования выключить способ оплаты и стереть ключи profcosmetica. Новые ключи — после договора с bePaid |
| WP Mail SMTP | Сразу после копирования отключить отправку. Потом — почта и домен нового сайта |
| Site Kit by Google | Отключить от GA4 profcosmetica (`GT-NC6C8L7M`), подключить свой GA4 и Search Console на этапе 6 |
| Google Listings & Ads (Google for WooCommerce) | Отключить от Merchant Center и Google Ads profcosmetica. Нужен ли новому сайту — решить на этапе 6 |
| PixelYourSite | Снять пиксели profcosmetica; свои — если будет реклама |
| Яндекс Метрика | Свой счётчик |
| XML for Google Merchant Center | Удалить фиды RU / GE (4, 7); фид BY — заново для нового сайта, на этапе 6 |
| YML for Yandex Market | Удалить фиды RU / GE (5, 6); фид BY — заново |
| MCP Adapter | Новые пароли приложений для нового сайта (переменные Windows `NEWSITE_…`) |
| All in One SEO | Название сайта, шаблоны title / description, соцсети — под новое имя. Проверить, что индексация закрыта |
| Jetpack | Отключить статистику и связи profcosmetica; выключить лишние модули (Search — поиск делает Ivory Search, формы, VideoPress) |

## 3. Дубли — оставить один
| Что | Плагины | Решение |
|---|---|---|
| Сниппеты | Code Snippets + WPCode | Оставить один. В аудите profcosmetica от 08.11 рабочие сниппеты были в WPCode, а в справочнике profcosmetica свой код — через Code Snippets: **сверить на копии**, где активные сниппеты, второй удалить |
| Скидки | Disco + Woo Discount Rules | Проверить, в каком настроены действующие скидки; второй удалить |
| Безопасность | All-In-One WP Security + защита Jetpack + защита WordPress.com | Оставить один набор правил входа |
| Google-теги | Site Kit, Google Listings & Ads, PixelYourSite, Jetpack Stats, Яндекс Метрика | Теги ставит один плагин. На profcosmetica тег Google Ads подключён дважды — на новом сайте сразу сделать один |

## 4. Проверить, нужен ли
| Плагин | Вопрос |
|---|---|
| Royal Elementor Addons | 14 файлов CSS / JS на каждой странице. Используются ли его виджеты, попапы, мега-меню? Если нет — удалить, сайт станет быстрее |
| Advanced Custom Fields | Нужен: на нём поля, которые выводят сниппеты (страна бренда, текст внизу категории, «Скрыть цену»). Без ACF страница товара упадёт с ошибкой (prof-product-brand) |
| WOOF / HUSKY (фильтр) | Нужен в каталоге; в настройках ограничить страницами каталога и оставить один скин (сейчас ~250 КБ CSS со всеми скинами) |
| Неизвестный плагин с хуком `icpd_f_plugins_arr` | ~20 мс на каждом запросе; определить по полному списку плагинов |
| Image Optimization, CTX Feed, IP2Location Country Blocker | На profcosmetica уже выключены. Если приедут с копией — удалить, предварительно отменив их задачи в Action Scheduler |

## 5. Нужные — оставить как есть
WooCommerce, Elementor, Blocksy Companion, Autoptimize, Perfect Brands (бренды), Ivory Search (поиск), Flexible Shipping (доставка), WP Crontrol, WP Consent API, Taxonomy Terms Order. `wpcomsh` и mu-плагины — платформа WordPress.com, не трогать.

- **Autoptimize:** на profcosmetica опция «Удалить блочный CSS WordPress» ломала вкладки товара — проверить, что она выключена. Опция «Inline all CSS» раздувает HTML на ~620 КБ — решить на этапе дизайна.
