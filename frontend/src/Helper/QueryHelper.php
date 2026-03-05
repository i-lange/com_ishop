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
     * @param string $order_by код сортировки
     *
     * @return string SQL столбцы для order by
     * @since 1.0.0
     */
    public static function orderbyPrimary($order_by)
    {
        switch ($order_by) {
            case 'alpha':
                $order_by = 'c.path, ';
                break;

            case 'ralpha':
                $order_by = 'c.path DESC, ';
                break;

            case 'order':
                $order_by = 'c.lft, ';
                break;

            default:
                $order_by = '';
                break;
        }

        return $order_by;
    }

    /**
     * Переводит код сортировки в столбец для запроса сортировки товаров
     *
     * @param   string              $orderby    код сортировки
     * @param   string              $orderDate  код сортировки для даты
     * @param   ?DatabaseInterface  $db         база данных
     *
     * @return  string  SQL столбцы для order by
     * @since   1.0.0
     */
    public static function orderbySecondary($orderby, $orderDate = 'created', ?DatabaseInterface $db = null)
    {
        $db = $db ?: $db = Factory::getContainer()->get(DatabaseInterface::class);;

        $queryDate = self::getQueryDate($orderDate, $db);

        switch ($orderby) {
            case 'date':
                $orderby = $queryDate;
                break;

            case 'rdate':
                $orderby = $queryDate . ' DESC ';
                break;

            case 'alpha':
                $orderby = 'a.title';
                break;

            case 'ralpha':
                $orderby = 'a.title DESC';
                break;

            case 'hits':
                $orderby = 'a.hits DESC';
                break;

            case 'rhits':
                $orderby = 'a.hits';
                break;

            case 'rorder':
                $orderby = 'a.ordering DESC';
                break;

            case 'author':
                $orderby = 'author';
                break;

            case 'rauthor':
                $orderby = 'author DESC';
                break;

            case 'front':
                $orderby = 'a.featured DESC, fp.ordering, ' . $queryDate . ' DESC ';
                break;

            case 'random':
                $orderby = $db->getQuery(true)->rand();
                break;

            case 'vote':
                $orderby = 'a.id DESC ';

                if (PluginHelper::isEnabled('content', 'vote')) {
                    $orderby = 'rating_count DESC ';
                }
                break;

            case 'rvote':
                $orderby = 'a.id ASC ';

                if (PluginHelper::isEnabled('content', 'vote')) {
                    $orderby = 'rating_count ASC ';
                }
                break;

            case 'rank':
                $orderby = 'a.id DESC ';

                if (PluginHelper::isEnabled('content', 'vote')) {
                    $orderby = 'rating DESC ';
                }
                break;

            case 'rrank':
                $orderby = 'a.id ASC ';

                if (PluginHelper::isEnabled('content', 'vote')) {
                    $orderby = 'rating ASC ';
                }
                break;

            default:
                $orderby = 'a.ordering';
                break;
        }

        return $orderby;
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
        $db = $db ?: Factory::getDbo();

        switch ($orderDate) {
            case 'modified':
                $queryDate = ' CASE WHEN a.modified IS NULL THEN a.created ELSE a.modified END';
                break;

            case 'published':
                $queryDate = ' CASE WHEN a.publish_up IS NULL THEN a.created ELSE a.publish_up END ';
                break;

            case 'unpublished':
                $queryDate = ' CASE WHEN a.publish_down IS NULL THEN a.created ELSE a.publish_down END ';
                break;

            case 'created':
            default:
                $queryDate = ' a.created ';
                break;
        }

        return $queryDate;
    }
}
