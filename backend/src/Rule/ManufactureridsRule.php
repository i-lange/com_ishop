<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2026 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Administrator\Rule;

defined('_JEXEC') or die;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormRule;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

/**
 * Правило проверки массива идентификаторов производителей.
 * @since 1.0.0
 */
class ManufactureridsRule extends FormRule implements DatabaseAwareInterface
{
    use DatabaseAwareTrait;

    /**
     * Проверяет, что все переданные ID существуют в таблице производителей.
     *
     * @param   \SimpleXMLElement  $element  XML-описание поля
     * @param   mixed              $value    Значение поля
     * @param   string|null        $group    Группа поля
     * @param   Registry|null      $input    Все данные формы
     * @param   Form|null          $form     Объект формы
     *
     * @return  bool
     * @throws \Exception
     * @since   1.0.0
     */
    public function test(\SimpleXMLElement $element, $value, $group = null, ?Registry $input = null, ?Form $form = null)
    {
        if (is_string($value)) {
            $value = $value === '' ? [] : explode(',', $value);
        } elseif (!is_array($value)) {
            $value = $value ? [$value] : [];
        }

        $ids = ArrayHelper::toInteger($value);
        $ids = array_filter($ids, static fn($id) => $id > 0);
        $ids = array_values(array_unique($ids));

        if (empty($ids)) {
            return true;
        }

        $db    = $this->getDatabase();
        $query = $db->createQuery()
            ->select('COUNT(*)')
            ->from($db->quoteName('#__ishop_manufacturers'))
            ->whereIn($db->quoteName('id'), $ids, ParameterType::INTEGER);
        $db->setQuery($query);

        return (int) $db->loadResult() === count($ids);
    }
}
