<?php
/**
 * @package        com_ishop
 * @author         Pavel Lange <pavel@ilange.ru>
 * @link           https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license        GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Associations;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;
use Joomla\String\StringHelper;

/**
 * Модель характеристики
 * @since 1.0.0
 */
class FieldModel extends AdminModel
{
    /**
     * Префикс для языковых констант
     * @var string
     * @since 1.0.0
     */
    protected $text_prefix = 'COM_ISHOP';

    /**
     * Псевдоним типа для данного типа контента
     * @var string
     * @since 1.0.0
     */
    public $typeAlias = 'com_ishop.field';

    /**
     * Контекст, используемый для таблицы ассоциаций
     * @var    string
     * @since  1.0.0
     */
    protected $associationsContext = 'com_ishop.field';

    /**
     * Метод проверки возможности удаления
     *
     * @param   object  $record  Объект записи
     *
     * @return  bool  True, если можно удалить. По умолчанию соответствует разрешению, установленному в компоненте
     * @since   1.0.0
     */
    protected function canDelete($record)
    {
        if (empty($record->id) || ($record->state != -2)) {
            return false;
        }

        return $this->getCurrentUser()->authorise('core.delete', 'com_ishop.field.' . (int)$record->id);
    }

    /**
     * Метод проверки возможности изменения статуса
     *
     * @param   object  $record  Объект записи
     *
     * @return  bool  True, если можно менять. По умолчанию соответствует разрешению, установленному в компоненте
     * @since   @since  1.0.0
     */
    protected function canEditState($record)
    {
        $user = $this->getCurrentUser();

        // Проверка для существующего производителя
        if (!empty($record->id)) {
            return $user->authorise('core.edit.state', 'com_ishop.field.' . (int)$record->id);
        }

        // По умолчанию используются настройки компонента
        return parent::canEditState($record);
    }

    /**
     * Подготавливает и обрабатывает данные таблицы перед сохранением
     *
     * @param   Table  $table  Объект таблицы
     *
     * @return  void
     * @since   1.0.0
     */
    protected function prepareTable($table)
    {
        // Упорядочим, чтобы новый был первым
        if (empty($table->id)) {
            $table->reorder('state >= 0');
        }
    }

    /**
     * Метод получения формы редактирования
     *
     * @param   array  $data      Данные для формы
     * @param   bool   $loadData  True, если форма должна загружать свои собственные данные (по умолчанию), false - если нет
     *
     * @return Form|bool Объект формы при успехе, false при неудаче
     * @throws \Exception
     * @since 1.0.0
     */
    public function getForm($data = [], $loadData = true)
    {
        $form = $this->loadForm(
            'com_ishop.field',
            'field',
            ['control' => 'jform', 'load_data' => $loadData]
        );

        if (empty($form)) {
            return false;
        }

        $app = Factory::getApplication();

        // Объект используется для проверки разрешения на редактирование
        $record = new \stdClass();

        // Получаем ID производителя, для фронтенда используем a_id, а для бэкенда - id
        $IdFromInput = $app->isClient('site')
            ? $app->getInput()->getInt('a_id', 0)
            : $app->getInput()->getInt('id', 0);

        // При редактировании
        // получаем идентификатор из состояния field.id,
        // при сохранении используем данные из input
        $id         = (int)$this->getState('field.id', $IdFromInput);
        $record->id = $id;

        // Изменяем форму с помощью элементов управления доступом
        if (!$this->canEditState($record)) {
            // Отключение отображение полей
            $form->setFieldAttribute('ordering', 'disabled', 'true');
            $form->setFieldAttribute('state', 'disabled', 'true');

            // Отключение полей при сохранении
            // Контроллер уже проверил, можно ли редактировать эту запись
            $form->setFieldAttribute('ordering', 'filter', 'unset');
            $form->setFieldAttribute('state', 'filter', 'unset');
        }

        // Не разрешаем изменять пользователя created_by, если ему не разрешен доступ к com_users
        if (!$this->getCurrentUser()->authorise('core.manage', 'com_users')) {
            $form->setFieldAttribute('created_by', 'filter', 'unset');
        }

        return $form;
    }

