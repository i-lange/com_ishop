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
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
use Joomla\Registry\Registry;
use Joomla\String\StringHelper;

/**
 * Модель заказа
 * @since 1.0.0
 */
class OrderModel extends AdminModel
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
    public $typeAlias = 'com_ishop.order';

    /**
     * Контекст, используемый для таблицы ассоциаций
     * @var    string
     * @since  1.0.0
     */
    protected $associationsContext = 'com_ishop.order';

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

        return $this->getCurrentUser()->authorise('core.delete', 'com_ishop.order.' . (int)$record->id);
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
            return $user->authorise('core.edit.state', 'com_ishop.order.' . (int)$record->id);
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
        // Устанавливаем дату публикации на сейчас
        if ($table->state == 1 && (int)$table->publish_up == 0) {
            $table->publish_up = Factory::getDate()->toSql();
        }

        // Устанавливаем дату завершения публикации null
        if ($table->state == 1 && intval($table->publish_down) == 0) {
            $table->publish_down = null;
        }

        // Упорядочим товары в категории, чтобы новый был первым
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
            'com_ishop.order',
            'order',
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
        // получаем идентификатор из состояния order.id,
        // при сохранении используем данные из input
        $id         = (int)$this->getState('order.id', $IdFromInput);
        $record->id = $id;

        // Изменяем форму с помощью элементов управления доступом
        if (!$this->canEditState($record)) {
            // Отключение отображение полей
            $form->setFieldAttribute('state', 'disabled', 'true');

            // Отключение полей при сохранении
            // Контроллер уже проверил, можно ли редактировать эту запись
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
        $data = Factory::getApplication()->getUserState('com_ishop.edit.order.data', []);

        if (empty($data)) {
            $data = $this->getItem();

            // Предварительный выбор некоторых фильтров
            // (Статус, Язык, Доступ и пр.) в форме редактирования,
            // если они ранее были выбраны в списке производителей
            if ($this->getState('order.id') == 0) {
                $filters = (array)$app->getUserState('com_ishop.order.filter');

                $data->state = $app->getInput()->getInt(
                    'state',
                    ((isset($filters['state']) && $filters['state'] !== '') ? $filters['state'] : null)
                );

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

        $this->preprocessData('com_ishop.order', $data);

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

        return parent::validate($form, $data, $group);
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

        if (isset($data['created_by_alias'])) {
            $data['created_by_alias'] = $filter->clean($data['created_by_alias'], 'TRIM');
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
            return true;
        }

        return false;
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
    public function getTable($name = 'Order', $prefix = '', $options = [])
    {
        return parent::getTable($name, $prefix, $options);
    }
}