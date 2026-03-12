<?php
/**
 * @package        com_ishop
 * @author         Pavel Lange <pavel@ilange.ru>
 * @link           https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license        GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Site\Helper;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Database\DatabaseInterface;

defined('_JEXEC') or die;

/**
 * Helper компонента com_ishop для манипуляций с ценами
 * @since 1.0.0
 */
class PriceHelper
{
    /**
     * Подготовка числа к выводу в HTML
     *
     * @param   string  $price
     * @param   bool    $whitCurrency
     * @param   string  $tag
     * @param   string  $class
     *
     * @return  string  html разметка цены
     * @since 1.0.0
     */
    public static function renderPrice(string $price, bool $whitCurrency = true, string $tag = 'span', string $class = 'currency')
    {
        $params = ComponentHelper::getParams('com_ishop');
        $round = $params->get('roundPrice', 0);

        $html = round($price, $round);
        $html = number_format($html, $round, ',', ' ');

        if ($whitCurrency) {
            $currency = mb_strtoupper($params->get('defaultCurrency', 'BYN'));
            $html .= '<' . $tag . ' class="' . $class . '">' . $currency . '</' . $tag . '>';
        }

        return $html;
    }

    /**
     * Получаем цены по списку товаров
     *
     * @param   array  $products
     *
     * @return  array  список цен, ключ массива = id товара
     * @since 1.0.0
     */
    public static function getPrices(array $products)
    {
        if (empty($products)) {
            return [];
        }

        $db	= Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select([
                $db->qn('id'),
                $db->qn('price'),
                $db->qn('sale_price'),
            ])
            ->from($db->quoteName('#__ishop_products'))
            ->whereIn($db->qn('id'), $products);
        $db->setQuery($query);
        $prices = $db->loadAssocList();

        $result = [];
        // Нужно пройти по всем товарам
        // и установить цену или цену со скидкой
        foreach ($prices as $item) {
            if ($item['sale_price'] > 0) {
                $result[$item['id']] = $item['sale_price'];
                continue;
            }
            $result[$item['id']] = $item['price'];
        }

        return $result;
    }

    /**
     * Получаем сумму по списку товаров
     *
     * @param   array  $prices список цен
     * @param   array  $quantity список количества
     *
     * @return  float  сумма цен всех товаров с учетом количества
     * @since 1.0.0
     */
    public static function getTotalSum(array $prices, array $quantity)
    {
        if (empty($prices) || empty($quantity)) {
            return 0;
        }

        $sum = 0;

        foreach ($prices as $id => $price) {
            $sum += ($price * $quantity[$id]);
        }

        return $sum;
    }

    /**
     * Получаем цену товара, по которой рассчитывать оплату частями
     *
     * @param   object $product данные товара
     * @param   int $mode метод расчета
     *
     * @return  float  искомое значение цены
     * @since 1.0.0
     */
    public static function getPartPrice(object $product, int $mode)
    {
        // $mode = 1 - цена со скидкой, если есть, иначе основная цена
        // $mode = 2 - основная цена, скидки не применимы для данной оплаты частями
        // $mode = 3 - старая цена, если есть, иначе основная цена, скидки не применимы для данной оплаты частями
        switch ($mode) {
            case 1:
                return ($product->sale_price > 0) ?: $product->price;

            case 2:
                return $product->price;

            case 3:
                return ($product->old_price) ?: $product->price;
        }

        return $product->price;
    }

    /**
     * Возвращает величину скидки указанного товара в процентах
     *
     * @param   object $product объект товара
     * @param   int $precision количество знаков после запятой
     *
     * @return  float  искомое значение в процентах
     * @since 1.0.0
     */
    public static function getDiscountSize(object $product, int $precision = 0)
    {
        // Параметры компонента
        $params = ComponentHelper::getParams('com_ishop');

        // Если применение скидок отключено - размер скидки всегда 0
        if (!$params->get('discounts_use', 0)) {
            return 0;
        }

        // Проверим, используются ли предустановленные скидки
        if ($params->get('discounts_use_manual', 0)) {
            return self::calculateDiscount($product);
        }

        // Проверим, используются ли автоматические скидки
        // Автоматические скидки применяются, если на товар не действует предустановленные скидки
        if ($params->get('discounts_use_auto', 0)) {
            // Параметры расчета автоматических скидок
            $target_percent  = $params->get('discounts_auto_percent', 0);
            $target_value    = $params->get('discounts_auto_value', 0);
            $current_value   = 0;
            $current_percent = 0;

            // Если оба параметры равны нулю,
            // значит подойдут любые товары с разницей в ценах больше нуля
            if (!$target_percent && !$target_value) {
                return self::calculateDiscount($product);
            }

            // Способ отбора товаров для автоматических скидок
            switch ($params->get('discounts_auto_mode', 1)) {
                // Способ 1 - ([старая цена] - [цена закупки]) / [цена закупки]
                case 1:
                    // Для расчета должны быть заданы: old_price и cost_price
                    if ($product->old_price > 0 && $product->cost_price > 0) {
                        $current_value    = $product->old_price - $product->cost_price;
                        $current_percent  = round($current_value / $product->cost_price * 100, $precision);
                    }

                    break;

                // Способ 2 - ([основная цена] - [цена закупки]) / [цена закупки]
                case 2:
                    // Для расчета должны быть заданы: price и cost_price
                    if ($product->price > 0 && $product->cost_price > 0) {
                        $current_value   = $product->price - $product->cost_price;
                        $current_percent = round($current_value / $product->cost_price * 100, $precision);
                    }

                    break;

                // Способ 3 - ([цена со скидкой] - [цена закупки]) / [цена закупки]
                case 3:
                    // Для расчета должны быть заданы: price и cost_price
                    if ($product->sale_price > 0 && $product->cost_price > 0) {
                        $current_value   = $product->sale_price - $product->cost_price;
                        $current_percent = round($current_value / $product->cost_price * 100, $precision);
                    }

                    break;
            }

            if ($target_percent > 0 && $current_percent >= $target_percent) {
                return self::calculateDiscount($product);
            }

            if ($target_value > 0 && $current_value >= $target_value) {
                return self::calculateDiscount($product);
            }
        }

        return 0;
    }

    /**
     * Рассчитывает величину скидки в процентах с округлением
     *
     * @param   object $product объект товара
     *
     * @return  float результат расчета в процентах
     * @since 1.0.0
     */
    public static function calculateDiscount(object $product)
    {
        // Размер скидки
        $discount_size = 0;

        // Рассчитаем размер скидки в процентах
        if ($product->old_price > 0 && $product->sale_price > 0) {
            // Если для товара были заданы старая цена и цена со скидкой
            $discount_size = round(($product->old_price - $product->sale_price) / $product->old_price * 100);
        } elseif ($product->price > 0 && $product->sale_price > 0) {
            // Если для товара были заданы только основная цена и цена со скидкой
            $discount_size = round(($product->price - $product->sale_price) / $product->price * 100);
        } elseif ($product->old_price > 0 && $product->price > 0) {
            // Если для товара были заданы старая цена и только основная цена
            $discount_size = round(($product->old_price - $product->price) / $product->old_price * 100);
        }

        // Возвращаем результат, если он больше нуля
        return ($discount_size > 0) ? $discount_size : 0;
    }
}