    /**
     * Метод для получения данных, которые должны быть введены в форму
     *
     * @return mixed Данные для формы
     * @throws \Exception
     * @since 1.0.0
     */
    protected function loadFormData()
    {
        $app = Factory::getApplication();

        // Проверяем сессию на наличие ранее введенных данных формы.
        $data = Factory::getApplication()->getUserState('com_ishop.edit.field.data', []);

        if (empty($data)) {
            $data = $this->getItem();

            // Предварительный выбор некоторых фильтров
            // (Статус, Язык, Доступ и пр.) в форме редактирования,
            // если они ранее были выбраны в списке
            if ($this->getState('field.id') == 0) {
                $filters = (array)$app->getUserState('com_ishop.field.filter');

                $data->state = $app->getInput()->getInt(
                    'state',
                    ((isset($filters['state']) && $filters['state'] !== '') ? $filters['state'] : null)
                );

                if ($app->isClient('administrator')) {
                    $data->language = $app->getInput()->getString(
                        'language',
                        (!empty($filters['language']) ? $filters['language'] : null)
                    );
                }

                $data->access = $app->getInput()->getInt(
                    'access',
                    (!empty($filters['access']) ? $filters['access'] : $app->get('access'))
                );
            }
        }

        // Если в форме есть наборы полей params типа Registry, преобразуем в массив
        if (isset($data->params) && $data->params instanceof Registry) {
            $data->params = $data->params->toArray();
        }

        $this->preprocessData('com_ishop.field', $data);

        return $data;
    }

