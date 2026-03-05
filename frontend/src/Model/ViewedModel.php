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
 * Модель списка просмотренных товаров com_ishop
 * @since 1.0.0
 */
class ViewedModel extends BaseDatabaseModel
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
     * Метод получения количества просмотренных товаров
     *
     * @return int Объект данных
     * @throws \Exception
     * @since 1.0.0
     */
    public function getCount()
    {
        return count($this->getViewedList());
    }

    /**
     * Метод получения списка просмотренных товаров
     *
     * @return array Массив идентификаторов
     * @throws \Exception
     * @since 1.0.0
     */
    public function getViewedList()
    {
        // Получаем данные пользователя
        $userData = $this->getMVCFactory()->createModel('User', 'Site')->getItem();

        if (empty($userData) || empty($userData->viewed)) {
            return [];
        }

        return $userData->viewed;
    }

    /**
     * Метод получения данных списка просмотренных товаров
     *
     * @return array данные списка просмотренных товаров
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
     * Метод получения данных списка просмотренных товаров текущего пользователя
     *
     * @return object Объект данных
     * @throws \Exception
     * @since 1.0.0
     */
    public function getViewed(array $filter = [], bool $full = true)
    {
        $viewed = new \stdClass();
        $viewed->products = [];
        $viewed->count = 0;
        $list = $this->getViewedList();
        $pks = (!empty($filter)) ? $filter : $list;

        if (empty($pks)) {
            return $viewed;
        }

        $viewed->products = $this->getProducts($pks, $full);
        $viewed->count = count($viewed->products);

        return $viewed;
    }

    /**
     * Добавляет товар в список просмотренных товаров
     * по его идентификатору
     *
     * @param   int  $id        Идентификатор товара
     *
     * @return array|false массив с данными просмотренных товаров
     * @throws \Exception
     * @since 1.0.0
     */
    public function add(int $id = 0)
    {
        if (!$id) {
            return false;
        }

        $params = ComponentHelper::getParams('com_ishop');
        // Проверим, включена ли история просмотра товаров
        // в настройках компонента
        $use_viewed = $params->get('use_viewed', false);
        if (!$use_viewed) {
            return false;
        }

        // Получим значение максимального числа
        // просмотренных товаров в истории пользователя
        $max = $params->get('viewed_max_count', 999);
        if (!$max) {
            return false;
        }

        $user = $this->getMVCFactory()->createModel('User', 'Site');
        $data = $user->getItem();
        if ($data === false) {
            return false;
        }

        $viewed = (new Registry($data->viewed))->toArray();
        // Сначала проверим, нет ли этого товара в списке
        $key = array_search($id, $viewed, true);
        // Если такой товар нашелся - удаляем его
        if ($key !== false) {
            unset($viewed[$key]);
        }

        // Удаляем последний элемент, если превышен лимит
        if (count($viewed) > $max) {
            array_pop($viewed);
        }

        // Добавляем новый элемент в начало
        array_unshift($viewed, $id);

        // Сохраняем корзину пользователя
        $data->viewed = (string) new Registry($viewed);
        $user->setData($data, 'viewed');

        // Возвращаем данные обновленного списка сравнения
        return ['count' => count($viewed), 'products' => $viewed];
    }
}
