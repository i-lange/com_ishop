<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\ParameterType;

/**
 * Модель профиля пользователя com_iShop
 * @since 1.0.0
 */
class ProfileModel extends BaseDatabaseModel
{

    /**
     * Метод для автоматического заполнения модели
     * Вызов getState в этом методе приведет к рекурсии
     * @return void
     * @throws \Exception
     * @since 1.0.0
     */
    protected function populateState($ordering = null, $direction = null)
    {
        //parent::populateState();

        $app = Factory::getApplication();

        $params	= $app->getParams();
        $this->setState('params', $params);

        // Фильтрация по состоянию публикации
        $this->setState('filter.published', 1);

        // Количество товаров в списках
        $this->setState('list.limit', 10);
        $this->setState('list.ordering', 'a.ordering');
        $this->setState('list.direction', 'ASC');
    }


    /**
     * Метод для получения
     * профиля пользователя
     *
     * @return mixed Массив элементов или false
     * @throws \Exception
     * @since 1.0.0
     */
    public function getProfile()
    {

        return false;
    }

    /**
     * Метод для получения
     * списка заказов пользователя
     *
     * @return mixed Массив элементов или false
     * @throws \Exception
     * @since 1.0.0
     */
    public function getOrders()
    {

        return false;
    }

    /**
     * Метод для получения
     * списка избранных товаров пользователя
     *
     * @return mixed Массив элементов или false
     * @throws \Exception
     * @since 1.0.0
     */
    public function getWishlist()
    {

        return false;
    }

    /**
     * Метод для получения
     * списка сравнения товаров пользователя
     *
     * @return mixed Массив элементов или false
     * @throws \Exception
     * @since 1.0.0
     */
    public function getCompare()
    {

        return false;
    }

    /**
     * Метод для получения
     * списка просмотренных товаров пользователя
     *
     * @return mixed Массив элементов или false
     * @throws \Exception
     * @since 1.0.0
     */
    public function getViewed()
    {

        return false;
    }
}
