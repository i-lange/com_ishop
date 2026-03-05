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
 * Модель списка остатков на складе
 * @since 1.0.0
 */
class WarehousestockModel extends BaseDatabaseModel
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

        // Фильтрация по id склада
        $value = $input->get('id', 0);
        $this->setState('filter.warehouse_id', $value);

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
     * Метод получения данных товаров на складе
     *
     * @return array данные товаров на складе
     * @throws \Exception
     * @since 1.0.0
     */
    public function getProducts()
    {
        $model = $this->getMVCFactory()->createModel('Products', 'Site', ['ignore_request' => true]);
        $model->setState('filter.warehouse_id', $this->getState('filter.warehouse_id'));
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
