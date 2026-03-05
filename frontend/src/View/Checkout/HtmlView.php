<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Site\View\Checkout;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Language\Text;

/**
 * HTML представление оформления заказа
 * @since 1.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * Состояние модели элемента
     * @var \Joomla\Registry\Registry
     * @since 1.0.0
     */
    protected $state;

    /**
     * Объект заказа
     * @var object
     * @since 1.0.0
     */
    public $checkout;

    /**
     * Объект текущей зоны доставки
     * @var object
     * @since 1.0.0
     */
    public $active_zone;

    /**
     * Список способов доставки
     * @var array
     * @since 1.0.0
     */
    public $deliveries;

    /**
     * Список способов оплаты
     * @var array
     * @since 1.0.0
     */
    public $payments;

    /**
     * Выполнение и отображение шаблона
     * @param string $tpl Имя файла шаблона для | автоматический поиск
     * @return void
     * @throws \Exception
     * @since 1.0.0
     */
    public function display($tpl = null)
    {
        $this->state        = $this->get('State');
        $this->params       = $this->state->get('params');
        $this->checkout		= $this->get('Checkout');
        $this->active_zone  = $this->get('Zone');
        $this->deliveries   = $this->get('Deliveries');
        $this->payments     = $this->get('Payments');
        $this->warehouses   = $this->get('Warehouses');

        // Проверяем, есть ли ошибки
        if (count($errors = $this->get('Errors'))) {
            throw new \Exception(implode("\n", $errors));
        }       
        
        $this->_prepareDocument();

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
        $app = Factory::getApplication();
        $active = $app->getMenu()->getActive();
        $doc = $this->getDocument();

        if ($active) {
            $menu_title   = $this->escape($active->title);
            $title        = $this->params->get('page_title', $active->title);
            $this->Itemid = $active->id;
        } else {
            $menu_title   = $title = Text::_('COM_ISHOP_CART');
        }

        $this->menu_title = $menu_title;

        // Page heading
        if ($this->params->def('show_page_heading')) {
            if (!$this->params->def('page_heading')) {
                $this->menu_title = null;
            }

            $this->page_heading = $this->escape($this->params->def('page_heading', $menu_title));
        } else {
            $this->page_heading = null;
        }

        $this->assigns['heading'] = $this->page_heading;
        $this->assigns['title']   = $this->menu_title;

        if ($app->get('sitename_pagetitles', 0) == 1) {
            $title = Text::sprintf('JPAGETITLE', $app->get('sitename'), $title);
        } elseif ($app->get('sitename_pagetitles', 0) == 2) {
            $title = Text::sprintf('JPAGETITLE', $title, $app->get('sitename'));
        }

        $doc->setTitle($title);
        if ($this->params->get('menu-meta_description')) {
            $doc->setDescription($this->params->get('menu-meta_description'));
        }
        if ($this->params->get('menu-meta_keywords')) {
            $doc->setMetadata('keywords', $this->params->get('menu-meta_keywords'));
        }
        if ($this->params->get('robots')) {
            $doc->setMetadata('robots', $this->params->get('robots'));
        }
    }
}
