<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Administrator\View\Filter;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\FormView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * Представление формы редактирования SEO-страницы фильтра.
 *
 * Настраивает административный экран одной записи `#__ishop_filters`, права
 * пользователя, служебные поля формы и панель инструментов для обычного и
 * modal-режима.
 *
 * @since 1.0.0
 */
class HtmlView extends FormView
{
    /**
     * Создает представление формы и задает параметры по умолчанию.
     *
     * @param   array  $config  Конфигурация представления Joomla.
     *
     * @since 1.0.0
     */
    public function __construct(array $config)
    {
        if (empty($config['option'])) {
            $config['option'] = 'com_ishop';
        }

        $config['help_link'] = 'Filter:_Edit';
        $config['toolbar_icon'] = 'pencil-alt filter-add';

        parent::__construct($config);
    }

    /**
     * Инициализирует данные формы перед выводом.
     *
     * Загружает ACL-действия для текущей записи и добавляет скрытые control
     * поля, необходимые для корректной отправки формы.
     *
     * @return  void
     *
     * @throws \Exception
     * @since 1.0.0
     */
    protected function initializeView()
    {
        parent::initializeView();

        $this->canDo = ContentHelper::getActions('com_ishop', 'filter', $this->item->id);

        $input = Factory::getApplication()->getInput();
        $this->form
            ->addControlField('task')
            ->addControlField('return', $input->getBase64('return', ''));
    }

    /**
     * Настраивает стандартную панель инструментов формы.
     *
     * Заголовок зависит от режима: создание, редактирование или просмотр
     * записи, заблокированной другим пользователем.
     *
     * @return  void
     *
     * @since 1.0.0
     */
    protected function addToolbar()
    {
        $user = $this->getCurrentUser();
        $userId = $user->id;
        $isNew = ($this->item->id == 0);
        $checkedOut = !(is_null($this->item->checked_out) || $this->item->checked_out == $userId);

        $this->toolbarTitle = Text::_('COM_ISHOP_' . ($checkedOut ? 'VIEW' : ($isNew ? 'ADD' : 'EDIT')) . '_FILTER');

        parent::addToolbar();
    }

    /**
     * Настраивает панель инструментов для modal-режима.
     *
     * В modal-режиме явно добавляет кнопки apply/save/cancel с учетом прав
     * создания и редактирования текущей записи.
     *
     * @return  void
     *
     * @since 1.0.0
     */
    protected function addModalToolbar()
    {
        $user = $this->getCurrentUser();
        $userId = $user->id;
        $isNew = ($this->item->id == 0);
        $checkedOut = !(is_null($this->item->checked_out) || $this->item->checked_out == $userId);
        $toolbar = $this->getDocument()->getToolbar();
        $canDo = $this->canDo;

        ToolbarHelper::title(
            Text::_('COM_ISHOP_' . ($checkedOut ? 'VIEW' : ($isNew ? 'ADD' : 'EDIT') . '_FILTER')),
            'pencil-alt filter-add'
        );

        $canCreate = $isNew && $user->authorise('core.create', 'com_ishop');
        $canEdit = $canDo->get('core.edit') || ($canDo->get('core.edit.own') && $this->item->created_by == $userId);

        if ($canCreate || $canEdit) {
            $toolbar->apply('filter.apply');
            $toolbar->save('filter.save');
        }

        $toolbar->cancel('filter.cancel');
    }
}
