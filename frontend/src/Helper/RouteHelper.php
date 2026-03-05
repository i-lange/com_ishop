<?php
/**
 * @package        com_ishop
 * @author         Pavel Lange <pavel@ilange.ru>
 * @link           https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license        GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Site\Helper;

use Joomla\CMS\Categories\CategoryNode;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Multilanguage;

defined('_JEXEC') or die;

/**
 * Helper роутера компонента com_ishop
 * @since 1.0.0
 */
abstract class RouteHelper
{
    /**
     * Получаем маршрут товара
     *
     * @param   int          $id        Идентификатор товара
     * @param   int          $catid     Идентификатор категории
     * @param   string|null  $language  Языковой код
     * @param   string|null  $layout    Шаблон вывода
     *
     * @return  string  Построенный маршрут товара
     * @since 1.0.0
     */
    public static function getProductRoute(int $id, int $catid = 0, $language = null, string $layout = null)
    {
        $link = 'index.php?option=com_ishop&view=product&id=' . $id;

        if ($catid > 1) {
            $link .= '&catid=' . $catid;
        }

        if ($language && $language !== '*' && Multilanguage::isEnabled()) {
            $link .= '&lang=' . $language;
        }

        if ($layout) {
            $link .= '&layout=' . $layout;
        }

        return $link;
    }

    /**
     * Получаем маршрут категории
     *
     * @param   int         $catid    Идентификатор категории
     * @param   string|null $language Код языка
     * @param   string|null $layout   Шаблон вывода
     *
     * @return string Построенный маршрут категории
     * @throws \Exception
     * @since 1.0.0
     */
    public static function getCategoryRoute($catid, $language = null, string $layout = null)
    {
        if ($catid instanceof CategoryNode) {
            $id = $catid->id;
        } else {
            $id = (int) $catid;
        }

        if ($id < 1) {
            return '';
        }

        $link = 'index.php?option=com_ishop&view=category&id=' . $catid;

        if ($language && $language !== '*' && Multilanguage::isEnabled()) {
            $link .= '&lang=' . $language;
        }

        if ($layout) {
            $link .= '&layout=' . $layout;
        }

        return $link;
    }

    /**
     * Получаем маршрут списка категорий
     *
     * @param   int  $language  Языковой код
     *
     * @return  string    Построенный маршрут списка категорий
     * @throws \Exception
     * @since 1.0.0
     */
    public static function getCategoriesRoute($language = null)
    {
        $link = 'index.php?option=com_ishop&view=categories';

        if ($language && $language !== '*' && Multilanguage::isEnabled()) {
            $link .= '&lang=' . $language;
        }

        return $link;
    }

    /**
     * Получаем маршрут производителя
     *
     * @param   int  $id        Идентификатор производителя
     * @param   int  $language  Языковой код
     *
     * @return    string    Построенный маршрут производителя
     * @throws \Exception
     * @since 1.0.0
     */
    public static function getManufacturerRoute(int $id, $language = 0)
    {
        $link = 'index.php?option=com_ishop&view=manufacturer&id=' . $id;

        if ($language && $language !== '*' && Multilanguage::isEnabled()) {
            $link .= '&lang=' . $language;
        }

        return $link;
    }

    /**
     * Получаем маршрут списка производителей
     *
     * @param   int  $language  Языковой код
     *
     * @return    string    Построенный маршрут производителей
     * @throws \Exception
     * @since 1.0.0
     */
    public static function getManufacturersRoute($language = 0)
    {
        $link = 'index.php?option=com_ishop&view=manufacturers';

        if ($language && $language !== '*' && Multilanguage::isEnabled()) {
            $link .= '&lang=' . $language;
        }

        return $link;
    }

    /**
     * Получаем маршрут поставщика
     *
     * @param   int  $id        Идентификатор поставщика
     * @param   int  $language  Языковой код
     *
     * @return    string    Построенный маршрут поставщика
     * @throws \Exception
     * @since 1.0.0
     */
    public static function getSupplierRoute(int $id, $language = 0)
    {
        $link = 'index.php?option=com_ishop&view=supplier&id=' . $id;

        if ($language && $language !== '*' && Multilanguage::isEnabled()) {
            $link .= '&lang=' . $language;
        }

        return $link;
    }

