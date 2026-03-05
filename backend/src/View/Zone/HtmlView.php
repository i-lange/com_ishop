<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Administrator\View\Zone;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\FormView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * Класс представления для зоны доставки
 * @since 1.0.0
 */
class HtmlView extends FormView
{
    /**
     * Конструктор представления
     * @param   array  $config  Ассоциативный массив параметров конфигурации
     * @since   1.0.0
     */
    public function __construct(array $config)
    {
        if (empty($config['option'])) {
            $config['option'] = 'com_ishop';
        }

        $config['help_link']      = 'Zone:_Edit';
        $config['toolbar_icon']   = 'pencil-alt zone-add';

        parent::__construct($config);
    }

    /**
     * Подготовка данных представления
     * @return  void
     * @throws \Exception
     * @since 1.0.0
     */
    protected function initializeView()
    {
        parent::initializeView();

        $this->canDo = ContentHelper::getActions('com_ishop', 'delivery', $this->item->id);

        if ($this->getLayout() === 'modalreturn') {
            return;
        }

        $input = Factory::getApplication()->getInput();
        // Добавление управляющих полей
        $this->form
            ->addControlField('task')
            ->addControlField('return', $input->getBase64('return', ''));
    }

    /**
     * Добавляем заголовок и панель инструментов
     * @return void
     * @throws \Exception
     * @since 1.0.0
     */
    protected function addToolbar()
    {
        if ($this->getLayout() === 'modal') {
            $this->addModalToolbar();

            return;
        }

        $user       = $this->getCurrentUser();
        $userId     = $user->id;
        $isNew      = ($this->item->id == 0);
        $checkedOut = !(is_null($this->item->checked_out) || $this->item->checked_out == $userId);

        $this->toolbarTitle = Text::_('COM_ISHOP_' . ($checkedOut ? 'VIEW' : ($isNew ? 'ADD' : 'EDIT')) . '_ZONE');

        parent::addToolbar();
    }

    /**
     * Добавляем панель инструментов в модальном окне
     * @return void
     * @throws \Exception
     * @since 1.0.0
     */
    protected function addModalToolbar()
    {
        $user       = $this->getCurrentUser();
        $userId     = $user->id;
        $isNew      = ($this->item->id == 0);
        $checkedOut = !(is_null($this->item->checked_out) || $this->item->checked_out == $userId);
        $toolbar    = $this->getDocument()->getToolbar();

        // Действия для новых и существующих записей
        $canDo = $this->canDo;

        ToolbarHelper::title(
            Text::_('COM_ISHOP_' . ($checkedOut ? 'VIEW' : ($isNew ? 'ADD' : 'EDIT') . '_ZONE')),
            'pencil-alt zone-add'
        );

        $canCreate = $isNew && (count($user->getAuthorisedCategories('com_ishop', 'core.create')) > 0);
        $canEdit   = $canDo->get('core.edit') || ($canDo->get('core.edit.own') && $this->item->created_by == $userId);

        // Для новых, проверяем разрешения на создание
        if ($canCreate || $canEdit) {
            $toolbar->apply('zone.apply');
            $toolbar->save('zone.save');
        }

        $toolbar->cancel('zone.cancel');
    }
}
