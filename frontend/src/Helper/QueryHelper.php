<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;

/**
 * iShop Query Helper
 * @since 1.0.0
 */
class QueryHelper
{
    /**
     * Переводит код сортировки в столбец для запроса сортировки категории
     *
     * @param   string  $ordering  код сортировки
     *
     * @return string SQL столбцы для order by
     * @since 1.0.0
     */
    public static function orderByPrimary(string $ordering)
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        switch ($ordering) {
            case 'alpha':
                $ordering = $db->qn('c.path') . ', ';
                break;

            case 'order':
                $ordering = $db->qn('c.lft') . ', ';
                break;

            default:
                $ordering = '';
                break;
        }

        return $ordering;
    }

    /**
     * Переводит код сортировки в столбец для запроса сортировки товаров
     *
     * @param   string              $ordering   код сортировки
     * @param   string              $orderDate  код сортировки для даты
     * @param   ?DatabaseInterface  $db         база данных
     *
     * @return  string  SQL столбцы для order by
     * @since   1.0.0
     */
    public static function orderBySecondary(string $ordering, string $orderDate = 'created', ?DatabaseInterface $db = null)
    {
        $db = $db ?: Factory::getContainer()->get(DatabaseInterface::class);

        $queryDate = self::getQueryDate($orderDate, $db);

        switch ($ordering) {
            case 'date':
                $ordering = $queryDate;
                break;

            case 'r_date':
                $ordering = $queryDate . ' DESC ';
                break;

            case 'alpha':
                $ordering = $db->qn('a.title');
                break;

            case 'hits':
                $ordering = $db->qn('a.hits') . ' DESC';
                break;

            case 'featured':
                $ordering = $db->qn('a.featured') . ' DESC, ' . $queryDate . ' DESC ';
                break;

            case 'random':
                $ordering = $db->getQuery(true)->rand();
                break;

            case 'r_order':
                $ordering = $db->qn('a.ordering') . ' DESC';
                break;

            default:
                $ordering = $db->qn('a.ordering');
                break;
        }

        return $ordering;
    }

    /**
     * Переводит код сортировки в столбец для запроса сортировки по датам
     *
     * @param   string              $orderDate  код сортировки
     * @param   ?DatabaseInterface  $db         база данных
     *
     * @return  string  SQL столбцы для order by
     * @since   1.0.0
     */
    public static function getQueryDate($orderDate, ?DatabaseInterface $db = null)
    {
        $db = $db ?: Factory::getContainer()->get(DatabaseInterface::class);

        switch ($orderDate) {
            case 'modified':
                $queryDate =
                    ' CASE WHEN ' . $db->qn('a.modified') .
                    ' IS NULL THEN ' . $db->qn('a.created') .
                    ' ELSE ' . $db->qn('a.modified') . ' END';
                break;

            case 'published':
                $queryDate =
                    ' CASE WHEN ' . $db->qn('a.publish_up') .
                    ' IS NULL THEN ' . $db->qn('a.created') .
                    ' ELSE ' . $db->qn('a.publish_up') . ' END ';
                break;

            case 'unpublished':
                $queryDate =
                    ' CASE WHEN ' . $db->qn('a.publish_down') .
                    ' IS NULL THEN ' . $db->qn('a.created') .
                    ' ELSE ' . $db->qn('a.publish_down') . ' END ';
                break;

            case 'created':
            default:
                $queryDate = ' a.created ';
                break;
        }

        return $queryDate;
    }

    /**
     * Возвращает SQL для отбора товаров по наличию скидки
     *
     * @return  string SQL условий отбора товаров
     * @since 1.0.0
     */
    public static function getDiscountFilterQuery(): string
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        // Изначально собираем массив условий,
        // любое из которых должно выполняться
        $sql = [];

        // Параметры компонента
        $params = ComponentHelper::getParams('com_ishop');

        // Если применение скидок отключено - размер скидки всегда 0
        if (!$params->get('discounts_use', 0)) {
            return '';
        }

        // Проверим, используются ли автоматические скидки
        // Фильтр по автоматическим скидкам имеет приоритет для бизнеса
        // и применяется в первую очередь
        if ($params->get('discounts_use_auto', 0)) {
            // Параметры расчета автоматических скидок
            $target_percent  = $params->get('discounts_auto_percent', 0);
            $target_value    = $params->get('discounts_auto_value', 0);

            // Если оба параметры равны нулю,
            // значит подойдут любые товары с разницей в ценах больше нуля
            if (!$target_percent && !$target_value) {
                // Если для товара были заданы старая цена и цена со скидкой
                $sql[] = '(' . $db->qn('a.old_price') . ' > 0 AND ' . $db->qn('a.sale_price') . ' > 0 AND (' . $db->qn('a.old_price') . ' - ' . $db->qn('a.sale_price') . ') > 0)';

                // Если для товара были заданы только основная цена и цена со скидкой
                $sql[] = '(' . $db->qn('a.price') . ' > 0 AND ' . $db->qn('a.sale_price') . ' > 0 AND (' . $db->qn('a.price') . ' - ' . $db->qn('a.sale_price') . ') > 0)';

                // Если для товара были заданы старая цена и только основная цена
                $sql[] = '(' . $db->qn('a.old_price') . ' > 0 AND ' . $db->qn('a.price') . ' > 0 AND (' . $db->qn('a.old_price') . ' - ' . $db->qn('a.price') . ') > 0)';
            }

            // Способ отбора товаров для автоматических скидок
            switch ($params->get('discounts_auto_mode', 1)) {
                // Способ 1 - ([старая цена] - [цена закупки]) / [цена закупки]
                case 1:
                    // Для отбора должны быть заданы: old_price и cost_price
                    if ($target_percent > 0) {
                        $sql[] =
                            '(' .
                            $db->qn('a.old_price') .  ' > 0 AND ' .
                            $db->qn('a.cost_price') . ' > 0 AND ' .
                            '(' . $db->qn('a.old_price') . ' - ' . $db->qn('a.cost_price') . ') / ' . $db->qn('a.cost_price') . ' * 100 >= ' . $target_percent .
                            ')';
                    }

                    if ($target_value > 0) {
                        $sql[] =
                            '(' .
                            $db->qn('a.old_price') .  ' > 0 AND ' .
                            $db->qn('a.cost_price') . ' > 0 AND ' .
                            '(' . $db->qn('a.old_price') . ' - ' . $db->qn('a.cost_price') . ') >= ' . $target_value .
                            ')';
                    }

                    break;

                // Способ 2 - ([основная цена] - [цена закупки]) / [цена закупки]
                case 2:
                    // Для отбора должны быть заданы: price и cost_price
                    if ($target_percent > 0) {
                        $sql[] =
                            '(' .
                            $db->qn('a.price') .      ' > 0 AND ' .
                            $db->qn('a.cost_price') . ' > 0 AND ' .
                            '(' . $db->qn('a.price') . ' - ' . $db->qn('a.cost_price') . ') / ' . $db->qn('a.cost_price') . ' * 100 >= ' . $target_percent .
                            ')';
                    }

                    if ($target_value > 0) {
                        $sql[] =
                            '(' .
                            $db->qn('a.price') .      ' > 0 AND ' .
                            $db->qn('a.cost_price') . ' > 0 AND ' .
                            '(' . $db->qn('a.price') . ' - ' . $db->qn('a.cost_price') . ') >= ' . $target_value .
                            ')';
                    }

                    break;

                // Способ 3 - ([цена со скидкой] - [цена закупки]) / [цена закупки]
                case 3:
                    // Для отбора должны быть заданы: sale_price и cost_price
                    if ($target_percent > 0) {
                        $sql[] =
                            '(' .
                            $db->qn('a.sale_price') . ' > 0 AND ' .
                            $db->qn('a.cost_price') . ' > 0 AND ' .
                            '(' . $db->qn('a.sale_price') . ' - ' . $db->qn('a.cost_price') . ') / ' . $db->qn('a.cost_price') . ' * 100 >= ' . $target_percent .
                            ')';
                    }

                    if ($target_value > 0) {
                        $sql[] =
                            '(' .
                            $db->qn('a.sale_price') . ' > 0 AND ' .
                            $db->qn('a.cost_price') . ' > 0 AND ' .
                            '(' . $db->qn('a.sale_price') . ' - ' . $db->qn('a.cost_price') . ') >= ' . $target_value .
                            ')';
                    }

                    break;
            }

            // Проверим, используются ли предустановленные скидки
            if ($params->get('discounts_use_manual', 0) && empty($sql)) {
                // Если для товара были заданы старая цена и цена со скидкой
                $sql[] = '(' . $db->qn('a.old_price') . ' > 0 AND ' . $db->qn('a.sale_price') . ' > 0 AND (' . $db->qn('a.old_price') . ' - ' . $db->qn('a.sale_price') . ') > 0)';

                // Если для товара были заданы только основная цена и цена со скидкой
                $sql[] = '(' . $db->qn('a.price') . ' > 0 AND ' . $db->qn('a.sale_price') . ' > 0 AND (' . $db->qn('a.price') . ' - ' . $db->qn('a.sale_price') . ') > 0)';

                // Если для товара были заданы старая цена и только основная цена
                $sql[] = '(' . $db->qn('a.old_price') . ' > 0 AND ' . $db->qn('a.price') . ' > 0 AND (' . $db->qn('a.old_price') . ' - ' . $db->qn('a.price') . ') > 0)';
            }
        }

        return '(' . implode(' OR ', $sql) . ')';
    }
}
