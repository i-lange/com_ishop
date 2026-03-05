<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Site\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Categories\CategoryFactoryInterface;
use Joomla\CMS\Categories\CategoryInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\CMS\Component\Router\Rules\NomenuRules;
//use Ilange\Component\Ishop\Site\Service\NomenuRules;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Класс роутер для com_ishop
 * @since 1.0.0
 */
class Router extends RouterView
{
    /**
     * Флаг удаления ID
     * @var bool
     * @since 1.0.0
     */
    protected $noIDs = false;

    /**
     * Фабрика категорий
     * @var CategoryFactoryInterface
     * @since 1.0.0
     */
    private $categoryFactory;

    /**
     * Кэш категории
     * @var CategoryFactoryInterface
     * @since 1.0.0
     */
    private $categoryCache = [];

    /**
     * Интерфейс базы данных
     * @var DatabaseInterface
     * @since 1.0.0
     */
    private $db;

    /**
     * Конструктор роутера для компонента com_ishop
     *
     * @param   SiteApplication           $app              The application object
     * @param   AbstractMenu              $menu             The menu object to work with
     * @param   CategoryFactoryInterface  $categoryFactory  The category object
     * @param   DatabaseInterface         $db               The database object
     *
     * @since 1.0.0
     */
    public function __construct(
        SiteApplication $app,
        AbstractMenu $menu,
        CategoryFactoryInterface $categoryFactory,
        DatabaseInterface $db
    ) {
        $this->categoryFactory = $categoryFactory;
        $this->db = $db;

        $params = ComponentHelper::getParams('com_ishop');
        $this->noIDs = (bool)$params->get('sef_ids');

        // главная страница магазина
        $frontpage = new RouterViewConfiguration('frontpage');
        $this->registerView($frontpage);

        // список категорий
        $categories = new RouterViewConfiguration('categories');
        $categories->setKey('id');
        $this->registerView($categories);

        // одиночная категория
        $category = new RouterViewConfiguration('category');
        $category->setKey('id')->setParent($categories, 'catid')->setNestable();
        $this->registerView($category);

        // одиночный товар
        $product = new RouterViewConfiguration('product');
        $product->setKey('id')->setParent($category, 'catid');
        $this->registerView($product);

        // избранные товары
        $featured = new RouterViewConfiguration('featured');
        $this->registerView($featured);

        // список производителей
        $manufacturers = new RouterViewConfiguration('manufacturers');
        $this->registerView($manufacturers);

        // одиночный производитель
        $manufacturer = new RouterViewConfiguration('manufacturer');
        $manufacturer->setKey('id')->setParent($manufacturers);
        $this->registerView($manufacturer);

        $this->registerView(new RouterViewConfiguration('profile'));
        $this->registerView(new RouterViewConfiguration('checkout'));
        $this->registerView(new RouterViewConfiguration('cart'));
        $this->registerView(new RouterViewConfiguration('compare'));
        $this->registerView(new RouterViewConfiguration('wishlist'));
        $this->registerView(new RouterViewConfiguration('viewed'));
        $this->registerView(new RouterViewConfiguration('warehousestock'));

        // страница остатков на указанном складе
        $warehousestock = new RouterViewConfiguration('warehousestock');
        $warehousestock->setKey('id');
        $this->registerView($warehousestock);

        parent::__construct($app, $menu);

        $this->attachRule(new MenuRules($this));
        $this->attachRule(new StandardRules($this));
        $this->attachRule(new NomenuRules($this));
    }

    /**
     * Метод получения сегмента (сегментов) для списка категорий
     *
     * @param   string  $id     ID списка категорий для получения сегментов
     * @param   array   $query  Запрос, который собирается в данный момент
     *
     * @return array Массив сегментов списка категорий
     * @since 1.0.0
     */
    public function getCategoriesSegment(string $id, array $query)
    {
        return $this->getCategorySegment($id, $query);
    }

    /**
     * Метод получения сегмента (сегментов) для категории
     *
     * @param   string  $id     ID категории для получения сегментов
     * @param   array   $query  Запрос, который собирается в данный момент
     *
     * @return array Массив сегментов категории
     * @since 1.0.0
     */
    public function getCategorySegment(string $id, array $query)
    {
        $category = $this->getCategories(['access' => true])->get($id);

        if ($category) {
            $path = array_reverse($category->getPath(), true);
            $path[0] = '1:root';

            if ($this->noIDs) {
                foreach ($path as &$segment) {
                    list($id, $segment) = explode(':', $segment, 2);
                }
            }

            return $path;
        }

        return [];
    }

