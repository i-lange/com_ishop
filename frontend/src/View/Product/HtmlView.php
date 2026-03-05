<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Site\View\Product;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\TagsHelper;
use Joomla\CMS\Language\Associations;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Ilange\Component\Ishop\Site\Helper\RouteHelper;
use Joomla\Component\Content\Site\Helper\AssociationHelper;

/**
 * HTML представление одиночного товара
 * @since 1.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * Объект товара
     * @var object
     * @since 1.0.0
     */
    protected $item;

    /**
     * Параметры страницы
     * @var \Joomla\Registry\Registry|null
     * @since 1.0.0
     */
    protected $params = null;

    /**
     * Должна ли отображаться кнопка печати или нет
     * @var bool
     * @since 1.0.0
     */
    protected $print = false;

    /**
     * Состояние модели элемента
     * @var \Joomla\Registry\Registry
     * @since 1.0.0
     */
    protected $state;

    /**
     * Объект пользователя
     * @var   \Joomla\CMS\User\User|null
     * @since 1.0.0
     */
    protected $user = null;

    /**
     * Суффикс класса страницы
     * @var string
     * @since 1.0.0
     */
    protected $pageclass_sfx = '';

    /**
     * Связан ли активный пункт меню с отображаемым товаром
     * @var bool
     * @since 1.0.0
     */
    protected $menuItemMatchProduct = false;

    /**
     * Выполнение и отображение шаблона
     *
     * @param   string  $tpl  Имя файла шаблона для | автоматический поиск
     *
     * @return void
     * @throws \Exception
     * @since 1.0.0
     */
    public function display($tpl = null)
    {
        $app  = Factory::getApplication();
        $user = $this->getCurrentUser();

        $this->item  = $this->get('Item');
        $this->print = $app->getInput()->getBool('print', false);
        $this->state = $this->get('State');

        $this->user  = $user;

        if (count($errors = $this->get('Errors'))) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        // Ярлык для $this->item
        $item = &$this->item;
        $item->tagLayout = new FileLayout('joomla.content.tags');

        // Ссылка на карточку товара
        $item->product_url = Route::_(RouteHelper::getProductRoute($item->id, $item->catid, $item->language));

        // Объединяем параметры товара. Если это представление одного товара, параметры меню переопределяют параметры товара
        // В противном случае параметры товара переопределяют параметры пункта меню
        $this->params = $this->state->get('params');
        $active       = $app->getMenu()->getActive();
        $temp         = clone $this->params;

        // Если активный пункт меню ссылается на текущий товар, то
        // параметры пункта меню имеют приоритет
        if ($active && $active->component == 'com_ishop' &&
            isset($active->query['view'], $active->query['id']) &&
            $active->query['view'] == 'product' &&
            $active->query['id'] == $item->id) {

            $this->menuItemMatchProduct = true;

            // Загрузка макета из активного запроса (в случае, если это альтернативный пункт меню)
            if (isset($active->query['layout'])) {
                $this->setLayout($active->query['layout']);
            } elseif ($layout = $item->params->get('product_layout')) {
                // Альтернативный макет товара
                $this->setLayout($layout);
            }

            // $item->params - параметры товара, $temp - параметры пункта меню.
            // Объединим их так, чтобы параметры пункта меню имели приоритет
            $item->params->merge($temp);
        } else {
            // Активный пункт меню не связан с этим товаром, поэтому приоритет здесь имеют параметры товара.
            // Объединим параметры пункта меню с параметрами товара, чтобы параметры товара имели приоритет
            $temp->merge($item->params);
            $item->params = $temp;

            // Проверяем наличие альтернативных макетов (поскольку мы не находимся в пункте меню с одним товаром).
            // Макет пункта меню одного товара имеет приоритет над макетом alt для товара
            if ($layout = $item->params->get('product_layout')) {
                $this->setLayout($layout);
            }
        }

        // Проверьте доступ к товару (модель уже вычислила значения)
        if ($item->params->get('access-view') == false &&
            ($item->params->get('show_noauth', '0') == '0')) {

            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $app->setHeader('status', 403, true);

            return;
        }

        // Теги товара
        $item->tags = new TagsHelper();
        $item->tags->getItemTags('com_ishop.product', $this->item->id);

        // Связи с товарами на других языках (если включена мультиязычность)
        if (Associations::isEnabled() && $item->params->get('show_associations')) {
            $item->associations = AssociationHelper::displayAssociations($item->id);
        }

        // Чистим строки для вывода в HTML
        $this->pageclass_sfx = htmlspecialchars($this->item->params->get('pageclass_sfx', ''));

        $this->_prepareDocument();

        // Зафиксируем просмотр карточки товара
        // для списка просмотренных
        Factory::getApplication()
            ->bootComponent('com_ishop')
            ->getMVCFactory()
            ->createModel('Viewed', 'Site')
            ->add($this->item->id);

        parent::display($tpl);
    }

    /**
     * Подготовка документа к выводу
     * @return void
     * @throws \Exception
     * @since 1.0.0
     */
    protected function _prepareDocument()
    {
        $app     = Factory::getApplication();
        $pathway = $app->getPathway();

        /**
         * Поскольку приложение устанавливает заголовок страницы по умолчанию,
         * нам нужно получить его из самого пункта меню
         */
        $menu = $app->getMenu()->getActive();

        if ($menu) {
            $this->params->def('page_heading', $this->params->get('page_title', $menu->title));
        } else {
            $this->params->def('page_heading', Text::_('COM_ISHOP_PRODUCTS'));
        }

        // Если пункт меню не связан с текущим товаром
        if (!$this->menuItemMatchProduct) {
            // Получение идентификатора категории из активного пункта меню
            if ($menu && $menu->component == 'com_ishop' && isset($menu->query['view'])
                && \in_array($menu->query['view'], ['categories', 'category'])) {
                $id = $menu->query['id'];
            } else {
                $id = 0;
            }

            $path     = [['title' => $this->item->title, 'link' => '']];
            $category = Factory::getApplication()->bootComponent('com_ishop')->getCategory()->get($this->item->catid);

            while ($category !== null && $category->id != $id && $category->id !== 'root') {
                $path[]   = ['title' => $category->title, 'link' => RouteHelper::getCategoryRoute($category->id, $category->language)];
                $category = $category->getParent();
            }

            $path = array_reverse($path);

            foreach ($path as $item) {
                $pathway->addItem($item['title'], $item['link']);
            }

        }

        // Если установлен заголовок title в карточке товара
        if ($this->item->metatitle) {
            $this->setDocumentTitle($this->item->metatitle);
        } elseif($this->item->fullname) {
            $this->setDocumentTitle($this->item->fullname);
        } else {
            $menuItemParams = $menu->getParams();
            $title          = $menuItemParams->get('page_title', $this->item->title);
            $this->setDocumentTitle($title);
        }

        // Если установлено описание description в карточке товара
        if ($this->item->metadesc) {
            $this->getDocument()->setDescription($this->item->metadesc);
        } elseif ($this->params->get('menu-meta_description')) {
            $this->getDocument()->setDescription($this->params->get('menu-meta_description'));
        }

        // Если установлены ключевые слова keywords в карточке товара
        if ($this->item->metakey){
            $this->getDocument()->setMetadata('keywords', $this->item->metakey);
        } elseif ($this->params->get('menu-meta_keywords')) {
            $this->getDocument()->setMetadata('keywords', $this->params->get('menu-meta_keywords'));
        }

        if ($this->params->get('robots')) {
            $this->getDocument()->setMetaData('robots', $this->params->get('robots'));
        }

        if ($app->get('MetaAuthor') == '1') {
            $author = $this->item->created_by_alias ?: $this->item->author;
            $this->getDocument()->setMetaData('author', $author);
        }

        $metadata = $this->item->metadata->toArray();
        foreach ($metadata as $k => $v) {
            if ($v) {
                $this->getDocument()->setMetaData($k, $v);
            }
        }

        if ($this->print) {
            $this->getDocument()->setMetaData('robots', 'noindex, nofollow');
        }
    }
}