    /**
     * Получаем маршрут склада
     *
     * @param   int  $id        Идентификатор склада
     * @param   int  $language  Языковой код
     *
     * @return    string    Построенный маршрут склада
     * @throws \Exception
     * @since 1.0.0
     */
    public static function getWarehouseRoute(int $id, $language = 0)
    {
        $link = 'index.php?option=com_ishop&view=warehouse&id=' . $id;

        if ($language && $language !== '*' && Multilanguage::isEnabled()) {
            $link .= '&lang=' . $language;
        }

        return $link;
    }

    /**
     * Получаем маршрут префикса
     *
     * @param   int  $id        Идентификатор префикса
     * @param   int  $language  Языковой код
     *
     * @return    string    Построенный маршрут префикса
     * @throws \Exception
     * @since 1.0.0
     */
    public static function getPrefixRoute(int $id, $language = 0)
    {
        $link = 'index.php?option=com_ishop&view=prefix&id=' . $id;

        if ($language && $language !== '*' && Multilanguage::isEnabled()) {
            $link .= '&lang=' . $language;
        }

        return $link;
    }

    /**
     * Получаем маршрут префикса
     *
     * @param   int  $id        Идентификатор префикса
     * @param   int  $language  Языковой код
     *
     * @return    string    Построенный маршрут префикса
     * @throws \Exception
     * @since 1.0.0
     */
    public static function getServiceRoute(int $id, $language = 0)
    {
        $link = 'index.php?option=com_ishop&view=service&id=' . $id;

        if ($language && $language !== '*' && Multilanguage::isEnabled()) {
            $link .= '&lang=' . $language;
        }

        return $link;
    }

    /**
     * Получаем маршрут профиля пользователя
     *
     * @param   int  $language  Языковой код
     *
     * @return    string    Построенный маршрут
     * @throws \Exception
     * @since 1.0.0
     */
    public static function getProfileRoute($language = 0)
    {
        $link = 'index.php?option=com_ishop&view=profile';

        if ($language && $language !== '*' && Multilanguage::isEnabled()) {
            $link .= '&lang=' . $language;
        }

        return $link;
    }

    /**
     * Получаем маршрут оформления заказа
     *
     * @param   int  $language  Языковой код
     *
     * @return    string    Построенный маршрут
     * @throws \Exception
     * @since 1.0.0
     */
    public static function getCheckoutRoute($language = 0)
    {
        $link = 'index.php?option=com_ishop&view=checkout';

        if ($language && $language !== '*' && Multilanguage::isEnabled()) {
            $link .= '&lang=' . $language;
        }

        return $link;
    }

    /**
     * Получаем маршрут корзины товаров
     *
     * @param   int  $language  Языковой код
     *
     * @return    string    Построенный маршрут корзины
     * @throws \Exception
     * @since 1.0.0
     */
    public static function getCartRoute($language = 0)
    {
        $link = 'index.php?option=com_ishop&view=cart';

        if ($language && $language !== '*' && Multilanguage::isEnabled()) {
            $link .= '&lang=' . $language;
        }

        return $link;
    }

    /**
     * Получаем маршрут к списку желаний
     *
     * @param   int  $language  Языковой код
     *
     * @return    string    Построенный списка желаний
     * @throws \Exception
     * @since 1.0.0
     */
    public static function getWishlistRoute($language = 0)
    {
        $link = 'index.php?option=com_ishop&view=wishlist';

        if ($language && $language !== '*' && Multilanguage::isEnabled()) {
            $link .= '&lang=' . $language;
        }

        return $link;
    }

    /**
     * Получаем маршрут к списку сравнения
     *
     * @param   int  $language  Языковой код
     *
     * @return    string    Построенный списка сравнения
     * @throws \Exception
     * @since 1.0.0
     */
    public static function getCompareRoute($language = 0)
    {
        $link = 'index.php?option=com_ishop&view=compare';

        if ($language && $language !== '*' && Multilanguage::isEnabled()) {
            $link .= '&lang=' . $language;
        }

        return $link;
    }
}