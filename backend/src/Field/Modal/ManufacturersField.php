<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2026 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Administrator\Field\Modal;

defined('_JEXEC') or die;

/**
 * Класс модального поля для выбора нескольких производителей.
 * @since 1.0.0
 */
class ManufacturersField extends MultipleField
{
    /**
     * Тип поля формы
     * @var string
     * @since 1.0.0
     */
    protected $type = 'Modal_Manufacturers';

    /**
     * Представление списка элементов
     * @var string
     * @since 1.0.0
     */
    protected string $itemsView = 'manufacturers';

    /**
     * Таблица элементов
     * @var string
     * @since 1.0.0
     */
    protected string $itemsTable = '#__ishop_manufacturers';

    /**
     * Ключ заголовка модального окна
     * @var string
     * @since 1.0.0
     */
    protected string $selectTitleKey = 'COM_ISHOP_SELECT_MANUFACTURERS';

    /**
     * Ключ текста одного выбранного элемента
     * @var string
     * @since 1.0.0
     */
    protected string $selectedOneKey = 'COM_ISHOP_1_MANUFACTURER_SELECTED';

    /**
     * Ключ текста нескольких выбранных элементов
     * @var string
     * @since 1.0.0
     */
    protected string $selectedManyKey = 'COM_ISHOP_N_MANUFACTURERS_SELECTED';

    /**
     * Ключ текста предупреждения о пустом выборе
     * @var string
     * @since 1.0.0
     */
    protected string $emptySelectionKey = 'COM_ISHOP_MODAL_ITEMS_SELECT_AT_LEAST_ONE';

    /**
     * CSS-класс обертки поля
     * @var string
     * @since 1.0.0
     */
    protected string $fieldClass = 'com-ishop-modal-manufacturers';
}
