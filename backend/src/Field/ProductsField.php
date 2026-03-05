<?php
/**
 * @package        com_ishop
 * @author         Pavel Lange <pavel@ilange.ru>
 * @link           https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license        GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Administrator\Field;

defined('_JEXEC') or die;

use Ilange\Component\Ishop\Administrator\Helper\ProductHelper;
use Joomla\CMS\Form\Field\ListField;

/**
 * Класс поля Товары
 * @since 1.0.0
 */
class ProductsField extends ListField
{
    /**
     * Список связанных товаров с учетом контроля доступа
     *
     * @var    string
     * @since 1.0.0
     */
    protected $type = 'Products';

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
            'text'     => Text::_('COM_ISHOP_FIELD_PRODUCT'),
            'selected' => $this->value === 0,
            'checked'  => $this->value === 0,
        ];*/

        return array_merge(parent::getOptions(), $options, ProductHelper::productOptions($language));
    }

    /**
     * Метод прикрепления объекта формы к полю
     * @param \SimpleXMLElement  $element  Объект SimpleXMLElement представляющий тег `<field>` для объекта поля формы.
     * @param mixed              $value    Значение поля формы для проверки.
     * @param string             $group    Имя группы полей. Оно действует как массив-контейнер для поля.
     *                                     Например, если поле имеет значение name="foo", а имя группы равно "bar", то
     *                                     полное имя поля в конечном итоге будет "bar[foo]".
     * @return  bool  True в случае успеха
     * @since   1.0.0
     */
    public function setup(\SimpleXMLElement $element, $value, $group = null)
    {
        return parent::setup($element, $value, $group);
    }
}
