/*
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

--
-- Структура таблицы `#__ishop_products`
-- здесь хранятся данные товаров
--

CREATE TABLE IF NOT EXISTS `#__ishop_products`
(
    `id`               int unsigned                                             NOT NULL AUTO_INCREMENT,
    `manufacturer_id`  int unsigned                                             NOT NULL DEFAULT 0      COMMENT 'ID в #__ishop_manufacturers производителя товара',
    `supplier_id`      int unsigned                                             NOT NULL DEFAULT 0      COMMENT 'ID в #__ishop_suppliers поставщика товара',
    `prefix_id`        int unsigned                                             NOT NULL DEFAULT 0      COMMENT 'ID в #__ishop_prefixes префикса названия товара',
    `title`            varchar(255)                                             NOT NULL                COMMENT 'Наименование модели техники',
    `alias`            varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin   NOT NULL                COMMENT 'Псевдоним товара для url',
    `introtext`        mediumtext                                               NOT NULL                COMMENT 'Краткое описание товара',
    `fulltext`         mediumtext                                               NOT NULL                COMMENT 'Полное описание товара',
    `state`            tinyint                                                  NOT NULL DEFAULT 0      COMMENT 'Статус публикации товара',
    `type`             tinyint                                                  NOT NULL DEFAULT 1      COMMENT 'Тип товара: 1 - для продажи, 2 - сопутствующий, 3 - не продается',
    `catid`            int unsigned                                             NOT NULL DEFAULT 0      COMMENT 'ID в #__categories главной категории товара',
    `created`          datetime                                                 NOT NULL                COMMENT 'Дата создания товара',
    `created_by`       int unsigned                                             NOT NULL DEFAULT 0      COMMENT 'ID в #__users пользователя, который создал товар',
    `created_by_alias` varchar(255)                                             NOT NULL DEFAULT ''     COMMENT 'Псевдоним автора',
    `modified`         datetime                                                 NOT NULL                COMMENT 'Дата изменения карточки товара',
    `modified_by`      int unsigned                                             NOT NULL DEFAULT 0      COMMENT 'ID в #__users пользователя, который изменил товар',
    `checked_out`      int unsigned                                                                     COMMENT 'Блокировка записи',
    `checked_out_time` datetime                                                 NULL     DEFAULT NULL   COMMENT 'Дата блокировки записи',
    `publish_up`       datetime                                                 NULL     DEFAULT NULL   COMMENT 'Дата начала публикации',
    `publish_down`     datetime                                                 NULL     DEFAULT NULL   COMMENT 'Дата окончания публикации',
    `images`           text                                                     NOT NULL                COMMENT 'Изображения товара',
    `reach_icons`      text                                                     NOT NULL                COMMENT 'Иконки с описанием функций товара',
    `reach_features`   text                                                     NOT NULL                COMMENT 'Главные особенности товара',
    `videos`           text                                                     NOT NULL                COMMENT 'Видео о товаре',
    `documents`        text                                                     NOT NULL                COMMENT 'Файлы и документы по товару',
    `attribs`          varchar(5120)                                            NOT NULL                COMMENT 'Дополнительные атрибуты',
    `version`          int unsigned                                             NOT NULL DEFAULT 1      COMMENT 'Версия',
    `ordering`         int                                                      NOT NULL DEFAULT 0      COMMENT 'Сортировка товаров',
    `metadata`         text                                                     NOT NULL                COMMENT 'Метаданные',
    `metatitle`        varchar(255)                                             NOT NULL DEFAULT ''     COMMENT 'Метаданные title',
    `metakey`          text                                                     NOT NULL DEFAULT ''     COMMENT 'Метаданные keywords',
    `metadesc`         text                                                     NOT NULL DEFAULT ''     COMMENT 'Метаданные description',
    `access`           int unsigned                                             NOT NULL DEFAULT 0      COMMENT 'Настройки доступа',
    `hits`             int unsigned                                             NOT NULL DEFAULT 0      COMMENT 'Количество просмотров',
    `featured`         tinyint unsigned                                         NOT NULL DEFAULT 0      COMMENT 'Устанавливает товар избранным',
    `language`         char(7)                                                  NOT NULL                COMMENT 'Код языка',
    `gtin`             bigint unsigned                                          NOT NULL DEFAULT 0      COMMENT 'Международный GTIN код товара',
    `price`            decimal(10, 2) unsigned                                  NOT NULL DEFAULT 0      COMMENT 'Основная цена товара',
    `old_price`        decimal(10, 2) unsigned                                  NOT NULL DEFAULT 0      COMMENT 'Цена товара без скидок',
    `sale_price`       decimal(10, 2) unsigned                                  NOT NULL DEFAULT 0      COMMENT 'Цена товара со всеми действующими скидками',
    `cost_price`       decimal(10, 2) unsigned                                  NOT NULL DEFAULT 0      COMMENT 'Цена закупки товара',
    `stock`            mediumint                                                NOT NULL DEFAULT '-1'   COMMENT 'Количество товаров в наличии, -1 неограниченно',
    `related`          varchar(255)                                             NOT NULL DEFAULT ''     COMMENT 'ID связанных товаров',
    `similar`          varchar(255)                                             NOT NULL DEFAULT ''     COMMENT 'ID похожих товаров',
    `offers`           varchar(255)                                             NOT NULL DEFAULT ''     COMMENT 'ID модификаций этого товара',
    `services`         varchar(255)                                             NOT NULL DEFAULT ''     COMMENT 'ID сервисных центров в #__ishop_services',
    `importers`        varchar(255)                                             NOT NULL DEFAULT ''     COMMENT 'ID импортеров товара в #__ishop_suppliers',
    `width`            decimal(10, 2) unsigned                                  NOT NULL DEFAULT 0      COMMENT 'Ширина товара',
    `height`           decimal(10, 2) unsigned                                  NOT NULL DEFAULT 0      COMMENT 'Высота товара',
    `depth`            decimal(10, 2) unsigned                                  NOT NULL DEFAULT 0      COMMENT 'Глубина товара',
    `weight`           decimal(10, 2) unsigned                                  NOT NULL DEFAULT 0      COMMENT 'Вес товара',
    `width_pkg`        decimal(10, 2) unsigned                                  NOT NULL DEFAULT 0      COMMENT 'Ширина в упаковке',
    `height_pkg`       decimal(10, 2) unsigned                                  NOT NULL DEFAULT 0      COMMENT 'Высота в упаковке',
    `depth_pkg`        decimal(10, 2) unsigned                                  NOT NULL DEFAULT 0      COMMENT 'Глубина в упаковке',
    `weight_pkg`       decimal(10, 2) unsigned                                  NOT NULL DEFAULT 0      COMMENT 'Вес в упаковке',
    `equipment`        text                                                     NOT NULL DEFAULT ''     COMMENT 'Комплектация товара',
    `delivery`         text                                                     NOT NULL DEFAULT ''     COMMENT 'Сроки доставки товара по зонам',
    `country`          varchar(255)                                             NOT NULL DEFAULT ''     COMMENT 'Страна производства',
    `warranty`         varchar(255)                                             NOT NULL DEFAULT ''     COMMENT 'Срок гарантии производителя',
    `rating`           float(4, 2) unsigned                                     NOT NULL DEFAULT 0      COMMENT 'Средний рейтинг товара по оценкам пользователей',
    `reviews_count`    int unsigned                                             NOT NULL DEFAULT 0      COMMENT 'Количество оценок',
    `search_keys`      text                                                     NOT NULL DEFAULT ''     COMMENT 'Текстовые ключи для поиска товара разделенные запятыми',
    `parse_url`        varchar(400)                                             NOT NULL DEFAULT ''     COMMENT 'URL для получения данных о товаре',
    `onliner_url`      varchar(400)                                             NOT NULL DEFAULT ''     COMMENT 'URL товара в каталоге Onliner.by',
    `wb_id`            varchar(400)                                             NOT NULL DEFAULT 0      COMMENT 'Идентификатор карточки Wildberries',
    `ozon_id`          varchar(400)                                             NOT NULL DEFAULT 0      COMMENT 'Идентификатор карточки Ozon',
    `bitrix24_id`      int unsigned                                             NOT NULL DEFAULT 0      COMMENT 'ID товара в системе Битрикс24',
    `system1c_guid`    varchar(40)                                              NOT NULL DEFAULT ''     COMMENT 'ID товара в системе учета 1С',
    `system1c_name`    varchar(255)                                             NOT NULL DEFAULT ''     COMMENT 'Наименование товара в системе учета 1С',
    `zoomos_id`        int unsigned                                             NOT NULL DEFAULT 0      COMMENT 'ID товара в системе Zoomos',
    `shopmanager_id`   int unsigned                                             NOT NULL DEFAULT 0      COMMENT 'ID товара в системе ShopManager',
    `onliner_sku`      varchar(100)                                             NOT NULL DEFAULT ''     COMMENT 'Код товара для сервиса Onliner',
    PRIMARY KEY (`id`),
    KEY `idx_manufacturer` (`manufacturer_id`),
    KEY `idx_supplier` (`supplier_id`),
    KEY `idx_alias` (`alias`(191)),
    KEY `idx_state` (`state`),
    KEY `idx_catid` (`catid`),
    KEY `idx_createdby` (`created_by`),
    KEY `idx_checkout` (`checked_out`),
    KEY `idx_access` (`access`),
    KEY `idx_featured_catid` (`featured`, `catid`),
    KEY `idx_language` (`language`),
    KEY `idx_price` (`price`),
    KEY `idx_rating` (`rating`),
    KEY `idx_bx24` (`bitrix24_id`),
    KEY `idx_1c` (`system1c_guid`),
    KEY `idx_zoomos` (`zoomos_id`),
    KEY `idx_shopmanager` (`shopmanager_id`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE = utf8mb4_unicode_ci COMMENT ='Хранение информации о товарах магазина в iShop';

--
-- Структура таблицы `#__ishop_fields`
-- здесь хранится список характеристик для каталога
--

CREATE TABLE IF NOT EXISTS `#__ishop_fields`
(
    `id`               int unsigned                                             NOT NULL AUTO_INCREMENT,
    `title`            varchar(255)                                             NOT NULL                    COMMENT 'Название характеристики',
    `alias`            varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin   NOT NULL                    COMMENT 'Псевдоним характеристики для URL',
    `introtext`        mediumtext                                               NOT NULL                    COMMENT 'Краткое описание характеристики',
    `fulltext`         mediumtext                                               NOT NULL                    COMMENT 'Полное описание характеристики',
    `state`            tinyint                                                  NOT NULL DEFAULT 0          COMMENT 'Статус публикации характеристики',
    `created`          datetime                                                 NOT NULL                    COMMENT 'Дата создания характеристики',
    `created_by`       int unsigned                                             NOT NULL DEFAULT 0          COMMENT 'ID в #__users пользователя, который создал характеристику',
    `created_by_alias` varchar(255)                                             NOT NULL DEFAULT ''         COMMENT 'Псевдоним автора',
    `modified`         datetime                                                 NOT NULL                    COMMENT 'Дата изменения характеристики',
    `modified_by`      int unsigned                                             NOT NULL DEFAULT 0          COMMENT 'ID в #__users пользователя, который изменил характеристику',
    `checked_out`      int unsigned                                                                         COMMENT 'Блокировка записи',
    `checked_out_time` datetime                                                 NULL     DEFAULT NULL       COMMENT 'Дата блокировки записи',
    `access`           int unsigned                                             NOT NULL DEFAULT 0          COMMENT 'Настройки доступа',
    `images`           text                                                     NOT NULL                    COMMENT 'Изображения характеристики',
    `icon`             varchar(25)                                              NOT NULL DEFAULT ''         COMMENT 'Имя иконки',
    `color`            varchar(6)                                               NOT NULL DEFAULT ''         COMMENT 'Код цвета hex',
    `type`             tinyint                                                  NOT NULL DEFAULT 0          COMMENT 'Тип поля, 0 - number, 1 - list, 2 - bool',
    `unit`             varchar(25)                                              NOT NULL DEFAULT ''         COMMENT 'Единицы измерения характеристики',
    `ordering`         int                                                      NOT NULL DEFAULT 0          COMMENT 'Сортировка характеристик',
    `language`         char(7)                                                  NOT NULL                    COMMENT 'Код языка',
    PRIMARY KEY (`id`),
    KEY `idx_alias` (`alias`(191)),
    KEY `idx_state` (`state`),
    KEY `idx_createdby` (`created_by`),
    KEY `idx_checkout` (`checked_out`),
    KEY `idx_access` (`access`),
    KEY `idx_language` (`language`)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE = utf8mb4_unicode_ci COMMENT ='Хранение информации о характеристик для каталога в iShop';

--
-- Структура таблицы `#__ishop_fields_map`
-- здесь хранятся связи товаров со значениями характеристик для каталога
--

CREATE TABLE IF NOT EXISTS `#__ishop_fields_map`
(
    `id`               int unsigned                                             NOT NULL AUTO_INCREMENT,
    `product_id`       int unsigned                                             NOT NULL DEFAULT 0          COMMENT 'ID товара в #__ishop_products',
    `field_id`         int unsigned                                             NOT NULL DEFAULT 0          COMMENT 'ID характеристики в #__ishop_fields',
    `value`            float(10, 2)                                             NOT NULL DEFAULT 0          COMMENT 'Значение либо ID значения в #__ishop_values',
    `hint`             varchar(255)                                             NOT NULL DEFAULT ''         COMMENT 'Особые пометки к значению характеристики',
    PRIMARY KEY (`id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_field_id` (`field_id`),
    KEY `idx_value` (`value`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE = utf8mb4_unicode_ci COMMENT ='Хранение информации о связи товаров со значениями характеристик для каталога в iShop';

--
-- Структура таблицы `#__ishop_groups`
-- здесь хранится список групп характеристик
--

CREATE TABLE IF NOT EXISTS `#__ishop_groups`
(
    `id`               int unsigned                                             NOT NULL AUTO_INCREMENT,
    `title`            varchar(255)                                             NOT NULL                    COMMENT 'Название группы',
    `alias`            varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin   NOT NULL                    COMMENT 'Псевдоним группы для URL',
    `introtext`        mediumtext                                               NOT NULL                    COMMENT 'Краткое описание группы',
    `fulltext`         mediumtext                                               NOT NULL                    COMMENT 'Полное описание группы',
    `state`            tinyint                                                  NOT NULL DEFAULT 0          COMMENT 'Статус публикации группы',
    `created`          datetime                                                 NOT NULL                    COMMENT 'Дата создания группы',
    `created_by`       int unsigned                                             NOT NULL DEFAULT 0          COMMENT 'ID в #__users пользователя, который создал группу',
    `created_by_alias` varchar(255)                                             NOT NULL DEFAULT ''         COMMENT 'Псевдоним автора',
    `modified`         datetime                                                 NOT NULL                    COMMENT 'Дата изменения группы',
    `modified_by`      int unsigned                                             NOT NULL DEFAULT 0          COMMENT 'ID в #__users пользователя, который изменил группу',
    `checked_out`      int unsigned                                                                         COMMENT 'Блокировка записи',
    `checked_out_time` datetime                                                 NULL     DEFAULT NULL       COMMENT 'Дата блокировки записи',
    `images`           text                                                     NOT NULL                    COMMENT 'Изображения группы',
    `icon`             varchar(25)                                              NOT NULL DEFAULT ''         COMMENT 'Имя иконки',
    `ordering`         int                                                      NOT NULL DEFAULT 0          COMMENT 'Сортировка групп',
    `language`         char(7)                                                  NOT NULL                    COMMENT 'Код языка',
    PRIMARY KEY (`id`),
    KEY `idx_alias` (`alias`(191)),
    KEY `idx_state` (`state`),
    KEY `idx_createdby` (`created_by`),
    KEY `idx_checkout` (`checked_out`),
    KEY `idx_language` (`language`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE = utf8mb4_unicode_ci COMMENT ='Хранение информации о группах характеристик в iShop';

--
-- Структура таблицы `#__ishop_values`
-- здесь хранится список значений характеристик для каталога
--

CREATE TABLE IF NOT EXISTS `#__ishop_values`
(
    `id`               int unsigned                                             NOT NULL AUTO_INCREMENT,
    `value`            varchar(255)                                             NOT NULL DEFAULT ''         COMMENT 'Значение',
    `alias`            varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin   NOT NULL DEFAULT ''         COMMENT 'Псевдоним значения характеристики для URL',
    `field_id`         int unsigned                                             NOT NULL DEFAULT 0          COMMENT 'ID в #__ishop_fields характеристики',
    `images`           text                                                     NOT NULL                    COMMENT 'Изображения',
    `icon`             varchar(25)                                              NOT NULL DEFAULT ''         COMMENT 'Имя иконки',
    `ordering`         int                                                      NOT NULL DEFAULT 0          COMMENT 'Сортировка значений',
    `language`         char(7)                                                  NOT NULL                    COMMENT 'Код языка',
    PRIMARY KEY (`id`),
    KEY `idx_alias` (`alias`(191)),
    KEY `idx_field_id` (`field_id`),
    KEY `idx_language` (`language`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE = utf8mb4_unicode_ci COMMENT ='Хранение значений характеристик для каталога в iShop';

--
-- Структура таблицы `#__ishop_manufacturers`
-- здесь хранятся данные о производителях товаров
--

CREATE TABLE IF NOT EXISTS `#__ishop_manufacturers`
(
    `id`               int unsigned                                           NOT NULL AUTO_INCREMENT,
    `title`            varchar(255)                                           NOT NULL DEFAULT ''   COMMENT 'Наименование производителя',
    `alias`            varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT ''   COMMENT 'Псевдоним для URL',
    `introtext`        mediumtext                                             NOT NULL              COMMENT 'Краткое описание производителя',
    `fulltext`         mediumtext                                             NOT NULL              COMMENT 'Полное описание производителя',
    `state`            tinyint                                                NOT NULL DEFAULT 0    COMMENT 'Состояние публикации производителя',
    `created`          datetime                                               NOT NULL              COMMENT 'Дата создания',
    `created_by`       int unsigned                                           NOT NULL DEFAULT 0    COMMENT 'ID в #__users пользователя, который создал производителя',
    `created_by_alias` varchar(255)                                           NOT NULL DEFAULT ''   COMMENT 'Псевдоним автора',
    `modified`         datetime                                               NOT NULL              COMMENT 'Дата изменения',
    `modified_by`      int unsigned                                           NOT NULL DEFAULT 0    COMMENT 'ID в #__users пользователя, который изменил производителя',
    `checked_out`      int unsigned                                                                 COMMENT 'Блокировка',
    `checked_out_time` datetime                                               NULL     DEFAULT NULL COMMENT 'Дата блокировки',
    `publish_up`       datetime                                               NULL     DEFAULT NULL COMMENT 'Дата начала публикации',
    `publish_down`     datetime                                               NULL     DEFAULT NULL COMMENT 'Дата окончания публикации',
    `images`           text                                                   NOT NULL              COMMENT 'Изображения производителя',
    `attribs`          varchar(5120)                                          NOT NULL              COMMENT 'Дополнительные атрибуты',
    `ordering`         int                                                    NOT NULL DEFAULT 0    COMMENT 'Порядок для сортировки',
    `metatitle`        varchar(255)                                           NOT NULL DEFAULT ''   COMMENT 'Метаданные title',
    `metakey`          text                                                   NOT NULL DEFAULT ''   COMMENT 'Метаданные keywords',
    `metadesc`         text                                                   NOT NULL DEFAULT ''   COMMENT 'Метаданные description',
    `access`           int unsigned                                           NOT NULL DEFAULT 0    COMMENT 'Настройки прав доступа',
    `hits`             int unsigned                                           NOT NULL DEFAULT 0    COMMENT 'Количество просмотров',
    `metadata`         text                                                   NOT NULL              COMMENT 'Метаданные',
    `language`         char(7)                                                NOT NULL              COMMENT 'Код языка',
    `site_url`         varchar(255)                                           NOT NULL DEFAULT ''   COMMENT 'Ссылка на сайт производителя',
    PRIMARY KEY (`id`),
    KEY `idx_alias` (`alias`(191)),
    KEY `idx_state` (`state`),
    KEY `idx_createdby` (`created_by`),
    KEY `idx_checkout` (`checked_out`),
    KEY `idx_access` (`access`),
    KEY `idx_language` (`language`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE = utf8mb4_unicode_ci COMMENT ='Хранение информации о производителях iShop';

--
-- Структура таблицы `#__ishop_suppliers`
-- здесь хранятся данные о производителях товаров
--

CREATE TABLE IF NOT EXISTS `#__ishop_suppliers`
(
    `id`               int unsigned                                           NOT NULL AUTO_INCREMENT,
    `title`            varchar(255)                                           NOT NULL DEFAULT ''       COMMENT 'Наименование поставщика',
    `alias`            varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT ''       COMMENT 'Псевдоним для URL',
    `introtext`        mediumtext                                             NOT NULL                  COMMENT 'Краткое описание поставщика',
    `fulltext`         mediumtext                                             NOT NULL                  COMMENT 'Полное описание поставщика',
    `state`            tinyint                                                NOT NULL DEFAULT 0        COMMENT 'Состояние публикации',
    `created`          datetime                                               NOT NULL                  COMMENT 'Дата создания',
    `created_by`       int unsigned                                           NOT NULL DEFAULT 0        COMMENT 'ID в #__users пользователя, который создал поставщика',
    `created_by_alias` varchar(255)                                           NOT NULL DEFAULT ''       COMMENT 'Псевдоним автора',
    `modified`         datetime                                               NOT NULL                  COMMENT 'Дата изменения',
    `modified_by`      int unsigned                                           NOT NULL DEFAULT 0        COMMENT 'ID в #__users пользователя, который изменил поставщика',
    `checked_out`      int unsigned                                                                     COMMENT 'Блокировка',
    `checked_out_time` datetime                                               NULL     DEFAULT NULL     COMMENT 'Дата блокировки',
    `publish_up`       datetime                                               NULL     DEFAULT NULL     COMMENT 'Дата начала публикации',
    `publish_down`     datetime                                               NULL     DEFAULT NULL     COMMENT 'Дата окончания публикации',
    `images`           text                                                   NOT NULL                  COMMENT 'Изображения поставщика',
    `attribs`          varchar(5120)                                          NOT NULL                  COMMENT 'Дополнительные атрибуты',
    `ordering`         int                                                    NOT NULL DEFAULT 0        COMMENT 'Порядок для сортировки',
    `metatitle`        varchar(255)                                           NOT NULL                  COMMENT 'Метаданные title',
    `metakey`          text                                                                             COMMENT 'Метаданные keywords',
    `metadesc`         text                                                   NOT NULL                  COMMENT 'Метаданные description',
    `access`           int unsigned                                           NOT NULL DEFAULT 0        COMMENT 'Настройки прав доступа',
    `hits`             int unsigned                                           NOT NULL DEFAULT 0        COMMENT 'Количество просмотров',
    `metadata`         text                                                   NOT NULL                  COMMENT 'Метаданные',
    `language`         char(7)                                                NOT NULL                  COMMENT 'Код языка',
    `site_url`         varchar(255)                                           NOT NULL DEFAULT ''       COMMENT 'Ссылка на сайт поставщика',
    `shopmanager_id`   int unsigned                                           NOT NULL DEFAULT 0        COMMENT 'ID поставщика в системе ShopManager',
    PRIMARY KEY (`id`),
    KEY `idx_alias` (`alias`(191)),
    KEY `idx_state` (`state`),
    KEY `idx_createdby` (`created_by`),
    KEY `idx_checkout` (`checked_out`),
    KEY `idx_access` (`access`),
    KEY `idx_language` (`language`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE = utf8mb4_unicode_ci COMMENT ='Хранение информации о поставщиках iShop';

--
-- Структура таблицы `#__ishop_services`
-- здесь хранятся данные о сервисных центрах
--

CREATE TABLE IF NOT EXISTS `#__ishop_services`
(
    `id`               int unsigned                                           NOT NULL AUTO_INCREMENT,
    `title`            varchar(255)                                           NOT NULL DEFAULT ''       COMMENT 'Наименование сервисного центра',
    `alias`            varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT ''       COMMENT 'Псевдоним для URL',
    `introtext`        mediumtext                                             NOT NULL                  COMMENT 'Краткое описание сервисного центра',
    `fulltext`         mediumtext                                             NOT NULL                  COMMENT 'Полное описание сервисного центра',
    `state`            tinyint                                                NOT NULL DEFAULT 0        COMMENT 'Состояние публикации',
    `created`          datetime                                               NOT NULL                  COMMENT 'Дата создания',
    `created_by`       int unsigned                                           NOT NULL DEFAULT 0        COMMENT 'ID в #__users пользователя, который создал сервисный центр',
    `created_by_alias` varchar(255)                                           NOT NULL DEFAULT ''       COMMENT 'Псевдоним автора',
    `modified`         datetime                                               NOT NULL                  COMMENT 'Дата изменения',
    `modified_by`      int unsigned                                           NOT NULL DEFAULT 0        COMMENT 'ID в #__users пользователя, который изменил сервисный центр',
    `checked_out`      int unsigned                                                                     COMMENT 'Блокировка',
    `checked_out_time` datetime                                               NULL     DEFAULT NULL     COMMENT 'Дата блокировки',
    `publish_up`       datetime                                               NULL     DEFAULT NULL     COMMENT 'Дата начала публикации',
    `publish_down`     datetime                                               NULL     DEFAULT NULL     COMMENT 'Дата окончания публикации',
    `images`           text                                                   NOT NULL                  COMMENT 'Изображения сервисного центра',
    `attribs`          varchar(5120)                                          NOT NULL                  COMMENT 'Дополнительные атрибуты',
    `ordering`         int                                                    NOT NULL DEFAULT 0        COMMENT 'Порядок для сортировки',
    `metatitle`        varchar(255)                                           NOT NULL                  COMMENT 'Метаданные title',
    `metakey`          text                                                                             COMMENT 'Метаданные keywords',
    `metadesc`         text                                                   NOT NULL                  COMMENT 'Метаданные description',
    `access`           int unsigned                                           NOT NULL DEFAULT 0        COMMENT 'Настройки прав доступа',
    `hits`             int unsigned                                           NOT NULL DEFAULT 0        COMMENT 'Количество просмотров',
    `metadata`         text                                                   NOT NULL                  COMMENT 'Метаданные',
    `language`         char(7)                                                NOT NULL                  COMMENT 'Код языка',
    `site_url`         varchar(255)                                           NOT NULL DEFAULT ''       COMMENT 'Ссылка на сайт сервисного центра',
    PRIMARY KEY (`id`),
    KEY `idx_alias` (`alias`(191)),
    KEY `idx_state` (`state`),
    KEY `idx_createdby` (`created_by`),
    KEY `idx_checkout` (`checked_out`),
    KEY `idx_access` (`access`),
    KEY `idx_language` (`language`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE = utf8mb4_unicode_ci COMMENT ='Хранение информации о сервисных центрах iShop';

--
-- Структура таблицы `#__ishop_users`
-- здесь хранятся данные для идентификации пользователей,
-- а также списки корзины, списки желаний и сравнения
--

CREATE TABLE IF NOT EXISTS `#__ishop_users`
(
    `id`        int unsigned                                                    NOT NULL AUTO_INCREMENT,
    `user_id`   int unsigned                                                    NOT NULL DEFAULT 0        COMMENT 'ID в #__users пользователя Joomla',
    `zone_id`   int unsigned                                                    NOT NULL DEFAULT 0        COMMENT 'ID в #__ishop_delivery_zones зона доставки',
    `pass`      varchar(255) CHARACTER SET utf8 COLLATE utf8_bin                NOT NULL                  COMMENT 'Уникальный случайный идентификатор пользователя',
    `cart`      text                                                            NOT NULL DEFAULT '{}'     COMMENT 'Товары в корзине пользователя',
    `wishlist`  text                                                            NOT NULL DEFAULT '{}'     COMMENT 'Товары в списке желаний пользователя',
    `compare`   text                                                            NOT NULL DEFAULT '{}'     COMMENT 'Товары в списке сравнения пользователя',
    `viewed`    text                                                            NOT NULL DEFAULT '{}'     COMMENT 'Список просмотренных карточек товаров',
    `modified`  datetime                                                        NOT NULL                  COMMENT 'Дата последнего обновления',
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_pass` (`pass`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE = utf8mb4_unicode_ci COMMENT ='Список пользователей магазина в iShop';

--
-- Структура таблицы `#__ishop_users_id`
-- здесь хранятся идентификаторы пользователей,
-- связанные с профилями в `#__ishop_users`
--

CREATE TABLE IF NOT EXISTS `#__ishop_users_id`
(
    `id`            int unsigned                                                    NOT NULL AUTO_INCREMENT,
    `ishop_user_id` int unsigned                                                    NOT NULL DEFAULT 0        COMMENT 'ID пользователя в #__ishop_users',
    `type`          varchar(20)                                                     NOT NULL DEFAULT 'phone'  COMMENT 'Тип идентификатора: phone, yandex_id, google_id, ...',
    `value`         varchar(255) CHARACTER SET utf8 COLLATE utf8_bin                NOT NULL                  COMMENT 'Значение идентификатора',
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`ishop_user_id`),
    KEY `idx_pass` (`type`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE = utf8mb4_unicode_ci COMMENT ='Идентификаторы пользователей iShop';

--
-- Структура таблицы `#__ishop_reviews`
-- здесь хранятся отзывы пользователей о товарах,
-- а также оценки товаров
--

CREATE TABLE IF NOT EXISTS `#__ishop_reviews`
(
    `id`               int unsigned     NOT NULL AUTO_INCREMENT,
    `parent_id`        int unsigned     NOT NULL DEFAULT 0          COMMENT 'ID в #__ishop_reviews родительского отзыва',
    `product_id`       int unsigned     NOT NULL DEFAULT 0          COMMENT 'ID в #__ishop_products товара, к которому привязан отзыв',
    `user_id`          varchar(255)     NOT NULL DEFAULT ''         COMMENT 'ID в #__users пользователя Joomla',
    `review_good`      text             NOT NULL                    COMMENT 'Преимущества товара',
    `review_bad`       text             NOT NULL                    COMMENT 'Недостатки товара',
    `review`           text             NOT NULL                    COMMENT 'Общий текст отзыва или комментария',
    `rating`           int unsigned     NOT NULL DEFAULT 0          COMMENT 'Оценка, установленная пользователем',
    `state`            tinyint          NOT NULL DEFAULT 0          COMMENT 'Статус публикации отзыва',
    `created`          datetime         NOT NULL                    COMMENT 'Дата создания отзыва',
    `created_by`       int unsigned     NOT NULL DEFAULT 0          COMMENT 'ID в #__users пользователя, который создал отзыв',
    `created_by_alias` varchar(255)     NOT NULL DEFAULT ''         COMMENT 'Псевдоним пользователя',
    `modified`         datetime         NOT NULL                    COMMENT 'Дата изменения отзыва',
    `modified_by`      int unsigned     NOT NULL DEFAULT 0          COMMENT 'ID в #__users пользователя, который изменил отзыв',
    `checked_out`      int unsigned                                 COMMENT 'Блокировка',
    `checked_out_time` datetime         NULL     DEFAULT NULL       COMMENT 'Дата блокировки',
    `hits`             int unsigned     NOT NULL DEFAULT 0          COMMENT 'Количество просмотров',
    PRIMARY KEY (`id`),
    KEY `idx_parent_id` (`parent_id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_rating` (`rating`),
    KEY `idx_state` (`state`),
    KEY `idx_createdby` (`created_by`),
    KEY `idx_checkout` (`checked_out`)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE = utf8mb4_unicode_ci COMMENT ='Отзывы и комментарии к товарам в iShop';

--
-- Структура таблицы `#__ishop_orders`
-- здесь хранятся данные всех оформленных заказов пользователей
--

CREATE TABLE IF NOT EXISTS `#__ishop_orders`
(
    `id`               int unsigned                                             NOT NULL AUTO_INCREMENT,
    `title`            varchar(255)                                             NOT NULL DEFAULT ''         COMMENT 'Название заказа',
    `alias`            varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin   NOT NULL DEFAULT ''         COMMENT 'Псевдоним для URL',
    `state`            tinyint                                                  NOT NULL DEFAULT 0          COMMENT 'Состояние публикации заказа',
    `ishop_user_id`    int unsigned                                             NOT NULL                    COMMENT 'ID пользователя в #__ishop_users',
    `products`         longtext                                                 NOT NULL                    COMMENT 'Список товаров и цен',
    `discounts`        longtext                                                 NOT NULL                    COMMENT 'Список скидок применяемых в заказе',
    `payment`          text                                                     NOT NULL                    COMMENT 'Информация об оплате',
    `delivery`         text                                                     NOT NULL                    COMMENT 'Информация о доставке',
    `created`          datetime                                                 NOT NULL                    COMMENT 'Дата оформления заказ',
    `created_by`       int unsigned                                             NOT NULL DEFAULT 0          COMMENT 'ID в #__users пользователя, который оформил заказ',
    `created_by_alias` varchar(255)                                             NOT NULL DEFAULT ''         COMMENT 'Псевдоним автора',
    `canceled`         datetime                                                 NOT NULL                    COMMENT 'Дата отмены заказа',
    `modified`         datetime                                                 NOT NULL                    COMMENT 'Дата изменения заказа',
    `modified_by`      int unsigned                                             NOT NULL DEFAULT 0          COMMENT 'ID в #__users пользователя, который изменил заказ',
    `checked_out`      int unsigned                                                                         COMMENT 'Блокировка',
    `checked_out_time` datetime                                                 NULL     DEFAULT NULL       COMMENT 'Дата блокировки',
    `access`           int unsigned                                             NOT NULL DEFAULT 0          COMMENT 'Настройки прав доступа',
    PRIMARY KEY (`id`),
    KEY `idx_alias` (`alias`(191)),
    KEY `idx_state` (`state`),
    KEY `idx_user_id` (`ishop_user_id`),
    KEY `idx_createdby` (`created_by`),
    KEY `idx_checkout` (`checked_out`),
    KEY `idx_access` (`access`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE = utf8mb4_unicode_ci COMMENT ='Список оформленных заказов пользователей в iShop';

--
-- Структура таблицы `#__ishop_discounts`
-- здесь хранятся данные обо всех скидках и специальных предложениях
--
CREATE TABLE IF NOT EXISTS `#__ishop_discounts`
(
    `id`               int unsigned                                           NOT NULL AUTO_INCREMENT,
    `title`            varchar(255)                                           NOT NULL DEFAULT ''       COMMENT 'Название скидки или спецпредложения',
    `alias`            varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT ''       COMMENT 'Псевдоним записи',
    `introtext`        mediumtext                                             NOT NULL                  COMMENT 'Краткое описание скидки',
    `fulltext`         mediumtext                                             NOT NULL                  COMMENT 'Полное описание скидки',
    `state`            tinyint                                                NOT NULL DEFAULT 0        COMMENT 'Состояние публикации',
    `created`          datetime                                               NOT NULL                  COMMENT 'Дата создания',
    `created_by`       int unsigned                                           NOT NULL DEFAULT 0        COMMENT 'ID в #__users пользователя, который создал скидку',
    `created_by_alias` varchar(255)                                           NOT NULL DEFAULT ''       COMMENT 'Псевдоним автора',
    `modified`         datetime                                               NOT NULL                  COMMENT 'Дата изменения',
    `modified_by`      int unsigned                                           NOT NULL DEFAULT 0        COMMENT 'ID в #__users пользователя, который изменил скидку',
    `checked_out`      int unsigned                                                                     COMMENT 'Блокировка',
    `checked_out_time` datetime                                               NULL     DEFAULT NULL     COMMENT 'Дата блокировки',
    `publish_up`       datetime                                               NULL     DEFAULT NULL     COMMENT 'Дата начала публикации',
    `publish_down`     datetime                                               NULL     DEFAULT NULL     COMMENT 'Дата окончания публикации',
    `images`           text                                                   NOT NULL                  COMMENT 'Изображения',
    `ordering`         int                                                    NOT NULL DEFAULT 0        COMMENT 'Порядок для сортировки',
    `type`             tinyint                                                NOT NULL DEFAULT 0        COMMENT 'Тип скидки, 2 - промокод, 1 - реальная, 0 - псевдо',
    `percent`          decimal(4, 2)                                          NOT NULL DEFAULT 0        COMMENT 'Размер скидки в процентах',
    `products`         varchar(400)                                           NOT NULL DEFAULT ''       COMMENT 'Список ID товаров, через запятую',
    `cats`             varchar(400)                                           NOT NULL DEFAULT ''       COMMENT 'Список ID категорий, через запятую',
    `manufacturers`    varchar(400)                                           NOT NULL DEFAULT ''       COMMENT 'Список ID производителей, через запятую',
    `suppliers`        varchar(400)                                           NOT NULL DEFAULT ''       COMMENT 'Список ID поставщиков, через запятую',
    `min_price`        decimal(10, 2) unsigned                                NOT NULL DEFAULT 0        COMMENT 'Минимальная цена товара',
    `min_amount`       decimal(10, 2) unsigned                                NOT NULL DEFAULT 0        COMMENT 'Минимальная сумма заказа',
    `code`             varchar(255)                                           NOT NULL DEFAULT ''       COMMENT 'Текстовый промокод',
    PRIMARY KEY (`id`),
    KEY `idx_alias` (`alias`(191)),
    KEY `idx_state` (`state`),
    KEY `idx_createdby` (`created_by`),
    KEY `idx_checkout` (`checked_out`),
    KEY `idx_type` (`type`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE = utf8mb4_unicode_ci COMMENT ='Хранение списка скидок iShop';

--
-- Структура таблицы `#__ishop_prefixes`
-- здесь хранится список префиксов и их склонений
--

CREATE TABLE IF NOT EXISTS `#__ishop_prefixes`
(
    `id`               int unsigned                                             NOT NULL AUTO_INCREMENT,
    `title`            varchar(255)                                             NOT NULL                    COMMENT 'Текст префикса',
    `alias`            varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin   NOT NULL                    COMMENT 'Псевдоним записи',
    `state`            tinyint                                                  NOT NULL DEFAULT 0          COMMENT 'Состояние публикации',
    `nominative_sin`   varchar(225)                                             NOT NULL                    COMMENT 'Именительный падеж ед. число',
    `nominative_plu`   varchar(225)                                             NOT NULL                    COMMENT 'Множественное число мн. число',
    `genitive_sin`     varchar(225)                                             NOT NULL                    COMMENT 'Родительный падеж ед. число',
    `genitive_plu`     varchar(225)                                             NOT NULL                    COMMENT 'Родительный падеж мн. число',
    `accusative_sin`   varchar(225)                                             NOT NULL                    COMMENT 'Винительный падеж ед. число',
    `accusative_plu`   varchar(225)                                             NOT NULL                    COMMENT 'Винительный падеж мн. число',
    `images`           text                                                     NOT NULL                    COMMENT 'Изображения',
    `icon`             varchar(25)                                              NOT NULL DEFAULT ''         COMMENT 'Имя иконки',
    `emoji`            varchar(25)                                              NOT NULL DEFAULT ''         COMMENT 'Эмодзи',
    `created`          datetime                                                 NOT NULL                    COMMENT 'Дата создания',
    `created_by`       int unsigned                                             NOT NULL DEFAULT 0          COMMENT 'ID в #__users пользователя, который создал скидку',
    `created_by_alias` varchar(255)                                             NOT NULL DEFAULT ''         COMMENT 'Псевдоним автора',
    `modified`         datetime                                                 NOT NULL                    COMMENT 'Дата изменения',
    `modified_by`      int unsigned                                             NOT NULL DEFAULT 0          COMMENT 'ID в #__users пользователя, который изменил скидку',
    `checked_out`      int unsigned                                                                         COMMENT 'Блокировка',
    `checked_out_time` datetime                                                 NULL     DEFAULT NULL       COMMENT 'Дата блокировки',
    `ordering`         int                                                      NOT NULL DEFAULT 0          COMMENT 'Сортировка значений',
    `language`         char(7)                                                  NOT NULL                    COMMENT 'Код языка',
    PRIMARY KEY (`id`),
    KEY `idx_alias` (`alias`(191)),
    KEY `idx_state` (`state`),
    KEY `idx_createdby` (`created_by`),
    KEY `idx_checkout` (`checked_out`),
    KEY `idx_language` (`language`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE = utf8mb4_unicode_ci COMMENT ='Хранение списка префиксов и их склонений для товаров iShop';

--
-- Структура таблицы `#__ishop_warehouses`
-- здесь хранится список складов, магазинов, пунктов выдачи заказов
--

CREATE TABLE IF NOT EXISTS `#__ishop_warehouses`
(
    `id`               int unsigned                                             NOT NULL AUTO_INCREMENT,
    `title`            varchar(255)                                             NOT NULL                    COMMENT 'Наименование склада',
    `alias`            varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin   NOT NULL                    COMMENT 'Псевдоним склада',
    `state`            tinyint                                                  NOT NULL DEFAULT 0          COMMENT 'Состояние публикации',
    `point`            tinyint                                                  NOT NULL DEFAULT 0          COMMENT 'Пункт самовывоза',
    `introtext`        mediumtext                                               NOT NULL                    COMMENT 'Краткое описание',
    `fulltext`         mediumtext                                               NOT NULL                    COMMENT 'Полное описание',
    `address`          varchar(255)                                             NOT NULL DEFAULT ''         COMMENT 'Адрес',
    `latitude`         varchar(25)                                              NOT NULL DEFAULT ''         COMMENT 'Широта',
    `longitude`        varchar(25)                                              NOT NULL DEFAULT ''         COMMENT 'Долгота',
    `images`           text                                                     NOT NULL                    COMMENT 'Изображения',
    `icon`             varchar(25)                                              NOT NULL DEFAULT ''         COMMENT 'Имя иконки',
    `emoji`            varchar(25)                                              NOT NULL DEFAULT ''         COMMENT 'Эмодзи',
    `created`          datetime                                                 NOT NULL                    COMMENT 'Дата создания',
    `created_by`       int unsigned                                             NOT NULL DEFAULT 0          COMMENT 'ID в #__users пользователя, который создал',
    `created_by_alias` varchar(255)                                             NOT NULL DEFAULT ''         COMMENT 'Псевдоним автора',
    `modified`         datetime                                                 NOT NULL                    COMMENT 'Дата изменения',
    `modified_by`      int unsigned                                             NOT NULL DEFAULT 0          COMMENT 'ID в #__users пользователя, который изменил',
    `checked_out`      int unsigned                                                                         COMMENT 'Блокировка',
    `checked_out_time` datetime                                                 NULL     DEFAULT NULL       COMMENT 'Дата блокировки',
    `ordering`         int                                                      NOT NULL DEFAULT 0          COMMENT 'Сортировка значений',
    `language`         char(7)                                                  NOT NULL                    COMMENT 'Код языка',
    `bitrix24_id`      int unsigned                                             NOT NULL DEFAULT 0          COMMENT 'ID склада в системе Битрикс24',
    `system1c_guid`    varchar(40)                                              NOT NULL DEFAULT ''         COMMENT 'ID склада в системе учета 1С',
    PRIMARY KEY (`id`),
    KEY `idx_alias` (`alias`(191)),
    KEY `idx_state` (`state`),
    KEY `idx_createdby` (`created_by`),
    KEY `idx_checkout` (`checked_out`),
    KEY `idx_language` (`language`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE = utf8mb4_unicode_ci COMMENT ='Хранение списка складов, магазинов, пунктов выдачи заказов iShop';

--
-- Структура таблицы `#__ishop_payments`
-- здесь хранится список доступных способов оплаты
--

CREATE TABLE IF NOT EXISTS `#__ishop_payments`
(
    `id`               int unsigned                                             NOT NULL AUTO_INCREMENT,
    `title`            varchar(255)                                             NOT NULL                    COMMENT 'Наименование',
    `alias`            varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin   NOT NULL                    COMMENT 'Псевдоним',
    `state`            tinyint                                                  NOT NULL DEFAULT 0          COMMENT 'Состояние публикации',
    `introtext`        mediumtext                                               NOT NULL                    COMMENT 'Краткое описание',
    `fulltext`         mediumtext                                               NOT NULL                    COMMENT 'Полное описание',
    `images`           text                                                     NOT NULL                    COMMENT 'Изображения',
    `icon`             varchar(25)                                              NOT NULL DEFAULT ''         COMMENT 'Имя иконки',
    `emoji`            varchar(25)                                              NOT NULL DEFAULT ''         COMMENT 'Эмодзи',
    `attribs`          varchar(5120)                                            NOT NULL                    COMMENT 'Параметры',
    `created`          datetime                                                 NOT NULL                    COMMENT 'Дата создания',
    `created_by`       int unsigned                                             NOT NULL DEFAULT 0          COMMENT 'ID в #__users пользователя, который создал',
    `created_by_alias` varchar(255)                                             NOT NULL DEFAULT ''         COMMENT 'Псевдоним автора',
    `modified`         datetime                                                 NOT NULL                    COMMENT 'Дата изменения',
    `modified_by`      int unsigned                                             NOT NULL DEFAULT 0          COMMENT 'ID в #__users пользователя, который изменил',
    `checked_out`      int unsigned                                                                         COMMENT 'Блокировка',
    `checked_out_time` datetime                                                 NULL     DEFAULT NULL       COMMENT 'Дата блокировки',
    `ordering`         int                                                      NOT NULL DEFAULT 0          COMMENT 'Сортировка значений',
    `language`         char(7)                                                  NOT NULL                    COMMENT 'Код языка',
    PRIMARY KEY (`id`),
    KEY `idx_alias` (`alias`(191)),
    KEY `idx_state` (`state`),
    KEY `idx_createdby` (`created_by`),
    KEY `idx_checkout` (`checked_out`),
    KEY `idx_language` (`language`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE = utf8mb4_unicode_ci COMMENT ='Хранение списка способов оплаты iShop';

--
-- Структура таблицы `#__ishop_deliveries`
-- здесь хранится список доступных способов доставки
--

CREATE TABLE IF NOT EXISTS `#__ishop_deliveries`
(
    `id`               int unsigned                                             NOT NULL AUTO_INCREMENT,
    `title`            varchar(255)                                             NOT NULL                    COMMENT 'Наименование',
    `alias`            varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin   NOT NULL                    COMMENT 'Псевдоним',
    `state`            tinyint                                                  NOT NULL DEFAULT 0          COMMENT 'Состояние публикации',
    `point`            tinyint                                                  NOT NULL DEFAULT 0          COMMENT 'Тип доставки, 0 - до клиента, 1 - ПВЗ',
    `introtext`        mediumtext                                               NOT NULL                    COMMENT 'Краткое описание',
    `fulltext`         mediumtext                                               NOT NULL                    COMMENT 'Полное описание',
    `images`           text                                                     NOT NULL                    COMMENT 'Изображения',
    `icon`             varchar(25)                                              NOT NULL DEFAULT ''         COMMENT 'Имя иконки',
    `emoji`            varchar(25)                                              NOT NULL DEFAULT ''         COMMENT 'Эмодзи',
    `attribs`          varchar(5120)                                            NOT NULL                    COMMENT 'Параметры',
    `created`          datetime                                                 NOT NULL                    COMMENT 'Дата создания',
    `created_by`       int unsigned                                             NOT NULL DEFAULT 0          COMMENT 'ID в #__users пользователя, который создал',
    `created_by_alias` varchar(255)                                             NOT NULL DEFAULT ''         COMMENT 'Псевдоним автора',
    `modified`         datetime                                                 NOT NULL                    COMMENT 'Дата изменения',
    `modified_by`      int unsigned                                             NOT NULL DEFAULT 0          COMMENT 'ID в #__users пользователя, который изменил',
    `checked_out`      int unsigned                                                                         COMMENT 'Блокировка',
    `checked_out_time` datetime                                                 NULL     DEFAULT NULL       COMMENT 'Дата блокировки',
    `ordering`         int                                                      NOT NULL DEFAULT 0          COMMENT 'Сортировка значений',
    `language`         char(7)                                                  NOT NULL                    COMMENT 'Код языка',
    PRIMARY KEY (`id`),
    KEY `idx_alias` (`alias`(191)),
    KEY `idx_state` (`state`),
    KEY `idx_createdby` (`created_by`),
    KEY `idx_checkout` (`checked_out`),
    KEY `idx_language` (`language`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE = utf8mb4_unicode_ci COMMENT ='Хранение списка способов доставки iShop';

--
-- Структура таблицы `#__ishop_delivery_zones`
-- здесь хранится список зон доставки
--

CREATE TABLE IF NOT EXISTS `#__ishop_delivery_zones`
(
    `id`               int unsigned                                             NOT NULL AUTO_INCREMENT,
    `title`            varchar(255)                                             NOT NULL                    COMMENT 'Наименование',
    `alias`            varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin   NOT NULL                    COMMENT 'Псевдоним',
    `state`            tinyint                                                  NOT NULL DEFAULT 0          COMMENT 'Состояние публикации',
    `introtext`        mediumtext                                               NOT NULL                    COMMENT 'Краткое описание',
    `fulltext`         mediumtext                                               NOT NULL                    COMMENT 'Полное описание',
    `images`           text                                                     NOT NULL                    COMMENT 'Изображения',
    `bitrix24_id`      int unsigned                                             NOT NULL DEFAULT 0          COMMENT 'ID зоны доставки в Битрикс24',
    `attribs`          varchar(5120)                                            NOT NULL                    COMMENT 'Параметры',
    `current`          varchar(5120)                                            NOT NULL                    COMMENT 'Информация о текущих заказах',
    `created`          datetime                                                 NOT NULL                    COMMENT 'Дата создания',
    `created_by`       int unsigned                                             NOT NULL DEFAULT 0          COMMENT 'ID в #__users пользователя, который создал',
    `created_by_alias` varchar(255)                                             NOT NULL DEFAULT ''         COMMENT 'Псевдоним автора',
    `modified`         datetime                                                 NOT NULL                    COMMENT 'Дата изменения',
    `modified_by`      int unsigned                                             NOT NULL DEFAULT 0          COMMENT 'ID в #__users пользователя, который изменил',
    `checked_out`      int unsigned                                                                         COMMENT 'Блокировка',
    `checked_out_time` datetime                                                 NULL     DEFAULT NULL       COMMENT 'Дата блокировки',
    `ordering`         int                                                      NOT NULL DEFAULT 0          COMMENT 'Сортировка значений',
    PRIMARY KEY (`id`),
    KEY `idx_alias` (`alias`(191)),
    KEY `idx_state` (`state`),
    KEY `idx_createdby` (`created_by`),
    KEY `idx_checkout` (`checked_out`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE = utf8mb4_unicode_ci COMMENT ='Хранение списка зон доставки iShop';

--
-- Структура таблицы `#__ishop_warehouses_stock`
-- здесь хранятся данные об остатках товаров на складах
--
CREATE TABLE IF NOT EXISTS `#__ishop_warehouses_stock`
(
    `id`               int unsigned                                           NOT NULL AUTO_INCREMENT,
    `product_id`       int unsigned                                           NOT NULL                  COMMENT 'ID товара в #__ishop_products',
    `warehouse_id`     int unsigned                                           NOT NULL                  COMMENT 'ID склада в #__ishop_warehouses',
    `stock`            int unsigned                                           NOT NULL                  COMMENT 'Количество товара на складе',
    PRIMARY KEY (`id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_warehouse_id` (`warehouse_id`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE = utf8mb4_unicode_ci COMMENT ='Хранение данные об остатках товаров на складах iShop';

--
-- Структура таблицы `#__ishop_suppliers_stock`
-- здесь хранятся данные об остатках товаров у поставщиков
--
CREATE TABLE IF NOT EXISTS `#__ishop_suppliers_stock`
(
    `id`               int unsigned                                           NOT NULL AUTO_INCREMENT,
    `product_id`       int unsigned                                           NOT NULL                  COMMENT 'ID товара в #__ishop_products',
    `supplier_id`      int unsigned                                           NOT NULL                  COMMENT 'ID поставщика в #__ishop_suppliers',
    `stock`            int unsigned                                           NOT NULL                  COMMENT 'Количество товара у поставщика',
    `price`            decimal(10, 2) unsigned                                NOT NULL DEFAULT 0        COMMENT 'Цена закупки',
    PRIMARY KEY (`id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_supplier_id` (`supplier_id`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE = utf8mb4_unicode_ci COMMENT ='Хранение данные об остатках товаров у поставщиков iShop';

--
-- Структура таблицы `#__ishop_payment_parts`
-- здесь хранится список кредитных продуктов для оплаты частями
--

CREATE TABLE IF NOT EXISTS `#__ishop_payment_parts`
(
    `id`               int unsigned                                             NOT NULL AUTO_INCREMENT,
    `title`            varchar(255)                                             NOT NULL                    COMMENT 'Наименование',
    `alias`            varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin   NOT NULL                    COMMENT 'Псевдоним',
    `state`            tinyint                                                  NOT NULL DEFAULT 0          COMMENT 'Состояние публикации',
    `type`             tinyint                                                  NOT NULL DEFAULT 1          COMMENT 'Тип продукта, 1 - рассрочка , 2 - кредит',
    `introtext`        mediumtext                                               NOT NULL                    COMMENT 'Краткое описание',
    `fulltext`         mediumtext                                               NOT NULL                    COMMENT 'Полное описание',
    `images`           text                                                     NOT NULL                    COMMENT 'Изображения',
    `icon`             varchar(255)                                             NOT NULL                    COMMENT 'Иконка',
    `attribs`          varchar(5120)                                            NOT NULL                    COMMENT 'Параметры продукта',
    `created`          datetime                                                 NOT NULL                    COMMENT 'Дата создания',
    `created_by`       int unsigned                                             NOT NULL DEFAULT 0          COMMENT 'ID в #__users пользователя, который создал',
    `created_by_alias` varchar(255)                                             NOT NULL DEFAULT ''         COMMENT 'Псевдоним автора',
    `modified`         datetime                                                 NOT NULL                    COMMENT 'Дата изменения',
    `modified_by`      int unsigned                                             NOT NULL DEFAULT 0          COMMENT 'ID в #__users пользователя, который изменил',
    `checked_out`      int unsigned                                                                         COMMENT 'Блокировка',
    `checked_out_time` datetime                                                 NULL     DEFAULT NULL       COMMENT 'Дата блокировки',
    `ordering`         int                                                      NOT NULL DEFAULT 0          COMMENT 'Сортировка значений',
    PRIMARY KEY (`id`),
    KEY `idx_alias` (`alias`(191)),
    KEY `idx_state` (`state`),
    KEY `idx_createdby` (`created_by`),
    KEY `idx_checkout` (`checked_out`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE = utf8mb4_unicode_ci COMMENT ='Хранение списка кредитных продуктов для оплаты частями iShop';