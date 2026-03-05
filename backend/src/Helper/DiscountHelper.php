<?php
/**
 * @package        com_ishop
 * @author         Pavel Lange <pavel@ilange.ru>
 * @link           https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license        GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Administrator\Helper;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Database\ParameterType;

defined('_JEXEC') or die;

/**
 * Класс helper
 * @since 1.0.0
 */
class DiscountHelper
{
    /**
     * Пересчитывает цены со скидкой и старые (зачеркнутые)
     * цены с учетом параметров всех скидок
     *
     * @return  bool
     * @throws \Exception
     * @since 1.0.0
     */
    public static function calculate()
    {
        // Получим список всех активных скидок
        // с параметрами расчета, в порядке
        // убывания значения скидок
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $published = 1;
        $now = Factory::getDate()->toSql();
        $query
            ->select([
                $db->qn('id'),
                $db->qn('type'),
                $db->qn('percent'),
                $db->qn('products'),
                $db->qn('cats'),
                $db->qn('manufacturers'),
                $db->qn('suppliers'),
            ])
            ->from($db->quoteName('#__ishop_discounts'))
            ->where($db->quoteName('state') . ' = :published')
            ->extendWhere(
                'AND',
                [
                    $db->quoteName('publish_down') . ' IS NULL',
                    $db->quoteName('publish_down') . ' >= :nowDate',
                ],
                'OR'
            )
            ->bind([':nowDate'], $now)
            ->bind(':published', $published, ParameterType::INTEGER)
            ->order('percent DESC');
        $db->setQuery($query);

        $discounts = $db->loadObjectList();
        if (empty($discounts)) {
            return false;
        }

        foreach ($discounts as $discount) {
            // Мы ожидаем получить значение скидки в процентах,
            $discount->percent = round($discount->percent / 100, 4);

            // Преобразуем данные в массивы
            if (empty($discount->products)) {
                $discount->products = [];
            } else {
                $discount->products = explode(',', $discount->products);
            }
            if (empty($discount->cats)) {
                $discount->cats = [];
            } else {
                $discount->cats = explode(',', $discount->cats);
            }
            if (empty($discount->manufacturers)) {
                $discount->manufacturers = [];
            } else {
                $discount->manufacturers = explode(',', $discount->manufacturers);
            }
            if (empty($discount->suppliers)) {
                $discount->suppliers = [];
            } else {
                $discount->suppliers = explode(',', $discount->suppliers);
            }
        }

        // Получим список товаров, которые есть в наличии
        $none = 0;
        $unlimited = -1;
        $query
            ->clear()
            ->select([
                $db->qn('id'),
                $db->qn('price'),
                $db->qn('catid'),
                $db->qn('manufacturer_id'),
                $db->qn('supplier_id'),
            ])
            ->from($db->quoteName('#__ishop_products'))
            ->where('(' . $db->qn('stock') . ' > :none OR' . $db->qn('stock') . ' = :unlimited)')
            ->where($db->quoteName('state') . ' = :published')
            ->bind(':none', $none, ParameterType::INTEGER)
            ->bind(':unlimited', $unlimited, ParameterType::INTEGER)
            ->bind(':published', $published, ParameterType::INTEGER);

        $db->setQuery($query);
        unset($none);
        unset($unlimited);
        unset($published);

        if (!$products = $db->loadObjectList()) {
            return false;
        }

        // Проходим по каждому товару
        foreach ($products as $product) {
            $final_old_price = 0;
            $final_sale_price = 0;
            $findOldPrice = $findSalePrice = false;

            // При расчете цены исходим из того,
            // что скидки одного типа не суммируются.
            // Всегда выбираем скидку с наибольшим значением,
            // если прочие параметры подходят для текущего товара.

            // Для каждого товара нужно пройти
            // по каждой скидке, проверяя ее тип
            // и применимость к товару
            foreach ($discounts as $discount) {
                switch ($discount->type) {
                    case 2:
                        // Промокоды не участвуют в расчетах цены,
                        // они применяются в корзине
                        break;
                    case 1:
                        if ($findSalePrice) {
                            break;
                        }
                        // Для реальных скидок нужно попытаться рассчитать,
                        // какая будет цена со скидкой
                        $sale_price = self::getPrice($product, $discount);
                        if ($sale_price > 0) {
                            $final_sale_price = $sale_price;
                            $findSalePrice = true;
                        }
                        break;
                    case 0:
                        // Для псевдо скидок нужно попытаться рассчитать,
                        // какая будет старая перечеркнутая цена
                        $old_price = self::getPrice($product, $discount, false);
                        if ($old_price > $final_old_price) {
                            $final_old_price = $old_price;
                            $findOldPrice = true;
                        }
                        break;
                }

                // Если нашли обе цены, продолжать обход не имеет смысла
                if ($findSalePrice && $findOldPrice) {
                    break;
                }
            }
            unset($sale_price);
            unset($findSalePrice);
            unset($old_price);
            unset($findOldPrice);

            // Сохранение необходимо в любом случае,
            // даже если цены остались нулевые
            $query
                ->clear()
                ->update($db->quoteName('#__ishop_products'))
                ->where($db->quoteName('id') . ' = :product_id')
                ->bind(':product_id', $product->id, ParameterType::INTEGER)
                ->set($db->quoteName('sale_price') . ' = :sale_price')
                ->bind(':sale_price', $final_sale_price, ParameterType::INTEGER)
                ->set($db->quoteName('old_price') . ' = :old_price')
                ->bind(':old_price', $final_old_price, ParameterType::INTEGER);
            $db->setQuery($query);
            $db->execute();
        }

        return true;
    }

    /**
     * Рассчитывает цену с учетом параметров скидок,
     * если это возможно, и возвращает результат
     *
     * @return float
     * @throws \Exception
     * @since 1.0.0
     */
    private static function getPrice($product, $discount, bool $type = true)
    {
        // Флаг применимости скидки к товару
        $usable = false;

        // Приоритет имеет совпадение
        // по идентификаторам товаров,
        // если их список не пуст
        if (!empty($discount->products) && in_array($product->id, $discount->products)) {
            $usable = true;
        }

        // Список товаров пуст,
        // необходимо одновременное соответствие
        // всем условиям отбора
        // по категории, бренду и поставщику
        if (empty($discount->products)) {
            // Проверка соответствия категории
            $categoryMatch = empty($discount->cats) || in_array($product->catid, $discount->cats);

            // Проверка соответствия производителя
            $manufacturerMatch = empty($discount->manufacturers) || in_array($product->manufacturer_id, $discount->manufacturers);

            // Проверка соответствия поставщика
            $supplierMatch = empty($discount->suppliers) || in_array($product->supplier_id, $discount->suppliers);

            // Товар соответствует, если выполнены все три условия
            $usable = $categoryMatch && $manufacturerMatch && $supplierMatch;
        }

        // Если скидка применима к товару,
        // рассчитываем значение в зависимости
        // от типа скидки
        if (!$usable) {
            return 0;
        }

        $params = ComponentHelper::getParams('com_ishop');
        $round = $params->get('roundPrice', 0);
        $calc = $product->price * $discount->percent;

        if ($type) {
            return round($product->price - $calc, $round);
        }

        return round($product->price + $calc, $round);
    }
}
