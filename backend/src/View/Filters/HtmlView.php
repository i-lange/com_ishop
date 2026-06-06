<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Administrator\View\Filters;

defined('_JEXEC') or die;

use Ilange\Component\Ishop\Administrator\Extension\IshopComponent;
use Ilange\Component\Ishop\Administrator\Model\FiltersModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Toolbar\Button\DropdownButton;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Registry\Registry;

/**
 * Представление списка SEO-страниц фильтра.
 *
 * Готовит данные административного списка `#__ishop_filters`: элементы,
 * пагинацию, состояние, форму фильтров, active filters и панель инструментов.
 *
 * @since 1.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * Элементы текущей страницы списка.
     *
     * @var array
     *
     * @since 1.0.0
     */
    protected array $items;

    /**
     * Объект пагинации списка.
     *
     * @var Pagination
     *
     * @since 1.0.0
     */
    protected Pagination $pagination;

    /**
     * Состояние модели списка.
     *
     * @var Registry
     *
     * @since 1.0.0
     */
    protected Registry $state;

    /**
     * Форма фильтров административного списка.
     *
     * @var Form
     *
     * @since 1.0.0
     */
    public Form $filterForm;

    /**
     * Активные фильтры списка.
     *
     * @var array
     *
     * @since 1.0.0
     */
    public array $activeFilters;

    /**
     * Признак отображения пустого состояния списка.
     *
     * @var bool
     *
     * @since 1.0.0
     */
    public bool $isEmptyState = false;

    /**
     * Подготавливает и выводит административный список SEO-страниц фильтра.
     *
     * Загружает данные из модели, переключает layout пустого состояния и
     * скрывает языковой фильтр, если мультиязычность Joomla выключена.
     *
     * @param   string|null  $tpl  Имя шаблона представления.
     *
     * @return  void
     *
     * @throws \Exception
     * @since 1.0.0
     */
    public function display($tpl = null)
    {
        /** @var FiltersModel $model */
        $model = $this->getModel();
        $model->setUseExceptions(true);
        $this->items = $model->getItems();
        $this->pagination = $model->getPagination();
        $this->state = $model->getState();
        $this->filterForm = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();
        $this->multiLang = Multilanguage::isEnabled();

        if (empty($this->items) && $this->isEmptyState = $model->getIsEmptyState()) {
            $this->setLayout('emptystate');
        }

        if ($this->getLayout() !== 'modal') {
            if (!Multilanguage::isEnabled()) {
                unset($this->activeFilters['language']);
                $this->filterForm->removeField('language', 'filter');
            }

            $this->addToolbar();
        }

        parent::display($tpl);
    }

    /**
     * Формирует панель инструментов списка.
     *
     * Добавляет кнопки создания, изменения состояния, batch-операций,
     * удаления из корзины и перехода к настройкам компонента с учетом прав
     * текущего пользователя.
     *
     * @return  void
     *
     * @since 1.0.0
     */
    protected function addToolbar()
    {
        $canDo = ContentHelper::getActions('com_ishop', 'filter');
        $user = $this->getCurrentUser();
        $toolbar = $this->getDocument()->getToolbar();

        ToolbarHelper::title(Text::_('COM_ISHOP_FILTERS'), 'fa fa-filter');

        if ($canDo->get('core.create')) {
            $toolbar->addNew('filter.add');
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

            if ($canDo->get('core.edit.state')) {
                $childBar->publish('filters.publish')->listCheck(true);
                $childBar->unpublish('filters.unpublish')->listCheck(true);
                $childBar->archive('filters.archive')->listCheck(true);
                $childBar->checkin('filters.checkin');

                if ($this->state->get('filter.published') != IshopComponent::CONDITION_TRASHED) {
                    $childBar->trash('filters.trash')->listCheck(true);
                }
            }

            if ($user->authorise('core.create', 'com_ishop') &&
                $user->authorise('core.edit', 'com_ishop')) {
                $childBar
                    ->popupButton('batch', 'JTOOLBAR_BATCH')
                    ->selector('collapseModal')
                    ->listCheck(true);
            }

            if ($this->state->get('filter.published') == IshopComponent::CONDITION_TRASHED &&
                $canDo->get('core.delete')) {
                $toolbar->delete('filters.delete')
                    ->message('JGLOBAL_CONFIRM_DELETE')
                    ->listCheck(true);
            }
        }

        if ($user->authorise('core.admin', 'com_ishop') ||
            $user->authorise('core.options', 'com_ishop')) {
            $toolbar->preferences('com_ishop');
        }

        $toolbar->help('Filters');
    }
}
