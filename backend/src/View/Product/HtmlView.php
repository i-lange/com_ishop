<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Administrator\View\Product;

defined('_JEXEC') or die;

use Ilange\Component\Ishop\Site\Helper\RouteHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\FormView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Registry\Registry;
use SimpleXMLElement;

/**
 * Класс представления для товара
 * @since 1.0.0
 */
class HtmlView extends FormView
{
    /**
     * Установить true, если должно поддерживаться сохранение в меню
     * @var bool
     * @since 1.0.0
     */
    protected $supportSaveMenu = true;

    /**
     * Расширение для категорий
     * @var Registry
     * @since 1.0.0
     */
    protected $categorySection = 'com_ishop';

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

        $config['help_link']      = 'Product:_Edit';
        $config['toolbar_icon']   = 'pencil-alt product-add';

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

        $this->canDo = ContentHelper::getActions('com_ishop', 'product', $this->item->id);

        if ($this->item->id) {
            $url = RouteHelper::getProductRoute($this->item->id, $this->item->catid, $this->item->language);
            $this->previewLink = $url;
            $this->jooa11yLink = $url . '&jooa11y=1';
        }

        if ($this->getLayout() === 'modalreturn') {
            return;
        }

        $input          = Factory::getApplication()->getInput();
        $forcedLanguage = $input->get('forcedLanguage', '');

        // Если явно указан язык в модальном отображении
        if ($this->getLayout() === 'modal' && $forcedLanguage) {
            // Установить значение forcedLanguage и запретить изменение
            $this->form->setValue('language', null, $forcedLanguage);
            $this->form->setFieldAttribute('language', 'readonly', 'true');
            // Разрешаем выбирать только категории со всеми языками или с указанным языком
            $this->form->setFieldAttribute('catid', 'language', '*,' . $forcedLanguage);
            // Разрешаем выбирать только теги со всеми языками или с указанным языком
            $this->form->setFieldAttribute('tags', 'language', '*,' . $forcedLanguage);
        }

        // Добавление управляющих полей
        $this->form
            ->addControlField('task')
            ->addControlField('return', $input->getBase64('return', ''))
            ->addControlField('forcedLanguage', $forcedLanguage);

        if (isset($this->item->ishop_fields)) {
            foreach ($this->item->ishop_fields as $group) {
                foreach ($group['fields'] as $field) {
                    if ($field['type'] === 1) {
                        $values = explode('||', $field['values']);
                        $ids = explode('||', $field['values_id']);
                        $selected = '';
                        if (!empty($field['active']) && in_array($field['active'], $ids)) {
                            $selected = $field['active'];
                        }
                        $xml_field =
                            '<field name="ishop_field_' . $field['id'] . '" 
                                type="list" 
                                label="' . $field['title'] . '" 
                                validate="options" 
                                default="' . $selected . '">';
                        $xml_field .= '<option value="">COM_ISHOP_FIELD_VALUE_NONE</option>';
                        foreach ($ids as $key => $id) {
                            $xml_field .= '<option value="' . $id . '">' . $values[$key] . '</option>';
                        }
                        $xml_field .= '</field>';

                    } elseif ($field['type'] === 2) {
                        $selected = '';
                        if (isset($field['active']) && $field['active'] !== false) {
                            $selected = (int) $field['active'];
                        }
                        $xml_field =
                            '<field name="ishop_field_' . $field['id'] . '" 
                                    type="list" 
                                    label="' . $field['title'] . '" 
                                    default="' . $selected . '">
                                <option value="">COM_ISHOP_FIELD_VALUE_NONE</option>
                                <option value="0">JNO</option>
                                <option value="1">JYES</option>
                            </field>';

                    } else {
                        $unit = '';
                        if (!empty($field['unit'])) {
                            $unit = ', ' . $field['unit'];
                        }
                        $selected = '';
                        if (!empty($field['active'])) {
                            $selected = (float) $field['active'];
                        }
                        $xml_field =
                            '<field name="ishop_field_' . $field['id'] . '" 
                                type="text" 
                                label="' . $field['title'] . $unit . '" 
                                filter="float" 
                                default="' . $selected . '" />';
                    }

                    $xml_field = new SimpleXMLElement($xml_field);
                    $this->form->setField($xml_field);

                    $hint = '';
                    if (!empty($field['hint'])) {
                        $hint = $field['hint'];
                    }
                    $xml_field =
                        '<field name="ishop_field_hint_' . $field['id'] . '" 
                                type="text" 
                                default="' . $hint .'" 
                                label="' . Text::_('COM_ISHOP_FIELD_HINT') . '" />';
                    $xml_field = new SimpleXMLElement($xml_field);
                    $this->form->setField($xml_field);
                }
            }
        }
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

        $this->toolbarTitle = Text::_('COM_ISHOP_' . ($checkedOut ? 'VIEW' : ($isNew ? 'ADD' : 'EDIT')) . '_PRODUCT');

        parent::addToolbar();

        $toolbar = $this->getDocument()->getToolbar();

        if (!$isNew) {
            $toolbar
                ->standardButton('set_images', 'COM_ISHOP_EDIT_SET_IMAGES', 'product.set_images')
                ->icon('icon-link');

            if (!empty($this->item->parse_url)) {
                $toolbar
                    ->linkButton('parse_url', 'COM_ISHOP_MANUFACTURER_URL')
                    ->url($this->item->parse_url)
                    ->target('_blank')
                    ->icon('icon-eye');
            }

            if (!empty($this->item->onliner_url)) {
                $toolbar
                    ->linkButton('onliner_url', 'COM_ISHOP_ONLINER_URL')
                    ->url('https://catalog.onliner.by/' . ltrim($this->item->onliner_url, '/'))
                    ->target('_blank')
                    ->icon('icon-eye');
            }
        }
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
            Text::_('COM_ISHOP_' . ($checkedOut ? 'VIEW' : ($isNew ? 'ADD' : 'EDIT') . '_PRODUCT')),
            'pencil-alt article-add'
        );

        $canCreate = $isNew && (count($user->getAuthorisedCategories('com_ishop', 'core.create')) > 0);
        $canEdit   = $canDo->get('core.edit') || ($canDo->get('core.edit.own') && $this->item->created_by == $userId);

        // Для новых, проверяем разрешения на создание
        if ($canCreate || $canEdit) {
            $toolbar->apply('product.apply');
            $toolbar->save('product.save');
        }

        $toolbar->cancel('product.cancel');
    }
}
