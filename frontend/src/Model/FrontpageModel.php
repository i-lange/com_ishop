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
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use RuntimeException;

/**
 * Модель главной страницы магазина com_iShop
 * @since 1.0.0
 */
class FrontpageModel extends BaseDatabaseModel
{
    /**
     * Список категорий товаров
     * @var array
     * @since 1.0.0
     */
    protected $_categories = null;

    /**
     * Список ТОП товаров
     * @var array
     * @since 1.0.0
     */
    public $_products = null;

    /**
     * Материал для описания
     * @var object
     * @since 1.0.0
     */
    public $_text = null;

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

        $this->setState('frontpage_show_categories', $params->get('frontpage_show_categories', 0));
        $this->setState('frontpage_show_products', $params->get('frontpage_show_products', 0));
        $this->setState('frontpage_products_count', $params->get('frontpage_products_count', 15));
        $this->setState('frontpage_products_catid', $params->get('frontpage_products_catid', []));
        $this->setState('frontpage_products_mode', $params->get('frontpage_products_mode', 2));
        $this->setState('frontpage_show_article', $params->get('frontpage_show_article', 0));
        $this->setState('frontpage_article_id', $params->get('frontpage_article_id', 0));
    }

    /**
     * Метод получает список категорий каталога
     *
     * @return mixed Массив записей или false
     * @throws \Exception
     * @since 1.0.0
     */
    public function getCategories()
    {
        if (!$this->getState('frontpage_show_categories')) {
            $this->_categories = false;
            return $this->_categories;
        }

        if ($this->_categories === null) {
            $model = $this
                ->bootComponent('com_ishop')
                ->getMVCFactory()
                ->createModel('Categories', 'Site', ['ignore_request' => true]);

            $model->setState('filter.extension', 'com_ishop');
            $model->setState('params', $this->getState('params'));
            //$model->setState('filter.get_children', 9999);
            $this->setState('filter.parentId', 'root');
            // Выводим только опубликованные записи
            $model->setState('filter.published', 1);
            // Выводим только доступные для просмотра записи
            $model->setState('filter.access', true);

            $this->_categories = $model->getItems();

            if ($this->_categories === false) {
                // Если не удалось получить список категорий из базы данных
                throw new RuntimeException(implode("\n", $model->getError()), 500);
            }

        }

        return $this->_categories;
    }

    /**
     * Метод получает текст статьи для главной страницы магазина
     *
     * @return mixed Список товаров или false
     * @throws \Exception
     * @since 1.0.0
     */
    public function getProducts()
    {
        if (!$this->getState('frontpage_show_products')) {
            $this->_products = false;
            return $this->_products;
        }

        if ($this->_products === null) {
            $model = $this
                ->bootComponent('com_ishop')
                ->getMVCFactory()
                ->createModel('Products', 'Site', ['ignore_request' => true]);

            // Количество товаров
            $this->setState('frontpage_products_count', $this->getState('frontpage_products_count', 15));

            // Фильтрация по категориям
            $this->setState('catid', $this->getState('frontpage_products_catid', []));

            // Подбор товаров по критериям
            $mode = $this->getState('frontpage_products_mode', 2);
            switch ($mode) {
                // Самые продаваемые
                case 1:
                    $model->setState('filter.published', 1);
                    break;
                // Самые просматриваемые
                case 2:
                    $model->setState('list.ordering', 'a.hits');
                    $model->setState('list.direction', 'DESC');
                    break;
                // Самые рейтинговые
                case 3:
                    $model->setState('list.ordering', 'a.rating');
                    $model->setState('list.direction', 'DESC');
                    break;
                // Самые большие скидки
                case 4:
                    $model->setState('good_price', 1);
                    break;
            }

            $this->_products = $model->getItems();

            if ($this->_products === false) {
                // Если не удалось получить список категорий из базы данных
                throw new RuntimeException(implode("\n", $model->getError()), 500);
            }

        }

        return $this->_products;
    }

    /**
     * Метод получает текст статьи для главной страницы магазина
     *
     * @return mixed Запись или false
     * @throws \Exception
     * @since 1.0.0
     */
    public function getText()
    {
        if (!$this->getState('frontpage_show_article') ||
            !$this->getState('frontpage_article_id')) {
            $this->_text = false;
            return $this->_text;
        }

        if ($this->_text === null) {
            $model = $this
                ->bootComponent('com_content')
                ->getMVCFactory()
                ->createModel('Article', 'Site', ['ignore_request' => true]);
            $model->setState('params', $this->getState('params'));

            $this->_text = $model->getItem($this->getState('frontpage_article_id'));

            if ($this->_text === false) {
                // Если не удалось получить список категорий из базы данных
                throw new RuntimeException(implode("\n", $model->getError()), 500);
            }
        }

        return $this->_text;
    }
}
