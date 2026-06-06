<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
use Joomla\Registry\Registry;

/**
 * Модель одной SEO-страницы фильтра.
 *
 * Обслуживает административную форму записи `#__ishop_filters`: загрузку
 * данных, проверку прав, подготовку JSON-полей характеристик и производителей,
 * а также передачу данных в table-класс для нормализации и сохранения.
 *
 * @since 1.0.0
 */
class FilterModel extends AdminModel
{
    /**
     * Префикс языковых ключей для сообщений модели.
     *
     * @var string
     *
     * @since 1.0.0
     */
    protected $text_prefix = 'COM_ISHOP';

    /**
     * Alias типа контента для ACL, history и стандартных интеграций Joomla.
     *
     * @var string
     *
     * @since 1.0.0
     */
    public $typeAlias = 'com_ishop.filter';

    /**
     * Проверяет право пользователя на удаление записи.
     *
     * Удаление разрешается только для записей в корзине, как принято в
     * административных моделях Joomla.
     *
     * @param   object  $record  Запись SEO-страницы фильтра.
     *
     * @return  bool
     *
     * @since 1.0.0
     */
    protected function canDelete($record)
    {
        if (empty($record->id) || ($record->state != -2)) {
            return false;
        }

        return $this->getCurrentUser()->authorise('core.delete', 'com_ishop.filter.' . (int) $record->id);
    }

    /**
     * Проверяет право пользователя на изменение состояния записи.
     *
     * Для существующей записи проверяется ACL конкретного объекта, для новой
     * используется стандартная проверка родительской модели.
     *
     * @param   object  $record  Запись SEO-страницы фильтра.
     *
     * @return  bool
     *
     * @since 1.0.0
     */
    protected function canEditState($record)
    {
        $user = $this->getCurrentUser();

        if (!empty($record->id)) {
            return $user->authorise('core.edit.state', 'com_ishop.filter.' . (int) $record->id);
        }

        return parent::canEditState($record);
    }

    /**
     * Загружает форму редактирования SEO-страницы фильтра.
     *
     * Дополнительно отключает системные поля состояния и автора, если текущему
     * пользователю не хватает соответствующих прав.
     *
     * @param   array  $data      Данные для привязки к форме.
     * @param   bool   $loadData  Загружать ли сохраненные данные формы.
     *
     * @return  Form|false
     *
     * @throws \Exception
     * @since 1.0.0
     */
    public function getForm($data = [], $loadData = true)
    {
        $form = $this->loadForm(
            'com_ishop.filter',
            'filter',
            ['control' => 'jform', 'load_data' => $loadData]
        );

        if (empty($form)) {
            return false;
        }

        $app = Factory::getApplication();
        $record = new \stdClass();
        $id = (int) $this->getState('filter.id', $app->getInput()->getInt('id', 0));
        $record->id = $id;

        if (!$this->canEditState($record)) {
            $form->setFieldAttribute('ordering', 'disabled', 'true');
            $form->setFieldAttribute('state', 'disabled', 'true');
            $form->setFieldAttribute('ordering', 'filter', 'unset');
            $form->setFieldAttribute('state', 'filter', 'unset');
        }

        if (!$this->getCurrentUser()->authorise('core.manage', 'com_users')) {
            $form->setFieldAttribute('created_by', 'filter', 'unset');
        }

        return $form;
    }

    /**
     * Загружает данные для формы редактирования.
     *
     * Сначала использует данные из user state после неудачной отправки формы,
     * затем текущую запись из таблицы. Для новой записи подставляет значения
     * состояния и языка из фильтров списка.
     *
     * @return  object|array
     *
     * @throws \Exception
     * @since 1.0.0
     */
    protected function loadFormData()
    {
        $app = Factory::getApplication();
        $data = $app->getUserState('com_ishop.edit.filter.data', []);

        if (empty($data)) {
            $data = $this->getItem();

            if ($this->getState('filter.id') == 0) {
                $filters = (array) $app->getUserState('com_ishop.filter.filter');
                $data->state = $app->getInput()->getInt(
                    'state',
                    ((isset($filters['state']) && $filters['state'] !== '') ? $filters['state'] : 1)
                );
                $data->language = $app->getInput()->getString(
                    'language',
                    (!empty($filters['language']) ? $filters['language'] : '*')
                );
            }
        }

        if (isset($data->params) && $data->params instanceof Registry) {
            $data->params = $data->params->toArray();
        }

        $this->preprocessData('com_ishop.filter', $data);

        return $data;
    }

    /**
     * Возвращает запись SEO-страницы фильтра для формы.
     *
     * JSON-поля производителей и характеристик преобразуются в формат,
     * удобный для административных контролов.
     *
     * @param   int|null  $pk  Идентификатор записи.
     *
     * @return  object|false
     *
     * @since 1.0.0
     */
    public function getItem($pk = null)
    {
        $item = parent::getItem($pk);

        if (!$item) {
            return false;
        }

        $item->manufacturers = $this->decodeJsonArray($item->manufacturers ?? '');
        $item->ishop_fields = json_encode(
            $this->decodeJsonArray($item->ishop_fields ?? ''),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return $item;
    }

    /**
     * Валидирует данные формы перед сохранением.
     *
     * Если пользователь не имеет права управлять пользователями, системные поля
     * автора и редактора удаляются из входных данных.
     *
     * @param   Form         $form   Объект формы Joomla.
     * @param   array        $data   Входные данные формы.
     * @param   string|null  $group  Группа полей для валидации.
     *
     * @return  array|false
     *
     * @throws \Exception
     * @since 1.0.0
     */
    public function validate($form, $data, $group = null)
    {
        if (Factory::getApplication()->isClient('administrator') &&
            !Factory::getApplication()->getIdentity()->authorise('core.manage', 'com_users')) {
            unset($data['created_by'], $data['modified_by']);
        }

        return parent::validate($form, $data, $group);
    }

    /**
     * Возвращает table-класс SEO-страницы фильтра.
     *
     * @param   string  $name     Имя table-класса.
     * @param   string  $prefix   Префикс table-класса.
     * @param   array   $options  Параметры создания таблицы.
     *
     * @return  Table
     *
     * @throws \Exception
     * @since 1.0.0
     */
    public function getTable($name = 'Filter', $prefix = '', $options = [])
    {
        return parent::getTable($name, $prefix, $options);
    }

    /**
     * Декодирует JSON-массив из поля таблицы.
     *
     * Некорректное или пустое значение безопасно превращается в пустой массив.
     *
     * @param   string  $value  JSON-строка.
     *
     * @return  array
     *
     * @since 1.0.0
     */
    private function decodeJsonArray(string $value): array
    {
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
