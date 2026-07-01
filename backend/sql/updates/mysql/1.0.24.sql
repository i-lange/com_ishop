/*
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

ALTER TABLE `#__ishop_fields`
    ADD COLUMN `compare` tinyint NOT NULL DEFAULT 0 COMMENT 'Метод сравнения, -1 - меньше лучше, 0 - не влияет, 1 - больше лучше' AFTER `type`;

ALTER TABLE `#__ishop_values`
    ADD COLUMN `weight` int NOT NULL DEFAULT 0 COMMENT 'Вес значения для сравнения' AFTER `icon`;