    /**
     * Метод получения сегмента (сегментов) для товара
     *
     * @param   string  $id     ID товара для получения сегментов
     * @param   array   $query  Запрос, который собирается в данный момент
     *
     * @return array Массив сегментов одиночного товара
     * @since 1.0.0
     */
    public function getProductSegment(string $id, array $query)
    {
        if (!strpos($id, ':')) {
            $id = (int) $id;
            $dbquery = $this->db->getQuery(true);
            $dbquery->select($this->db->quoteName('alias'))
                ->from($this->db->quoteName('#__ishop_products'))
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':id', $id, ParameterType::INTEGER);
            $this->db->setQuery($dbquery);

            $id .= ':' . $this->db->loadResult();
        }

        if ($this->noIDs) {
            list($void, $segment) = explode(':', $id, 2);

            return [$void => $segment];
        }

        return [(int)$id => $id];
    }

    /**
     * Метод получения сегмента (сегментов) для производителя
     *
     * @param   string  $id     ID производителя для получения сегментов
     * @param   array   $query  Запрос, который собирается в данный момент
     *
     * @return array Массив сегментов одиночного производителя
     * @since 1.0.0
     */
    public function getManufacturerSegment(string $id, array $query)
    {
        if (!strpos($id, ':')) {
            $id = (int) $id;
            $dbquery = $this->db->getQuery(true);
            $dbquery->select($this->db->quoteName('alias'))
                ->from($this->db->quoteName('#__ishop_manufacturers'))
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':id', $id, ParameterType::INTEGER);
            $this->db->setQuery($dbquery);

            $id .= ':' . $this->db->loadResult();
        }

        if ($this->noIDs) {
            list($void, $segment) = explode(':', $id, 2);

            return [$void => $segment];
        }

        return [(int)$id => $id];
    }


    /**
     * Метод получения идентификатора для списка категорий
     *
     * @param   string  $segment  Сегмент для получения идентификатора
     * @param   array   $query    Запрос, который разбирается в данный момент
     *
     * @return int Идентификатор найденного элемента или нуль
     * @since 1.0.0
     */
    public function getCategoriesId(string $segment, array $query)
    {
        return $this->getCategoryId($segment, $query);
    }

    /**
     * Метод получения идентификатора для категории
     *
     * @param   string  $segment  Сегмент категории для получения идентификатора
     * @param   array   $query    Запрос, который разбирается в данный момент
     *
     * @return int Идентификатор найденной категории или нуль
     * @since 1.0.0
     */
    public function getCategoryId(string $segment, array $query)
    {
        if (isset($query['id'])) {
            $category = $this->getCategories(['access' => false])->get($query['id']);

            if ($category) {
                foreach ($category->getChildren() as $child) {
                    if ($this->noIDs) {
                        if ($child->alias == $segment) {
                            return $child->id;
                        }
                    } elseif ($child->id == (int)$segment) {
                        return $child->id;
                    }
                }
            }
        }

        return 0;
    }

    /**
     * Метод получения идентификатора для товара
     *
     * @param   string  $segment  Сегмент товара для получения идентификатора
     * @param   array   $query    Запрос, который разбирается в данный момент
     *
     * @return int Идентификатор найденного товара или нуль
     * @since 1.0.0
     */
    public function getProductId(string $segment, array $query)
    {
        if ($this->noIDs) {
            $db_query = $this->db->getQuery(true);
            $db_query
                ->select($this->db->quoteName('id'))
                ->from($this->db->quoteName('#__ishop_products'))
                ->where($this->db->quoteName('alias') . ' = :segment')
                ->bind(':segment', $segment);
            $this->db->setQuery($db_query);

            return (int) $this->db->loadResult();
        }

        return (int) $segment;
    }

    /**
     * Метод получения идентификатора для производителя
     *
     * @param   string  $segment  Сегмент производителя для получения идентификатора
     * @param   array   $query    Запрос, который разбирается в данный момент
     *
     * @return int Идентификатор найденного производителя или нуль
     * @since 1.0.0
     */
    public function getManufacturerId(string $segment, array $query)
    {
        if ($this->noIDs) {
            $db_query = $this->db->getQuery(true);
            $db_query
                ->select($this->db->quoteName('id'))
                ->from($this->db->quoteName('#__ishop_manufacturers'))
                ->where($this->db->quoteName('alias') . ' = :segment')
                ->bind(':segment', $segment);
            $this->db->setQuery($db_query);

            return (int) $this->db->loadResult();
        }

        return (int) $segment;
    }

    /**
     * Метод получения категорий из кэша
     *
     * @param   array  $options  Параметры получения категорий
     *
     * @return CategoryInterface Объект, содержащий категории
     * @since 1.0.0
     */
    private function getCategories(array $options = []): CategoryInterface
    {
        $key = serialize($options);

        if (!isset($this->categoryCache[$key])) {
            $this->categoryCache[$key] = $this->categoryFactory->createCategory($options);
        }

        return $this->categoryCache[$key];
    }
}
