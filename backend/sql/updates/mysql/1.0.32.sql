/*
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

ALTER TABLE `#__ishop_filters`
    ADD COLUMN `warehouses` text NOT NULL COMMENT 'JSON список складов' AFTER `manufacturers`,
    ADD COLUMN `min_price` int unsigned NOT NULL DEFAULT 0 COMMENT 'Минимальная цена' AFTER `ishop_fields`,
    ADD COLUMN `max_price` int unsigned NOT NULL DEFAULT 0 COMMENT 'Максимальная цена' AFTER `min_price`,
    ADD COLUMN `good_price` tinyint unsigned NOT NULL DEFAULT 0 COMMENT 'Только товары со скидкой' AFTER `max_price`;

UPDATE `#__ishop_filters`
SET `warehouses` = '[]'
WHERE `warehouses` = '';
