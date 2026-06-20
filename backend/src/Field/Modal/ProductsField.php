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
 * Класс модального поля для выбора нескольких товаров.
 * @since 1.0.0
 */
class ProductsField extends MultipleField
{
    /**
     * Тип поля формы
     * @var string
     * @since 1.0.0
     */
    protected $type = 'Modal_Products';

    /**
     * Представление списка элементов
     * @var string
     * @since 1.0.0
     */
    protected string $itemsView = 'products';

    /**
     * Таблица элементов
     * @var string
     * @since 1.0.0
     */
    protected string $itemsTable = '#__ishop_products';

    /**
     * Ключ заголовка модального окна
     * @var string
     * @since 1.0.0
     */
    protected string $selectTitleKey = 'COM_ISHOP_SELECT_PRODUCTS';

    /**
     * Ключ текста одного выбранного элемента
     * @var string
     * @since 1.0.0
     */
    protected string $selectedOneKey = 'COM_ISHOP_1_PRODUCT_SELECTED';

    /**
     * Ключ текста нескольких выбранных элементов
     * @var string
     * @since 1.0.0
     */
    protected string $selectedManyKey = 'COM_ISHOP_N_PRODUCTS_SELECTED';

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
    protected string $fieldClass = 'com-ishop-modal-products';

    /**
     * Возвращает SQL-выражение заголовка товара.
     *
     * @return string
     * @since 1.0.0
     */
    protected function getTitleExpression(): string
    {
        $db = $this->getDatabase();

        return 'CONCAT('
            . 'COALESCE(' . $db->quoteName('prefixes.title') . ', ' . $db->quote('') . '), '
            . $db->quote(' ') . ', '
            . 'COALESCE(' . $db->quoteName('manufacturers.title') . ', ' . $db->quote('') . '), '
            . $db->quote(' ') . ', '
            . $db->quoteName('a.title') .
            ')';
    }

    /**
     * Добавляет связи для формирования заголовка товара.
     *
     * @param   \Joomla\Database\QueryInterface  $query  Запрос
     *
     * @return  void
     * @since   1.0.0
     */
    protected function extendSelectedItemsQuery($query): void
    {
        $db = $this->getDatabase();

        $query
            ->join(
                'LEFT',
                $db->quoteName('#__ishop_prefixes', 'prefixes'),
                $db->quoteName('prefixes.id') . ' = ' . $db->quoteName('a.prefix_id')
            )
            ->join(
                'LEFT',
                $db->quoteName('#__ishop_manufacturers', 'manufacturers'),
                $db->quoteName('manufacturers.id') . ' = ' . $db->quoteName('a.manufacturer_id')
            );
    }
}
