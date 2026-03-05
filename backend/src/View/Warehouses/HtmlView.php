<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Administrator\View\Warehouses;

defined('_JEXEC') or die;

use Ilange\Component\Ishop\Administrator\Extension\IshopComponent;
use Ilange\Component\Ishop\Administrator\Model\WarehousesModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Associations;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Toolbar\Button\DropdownButton;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Registry\Registry;

/**
 * Класс отображения списка
 * @since 1.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * Массив записей
     * @var array
     * @since 1.0.0
     */
    protected array $items;

    /**
     * Постраничная навигация
     * @var Pagination
     * @since 1.0.0
     */
    protected Pagination $pagination;

    /**
     * Состояние модели
     * @var Registry
     * @since 1.0.0
     */
    protected Registry $state;

    /**
     * Объект формы для фильтров поиска
     * @var Form
     * @since 1.0.0
     */
    public Form $filterForm;

    /**
     * Активные фильтры поиска
     * @var array
     * @since 1.0.0
     */
    public array $activeFilters;

    /**
     * Является ли это представление пустым
     * @var bool
     * @since 1.0.0
     */
    public bool $isEmptyState = false;

    /**
     * Отображение шаблона вывода
     * @param string $tpl Template name
     * @return void
     * @throws \Exception
     * @since 1.0.0
     */
    public function display($tpl = null)
    {
        /** @var WarehousesModel $model */
        $model = $this->getModel();
        $model->setUseExceptions(true);
        $this->items = $model->getItems();
        $this->pagination = $model->getPagination();
        $this->state = $model->getState();
        $this->filterForm = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();
        $this->assoc			= Associations::isEnabled();
        $this->multiLang		= Multilanguage::isEnabled();
        $this->root				= Uri::root();

        if (empty($this->items) && $this->isEmptyState = $model->getIsEmptyState()) {
            $this->setLayout('emptystate');
        }

        // Нам не нужна панель инструментов в модальном окне
        if ($this->getLayout() !== 'modal') {
            $this->addToolbar();

            if (!Multilanguage::isEnabled()) {
                unset($this->activeFilters['language']);
                $this->filterForm->removeField('language', 'filter');
            }
        } elseif ($forcedLanguage = Factory::getApplication()->input->get('forcedLanguage', '', 'CMD')) {
            // Если язык установлен принудительно, то мы не можем позволить выбрать язык,
            // фильтр выбора языка переводим в скрытое поле.
            $languageXml = new \SimpleXMLElement(
                '<field name="language" type="hidden" default="' . $forcedLanguage . '" />'
            );
            $this->filterForm->setField($languageXml, 'filter');

            unset($this->activeFilters['language']);
        }

        parent::display($tpl);
    }

    /**
     * Добавляем заголовок страницы и панель инструментов
     * @return void
     * @throws \Exception
     * @since 1.0.0
     */
    protected function addToolbar()
    {
        $canDo	 = ContentHelper::getActions('com_ishop', 'delivery');
        $user    = $this->getCurrentUser();
        $toolbar = $this->getDocument()->getToolbar();

        // Заголовок страницы
        ToolbarHelper::title(Text::_('COM_ISHOP_WAREHOUSES'), 'fa fa-cubes');

        // Кнопка создания
        if ($canDo->get('core.create')) {
            $toolbar->addNew('warehouse.add');
        }

        if (!$this->isEmptyState) {
            /** @var DropdownButton $dropdown */
            $dropdown = $toolbar->dropdownButton('status-group')
                ->text('JTOOLBAR_CHANGE_STATUS')
                ->toggleSplit(false)
                ->icon('icon-ellipsis-h')
                ->buttonClass('btn btn-action')
                ->listCheck(true);

            $childBar = $dropdown->getChildToolbar();

            // Если разрешено управлять состоянием
            if ($canDo->get('core.edit.state')) {
                // Кнопка опубликовать
                $childBar->publish('warehouses.publish')->listCheck(true);
                // Кнопка снять с публикации
                $childBar->unpublish('warehouses.unpublish')->listCheck(true);
                // Кнопка переместить в архив
                $childBar->archive('warehouses.archive')->listCheck(true);
                // Кнопка разблокировать
                $childBar->checkin('warehouses.checkin');

                // Кнопка перемещения в корзину
                if ($this->state->get('filter.published') != IshopComponent::CONDITION_TRASHED) {
                    $childBar->trash('warehouses.trash')->listCheck(true);
                }
            }

            if ($user->authorise('core.create', 'com_ishop') &&
                $user->authorise('core.edit', 'com_ishop')) {
                // Кнопка вызова пакетной обработки
                $childBar
                    ->popupButton('batch', 'JTOOLBAR_BATCH')
                    ->selector('collapseModal')
                    ->listCheck(true);
            }

            // Кнопка удаления, если мы в корзине
            if ($this->state->get('filter.published') == IshopComponent::CONDITION_TRASHED &&
                $canDo->get('core.delete')) {
                $toolbar->delete('warehouses.delete')
                    ->message('JGLOBAL_CONFIRM_DELETE')
                    ->listCheck(true);
            }
        }

        // Кнопка ссылка на настройки компонента iShop
        if ($user->authorise('core.admin', 'com_ishop') ||
            $user->authorise('core.options', 'com_ishop')) {
            $toolbar->preferences('com_ishop');
        }

        // Кнопка справки
        $toolbar->help('Warehouses');
    }
}
