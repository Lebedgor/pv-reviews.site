# pv-reviews.site — лендинг, покупка и биллинг — Agent Instructions

Публичный сайт проекта **PVR Media Reviews for WooCommerce**: лендинг, страницы возможностей, блог, страница продления (renew), покупка Pro через Lemon Squeezy/Getly и выдача лицензий.

Отдельный проект — **не часть WordPress-плагина**. Ранее находился в корне репозитория плагина (папка `pv-reviews.site/`), теперь живёт самостоятельно и деплоится отдельно.

## Связь с другими проектами

- Сервер лицензий: `https://admin.pv-reviews.site/provision.php` (см. `ADMIN_API_URL` в `.env`; ранее — `admin.claz.site`, домен отключён). Правила сервера лицензий — в его собственном `AGENTS.md`.
- Клиентская логика лицензий/обновлений — в репозитории плагина: `includes/class-pvr-updater.php`, `includes/class-pvr-pro-updater.php`, `pro/license/class-protect.php`.

## Структура

```
pv-reviews.site/
├── index.php                   # роутинг/вход
├── router.php                  # язык, маршрутизация, переводы (t()), выбор шаблона
├── .env                        # секреты (НЕ коммитить, НЕ логировать)
├── assets/                     # ДЕЙСТВУЮЩИЕ стили (styles.css), логотипы, скриншоты, favicons
├── pages/                      # PHP-шаблоны страниц
│   ├── layout.php              # общий layout: head, навигация, футер, чекаут-модалка, скрипты
│   ├── index.php               # главная (article, pricing, faq)
│   ├── blog.php                # страница /blog (hero + сетка карточек из реестра)
│   ├── registry.php            # реестр контента: статьи (slug, даты, og, i18n-мета), hero блога
│   ├── page.php                # generic-страница (контент из переводов)
│   ├── renew.php               # страница продления (self-contained стили/скрипт)
│   └── 404.php
├── lang/                       # переводы по языкам: en.json (база), ru/es/fr.json
│   ├── en_articles/            # тела статей (en, источник правды)
│   │   └── …(ru_articles/, es_articles/, fr_articles/ — переводы тел; при отсутствии файла — fallback на en)
│   └── …json: тексты страниц по ключам (index.pricing.*, index.faq.items, index.jsonld и т.д.)
├── api/
│   ├── config.php              # настройки бэкенда (читает .env)
│   ├── generate_sitemap.php    # CLI: перегенерация sitemap.xml из реестра
│   ├── license/provision.php   # server-to-server клиент: создание/продление/поиск лицензий
│   ├── webhooks/lemon-squeezy.php  # вебхуки заказов: покупка/возврат/отзыв лицензий
│   ├── renew/, checkout/, providers/  # продление, чекаут, провайдеры платежей
│   └── test.php                # CLI-утилита тестов (domain, token, checkout, webhook, orders, all)
├── docs/, storage/, llms.txt, robots.txt, sitemap.xml
```

Историческая папка `html/` (статический сайт) удалена — лендинг полностью PHP: `router.php` → `pages/*.php` + тексты из `lang/*.json`. Правки контента главной делаются в `pages/index.php` (разметка) и `lang/en.json` + `lang/{ru,es,fr}.json` (тексты); JSON-LD хранится в `lang/{lang}.json` под ключом `index.jsonld`.

## Ключевые правила

