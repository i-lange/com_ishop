<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Administrator\Field;

defined('_JEXEC') or die;

use Joomla\CMS\Form\Field\ListField;
use Ilange\Component\Ishop\Administrator\Helper\ProductHelper;

/**
 * Класс поля Префикс
 * @since 1.0.0
 */
class PrefixesField extends ListField
{
    /**
     * Тип поля формы
     * @var		string
     * @since 1.0.0
     */
    protected $type = 'Prefixes';

    /**
     * Шаблон вывода
     * @var		string
     * @since 1.0.0
     */
    protected $layout = 'joomla.form.field.list-fancy-select';

    /**
     * Метод получения списка значений
     *
     * @return  array  массив select.option
     * @since 1.0.0
     */
    protected function getOptions()
    {
        $language = $this->form->getValue('language');

        $options = [];
        /*$options[] = (object) [
            'value'    => '',
            'text'     => Text::_('COM_ISHOP_FIELD_PREFIX'),
            'selected' => $this->value === 0,
            'checked'  => $this->value === 0,
        ];*/

        return array_merge(parent::getOptions(), $options, ProductHelper::prefixesOptions($language));
    }
}
