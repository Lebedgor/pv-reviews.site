<?php
/**
 * Single source of truth for the landing's pages and blog articles.
 * Everything derived is generated from here: page <title>/meta, article
 * JSON-LD, breadcrumbs, the /blog card grid and sitemap.xml
 * (run: php api/generate_sitemap.php after adding/changing articles).
 *
 * Article bodies live in lang/{lang}_articles/<slug_underscores>.html
 * with a fallback to lang/en_articles/ when no translation exists.
 */

return [
  'static_pages' => [
    [
      'slug' => 'index',
      'changefreq' => 'weekly',
      'priority' => '1.0'
    ],
    [
      'slug' => 'features',
      'changefreq' => 'monthly',
      'priority' => '0.8'
    ],
    [
      'slug' => 'settings',
      'changefreq' => 'monthly',
      'priority' => '0.6'
    ],
    [
      'slug' => 'blog',
      'changefreq' => 'weekly',
      'priority' => '0.7'
    ],
    [
      'slug' => 'renew',
      'changefreq' => 'monthly',
      'priority' => '0.5'
    ]
  ],
  'articles' => [
    'blog_judge_me_yotpo_alternatives_woocommerce' => [
      'slug' => 'blog-judge-me-yotpo-alternatives-woocommerce',
      'date' => '2026-08-25',
      'og_image' => '/assets/blog_images/modal_media_and_review_details.jpg',
      'i18n' => [
        'en' => [
          'title' => 'Best Self-Hosted Alternatives to Judge.me and Yotpo for WooCommerce in 2026',
          'description' => 'Discover the best self-hosted alternatives to Judge.me and Yotpo for WooCommerce. Save thousands on monthly SaaS fees while owning your customer review data.',
          'card_title' => 'Best Self-Hosted Alternatives to Judge.me and Yotpo for WooCommerce in 2026',
          'tag' => 'SaaS Comparison',
          'excerpt' => 'Compare recurring cloud review subscriptions vs. one-time self-hosted review plugins for site speed, data ownership, and cost savings.',
          'cta' => 'Read Comparison &rarr;'
        ],
        'ru' => [
          'title' => 'Лучшие self-hosted альтернативы Judge.me и Yotpo для WooCommerce в 2026 году',
          'description' => 'Узнайте, чем заменить Judge.me и Yotpo в WooCommerce: как отказаться от ежемесячной подписки на SaaS, сэкономить тысячи долларов и сохранить полный контроль над отзывами покупателей.',
          'card_title' => 'Лучшие self-hosted альтернативы Judge.me и Yotpo для WooCommerce в 2026 году',
          'tag' => 'Сравнение с SaaS',
          'excerpt' => 'Сравниваем ежемесячные подписки на облачные сервисы и разовую покупку self-hosted плагинов: влияние на скорость сайта, владение данными и реальная экономия.',
          'cta' => 'Читать сравнение &rarr;'
        ],
        'es' => [
          'title' => 'Las mejores alternativas autoalojadas a Judge.me y Yotpo para WooCommerce en 2026',
          'description' => 'Descubre las mejores alternativas autoalojadas a Judge.me y Yotpo para WooCommerce. Ahorra miles en cuotas mensuales de SaaS manteniendo la propiedad de los datos de las reseñas de tus clientes.',
          'card_title' => 'Las mejores alternativas autoalojadas a Judge.me y Yotpo para WooCommerce en 2026',
          'tag' => 'Comparación SaaS',
          'excerpt' => 'Compara las suscripciones recurrentes en la nube con plugins autoalojados de pago único en términos de velocidad, propiedad de los datos y ahorro.',
          'cta' => 'Leer Comparación &rarr;'
        ],
        'fr' => [
          'title' => 'Les meilleures alternatives auto-hébergées à Judge.me et Yotpo pour WooCommerce en 2026',
          'description' => 'Découvrez les meilleures alternatives auto-hébergées à Judge.me et Yotpo pour WooCommerce. Économisez des milliers de dollars sur les frais mensuels SaaS tout en gardant le contrôle de vos données d\'avis clients.',
          'card_title' => 'Les meilleures alternatives auto-hébergées à Judge.me et Yotpo pour WooCommerce en 2026',
          'tag' => 'Comparatif SaaS',
          'excerpt' => 'Comparez les services cloud d\'avis facturés par abonnement aux plugins d\'avis auto-hébergés proposés avec un paiement unique, en matière de vitesse du site, de contrôle des données et d\'économies.',
          'cta' => 'Lire le Comparatif &rarr;'
        ]
      ]
    ],
    'blog_how_to_get_star_ratings_google_search_woocommerce' => [
      'slug' => 'blog-how-to-get-star-ratings-google-search-woocommerce',
      'date' => '2026-08-25',
      'og_image' => '/assets/blog_images/product_reviews_tab.jpg',
      'i18n' => [
        'en' => [
          'title' => 'How to Get Star Ratings in Google Search Results for WooCommerce (2026 Guide)',
          'description' => 'Step-by-step guide to displaying star ratings and review counts directly in Google search results using Schema.org JSON-LD structured data in WooCommerce.',
          'card_title' => 'How to Get Star Ratings in Google Search Results for WooCommerce (2026 Guide)',
          'tag' => 'SEO &amp; Rich Snippets',
          'excerpt' => 'Master Schema.org JSON-LD structured data to display star ratings and review counts directly in Google search results.',
          'cta' => 'Read Guide &rarr;'
        ],
        'ru' => [
          'title' => 'Как получить звёздный рейтинг в поиске Google для WooCommerce (руководство 2026)',
          'description' => 'Пошаговое руководство: как настроить микроразметку Schema.org JSON-LD в WooCommerce, чтобы звёзды рейтинга и количество отзывов отображались прямо в выдаче Google.',
          'card_title' => 'Как вывести звёзды рейтинга в Google для товаров WooCommerce (руководство 2026)',
          'tag' => 'SEO и расширенные сниппеты',
          'excerpt' => 'Как правильно настроить микроразметку Schema.org JSON-LD, чтобы звёзды рейтинга и количество отзывов появились в поисковой выдаче Google.',
          'cta' => 'Читать руководство &rarr;'
        ],
        'es' => [
          'title' => 'Cómo conseguir valoraciones por estrellas en los resultados de búsqueda de Google para productos de WooCommerce (guía de 2026)',
          'description' => 'Guía paso a paso para mostrar valoraciones por estrellas y recuentos de reseñas directamente en los resultados de búsqueda de Google mediante datos estructurados Schema.org JSON-LD en WooCommerce.',
          'card_title' => 'Cómo conseguir valoraciones por estrellas en los resultados de Google para WooCommerce (guía de 2026)',
          'tag' => 'SEO y fragmentos enriquecidos',
          'excerpt' => 'Domina los datos estructurados Schema.org JSON-LD para mostrar valoraciones por estrellas y recuentos de reseñas directamente en los resultados de búsqueda de Google.',
          'cta' => 'Leer Guía &rarr;'
        ],
        'fr' => [
          'title' => 'Comment obtenir des notes en étoiles dans les résultats de recherche Google pour WooCommerce (guide 2026)',
          'description' => 'Guide étape par étape pour afficher les notes en étoiles et le nombre d\'avis directement dans les résultats de recherche Google grâce aux données structurées Schema.org en JSON-LD dans WooCommerce.',
          'card_title' => 'Comment obtenir des notes en étoiles dans les résultats de recherche Google pour WooCommerce (guide 2026)',
          'tag' => 'SEO et Extraits Enrichis',
          'excerpt' => 'Maîtrisez les données structurées Schema.org en JSON-LD pour afficher les notes en étoiles et le nombre d\'avis directement dans les résultats de recherche Google.',
          'cta' => 'Lire le Guide &rarr;'
        ]
      ]
    ],
    'blog_how_to_add_video_reviews_woocommerce' => [
      'slug' => 'blog-how-to-add-video-reviews-woocommerce',
      'date' => '2026-08-25',
      'og_image' => '/assets/blog_images/review_write_form_full.jpg',
      'i18n' => [
        'en' => [
          'title' => 'How to Add Video Reviews to WooCommerce Without Crashing Your Server',
          'description' => 'Learn how to collect authentic customer video reviews in WooCommerce without server load, FFmpeg dependencies, or expensive SaaS subscriptions.',
          'card_title' => 'How to Add Video Reviews to WooCommerce Without Crashing Your Server',
          'tag' => 'Performance &amp; Media',
          'excerpt' => 'Why traditional server-side video uploads fail on WooCommerce, and how client-side in-browser video compression delivers zero server load.',
          'cta' => 'Read Guide &rarr;'
        ],
        'ru' => [
          'title' => 'Как добавить видеоотзывы в WooCommerce и не положить сервер',
          'description' => 'Как собирать честные видеоотзывы покупателей в WooCommerce без нагрузки на сервер, без сложной настройки FFmpeg и без дорогих SaaS-подписок.',
          'card_title' => 'Как добавить видеоотзывы в WooCommerce и не положить сервер',
          'tag' => 'Скорость и медиафайлы',
          'excerpt' => 'Почему серверная обработка видео тормозит и роняет WooCommerce и как сжатие прямо в браузере клиента полностью снимает нагрузку с хостинга.',
          'cta' => 'Читать руководство &rarr;'
        ],
        'es' => [
          'title' => 'Cómo añadir reseñas en vídeo a WooCommerce sin sobrecargar tu servidor',
          'description' => 'Aprende a recopilar reseñas auténticas en vídeo de clientes en WooCommerce sin sobrecargar el servidor, depender de FFmpeg ni pagar costosas suscripciones SaaS.',
          'card_title' => 'Cómo añadir reseñas en vídeo a WooCommerce sin sobrecargar tu servidor',
          'tag' => 'Rendimiento y multimedia',
          'excerpt' => 'Por qué las cargas tradicionales de vídeo en el servidor fallan en WooCommerce y cómo la compresión de vídeo en el navegador evita añadir carga al servidor.',
          'cta' => 'Leer Guía &rarr;'
        ],
        'fr' => [
          'title' => 'Comment Ajouter des Avis Vidéo à WooCommerce Sans Faire Planter Votre Serveur',
          'description' => 'Découvrez comment recueillir des avis vidéo authentiques dans WooCommerce sans surcharger le serveur, sans dépendance à FFmpeg et sans abonnement SaaS coûteux.',
          'card_title' => 'Comment Ajouter des Avis Vidéo à WooCommerce Sans Faire Planter Votre Serveur',
          'tag' => 'Performance et Média',
          'excerpt' => 'Pourquoi les téléchargements vidéo traditionnels côté serveur posent problème sur WooCommerce et comment la compression vidéo côté client dans le navigateur n\'impose aucune charge au serveur.',
          'cta' => 'Lire le Guide &rarr;'
        ]
      ]
    ],
    'blog_best_woocommerce_review_plugins_comparison' => [
      'slug' => 'blog-best-woocommerce-review-plugins-comparison',
      'date' => '2026-08-25',
      'featured' => true,
      'og_image' => '/assets/blog_images/product_reviews_tab.jpg',
      'i18n' => [
        'en' => [
          'title' => '5 Best WooCommerce Review Plugins in 2026 (Detailed Comparison)',
          'description' => 'Compare the best WooCommerce review plugins in 2026. Detailed breakdown of PVR Media Reviews, CusRev, VillaTheme, Site Reviews, and SaaS platforms like Judge.me.',
          'card_title' => '5 Best WooCommerce Review Plugins in 2026 (Detailed Comparison)',
          'tag' => 'Comparisons &amp; Buyer&#039;s Guide',
          'excerpt' => 'PVR Media Reviews vs CusRev, VillaTheme Photo Reviews, Site Reviews, and cloud platforms like Judge.me and Yotpo — features, media workflow, and real 3-year costs.',
          'cta' => 'Read Full Comparison &rarr;'
        ],
        'ru' => [
          'title' => '5 лучших плагинов отзывов для WooCommerce в 2026 году (подробное сравнение)',
          'description' => 'Сравниваем лучшие плагины отзывов для WooCommerce: PVR Media Reviews, CusRev, VillaTheme, Site Reviews и облачные SaaS-сервисы вроде Judge.me.',
          'card_title' => '5 лучших плагинов отзывов для WooCommerce в 2026 году (подробное сравнение)',
          'tag' => 'Сравнение плагинов',
          'excerpt' => 'Детальный разбор PVR Media Reviews, CusRev, VillaTheme Photo Reviews, Site Reviews и облачных сервисов (Judge.me, Yotpo): возможности, работа с медиа и расходы за 3 года.',
          'cta' => 'Читать сравнение &rarr;'
        ],
        'es' => [
          'title' => 'Los 5 mejores plugins de reseñas para WooCommerce en 2026 (comparativa detallada)',
          'description' => 'Compara los mejores plugins de reseñas para WooCommerce en 2026. Análisis detallado de PVR Media Reviews, CusRev, VillaTheme, Site Reviews y plataformas SaaS como Judge.me.',
          'card_title' => 'Los 5 mejores plugins de reseñas para WooCommerce en 2026 (comparativa detallada)',
          'tag' => 'Comparativas',
          'excerpt' => 'Comparamos PVR Media Reviews, CusRev, VillaTheme Photo Reviews, Site Reviews y plataformas cloud como Judge.me y Yotpo: funciones, flujo de medios y coste real a tres años.',
          'cta' => 'Leer la comparativa &rarr;'
        ],
        'fr' => [
          'title' => '5 Meilleurs Plugins d\'Avis pour WooCommerce en 2026 (Comparatif Détaillé)',
          'description' => 'Comparez les meilleurs plugins d\'avis pour WooCommerce en 2026. Analyse détaillée de PVR Media Reviews, CusRev, VillaTheme, Site Reviews et des plateformes SaaS comme Judge.me.',
          'card_title' => '5 Meilleurs Plugins d\'Avis pour WooCommerce en 2026 (Comparatif Détaillé)',
          'tag' => 'Comparatifs',
          'excerpt' => 'Pourquoi les téléchargements vidéo traditionnels côté serveur posent problème sur WooCommerce et comment la compression vidéo côté client dans le navigateur n\'impose aucune charge au serveur.',
          'cta' => 'Lire le comparatif &rarr;'
        ]
      ]
    ],
    'blog_seo_review_hub_woocommerce' => [
      'slug' => 'blog-seo-review-hub-woocommerce',
      'date' => '2026-09-10',
      'og_image' => '/assets/screenshots/product_reviews_tab.jpg',
      'i18n' => [
        'en' => [
          'title' => 'The WooCommerce Review Hub: Turn Customer Reviews into Organic Traffic',
          'description' => 'How the all-reviews hub, per-filter SEO templates and landing pages turn WooCommerce reviews into long-tail organic traffic — without doorway pages.',
          'card_title' => 'The WooCommerce Review Hub: Turn Customer Reviews into Organic Traffic',
          'tag' => 'WooCommerce SEO',
          'excerpt' => 'How the filterable all-reviews hub, per-combination SEO templates, and landing pages turn WooCommerce reviews into a growing long-tail organic traffic source.',
          'cta' => 'Read Guide &rarr;'
        ],
        'ru' => [
          'title' => 'SEO-хаб отзывов для WooCommerce: превращаем отзывы в органический трафик',
          'description' => 'Как общий хаб отзывов, посадочные страницы и SEO-шаблоны под комбинации фильтров привлекают в WooCommerce целевой поисковый трафик по низкочастотным запросам.',
          'card_title' => 'SEO-хаб отзывов: как отзывы в WooCommerce приносят поисковый трафик',
          'tag' => 'WooCommerce SEO',
          'excerpt' => 'Как общий хаб отзывов с фильтрами, посадочными страницами и SEO-шаблонами превращает отзывы покупателей в стабильный источник поискового трафика.',
          'cta' => 'Читать руководство &rarr;'
        ],
        'es' => [
          'title' => 'El hub de reseñas de WooCommerce: convierte las reseñas en tráfico orgánico',
          'description' => 'Cómo el hub de reseñas, las plantillas SEO por filtro y las landing pages convierten las reseñas de WooCommerce en tráfico orgánico long-tail.',
          'card_title' => 'El hub de reseñas de WooCommerce: tráfico orgánico desde las reseñas',
          'tag' => 'SEO de WooCommerce',
          'excerpt' => 'Cómo el hub filtrable de reseñas, las plantillas SEO por combinación y las landing pages convierten las reseñas en una fuente creciente de tráfico long-tail.',
          'cta' => 'Leer la guía &rarr;'
        ],
        'fr' => [
          'title' => 'Le hub d’avis WooCommerce : transformer les avis en trafic organique',
          'description' => 'Comment le hub d’avis, les modèles SEO par filtre et les landing pages transforment les avis WooCommerce en trafic organique long-tail.',
          'card_title' => 'Le hub d’avis WooCommerce : les avis comme source de trafic organique',
          'tag' => 'SEO WooCommerce',
          'excerpt' => 'Comment le hub d’avis filtrable, les modèles SEO par combinaison et les landing pages transforment les avis en source croissante de trafic long-tail.',
          'cta' => 'Lire le guide &rarr;'
        ]
      ]
    ],
    'blog_photo_video_reviews_woocommerce' => [
      'slug' => 'blog-photo-video-reviews-woocommerce',
      'date' => '2026-09-10',
      'og_image' => '/assets/screenshots/review_write_form_full.jpg',
      'i18n' => [
        'en' => [
          'title' => 'Photo & Video Reviews in WooCommerce: Why Real Customer Photos Sell',
          'description' => 'Why honest customer photos and videos outperform studio shots, and how the native WooCommerce review form collects them with zero server load.',
          'card_title' => 'Photo & Video Reviews in WooCommerce: Why Real Customer Photos Sell',
          'tag' => 'Social Proof & UGC',
          'excerpt' => 'Why honest customer photos and videos outperform studio shots — and how the native WooCommerce review form collects them with zero server load.',
          'cta' => 'Read Guide &rarr;'
        ],
        'ru' => [
          'title' => 'Фото и видео в отзывах WooCommerce: почему реальные кадры покупателей продают лучше',
          'description' => 'Почему искренние фото и видео от реальных клиентов работают убедительнее студийных фотосессий, и как встроенная форма WooCommerce собирает их без лишней нагрузки на хостинг.',
          'card_title' => 'Фото и видео в отзывах WooCommerce: почему реальные фото продают лучше',
          'tag' => 'Социальное доказательство и UGC',
          'excerpt' => 'Почему живые фото и видео от покупателей вызывают больше доверия, чем студийный глянец — и как собирать их прямо на сайте без нагрузки на сервер.',
          'cta' => 'Читать статью &rarr;'
        ],
        'es' => [
          'title' => 'Reseñas con foto y vídeo en WooCommerce: por qué las fotos reales venden',
          'description' => 'Por qué las fotos y vídeos honestos de los clientes superan a las fotos de estudio — y cómo el formulario nativo de WooCommerce los recopila sin carga en el servidor.',
          'card_title' => 'Reseñas con foto y vídeo en WooCommerce: por qué las fotos reales venden',
          'tag' => 'Prueba social y UGC',
          'excerpt' => 'Por qué las fotos y vídeos honestos de los clientes funcionan mejor que las fotos de estudio — y cómo el formulario nativo los recopila sin carga en el servidor.',
          'cta' => 'Leer el artículo &rarr;'
        ],
        'fr' => [
          'title' => 'Avis photo et vidéo dans WooCommerce : pourquoi les vraies photos vendent',
          'description' => 'Pourquoi les photos et vidéos authentiques de clients surpassent les photos studio — et comment le formulaire d’avis natif de WooCommerce les collecte sans charge serveur.',
          'card_title' => 'Avis photo et vidéo dans WooCommerce : pourquoi les vraies photos vendent',
          'tag' => 'Preuve sociale et UGC',
          'excerpt' => 'Pourquoi les photos et vidéos authentiques de clients surpassent les photos studio — et comment le formulaire natif les collecte sans charge serveur.',
          'cta' => 'Lire l’article &rarr;'
        ]
      ]
    ],
    'blog_media_gallery_criteria_ratings_woocommerce' => [
      'slug' => 'blog-media-gallery-criteria-ratings-woocommerce',
      'date' => '2026-09-10',
      'og_image' => '/assets/screenshots/modal_all_media_thumbs.jpg',
      'i18n' => [
        'en' => [
          'title' => 'WooCommerce Review Media Gallery & Custom Rating Criteria',
          'description' => 'A full WooCommerce review media gallery with an in-modal video player, plus custom per-criteria rating breakdowns instead of one bare star number.',
          'card_title' => 'WooCommerce Review Media Gallery & Custom Rating Criteria',
          'tag' => 'Review Experience & UX',
          'excerpt' => 'Two gallery modal windows with a custom in-modal video player, plus custom rating criteria that tell buyers more than a single star number.',
          'cta' => 'Read Guide &rarr;'
        ],
        'ru' => [
          'title' => 'Медиагалерея отзывов и оценки по критериям в WooCommerce',
          'description' => 'Удобная медиагалерея отзывов со встроенным видеоплеером в модальном окне и детальная оценка товаров по отдельным критериям вместо одной общей оценки.',
          'card_title' => 'Медиагалерея и критерии оценок: отзывы, вызывающие доверие',
          'tag' => 'UX и визуал отзывов',
          'excerpt' => 'Два режима модальной галереи со встроенным видеоплеером и наглядные оценки по параметрам товара, дающие покупателям максимум полезной информации.',
          'cta' => 'Читать статью &rarr;'
        ],
        'es' => [
          'title' => 'Galería multimedia de reseñas y criterios de valoración personalizados en WooCommerce',
          'description' => 'Una galería multimedia completa de reseñas WooCommerce con reproductor de vídeo en modal y valoraciones por criterios en lugar de una sola cifra.',
          'card_title' => 'Galería multimedia y criterios de valoración personalizados',
          'tag' => 'UX de reseñas',
          'excerpt' => 'Dos ventanas modales de galería con reproductor de vídeo propio, más valoraciones por criterios en lugar de una sola cifra.',
          'cta' => 'Leer el artículo &rarr;'
        ],
        'fr' => [
          'title' => 'Galerie média des avis et critères d’évaluation personnalisés dans WooCommerce',
          'description' => 'Une galerie média complète pour les avis WooCommerce avec lecteur vidéo en modal et des notes par critères plutôt qu’un simple chiffre.',
          'card_title' => 'Galerie média et critères d’évaluation personnalisés',
          'tag' => 'UX des avis',
          'excerpt' => 'Deux fenêtres modales de galerie avec lecteur vidéo dédié, plus des notes par critères plutôt qu’un simple chiffre.',
          'cta' => 'Lire l’article &rarr;'
        ]
      ]
    ],
    'blog_review_widgets_carousel_grid_woocommerce' => [
      'slug' => 'blog-review-widgets-carousel-grid-woocommerce',
      'date' => '2026-09-10',
      'og_image' => '/assets/screenshots/review_cards.jpg',
      'i18n' => [
        'en' => [
          'title' => 'Carousel & Grid Review Widgets for WordPress: Social Proof on Any Page',
          'description' => 'Display your best WooCommerce reviews anywhere on WordPress with responsive carousel and grid widgets, five-breakpoint controls, and media filtering.',
          'card_title' => 'Carousel & Grid Review Widgets for WordPress: Social Proof on Any Page',
          'tag' => 'Widgets & Social Proof',
          'excerpt' => 'Show your best WooCommerce reviews on the homepage, in categories, and on landing pages: responsive carousel and grid widgets with five-breakpoint controls.',
          'cta' => 'Read Guide &rarr;'
        ],
        'ru' => [
          'title' => 'Виджеты отзывов для WordPress: карусели и сетки для любой страницы',
          'description' => 'Показывайте лучшие отзывы покупателей в любом месте сайта: адаптивные карусели и сетки с точной настройкой под экраны любых устройств и фильтром по фото/видео.',
          'card_title' => 'Карусели и сетки отзывов: социальное доказательство на любой странице сайта',
          'tag' => 'Виджеты и блоки',
          'excerpt' => 'Выводите отзывы на главной, в каталоге или на посадочных страницах: гибкие карусели и сетки с 5 контрольными точками адаптивности.',
          'cta' => 'Читать статью &rarr;'
        ],
        'es' => [
          'title' => 'Widgets de carrusel y cuadrícula de reseñas para WordPress: prueba social en cualquier página',
          'description' => 'Muestra tus mejores reseñas de WooCommerce en cualquier página de WordPress con widgets de carrusel y cuadrícula responsivos y filtrado por medios.',
          'card_title' => 'Widgets de carrusel y cuadrícula: prueba social en cualquier página de WordPress',
          'tag' => 'Widgets',
          'excerpt' => 'Tus mejores reseñas en la portada, categorías y landings: carrusel y cuadrícula responsivos con controles por breakpoint.',
          'cta' => 'Leer el artículo &rarr;'
        ],
        'fr' => [
          'title' => 'Widgets carrousel et grille d’avis pour WordPress : preuve sociale sur toutes les pages',
          'description' => 'Affichez vos meilleurs avis WooCommerce partout sur WordPress avec des widgets carrousel et grille responsives et le filtrage par médias.',
          'card_title' => 'Widgets carrousel et grille : la preuve sociale sur toutes vos pages WordPress',
          'tag' => 'Widgets',
          'excerpt' => 'Vos meilleurs avis en accueil, catégories et landing pages : carrousel et grille responsives avec réglages par breakpoint.',
          'cta' => 'Lire l’article &rarr;'
        ]
      ]
    ],
    'blog_review_rewards_coupons_reminders_woocommerce' => [
      'slug' => 'blog-review-rewards-coupons-reminders-woocommerce',
      'date' => '2026-09-10',
      'og_image' => '/assets/screenshots/admin_coupon_settings.jpg',
      'i18n' => [
        'en' => [
          'title' => 'Coupon Rewards & Automated Review Reminders for WooCommerce',
          'description' => 'Automatic coupon rewards for WooCommerce reviews with separate amounts for text, photo, and video — plus timed reminder emails and branded notifications for every review event.',
          'card_title' => 'Coupon Rewards & Automated Review Reminders for WooCommerce',
          'tag' => 'Review Automation & Rewards',
          'excerpt' => 'Automatic coupon rewards with separate amounts for text, photo, and video reviews — plus timed reminder emails and branded notifications for every review event.',
          'cta' => 'Read Guide &rarr;'
        ],
        'ru' => [
          'title' => 'Купоны за отзывы и автонапоминания по email для WooCommerce',
          'description' => 'Автоматическая выдача купонов за отзывы с дифференцированной скидкой за текст, фото и видео, плюс цепочки email-напоминаний и стильные уведомления.',
          'card_title' => 'Купоны за отзывы и автоматические напоминания для WooCommerce',
          'tag' => 'Автоматизация и мотивация',
          'excerpt' => 'Автоматическая отправка купонов (отдельные скидки за текст, фото и видео), своевременные напоминания на почту и брендированные уведомления.',
          'cta' => 'Читать статью &rarr;'
        ],
        'es' => [
          'title' => 'Cupones de recompensa y recordatorios automáticos de reseñas para WooCommerce',
          'description' => 'Cupones automáticos por reseñas de WooCommerce con importes separados para texto, foto y vídeo — recordatorios programados y notificaciones de marca para cada evento.',
          'card_title' => 'Cupones por reseñas y recordatorios automáticos para WooCommerce',
          'tag' => 'Automatización y recompensas',
          'excerpt' => 'Cupones automáticos por reseña (importes separados para texto, foto y vídeo), recordatorios por correo y notificaciones de marca.',
          'cta' => 'Leer el artículo &rarr;'
        ],
        'fr' => [
          'title' => 'Coupons pour avis et rappels automatiques pour WooCommerce',
          'description' => 'Coupons automatiques pour les avis WooCommerce avec montants distincts pour texte, photo et vidéo — e-mails de rappel programmés et notifications de marque.',
          'card_title' => 'Coupons pour avis et rappels automatiques pour WooCommerce',
          'tag' => 'Automatisation et récompenses',
          'excerpt' => 'Coupons automatiques par avis (montants distincts pour texte, photo et vidéo), e-mails de rappel et notifications de marque.',
          'cta' => 'Lire l’article &rarr;'
        ]
      ]
    ],
    'blog_review_moderation_trust_woocommerce' => [
      'slug' => 'blog-review-moderation-trust-woocommerce',
      'date' => '2026-09-10',
      'og_image' => '/assets/screenshots/review_cards.jpg',
      'i18n' => [
        'en' => [
          'title' => 'Review Moderation, Anti-Spam & Trust Signals for WooCommerce Stores',
          'description' => 'Anti-spam limits, verified-buyer permissions, helpfulness voting, store replies, reviewer avatars, and a full analytics dashboard — how to keep WooCommerce reviews clean and trusted.',
          'card_title' => 'Review Moderation, Anti-Spam & Trust Signals for WooCommerce Stores',
          'tag' => 'Moderation & Trust',
          'excerpt' => 'Anti-spam limits, verified-buyer permissions, helpfulness voting, store replies, and a full analytics dashboard — how to keep WooCommerce reviews clean and trusted.',
          'cta' => 'Read Guide &rarr;'
        ],
        'ru' => [
          'title' => 'Модерация отзывов, защита от спама и доверие покупателей в WooCommerce',
          'description' => 'Защита от спам-атак, отзывы только от реальных покупателей, голосование за полезность, официальные ответы магазина, аватары и подробная аналитика в админке.',
          'card_title' => 'Модерация, защита от спама и факторы доверия для интернет-магазинов',
          'tag' => 'Модерация и безопасность',
          'excerpt' => 'Ограничения против спама, бейджи проверенных покупателей, оценка полезности, ответы от лица магазина и наглядная аналитика отзывов в WordPress.',
          'cta' => 'Читать статью &rarr;'
        ],
        'es' => [
          'title' => 'Moderación de reseñas, anti-spam y señales de confianza para tiendas WooCommerce',
          'description' => 'Límites anti-spam, compradores verificados, votos de utilidad, respuestas de la tienda, avatares y un panel de análisis completo en el admin de WordPress.',
          'card_title' => 'Moderación, anti-spam y señales de confianza para tiendas WooCommerce',
          'tag' => 'Moderación y confianza',
          'excerpt' => 'Límites anti-spam, reseñas solo de compradores verificados, votos de utilidad, respuestas de la tienda y analítica en el admin.',
          'cta' => 'Leer el artículo &rarr;'
        ],
        'fr' => [
          'title' => 'Modération des avis, anti-spam et signaux de confiance pour les boutiques WooCommerce',
          'description' => 'Limites anti-spam, acheteurs vérifiés uniquement, votes d’utilité, réponses de la boutique, avatars et tableau de bord analytique dans l’admin WordPress.',
          'card_title' => 'Modération, anti-spam et signaux de confiance pour boutiques WooCommerce',
          'tag' => 'Modération et confiance',
          'excerpt' => 'Limites anti-spam, avis d’acheteurs vérifiés uniquement, votes d’utilité, réponses de la boutique et tableau de bord analytique.',
          'cta' => 'Lire l’article &rarr;'
        ]
      ]
    ]
  ],
  'blog_hero' => [
    'en' => [
      'title' => 'E-Commerce Reviews &amp; Social Proof Guides',
      'subtitle' => 'Learn how to scale customer social proof, add lightweight video reviews, optimize for Google Rich Snippets, and avoid recurring SaaS costs.'
    ],
    'ru' => [
      'title' => 'Руководства по отзывам и социальному доказательству в e-commerce',
      'subtitle' => 'Практические материалы о том, как собирать живые отзывы и видео, настраивать расширенные сниппеты в Google и развивать магазин без лишних расходов на сторонние SaaS-сервисы.'
    ],
    'es' => [
      'title' => 'Guías sobre reseñas y prueba social para tiendas online',
      'subtitle' => 'Aprende a potenciar la prueba social de tus clientes, añadir reseñas en vídeo ligeras, optimizar los fragmentos enriquecidos de Google y evitar los costes recurrentes de SaaS.'
    ],
    'fr' => [
      'title' => 'Guides sur les avis clients et la preuve sociale en e-commerce',
      'subtitle' => 'Découvrez comment développer la preuve sociale, ajouter des avis vidéo légers, optimiser vos pages pour les extraits enrichis Google et éviter les coûts récurrents des services SaaS.'
    ]
  ]
];
