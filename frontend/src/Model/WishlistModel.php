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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Registry\Registry;

/**
 * Модель списка сравнения com_ishop
 * @since 1.0.0
 */
class WishlistModel extends BaseDatabaseModel
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
        $app = Factory::getApplication();
        $this->setState('filter.language', Multilanguage::isEnabled());

        $params	= $app->getParams();
        $this->setState('params', $params);

        // Фильтрация по уровню доступа
        $this->setState('filter.access', true);

        // Фильтрация по состоянию публикации
        $this->setState('filter.published', 1);

        // Количество товаров на странице, все
        $this->setState('list.limit', 0);
    }

    /**
     * Метод получения количества товаров в списке избранного
     *
     * @return int Объект данных
     * @throws \Exception
     * @since 1.0.0
     */
    public function getCount()
    {
        return count($this->getWishlistList());
    }

    /**
     * Метод получения списка идентификаторов из списка избранного
     *
     * @return array Массив идентификаторов
     * @throws \Exception
     * @since 1.0.0
     */
    public function getWishlistList()
    {
        // Получаем данные пользователя
        $userData = $this->getMVCFactory()->createModel('User', 'Site')->getItem();

        if (empty($userData) || empty($userData->wishlist)) {
            return [];
        }

        return $userData->wishlist;
    }

    /**
     * Метод получения данных списка товаров в избранном
     *
     * @return array данные списка товаров в избранном
     * @throws \Exception
     * @since 1.0.0
     */
    public function getProducts(array $pks, bool $full = false)
    {
        if (empty($pks)) {
            return [];
        }

        $model = $this->getMVCFactory()->createModel('Products', 'Site', ['ignore_request' => true]);
        $model->setState('filter.warehouse_id', false);
        $model->setState('params', Factory::getApplication()->getParams());

        $model->setState('filter.published', $this->getState('filter.published'));
        $model->setState('filter.access', $this->getState('filter.access'));
        $model->setState('filter.language', $this->getState('filter.language'));

        // Добавляем фильтрацию по списку товаров в корзине
        $model->setState('filter.products', $pks);

        $model->setState('list.ordering', 'FIELD(a.id, '. implode(',', $pks) . ')');
        $model->setState('list.direction', '');
        $model->setState('list.limit', $this->getState('list.limit'));

        if (!$full) {
            $db = $this->getDatabase();
            $model->setState(
                'list.select',
                [
                    $db->quoteName('a.id'),
                    $db->quoteName('a.title'),
                    $db->quoteName('a.stock'),
                    $db->quoteName('a.catid'),
                    $db->quoteName('a.manufacturer_id'),
                    $db->quoteName('a.price'),
                    $db->quoteName('a.old_price'),
                    $db->quoteName('a.sale_price'),
                    $db->quoteName('a.cost_price'),
                    $db->quoteName('a.delivery'),
                    $db->quoteName('a.gtin'),
                    $db->quoteName('a.attribs'),
                ]
            );
        }

        return $model->getItems();
    }

    /**
     * Метод получения данных списка избранного текущего пользователя
     *
     * @return object Объект данных
     * @throws \Exception
     * @since 1.0.0
     */
    public function getWishlist(array $filter = [], bool $full = true)
    {
        $wishlist = new \stdClass();
        $wishlist->products = [];
        $wishlist->count = 0;
        $list = $this->getWishlistList();
        $pks = (!empty($filter)) ? $filter : $list;

        if (empty($pks)) {
            return $wishlist;
        }

        $wishlist->products = $this->getProducts($pks, $full);
        $wishlist->count = count($wishlist->products);

        return $wishlist;
    }

    /**
     * Добавляет товар в список избранного
     * по его идентификатору
     *
     * @param   int  $id        Идентификатор товара
     *
     * @return array|false массив с данными избранного
     * @throws \Exception
     * @since 1.0.0
     */
    public function add(int $id = 0)
    {
        if (!$id) {
            return false;
        }

        $params = ComponentHelper::getParams('com_ishop');
        // Проверим, включено ли
        // использование списка избранных товаров
        // в настройках компонента
        $use_wishlist = $params->get('use_wishlist', false);
        if (!$use_wishlist) {
            return false;
        }

        // Получим значение максимального числа
        // избранных товаров для одного пользователя
        $max = $params->get('wishlist_max_count', 999);
        if (!$max) {
            return false;
        }

        $user = $this->getMVCFactory()->createModel('User', 'Site');
        $data = $user->getItem();
        if ($data === false) {
            return false;
        }

        $wishlist = (new Registry($data->wishlist))->toArray();

        // Сначала проверим, нет ли этого товара в списке
        $key = array_search($id, $wishlist, true);
        // Если такой товар нашелся - удаляем его
        if ($key !== false) {
            unset($wishlist[$key]);
        }

        // Если количество товаров не превышает лимит, добавляем
        if (count($wishlist) < $max) {
            // Добавляем новый элемент в начало
            array_unshift($wishlist, $id);
        }

        // Сохраняем корзину пользователя
        $data->wishlist = (string) new Registry($wishlist);
        $user->setData($data, 'wishlist');

        // Возвращаем данные обновленного списка сравнения
        return ['count' => count($wishlist), 'products' => $wishlist];
    }

    /**
     * Удаляет товар из списка избранного по его идентификатору
     * или очищает список, если id не задан
     *
     * @param int $id Идентификатор товара
     *
     * @return array|false массив с данными избранного
     * @throws \Exception
     * @since 1.0.0
     */
    public function remove(int $id = 0)
    {
        $user = $this->getMVCFactory()->createModel('User', 'Site');
        $data = $user->getItem();
        if ($data === false) {
            return false;
        }

        $wishlist = (new Registry($data->wishlist))->toArray();

        // Если не указан идентификатор товара,
        // очищаем весь список
        if (!$id || empty($wishlist)) {
            $data->wishlist = (string) new Registry([]);
            $user->setData($data, 'wishlist');

            // Возвращаем данные обновленного списка
            return ['count' => 0, 'products' => []];
        }

        // Сначала проверим наличие этого товара в списке
        $key = array_search($id, $wishlist, true);
        // Если такой товар нашелся - удаляем его
        if ($key !== false) {
            unset($wishlist[$key]);
        }

        // Сохраняем список сравнения пользователя
        $data->wishlist = (string) new Registry($wishlist);
        $user->setData($data, 'wishlist');

        // Возвращаем данные обновленного списка сравнения
        return ['count' => count($wishlist), 'products' => $wishlist];
    }
}