- **Секреты только в `.env`** (и он должен лежать вне public web root): `PAYMENT_PROVIDER` (`getly` или `lemonsqueezy`), `GETLY_API_KEY`, `GETLY_WEBHOOK_SECRET`, `GETLY_PRODUCT_ID`, `LEMONSQUEEZY_API_KEY`, `LEMONSQUEEZY_WEBHOOK_SECRET`, `LEMONSQUEEZY_STORE_ID`, `LEMONSQUEEZY_VARIANT_ID`, `ADMIN_API_URL`, `ADMIN_API_KEY`, `RESEND_*`, `SQLITE_DB_PATH` (SQLite в `storage/`). Не коммитить, не логировать, не отправлять в responses.
- `ADMIN_API_URL=https://admin.pv-reviews.site/provision.php` — сервер лицензий. `ADMIN_API_KEY` — ключ авторизации server-to-server вызовов.
- **Бизнес-модель лицензии**: одна вечная лицензия (`l_expires = 'never'`) с обновлениями включёнными навсегда (`l_updates_expires = 0`); домен привязывается при первой активации клиента. Действует оффер «Founding Customer»: первые 100 лицензий — навсегда, далее планируется годовая подписка для новых покупателей (старые остаются вечными). Отдельной подписки на обновления больше нет.
- `test.php` — только CLI (`PHP_SAPI !== 'cli'` → 403); запускается как `php api/test.php [test_name]`.
- PHP: `declare(strict_types=1)` во всех файлах `api/`.
- Деплой отдельный от плагина (`/var/www/pv-reviews.site`). В установочные архивы плагина не включается (исключён через `.gitignore` и `build.sh`).
- Все страницы — PHP (`pages/*.php`) с общим layout (`pages/layout.php`): навигация/футер/чекаут-модалка правятся в одном месте и подхватываются всеми страницами.
- **Медиа в статьях**: исходники — `assets/screenshots/` (png/mov + сгенерированные jpg/mp4, см. `assets/screenshots/README.md`). В разметке статьи медиа оформляются как `<figure class="pvr-article__media">` (варианты `--narrow`, `--grid`) с `<figcaption>`; видео — `<span class="pvr-article__video-thumb"><video muted playsinline preload="none" poster="/assets/screenshots/posters/<имя-ролика>.jpg">` (без inline `controls`; постер — кадр из самого ролика, генерируется `assets/screenshots/make_posters.sh`, см. `assets/screenshots/README.md`). Все `img` и video-thumb внутри `.pvr-article__media` автоматически собираются self-contained скриптом в конце статьи в общий лайтбокс (стрелки/клавиатура/свайп, счётчик, подпись из figcaption; видео открывается в плеере, стартует muted). Ширина контента — `--max-width: 1200px` для всех страниц и статей (на десктопах от 1024px контейнер дополнительно ограничен `min(1200px, 80%)`). Все `<video>` стартуют без звука (`muted`).
- **Стиль статей и ссылки**: статьи пишутся человеческим языком для обычного читателя — живой тон, короткие фразы, термины объясняются по ходу текста; без жаргона, канцелярита и ИИ-штампов (никаких «TL;DR», обезличенных деклараций и шаблонных оборотов). Ссылка на плагин в статьях — всегда `https://wordpress.org/plugins/pvr-media-reviews-for-woocommerce/` (внешняя, с `target="_blank" rel="noopener noreferrer"`), а не на главную лендинга. Внутренние ссылки лендинга — относительные, локализуются через `localizeBodyLinks()`.
- **Навигация**: пунктов меню с якорями (`#article`, `#pvr-get`, `#pricing`, `#faq`) указывают на главную страницу (`/#…` — фикс в `navUrl()`), т.к. секции живут на index; `scroll-margin-top: 88px` в CSS компенсирует фиксированную шапку. Ссылки на Renew в меню нет (страница `/renew` доступна по прямой ссылке). **Хлебные крошки** (`router.php` `getBreadcrumbs()` → `pages/layout.php` после `</nav>`) выводятся на всех страницах, кроме главной и 404; подписи — в топ-уровневом ключе `breadcrumbs` каждого `lang/*.json`, для статей блога берётся `headline` из JSON-LD (короткий, уже переведён); ссылки относительные (`navUrl()`); `<body class="has-breadcrumbs">` компенсирует верхние паддинги страниц (`styles.css`, секция 5.1).
- **Статьи блога**: главная статья (`lang/en_articles/index.html`) — короткое саммари (баннер, лид, видео «добавление и публикация отзыва», callout «Live demo», сетка из 6 карточек со ссылками на тематические статьи, CTA). Подробные тексты живут в шести тематических статьях блога (`blog-seo-review-hub-*`, `blog-photo-video-reviews-*`, `blog-media-gallery-criteria-ratings-*`, `blog-review-widgets-carousel-grid-*`, `blog-review-rewards-coupons-reminders-*`, `blog-review-moderation-trust-*`) плюс 4 старых сравнения/гида. В статьях и на главной есть callout «Live demo» со ссылками на демо-магазин с демо-данными: `demo.pv-reviews.site` — `/product/hoodie-with-logo/` (страница товара с отзывами), `/product-reviews/` (хаб всех отзывов с фильтрами), `/sample-page/` (виджет карусель), `/reviews-grid/` (виджет сетка).
- **Реестр контента** (`pages/registry.php`) — единый источник правды: список статей (slug, дата, og_image, флаг `featured`, i18n-заголовки/описания/теги/тизеры карточек) и hero блога. Из него генерируются: `<title>`/meta/JSON-LD статей, хлебные крошки, сетка карточек `/blog` (шаблон `pages/blog.php`), `sitemap.xml` (перегенерация: `php api/generate_sitemap.php` после добавления/правок статей). Тела статей — файлы `lang/{lang}_articles/<slug_underscore>.html` с fallback на `lang/en_articles/` (переводы можно добавлять постепенно, статьи не 404; русские, испанские и французские версии всех 11 статей лежат в `lang/ru_articles/`, `lang/es_articles/` и `lang/fr_articles/`). Внутренние ссылки в телах пишутся языко-нейтральными (`/blog-…`, `/#pricing`, `/`) — роутер (`localizeBodyLinks()`) переписывает их под текущий язык при выдаче. Новая статья = файл тела + запись в реестре + перегенерация сайтмапа; JSON переводов статьи больше не содержит.
