/*
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

CREATE TABLE IF NOT EXISTS `#__ishop_filters` (
    `id`               int unsigned                                           NOT NULL AUTO_INCREMENT,
    `category_id`      int unsigned                                           NOT NULL DEFAULT 0          COMMENT 'ID категории товаров',
    `manufacturers`    text                                                   NOT NULL                    COMMENT 'JSON список производителей',
    `ishop_fields`     mediumtext                                             NOT NULL                    COMMENT 'JSON набор характеристик и значений',
    `min_width`        int unsigned                                           NOT NULL DEFAULT 0          COMMENT 'Минимальная ширина',
    `max_width`        int unsigned                                           NOT NULL DEFAULT 0          COMMENT 'Максимальная ширина',
    `min_height`       int unsigned                                           NOT NULL DEFAULT 0          COMMENT 'Минимальная высота',
    `max_height`       int unsigned                                           NOT NULL DEFAULT 0          COMMENT 'Максимальная высота',
    `min_depth`        int unsigned                                           NOT NULL DEFAULT 0          COMMENT 'Минимальная глубина',
    `max_depth`        int unsigned                                           NOT NULL DEFAULT 0          COMMENT 'Максимальная глубина',
    `min_weight`       int unsigned                                           NOT NULL DEFAULT 0          COMMENT 'Минимальный вес',
    `max_weight`       int unsigned                                           NOT NULL DEFAULT 0          COMMENT 'Максимальный вес',
    `filter_key`       char(64)                                               NOT NULL                    COMMENT 'Канонический ключ комбинации фильтра',
    `description`      mediumtext                                             NOT NULL                    COMMENT 'HTML описание страницы',
    `metatitle`        varchar(255)                                           NOT NULL DEFAULT ''         COMMENT 'Meta title страницы',
    `metadesc`         text                                                   NOT NULL                    COMMENT 'Meta description страницы',
    `metakey`          text                                                   NOT NULL                    COMMENT 'Meta keywords страницы',
    `state`            tinyint                                                NOT NULL DEFAULT 0          COMMENT 'Состояние публикации',
    `created`          datetime                                               NOT NULL                    COMMENT 'Дата создания',
    `created_by`       int unsigned                                           NOT NULL DEFAULT 0          COMMENT 'ID пользователя, который создал',
    `created_by_alias` varchar(255)                                           NOT NULL DEFAULT ''         COMMENT 'Псевдоним автора',
    `modified`         datetime                                               NOT NULL                    COMMENT 'Дата изменения',
    `modified_by`      int unsigned                                           NOT NULL DEFAULT 0          COMMENT 'ID пользователя, который изменил',
    `checked_out`      int unsigned                                                                       COMMENT 'Блокировка',
    `checked_out_time` datetime                                               NULL     DEFAULT NULL       COMMENT 'Дата блокировки',
    `ordering`         int                                                    NOT NULL DEFAULT 0          COMMENT 'Порядок применения',
    `language`         char(7)                                                NOT NULL DEFAULT '*'        COMMENT 'Код языка',
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_filter_unique` (`category_id`, `filter_key`, `language`),
    KEY `idx_category` (`category_id`),
    KEY `idx_state` (`state`),
    KEY `idx_createdby` (`created_by`),
    KEY `idx_checkout` (`checked_out`),
    KEY `idx_ordering` (`ordering`),
    KEY `idx_language` (`language`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE = utf8mb4_unicode_ci COMMENT ='SEO-страницы результатов фильтрации товаров iShop';
