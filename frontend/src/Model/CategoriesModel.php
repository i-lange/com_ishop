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

use Joomla\CMS\Categories\Categories;
use Joomla\CMS\Categories\CategoryNode;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

/**
 * Эта модель поддерживает получение списков категорий товаров
 * @since 1.0.0
 */
class CategoriesModel extends ListModel
{
    /**
     * Строка контекста модели
     * @var string
     * @since 1.0.0
     */
    public $_context = 'com_ishop.categories';

    /**
     * Контекст категории, позволяет выводить другие расширения из этой модели
     * @var string
     * @since 1.0.0
     */
    protected $_extension = 'com_ishop';

    /**
     * Родительская категория для текущей
     * @var CategoryNode|null
     * @since 1.0.0
     */
    private $_parent = null;

    /**
     * Метод для автоматического заполнения модели
     * Вызов getState в этом методе приведет к рекурсии
     *
     * @param   string  $ordering   Порядок элементов
     * @param   string  $direction  Направление сортировки
     *
     * @return void
     * @throws \Exception
     * @since 1.0.0
     */
    protected function populateState($ordering = null, $direction = null)
    {
        $app = Factory::getApplication();
        $this->setState('filter.extension', $this->_extension);

        // Получаем родительский идентификатор, если он определен
        $parentId = $app->getInput()->getInt('id');
        $this->setState('filter.parentId', $parentId);

        $params = $app->getParams();
        $this->setState('params', $params);

        // Выводим только опубликованные записи
        $this->setState('filter.published', 1);
        // Выводим только доступные для просмотра записи
        $this->setState('filter.access', true);
    }

    /**
     * Метод для получения идентификатора на основе конфигурации модели
     * Это необходимо, поскольку модель используется компонентом и различными модулями,
     * которым могут понадобиться разные наборы данных или разный порядок сортировки
     *
     * @param   string  $id  Префикс
     *
     * @return string Идентификатор
     * @since 1.0.0
     */
    protected function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.extension');
        $id .= ':' . $this->getState('filter.published');
        $id .= ':' . $this->getState('filter.access');
        $id .= ':' . $this->getState('filter.parentId');

        return parent::getStoreId($id);
    }

    /**
     * Метод для получения списка записей,
     * переопределяем для добавления проверки уровней доступа
     * @return mixed Массив элементов или false
     * @throws \Exception
     * @since 1.0.0
     */
    public function getItems(bool $recursive = false)
    {
        $store = $this->getStoreId();

        if (!isset($this->cache[$store])) {
            $app = Factory::getApplication();
            $menu = $app->getMenu();
            $active = $menu->getActive();

            if ($active) {
                $params = $active->getParams();
            } else {
                $params = new Registry();
            }

            $categories = Factory::getApplication()->bootComponent('com_ishop')->getCategory();
            $this->_parent = $categories->get($this->getState('filter.parentId', 'root'));

            if (is_object($this->_parent)) {
                $this->cache[$store] = $this->_parent->getChildren($recursive);
            } else {
                $this->cache[$store] = false;
            }
        }

        return $this->cache[$store];
    }

    /**
     * Получение родителя
     * @return object Массив элементов при успехе, false при неудаче
     * @throws \Exception
     * @since 1.0.0
     */
    public function getParent()
    {
        if (!is_object($this->_parent)) {
            $this->getItems();
        }

        return $this->_parent;
    }

    /**
     * Получение данных списка категорий
     * по массиву их идентификаторов
     * @return array stdClass::class Массив элементов при успехе, false при неудаче
     * @throws \Exception
     * @since 1.0.0
     */
    public function getListByIds(array $ids = [])
    {
        if (empty($ids)) {
            return [];
        }

        $db = $this->getDatabase();

        $query = $db->getQuery(true);
        $query
            ->select([
                $db->quoteName('a.id'),
                $db->quoteName('a.title'),
                $db->quoteName('a.alias'),
                $db->quoteName('a.params'),
            ])
            ->from($db->quoteName('#__categories', 'a'))
            ->whereIn($db->quoteName('a.id'), ArrayHelper::toInteger($ids));
        $db->setQuery($query);

        return $db->loadObjectList('id');
    }
}
