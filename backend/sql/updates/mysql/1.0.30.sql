/*
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

ALTER TABLE `#__ishop_filters`
    ADD COLUMN `link_anchor` varchar(255) NOT NULL DEFAULT '' COMMENT 'Короткий анкор ссылки' AFTER `heading`;
