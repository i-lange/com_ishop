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
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\ItemModel;
use Joomla\Database\ParameterType;

/**
 * Модель списка остатков на складе
 * @since 1.0.0
 */
class WarehouseModel extends ItemModel
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
        $input = $app->getInput();

        // Устанавливаем состояние из запроса - id товара
        $pk = $input->getInt('id');
        $this->setState('warehouse.id', $pk);

        // Отступ списка
        $offset = $input->getUint('limitstart');
        $this->setState('list.offset', $offset);

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
     * Метод получения данных записи
     * @param int $pk Идентификатор записи
     * @return object|bool Объект данных записи при успехе, иначе false
     * @throws \Exception
     * @since 1.0.0
     */
    public function getItem($pk = null)
    {
        $pk = (int) ($pk ?: $this->getState('warehouse.id'));

        if ($this->_item === null) {
            $this->_item = [];
        }

        if (!isset($this->_item[$pk])) {
            try {
                $db    = $this->getDatabase();
                $query = $db->getQuery(true);

                $query
                    ->select(
                        $this->getState(
                            'item.select',
                            [
                                $db->quoteName('a.id'),
                                $db->quoteName('a.title'),
                                $db->quoteName('a.alias'),
                                $db->quoteName('a.state'),
                                $db->quoteName('a.point'),
                                $db->quoteName('a.introtext'),
                                $db->quoteName('a.fulltext'),
                                $db->quoteName('a.address'),
                                $db->quoteName('a.phone'),
                                $db->quoteName('a.latitude'),
                                $db->quoteName('a.longitude'),
                                $db->quoteName('a.images'),
                                $db->quoteName('a.icon'),
                                $db->quoteName('a.emoji'),
                                $db->quoteName('a.created'),
                                $db->quoteName('a.created_by'),
                                $db->quoteName('a.created_by_alias'),
                                $db->quoteName('a.modified'),
                                $db->quoteName('a.modified_by'),
                                $db->quoteName('a.checked_out'),
                                $db->quoteName('a.checked_out_time'),
                                $db->quoteName('a.language'),
                            ]
                        )
                    )
                    ->from($db->quoteName('#__ishop_warehouses', 'a'))
                    ->where($db->quoteName('a.id') . ' = :pk')
                    ->bind(':pk', $pk, ParameterType::INTEGER);

                // Фильтрация по языку
                if ($this->getState('filter.language')) {
                    $query->whereIn(
                        $db->quoteName('a.language'),
                        [Factory::getApplication()->getLanguage()->getTag(), '*'],
                        ParameterType::STRING
                    );
                }

                // Фильтрация по состоянию публикации
                $published = $this->getState('filter.published');
                if (is_numeric($published)) {
                    $query
                        ->where($db->quoteName('a.state') . ' = :published')
                        ->bind(':published', $published, ParameterType::INTEGER);
                }

                $db->setQuery($query);
                $data = $db->loadObject();

                if (empty($data)) {
                    throw new \Exception(Text::_('COM_ISHOP_ERROR_WAREHOUSE_NOT_FOUND'), 404);
                }

                // Проверяем состояние, если установлен фильтр
                if (is_numeric($published) && ($data->state != $published)) {
                    throw new \Exception(Text::_('COM_ISHOP_ERROR_WAREHOUSE_NOT_FOUND'), 404);
                }

                $data->params = $this->getState('params');

                // Конвертируем сериализованные данные в объекты
                $data->images = json_decode($data->images);

                $this->_item[$pk] = $data;

            } catch (\Exception $e) {
                if ($e->getCode() == 404) {
                    // Необходимо пройти через обработчик ошибок, чтобы Redirect заработал
                    throw $e;
                }
                $this->_item[$pk] = false;
            }
        }

        return $this->_item[$pk];
    }

    /**
     * Метод получения данных товаров на складе
     *
     * @return array данные товаров на складе
     * @throws \Exception
     * @since 1.0.0
     */
    public function getProducts()
    {
        $model = $this->getMVCFactory()->createModel('Products', 'Site', ['ignore_request' => true]);
        $model->setState('filter.warehouse_id', $this->getState('warehouse.id'));
        $model->setState('filter.language', $this->getState('filter.language'));
        $model->setState('params', Factory::getApplication()->getParams());
        $model->setState('filter.access', $this->getState('filter.access'));
        $model->setState('filter.published', $this->getState('filter.published'));
        $model->setState('list.limit', $this->getState('list.limit'));
        //$model->setState('list.ordering', 'a.ordering');

        return $model->getItems();
    }

    /**
     * Метод получения данных по наличию на складе/ на всех складах
     *
     * @return array Объект данных
     * @throws \Exception
     * @since 1.0.0
     */
    public function getStock()
    {
        $stock = [];

        $products = $this->getProducts();
        if (empty($products)) {
            return $stock;
        }

        $categories_ids = array_unique(array_column($products, 'catid'));
        $stock = $this
            ->getMVCFactory()
            ->createModel('Categories', 'Site', ['ignore_request' => true])
            ->getListByIds($categories_ids);
        unset($categories_ids);

        foreach ($products as $product) {
            if (!isset($stock[$product->catid]->products)) {
                $stock[$product->catid]->products = [];
                $stock[$product->catid]->count = 0;
            }

            $stock[$product->catid]->products[] = $product;
            $stock[$product->catid]->count++;
        }

        return $stock;
    }
}