    /**
     * Метод получения записи
     *
     * @param   int  $pk  Идентификатор
     *
     * @return object|bool Object при успехе, false при неудаче
     * @throws \Exception
     * @since 1.0.0
     */
    public function getItem($pk = null)
    {
        $item = parent::getItem($pk);

        if (!$item) {
            return false;
        }

        // Преобразуем изображения в массив
        $registry     = new Registry($item->images);
        $item->images = $registry->toArray();
        $item->values = [];

        if (!empty($item->id)) {
            $db    = $this->getDatabase();
            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('id'),
                    $db->quoteName('value'),
                    $db->quoteName('alias'),
                    $db->quoteName('icon'),
                ])
                ->from($db->quoteName('#__ishop_values'))
                ->where($db->quoteName('field_id') . ' = :fieldId')
                ->bind(':fieldId', $item->id, ParameterType::INTEGER)
                ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('value') . ' ASC');

            $db->setQuery($query);
            $item->values = $db->loadAssocList();
        }

        // Загрузка связанных по языку производителей
        $assoc = Associations::isEnabled();
        if ($assoc) {
            $item->associations = [];

            if ($item->id != null) {
                $associations = Associations::getAssociations(
                    'com_ishop',
                    '#__ishop_fields',
                    'com_ishop.field',
                    $item->id,
                    'id',
                    'alias',
                    ''
                );

                foreach ($associations as $tag => $association) {
                    $item->associations[$tag] = $association->id;
                }
            }
        }

        return $item;
    }

    /**
     * Метод проверки данных формы
     *
     * @param   Form    $form   Форма для проверки
     * @param   array   $data   Данные для проверки
     * @param   string  $group  Имя группы полей для проверки
     *
     * @return  array|bool  Массив отфильтрованных данных или false
     * @throws \Exception
     * @since   1.0.0
     */
    public function validate($form, $data, $group = null)
    {
        // Не разрешать изменять пользователей, если не разрешен доступ к com_users
        if (Factory::getApplication()->isClient('administrator') &&
            !Factory::getApplication()->getIdentity()->authorise('core.manage', 'com_users')) {
            if (isset($data['created_by'])) {
                unset($data['created_by']);
            }

            if (isset($data['modified_by'])) {
                unset($data['modified_by']);
            }
        }

        $values = $data['values'] ?? [];

        if ($data = parent::validate($form, $data, $group)) {
            $data['values'] = $values;

            if (!$this->validateTypeChange($data)) {
                return false;
            }

            if ((int)($data['type'] ?? 0) === 1 && !empty($data['id'])) {
                $values = $this->normaliseValues($values);

                if (!$this->validateValues($values)) {
                    return false;
                }

                if (!$this->validateValueIds((int)$data['id'], $values) ||
                    !$this->validateValueDeletion((int)$data['id'], $values)) {
                    return false;
                }

                $data['values'] = $values;
            }

            return $data;
        }

        return false;
    }

    /**
     * Метод для сохранения данных формы
     *
     * @param   array  $data  Данные
     *
     * @return  bool    Удалось ли сохранить
     * @throws \Exception
     * @since   1.0.0
     */
    public function save($data)
    {
        $app    = Factory::getApplication();
        $input  = $app->getInput();
        $filter = InputFilter::getInstance();
        $values = $data['values'] ?? [];

        unset($data['values']);

        if (isset($data['metadata']['author'])) {
            $data['metadata']['author'] = $filter->clean($data['metadata']['author'], 'TRIM');
        }

        if (isset($data['created_by_alias'])) {
            $data['created_by_alias'] = $filter->clean($data['created_by_alias'], 'TRIM');
        }

        if (isset($data['images']) && is_array($data['images'])) {
            $data['images'] = (string) new Registry($data['images']);
        }

        // Изменение заголовка для сохранения как копии
        if ($input->get('task') == 'save2copy') {
            $origTable = $this->getTable();

            if ($app->isClient('site')) {
                $origTable->load($input->getInt('a_id'));
            } else {
                $origTable->load($input->getInt('id'));
            }

            if ($data['title'] == $origTable->title) {
                list($title, $alias) = $this->generateNewTitle(0, $data['alias'], $data['title']);
                $data['title']       = $title;
                $data['alias']       = $alias;
            } elseif ($data['alias'] == $origTable->alias) {
                $data['alias'] = '';
            }
        }

        // Автоматическая обработка псевдонимов
        if (in_array($input->get('task'), ['apply', 'save', 'save2new']) && (!isset($data['id']) || (int) $data['id'] == 0)) {
            if ($data['alias'] == null) {
                $data['alias'] = ApplicationHelper::stringURLSafe($data['title']);

                $table = $this->getTable();

                if ($table->load(['alias' => $data['alias']])) {
                    $msg = Text::_('COM_ISHOP_ALIAS_SAVE_WARNING');
                }

                list($title, $alias) = $this->generateNewTitle(0, $data['alias'], $data['title']);
                $data['alias']       = $alias;

                if (isset($msg)) {
                    $app->enqueueMessage($msg, 'warning');
                }
            }
        }

        if (parent::save($data)) {
            $fieldId = (int)$this->getState('field.id');

            if ((int)($data['type'] ?? 0) === 1 && $fieldId > 0 && !empty($data['id'])) {
                return $this->saveValues($fieldId, (array)$values, (string)($data['language'] ?? '*'));
            }

            return true;
        }

        return false;
    }

    /**
     * Проверяет, можно ли менять тип характеристики.
     *
     * @param   array  $data  Данные формы
     *
     * @return  bool
     * @throws \Exception
     * @since   1.0.0
     */
    private function validateTypeChange(array $data): bool
    {
        $fieldId = (int)($data['id'] ?? 0);

        if (!$fieldId) {
            return true;
        }

        $table = $this->getTable();

        if (!$table->load($fieldId) || (int)$table->type === (int)($data['type'] ?? 0)) {
            return true;
        }

        if ($this->hasValues($fieldId) || $this->hasProductLinks($fieldId)) {
            Factory::getApplication()->enqueueMessage(
                Text::_('COM_ISHOP_ERROR_FIELD_TYPE_CHANGE_HAS_VALUES'),
                'error'
            );

            return false;
        }

        return true;
    }

    /**
     * Нормализует строки значений характеристики.
     *
     * @param   array  $rows  Строки subform
     *
     * @return  array
     * @since   1.0.0
     */
    private function normaliseValues(array $rows): array
    {
        $values = [];
        $ordering = 1;

        foreach ($rows as $row) {
            if (is_object($row)) {
                $row = (array)$row;
            }

            if (!is_array($row)) {
                continue;
            }

            $value = trim((string)($row['value'] ?? ''));
            $alias = trim((string)($row['alias'] ?? ''));
            $icon  = trim((string)($row['icon'] ?? ''));

            if ($value === '' && $alias === '' && $icon === '' && empty($row['id'])) {
                continue;
            }

            $alias = $alias === '' ? $value : $alias;
            $alias = ApplicationHelper::stringURLSafe($alias);

            if (trim(str_replace('-', '', $alias)) === '') {
                $alias = Factory::getDate()->format('Y-m-d-H-i-s');
            }

            $values[] = [
                'id'       => (int)($row['id'] ?? 0),
                'value'    => $value,
                'alias'    => $alias,
                'icon'     => $icon,
                'ordering' => $ordering++,
            ];
        }

        return $values;
    }

    /**
     * Проверяет строки значений на обязательность и дубли.
     *
     * @param   array  $values  Нормализованные строки значений
     *
     * @return  bool
     * @since   1.0.0
     */
    private function validateValues(array $values): bool
    {
        $seenValues = [];
        $seenAliases = [];

        foreach ($values as $value) {
            if ($value['value'] === '') {
                Factory::getApplication()->enqueueMessage(
                    Text::_('COM_ISHOP_ERROR_FIELD_VALUE_EMPTY'),
                    'error'
                );

                return false;
            }

            $valueKey = mb_strtolower($value['value']);

            if (isset($seenValues[$valueKey])) {
                Factory::getApplication()->enqueueMessage(
                    Text::sprintf('COM_ISHOP_ERROR_FIELD_VALUE_DUPLICATE', $value['value']),
                    'error'
                );

                return false;
            }

            $seenValues[$valueKey] = true;

            if (isset($seenAliases[$value['alias']])) {
                Factory::getApplication()->enqueueMessage(
                    Text::sprintf('COM_ISHOP_ERROR_FIELD_VALUE_ALIAS_DUPLICATE', $value['alias']),
                    'error'
                );

                return false;
            }

            $seenAliases[$value['alias']] = true;
        }

        return true;
    }

    /**
     * Сохраняет значения list-характеристики.
     *
     * @param   int     $fieldId   ID характеристики
     * @param   array   $values    Строки значений
     * @param   string  $language  Язык характеристики
     *
     * @return  bool
     * @throws \Exception
     * @since   1.0.0
     */
    private function saveValues(int $fieldId, array $values, string $language): bool
    {
        $values = $this->normaliseValues($values);

        if (!$this->validateValues($values)) {
            return false;
        }

        $existingIds = $this->getValueIds($fieldId);
        $savedIds    = [];
        $language    = $language !== '' ? $language : '*';

        foreach ($values as $value) {
            $table = $this
                ->bootComponent('com_ishop')
                ->getMVCFactory()
                ->createTable('Value', 'Administrator', ['dbo' => $this->getDatabase()]);

            if ($value['id'] > 0 && !$table->load($value['id'])) {
                Factory::getApplication()->enqueueMessage(
                    Text::_('COM_ISHOP_ERROR_FIELD_VALUE_INVALID'),
                    'error'
                );

                return false;
            }

            if ($value['id'] > 0 && (int)$table->field_id !== $fieldId) {
                Factory::getApplication()->enqueueMessage(
                    Text::_('COM_ISHOP_ERROR_FIELD_VALUE_INVALID'),
                    'error'
                );

                return false;
            }

            $table->id       = $value['id'];
            $table->value    = $value['value'];
            $table->alias    = $value['alias'];
            $table->field_id = $fieldId;
            $table->icon     = $value['icon'];
            $table->ordering = $value['ordering'];
            $table->language = $language;

            if (!$table->store()) {
                return false;
            }

            $savedIds[] = (int)$table->id;
        }

        $deleteIds = array_values(array_diff($existingIds, $savedIds));

        if (empty($deleteIds)) {
            return true;
        }

        if ($this->hasProductLinks($fieldId, $deleteIds)) {
            Factory::getApplication()->enqueueMessage(
                Text::_('COM_ISHOP_ERROR_FIELD_VALUE_DELETE_IN_USE'),
                'error'
            );

            return false;
        }

        $valueModel = $this
            ->bootComponent('com_ishop')
            ->getMVCFactory()
            ->createModel('Value', 'Administrator', ['ignore_request' => true]);

        return $valueModel->delete($deleteIds);
    }

    /**
     * Проверяет, что существующие строки принадлежат текущей характеристике.
     *
     * @param   int    $fieldId  ID характеристики
     * @param   array  $values   Нормализованные строки значений
     *
     * @return  bool
     * @throws \Exception
     * @since   1.0.0
     */
    private function validateValueIds(int $fieldId, array $values): bool
    {
        $existingIds = $this->getValueIds($fieldId);

        foreach ($values as $value) {
            if ($value['id'] > 0 && !in_array($value['id'], $existingIds, true)) {
                Factory::getApplication()->enqueueMessage(
                    Text::_('COM_ISHOP_ERROR_FIELD_VALUE_INVALID'),
                    'error'
                );

                return false;
            }
        }

        return true;
    }

    /**
     * Проверяет, что из формы не удаляются используемые товарами значения.
     *
     * @param   int    $fieldId  ID характеристики
     * @param   array  $values   Нормализованные строки значений
     *
     * @return  bool
     * @throws \Exception
     * @since   1.0.0
     */
    private function validateValueDeletion(int $fieldId, array $values): bool
    {
        $existingIds = $this->getValueIds($fieldId);
        $postedIds   = array_values(array_filter(array_map('intval', array_column($values, 'id'))));
        $deleteIds   = array_values(array_diff($existingIds, $postedIds));

        if (!empty($deleteIds) && $this->hasProductLinks($fieldId, $deleteIds)) {
            Factory::getApplication()->enqueueMessage(
                Text::_('COM_ISHOP_ERROR_FIELD_VALUE_DELETE_IN_USE'),
                'error'
            );

            return false;
        }

        return true;
    }

    /**
     * Возвращает ID значений характеристики.
     *
     * @param   int  $fieldId  ID характеристики
     *
     * @return  array
     * @throws \Exception
     * @since   1.0.0
     */
    private function getValueIds(int $fieldId): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__ishop_values'))
            ->where($db->quoteName('field_id') . ' = :fieldId')
            ->bind(':fieldId', $fieldId, ParameterType::INTEGER);

        $db->setQuery($query);

        return array_map('intval', $db->loadColumn());
    }

    /**
     * Проверяет наличие значений у характеристики.
     *
     * @param   int  $fieldId  ID характеристики
     *
     * @return  bool
     * @throws \Exception
     * @since   1.0.0
     */
    private function hasValues(int $fieldId): bool
    {
        return !empty($this->getValueIds($fieldId));
    }

    /**
     * Проверяет использование характеристики или конкретных значений в товарах.
     *
     * @param   int    $fieldId   ID характеристики
     * @param   array  $valueIds  ID значений
     *
     * @return  bool
     * @throws \Exception
     * @since   1.0.0
     */
    private function hasProductLinks(int $fieldId, array $valueIds = []): bool
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__ishop_fields_map'))
            ->where($db->quoteName('field_id') . ' = :fieldId')
            ->bind(':fieldId', $fieldId, ParameterType::INTEGER);

        if (!empty($valueIds)) {
            $query->whereIn($db->quoteName('value'), $valueIds);
        }

        $db->setQuery($query);

        return (int)$db->loadResult() > 0;
    }


    /**
     * Переопределяем метод создания новых заголовков
     *
     * @param   int     $categoryId  Идентификатор категории (0)
     * @param   string  $alias       Псевдоним
     * @param   string  $title       Заголовок
     *
     * @return  array  Массив содержит новый заголовок и псевдоним
     *
     * @throws \Exception
     * @since   1.0.0
     */
    protected function generateNewTitle($categoryId, $alias, $title)
    {
        $table      = $this->getTable();
        $aliasField = $table->getColumnAlias('alias');
        $titleField = $table->getColumnAlias('title');

        while ($table->load([$aliasField => $alias])) {
            if ($title === $table->$titleField) {
                $title = StringHelper::increment($title);
            }

            $alias = StringHelper::increment($alias, 'dash');
        }

        return [$title, $alias];
    }

    /**
     * Метод удаляет одну или несколько характеристик
     *
     * @param   array  &$pks  Массив идентификаторов для удаления
     *
     * @return  bool  True если успешно
     *
     * @throws \Exception
     * @since   1.0.0
     */
    public function delete(&$pks)
    {
        // Перед удалением характеристики,
        // нужно удалить ее значения и все связи
        $db = $this->getDatabase();

        // Получим все связи из #__ishop_fields_map с данными характеристиками
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__ishop_fields_map'))
            ->whereIn($db->quoteName('field_id'), $pks);
        $db->setQuery($query);
        $map = $db->loadColumn();

        // Удаление связей произведем с помощью модели карты характеристик
        if (!empty($map)) {
            $mapModel = $this
                ->bootComponent('com_ishop')
                ->getMVCFactory()
                ->createModel('Map', 'Administrator', ['ignore_request' => true]);
            $ok = $mapModel->delete($map);

            if (!$ok) {
                return false;
            }
        }
        unset($map);


        // Получим все значения характеристик
        // из #__ishop_values, для последующего их удаления
        $query
            ->clear()
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__ishop_values'))
            ->whereIn($db->quoteName('field_id'), $pks);
        $db->setQuery($query);
        $values = $db->loadColumn();

        // Удаление значений произведем с помощью модели значений характеристик
        if (!empty($values)) {
            $mapModel = $this
                ->bootComponent('com_ishop')
                ->getMVCFactory()
                ->createModel('Value', 'Administrator', ['ignore_request' => true]);
            $ok = $mapModel->delete($values);

            if (!$ok) {
                return false;
            }
        }
        unset($values);

        // Удаление непосредственно списка характеристик
        $ok = parent::delete($pks);

        if (!$ok) {
            return false;
        }

        return true;
    }

    /**
     * Возвращает ссылку на объект Table, всегда создавая его
     *
     * @param   string  $name     Тип таблицы
     * @param   string  $prefix   Префикс для имени класса таблицы, необязательно
     * @param   array   $options  Массив параметров для модели, необязательно
     *
     * @return Table объект базы данных
     * @throws \Exception
     * @since 1.0.0
     */
    public function getTable($name = 'Field', $prefix = '', $options = [])
    {
        return parent::getTable($name, $prefix, $options);
    }
}
