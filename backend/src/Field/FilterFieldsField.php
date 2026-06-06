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

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

/**
 * Поле формы для выбора характеристик SEO-страницы фильтра.
 *
 * Строит HTML-блок с контролами, соответствующими типам характеристик:
 * диапазон min/max для числовых, множественный select для списочных и switch
 * для булевых. Список характеристик ограничивается настройками выбранной
 * категории.
 *
 * @since 1.0.0
 */
class FilterFieldsField extends FormField
{
    /**
     * Тип поля Joomla Form API.
     *
     * @var string
     *
     * @since 1.0.0
     */
    protected $type = 'FilterFields';

    /**
     * Формирует HTML поля выбора характеристик.
     *
     * Если категория еще не выбрана или для нее нет характеристик, возвращает
     * информационный блок. Иначе строит набор контролов по типам характеристик.
     *
     * @return  string  HTML-разметка поля.
     *
     * @throws \Exception
     * @since 1.0.0
     */
    protected function getInput()
    {
        $value = $this->normalizeValue($this->value);
        $categoryId = (int) $this->form->getValue('category_id');

        if ($categoryId <= 0) {
            $categoryId = Factory::getApplication()->getInput()->getInt('category_id', 0);
        }
        $fields = $this->getFields($categoryId);

        if ($categoryId <= 0) {
            return '<div class="ishop-filter-fields" data-filter-fields-container>' .
                '<div class="alert alert-info">' . Text::_('COM_ISHOP_FILTER_SELECT_CATEGORY_FIRST') . '</div>' .
                '</div>';
        }

        if (empty($fields)) {
            return '<div class="ishop-filter-fields" data-filter-fields-container>' .
                '<div class="alert alert-info">' . Text::_('COM_ISHOP_FILTER_NO_FIELDS') . '</div>' .
                '</div>';
        }

        $html = [];
        $html[] = '<div class="ishop-filter-fields" data-filter-fields-container>';

        foreach ($fields as $field) {
            $fieldId = (int) $field->id;
            $selected = $value[$fieldId] ?? $value[(string) $fieldId] ?? null;
            $html[] = '<fieldset class="options-form mb-3" data-filter-field="' . $fieldId . '" data-filter-field-type="' . (int) $field->type . '">';
            $html[] = '<legend>' . htmlspecialchars($field->title, ENT_QUOTES, 'UTF-8') . '</legend>';

            if ((int) $field->type === 0) {
                $min = is_array($selected) ? (int) ($selected['min'] ?? 0) : 0;
                $max = is_array($selected) ? (int) ($selected['max'] ?? 0) : 0;
                $html[] = '<div class="row">';
                $html[] = '<div class="col-6">';
                $html[] = '<label class="form-label" for="' . $this->id . '_' . $fieldId . '_min">' . Text::_('COM_ISHOP_FILTER_MIN_VALUE') . '</label>';
                $html[] = '<input type="number" min="0" class="form-control" id="' . $this->id . '_' . $fieldId . '_min" name="' . $this->name . '[' . $fieldId . '][min]" value="' . $min . '">';
                $html[] = '</div>';
                $html[] = '<div class="col-6">';
                $html[] = '<label class="form-label" for="' . $this->id . '_' . $fieldId . '_max">' . Text::_('COM_ISHOP_FILTER_MAX_VALUE') . '</label>';
                $html[] = '<input type="number" min="0" class="form-control" id="' . $this->id . '_' . $fieldId . '_max" name="' . $this->name . '[' . $fieldId . '][max]" value="' . $max . '">';
                $html[] = '</div>';
                $html[] = '</div>';
            } elseif ((int) $field->type === 1) {
                $selectedIds = is_array($selected) ? array_map('intval', $selected) : [];
                $html[] = '<select multiple class="form-select advancedSelect" name="' . $this->name . '[' . $fieldId . '][]">';
                foreach ($this->getValues($fieldId) as $option) {
                    $optionId = (int) $option->id;
                    $html[] = '<option value="' . $optionId . '"' . (in_array($optionId, $selectedIds, true) ? ' selected' : '') . '>' .
                        htmlspecialchars($option->value, ENT_QUOTES, 'UTF-8') . '</option>';
                }
                $html[] = '</select>';
            } elseif ((int) $field->type === 2) {
                $html[] = '<div class="form-check form-switch">';
                $html[] = '<input type="checkbox" class="form-check-input" id="' . $this->id . '_' . $fieldId . '" name="' . $this->name . '[' . $fieldId . ']" value="1"' . ((int) $selected > 0 ? ' checked' : '') . '>';
                $html[] = '<label class="form-check-label" for="' . $this->id . '_' . $fieldId . '">' . Text::_('JYES') . '</label>';
                $html[] = '</div>';
            }

            $html[] = '</fieldset>';
        }

        $html[] = '</div>';

        return implode("\n", $html);
    }

    /**
     * Приводит сохраненное значение поля к массиву.
     *
     * Поле может получить данные как JSON-строку из таблицы, так и массив из
     * POST-данных формы.
     *
     * @param   mixed  $value  Исходное значение поля.
     *
     * @return  array
     *
     * @since 1.0.0
     */
    private function normalizeValue(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($value) ? $value : [];
    }

    /**
     * Загружает опубликованные характеристики выбранной категории.
     *
     * @param   int  $categoryId  Идентификатор категории товаров.
     *
     * @return  array  Список объектов характеристик.
     *
     * @since 1.0.0
     */
    private function getFields(int $categoryId): array
    {
        $fieldIds = $this->getCategoryFieldIds($categoryId);

        if (empty($fieldIds)) {
            return [];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('title'),
                $db->quoteName('type'),
                $db->quoteName('ordering'),
            ])
            ->from($db->quoteName('#__ishop_fields'))
            ->where($db->quoteName('state') . ' = 1')
            ->whereIn($db->quoteName('id'), $fieldIds)
            ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('title') . ' ASC');

        return (array) $db->setQuery($query)->loadObjectList();
    }

    /**
     * Получает ID характеристик, разрешенных в настройках категории.
     *
     * @param   int  $categoryId  Идентификатор категории товаров.
     *
     * @return  array  Уникальный список ID характеристик.
     *
     * @since 1.0.0
     */
    private function getCategoryFieldIds(int $categoryId): array
    {
        if ($categoryId <= 0) {
            return [];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('params'))
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('id') . ' = :id')
            ->where($db->quoteName('extension') . ' = ' . $db->quote('com_ishop'))
            ->bind(':id', $categoryId, ParameterType::INTEGER);

        $params = new Registry((string) $db->setQuery($query)->loadResult());
        $fieldIds = array_values(array_filter(array_map('intval', (array) $params->get('filter_fields', []))));

        return array_values(array_unique($fieldIds));
    }

    /**
     * Загружает значения списочной характеристики для select-контрола.
     *
     * @param   int  $fieldId  Идентификатор характеристики.
     *
     * @return  array  Список объектов значений.
     *
     * @since 1.0.0
     */
    private function getValues(int $fieldId): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('value'),
                $db->quoteName('ordering'),
            ])
            ->from($db->quoteName('#__ishop_values'))
            ->where($db->quoteName('field_id') . ' = :field_id')
            ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('value') . ' ASC')
            ->bind(':field_id', $fieldId, ParameterType::INTEGER);

        return (array) $db->setQuery($query)->loadObjectList();
    }
}
