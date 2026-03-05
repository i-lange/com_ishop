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

}