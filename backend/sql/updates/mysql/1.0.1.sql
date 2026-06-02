/*
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

ALTER TABLE `#__ishop_warehouses_stock`
    ADD KEY `idx_product_stock_warehouse` (`product_id`, `stock`, `warehouse_id`);

ALTER TABLE `#__ishop_suppliers_stock`
    ADD KEY `idx_product_stock_supplier` (`product_id`, `stock`, `supplier_id`);
