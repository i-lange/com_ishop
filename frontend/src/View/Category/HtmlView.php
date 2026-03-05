<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Site\View\Category;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\TagsHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\CategoryView;
use Ilange\Component\Ishop\Site\Helper\RouteHelper;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;

/**
 * HTML представление одиночной категории
 * @since 1.0.0
 */
class HtmlView extends CategoryView
{
    /**
     * @var string Имя расширения для категории
     * @since 1.0.0
     */
    protected $extension = 'com_ishop';

    /**
     * @var string Заголовок по умолчанию, используемый для заголовка страницы
     * @since 1.0.0
     */
    protected $defaultPageTitle = 'COM_ISHOP_CATEGORY';

    /**
     * @var string Имя представления, с которым будут связаны записи в списке
     * @since 1.0.0
     */
    protected $viewName = 'product';

    /**
     * Выполнение и отображение шаблона
     *
     * @param   string  $tpl  Имя файла шаблона | автоматический поиск
     *
     * @return void
     * @throws \Exception
     * @since 1.0.0
     */
    public function display($tpl = null)
    {
        parent::commonCategoryDisplay();

        $app = Factory::getApplication();
        $this->filter_object = $this->get('FilterObject');

        // Флаг указывает не добавлять limitstart=0 к URL адресу
        $this->pagination->hideEmptyLimitstart = true;

        // Поскольку приложение устанавливает заголовок страницы по умолчанию,
        // мы должны получить его из самого пункта меню
        $active = $app->getMenu()->getActive();
        if ($this->menuItemMatchCategory) {
            $this->params->def('page_heading', $this->params->get('page_title', $active->title));
            $title = $this->params->get('page_title', $active->title);
        } else {
            $this->params->def('page_heading', $this->category->title);
            $title = $this->category->title;
            $this->params->set('page_title', $title);
        }

        if (empty($title)) {
            $title = $this->category->title;
        }
        $this->setDocumentTitle($title);

        if ($this->category->metadesc) {
            $this->getDocument()->setDescription($this->category->metadesc);
        } elseif ($this->params->get('menu-meta_description')) {
            $this->getDocument()->setDescription($this->params->get('menu-meta_description'));
        }

        if ($this->params->get('robots')) {
            $this->getDocument()->setMetaData('robots', $this->params->get('robots'));
        }

        if (!is_object($this->category->metadata)) {
            $this->category->metadata = new Registry($this->category->metadata);
        }

        if (($app->get('MetaAuthor') == '1') && $this->category->get('author', '')) {
            $this->getDocument()->setMetaData('author', $this->category->get('author', ''));
        }

        $mdata = $this->category->metadata->toArray();

        foreach ($mdata as $k => $v) {
            if ($v) {
                $this->getDocument()->setMetaData($k, $v);
            }
        }

        parent::display($tpl);
    }

    /**
     * Подготовка документа к выводу
     * @return void
     * @throws \Exception
     * @since 1.0.0
     */
    protected function prepareDocument()
    {
        parent::prepareDocument();

        // Если активный пункт меню связан непосредственно с отображаемой категорией, дальнейшая обработка не требуется
        if ($this->menuItemMatchCategory) {
            return;
        }

        // Получение идентификатора категории из активного пункта меню
        $menu = $this->menu;
        if ($menu && $menu->component == 'com_ishop' && isset($menu->query['view']) &&
            in_array($menu->query['view'], ['categories', 'category'])) {
            $id = $menu->query['id'];
        } else {
            $id = 0;
        }

        $path = [['title' => $this->category->title, 'link' => '']];
        $category = $this->category->getParent();

        while ($category !== null && $category->id !== 'root' && $category->id != $id) {
            $path[] = [
                'title' => $category->title,
                'link' => RouteHelper::getCategoryRoute($category->id, $category->language)
            ];
            $category = $category->getParent();
        }

        $path = array_reverse($path);

        foreach ($path as $item) {
            $this->pathway->addItem($item['title'], $item['link']);
        }
    }
}
