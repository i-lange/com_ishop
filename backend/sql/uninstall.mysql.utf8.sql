/*
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

--
-- Удаляем таблицы компонента com_ishop
--
DELETE FROM `#__content_types` WHERE `type_alias` IN ('com_ishop.product', 'com_ishop.category');
DROP TABLE IF EXISTS `#__ishop_products`;
DROP TABLE IF EXISTS `#__ishop_categories`;
DROP TABLE IF EXISTS `#__ishop_fields`;
DROP TABLE IF EXISTS `#__ishop_fields_map`;
DROP TABLE IF EXISTS `#__ishop_groups`;
DROP TABLE IF EXISTS `#__ishop_values`;
DROP TABLE IF EXISTS `#__ishop_manufacturers`;
DROP TABLE IF EXISTS `#__ishop_suppliers`;
DROP TABLE IF EXISTS `#__ishop_users`;
DROP TABLE IF EXISTS `#__ishop_users_id`;
DROP TABLE IF EXISTS `#__ishop_reviews`;
DROP TABLE IF EXISTS `#__ishop_orders`;
DROP TABLE IF EXISTS `#__ishop_discounts`;
DROP TABLE IF EXISTS `#__ishop_prefixes`;