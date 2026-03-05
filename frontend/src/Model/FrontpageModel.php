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
     * Список ТОП товаров
     * @var array
     * @since 1.0.0
     */
    public $_products = null;

    /**
     * Список категорий товаров
     * @var array
     * @since 1.0.0
     */
    protected $_categories = null;

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

        $this->setState('show_products', $params->get('show_products', 0));
        $this->setState('products_catid', $params->get('products_catid', []));
        $this->setState('products_top', $params->get('products_top', 1));
        $this->setState('products_count', $params->get('products_count', 15));
        $this->setState('show_categories', $params->get('show_categories', 0));
        $this->setState('show_text', $params->get('show_text', 0));
        $this->setState('article_id', $params->get('article_id', 0));
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
        if (!$this->getState('show_categories')) {
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
     * @return mixed Запись или false
     * @throws \Exception
     * @since 1.0.0
     */
    public function getText()
    {
        if (!$this->getState('show_text') ||
            !$this->getState('article_id')) {
            $this->_text = false;
            return $this->_text;
        }

        if ($this->_text === null) {
            $model = $this
                ->bootComponent('com_content')
                ->getMVCFactory()
                ->createModel('Article', 'Site', ['ignore_request' => true]);
            $model->setState('params', $this->getState('params'));

            $this->_text = $model->getItem($this->getState('article_id'));

            if ($this->_text === false) {
                // Если не удалось получить список категорий из базы данных
                throw new RuntimeException(implode("\n", $model->getError()), 500);
            }

        }

        return $this->_text;
    }
}
