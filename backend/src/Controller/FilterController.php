<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

/**
 * Контроллер формы SEO-страницы фильтра.
 *
 * Отвечает за операции с одной записью `#__ishop_filters` в административной
 * части и предоставляет AJAX-метод для подгрузки характеристик выбранной
 * категории в форме редактирования.
 *
 * @since 1.0.0
 */
class FilterController extends FormController
{
    /**
     * Выполняет пакетные операции над записями SEO-страниц фильтра.
     *
     * Метод явно получает модель `Filter`, настраивает возврат к списку
     * записей и затем передает выполнение базовому контроллеру Joomla.
     *
     * @param   object|null  $model  Модель, переданная базовым контроллером.
     *
     * @return  bool
     *
     * @throws \Exception
     * @since 1.0.0
     */
    public function batch($model = null)
    {
        $this->checkToken();
        $model = $this->getModel('Filter', 'Administrator', []);
        $this->setRedirect(Route::_('index.php?option=com_ishop&view=filters' . $this->getRedirectToListAppend(), false));

        return parent::batch($model);
    }

    /**
     * Возвращает характеристики, доступные для выбранной категории.
     *
     * Используется административной формой при смене категории, чтобы
     * перестроить блок выбора характеристик без перезагрузки страницы.
     *
     * @return void
     *
     * @throws \Exception
     * @since 1.0.0
     */
    public function categoryFields(): void
    {
        $this->checkToken('get');

        $app = Factory::getApplication();
        $user = $app->getIdentity();

        if (!$user->authorise('core.manage', 'com_ishop')) {
            echo new JsonResponse(null, Text::_('JERROR_ALERTNOAUTHOR'), true);
            $app->close();
        }

        $categoryId = $this->input->getInt('category_id', 0);
        $fields = $categoryId > 0 ? $this->getCategoryFields($categoryId) : [];

        echo new JsonResponse(['fields' => $fields]);
        $app->close();
    }

    /**
     * Загружает список опубликованных характеристик категории.
     *
     * Для списочных характеристик дополнительно подставляет доступные значения,
     * чтобы форма могла сразу отрисовать множественный выбор.
     *
     * @param   int  $categoryId  Идентификатор категории товаров.
     *
     * @return  array  Список объектов характеристик.
     *
     * @since 1.0.0
     */
    private function getCategoryFields(int $categoryId): array
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

        $fields = (array) $db->setQuery($query)->loadObjectList();

        foreach ($fields as $field) {
            $field->values = (int) $field->type === 1 ? $this->getFieldValues((int) $field->id) : [];
        }

        return $fields;
    }

    /**
     * Получает идентификаторы характеристик, включенных в настройках категории.
     *
     * Значения берутся из параметра `filter_fields` записи Joomla Categories
     * для расширения `com_ishop`.
     *
     * @param   int  $categoryId  Идентификатор категории товаров.
     *
     * @return  array  Уникальный список ID характеристик.
     *
     * @since 1.0.0
     */
    private function getCategoryFieldIds(int $categoryId): array
    {
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
     * Загружает значения списочной характеристики.
     *
     * @param   int  $fieldId  Идентификатор характеристики.
     *
     * @return  array  Список объектов значений, отсортированный как в каталоге.
     *
     * @since 1.0.0
     */
    private function getFieldValues(int $fieldId): array
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
