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
use Joomla\CMS\Event\AbstractEvent;
use Joomla\CMS\Event\Model\AfterSaveEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\Helper\TagsHelper;
use Joomla\CMS\Language\Associations;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Table\TableInterface;
use Joomla\Component\Categories\Administrator\Helper\CategoriesHelper;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

/**
 * Модель товара
 * @since 1.0.0
 */
class ProductModel extends AdminModel
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
    public $typeAlias = 'com_ishop.product';

    /**
     * Контекст, используемый для таблицы ассоциаций
     * @var    string
     * @since  1.0.0
     */
    protected $associationsContext = 'com_ishop.product';

    /**
     * Пакетные операции
     * @var array
     * @since  1.0.0
     */
    protected $batch_commands = [
        'assetgroup_id'   => 'batchAccess',
        'language_id'     => 'batchLanguage',
        'tag'             => 'batchTag',
        'manufacturer_id' => 'batchManufacturer',
        'supplier_id'     => 'batchSupplier',
        'prefix_id'       => 'batchPrefix',
    ];

    /**
     * Событие, которое должно произойти перед
     * изменением статуса «Избранный» у товара
     * @var    ?string
     * @since  1.0.0
     */
    protected ?string $event_before_change_featured = null;

    /**
     * Событие, которое должно произойти после
     * изменения статуса «Избранный» у товара
     * @var    ?string
     * @since  1.0.0
     */
    protected ?string $event_after_change_featured = null;

    /**
     * Конструктор модели
     *
     * @param   array  $config  Ассоциативный массив параметров конфигурации, необязательно
     *                          (name, state, dbo, table_path, ignore_request).
     * @param   ?MVCFactoryInterface   $factory
     * @param   ?FormFactoryInterface  $formFactory
     *
     * @throws  \Exception
     *@since   1.6
     */
    public function __construct($config = [], ?MVCFactoryInterface $factory = null, ?FormFactoryInterface $formFactory = null)
    {
        $config['events_map'] ??= [];

        $config['events_map'] = array_merge(
            ['featured' => 'ishop'],
            $config['events_map']
        );

        parent::__construct($config, $factory, $formFactory);

        // События изменения статуса «Избранный» у товара
        $this->event_before_change_featured = $config['event_before_change_featured'] ?? $this->event_before_change_featured;
        $this->event_before_change_featured ??= 'onContentBeforeChangeFeatured';
        $this->event_after_change_featured  = $config['event_after_change_featured'] ?? $this->event_after_change_featured;
        $this->event_after_change_featured  ??= 'onContentAfterChangeFeatured';
    }

    /**
     * Переопределение метода для выполнения очистки данных после пакетного копирования товара
     * @param TableInterface $table  Объект таблицы, содержащий только что созданный элемент
     * @param int $newId  Идентификатор нового элемента
     * @param int $oldId  Идентификатор исходного элемента
     * @return void
     * @throws \Exception
     * @since 1.0.0
     */
    protected function cleanupPostBatchCopy(TableInterface $table, $newId, $oldId)
    {
        // То DO: добавить копирование характеристик и прочее для пакетных операций с товарами
        Factory::getApplication()->getDispatcher()->dispatch(
            'onContentAfterSave',
            AbstractEvent::create(
                $this->event_before_change_featured,
                [
                    'eventClass' => AfterSaveEvent::class,
                    'subject'    => $this,
                    'extension'  => $this->typeAlias,
                ]
            )
        );
    }

    /**
     * Метод проверки возможности удаления
     *
     * @param   object  $record  Объект товара
     *
     * @return  bool  True, если можно удалить. По умолчанию соответствует разрешению, установленному в компоненте
     * @since   1.0.0
     */
    protected function canDelete($record)
    {
        if (empty($record->id) || ($record->state != -2)) {
            return false;
        }

        return $this->getCurrentUser()->authorise('core.delete', 'com_ishop.product.' . (int)$record->id);
    }

    /**
     * Метод проверки возможности изменения статуса
     *
     * @param   object  $record  Объект товара
     *
     * @return  bool  True, если можно менять. По умолчанию соответствует разрешению, установленному в компоненте
     * @since   @since  1.0.0
     */
    protected function canEditState($record)
    {
        $user = $this->getCurrentUser();

        // Проверка для существующего товара
        if (!empty($record->id)) {
            return $user->authorise('core.edit.state', 'com_ishop.product.' . (int)$record->id);
        }

        // При создании товара проверяем по категории
        if (!empty($record->catid)) {
            return $user->authorise('core.edit.state', 'com_ishop.category.' . (int)$record->catid);
        }

        // По умолчанию используются настройки компонента, если не известны ни товар, ни категория
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

        // ID связанных товаров
        if (!isset($table->related) || !is_array($table->related)) {
            $table->related = [];
        } else {
            // удалить пустые элементы массива
            $table->related = array_diff($table->related, [0, null]);
        }
        // преобразуем в строку
        $table->related = implode(',', $table->related);

        // ID похожих товаров
        if (!isset($table->similar) || !is_array($table->similar)) {
            $table->similar = [];
        } else {
            // удалить пустые элементы массива
            $table->similar = array_diff($table->similar, [0, null]);
        }
        // преобразуем в строку
        $table->similar = implode(',', $table->similar);

        // ID модификаций этого товара
        if (!isset($table->offers) || !is_array($table->offers)) {
            $table->offers = [];
        } else {
            // удалить пустые элементы массива
            $table->offers = array_diff($table->offers, [0, null]);
        }
        // преобразуем в строку
        $table->offers = implode(',', $table->offers);

        // ID сервисных центров
        if (!isset($table->services) || !is_array($table->services)) {
            $table->services = [];
        } else {
            // удалить пустые элементы массива
            $table->services = array_diff($table->services, [0, null]);
        }
        // преобразуем в строку
        $table->services = implode(',', $table->services);

        // ID импортеров
        if (!isset($table->importers) || !is_array($table->importers)) {
            $table->importers = [];
        } else {
            // удалить пустые элементы массива
            $table->importers = array_diff($table->importers, [0, null]);
        }
        // преобразуем в строку
        $table->importers = implode(',', $table->importers);

        // Ширина
        if ($table->width === '') {
            $table->width = 0;
        }

        // Высота
        if ($table->height === '') {
            $table->height = 0;
        }

        // Глубина
        if ($table->depth === '') {
            $table->depth = 0;
        }

        // Вес
        if ($table->weight === '') {
            $table->weight = 0;
        }

        // Ширина в упаковке
        if ($table->width_pkg === '') {
            $table->width_pkg = 0;
        }

        // Высота в упаковке
        if ($table->height_pkg === '') {
            $table->height_pkg = 0;
        }

        // Глубина в упаковке
        if ($table->depth_pkg === '') {
            $table->depth_pkg = 0;
        }

        // Вес в упаковке
        if ($table->weight_pkg === '') {
            $table->weight_pkg = 0;
        }

        // Упорядочим товары в категории, чтобы новый был первым
        if (empty($table->id)) {
            $table->reorder('catid = ' . (int) $table->catid . ' AND state >= 0');
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
            'com_ishop.product',
            'product',
            ['control' => 'jform', 'load_data' => $loadData]
        );

        if (empty($form)) {
            return false;
        }


        $app = Factory::getApplication();

        // Объект используется для проверки разрешения на редактирование товара
        $record = new \stdClass();

        // Получаем ID товара, для фронтенда используем a_id, а для бэкенда - id
        $idFromInput = $app->isClient('site')
            ? $app->getInput()->getInt('a_id', 0)
            : $app->getInput()->getInt('id', 0);

        // При редактировании товара
        // получаем идентификатор из состояния product.id,
        // при сохранении используем данные из input
        $id         = (int)$this->getState('product.id', $idFromInput);
        $record->id = $id;

        // Для новых товаров мы загружаем потенциальный статус и ассоциации
        if ($id == 0 && $formField = $form->getField('catid')) {
            $assignedCatids = $data['catid'] ?? $form->getValue('catid');

            $assignedCatids = is_array($assignedCatids)
                ? (int)reset($assignedCatids)
                : (int)$assignedCatids;

            // Пробуем получить категорию из поля категории
            if (empty($assignedCatids)) {
                $assignedCatids = $formField->getAttribute('default');

                if (!$assignedCatids) {
                    // Выбираем первую доступную категорию
                    $catOptions = $formField->options;

                    if ($catOptions && !empty($catOptions[0]->value)) {
                        $assignedCatids = (int)$catOptions[0]->value;
                    }
                }
            }

            // Перезагружаем форму при изменении категории
            //$form->setFieldAttribute('catid', 'refresh-enabled', true);
            //$form->setFieldAttribute('catid', 'refresh-cat-id', $assignedCatids);
            //$form->setFieldAttribute('catid', 'refresh-section', 'product');

            // ID категории, используемой для проверки разрешения на редактирование
            $record->catid = $assignedCatids;
        } else {
            // Получаем категорию, в которую добавляется товар
            if (!empty($data['catid'])) {
                $catId = (int)$data['catid'];
            } else {
                $catIds = $form->getValue('catid');

                $catId = is_array($catIds)
                    ? (int)reset($catIds)
                    : (int)$catIds;

                if (!$catId) {
                    $catId = (int)$form->getFieldAttribute('catid', 'default', 0);
                }
            }

            $record->catid = $catId;
        }

        // Изменяем форму с помощью элементов управления доступом
        if (!$this->canEditState($record)) {
            // Отключение отображение полей
            $form->setFieldAttribute('featured', 'disabled', 'true');
            $form->setFieldAttribute('ordering', 'disabled', 'true');
            $form->setFieldAttribute('publish_up', 'disabled', 'true');
            $form->setFieldAttribute('publish_down', 'disabled', 'true');
            $form->setFieldAttribute('state', 'disabled', 'true');

            // Отключение полей при сохранении
            // Контроллер уже проверил, можно ли редактировать этот товар
            $form->setFieldAttribute('featured', 'filter', 'unset');
            $form->setFieldAttribute('ordering', 'filter', 'unset');
            $form->setFieldAttribute('publish_up', 'filter', 'unset');
            $form->setFieldAttribute('publish_down', 'filter', 'unset');
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
        $data = Factory::getApplication()->getUserState('com_ishop.edit.product.data', []);

        if (empty($data)) {
            $data = $this->getItem();

            // Предварительный выбор некоторых фильтров
            // (Статус, Категория, Язык, Доступ и пр.) в форме редактирования,
            // если они ранее были выбраны в списке товаров
            if ($this->getState('product.id') == 0) {
                $filters = (array)$app->getUserState('com_ishop.products.filter');

                $data->state = $app->getInput()->getInt(
                    'state',
                    ((isset($filters['state']) && $filters['state'] !== '') ? $filters['state'] : null)
                );

                $data->catid = $app->getInput()->getInt(
                    'catid',
                    (!empty($filters['category_id']) ? $filters['category_id'] : null)
                );

                $data->manufacturer = $app->input->getInt(
                    'manufacturer',
                    (!empty($filters['manufacturer']) ? $filters['manufacturer'] : null)
                );

                $data->prefix = $app->input->getInt(
                    'prefix',
                    (!empty($filters['prefix']) ? $filters['prefix'] : null)
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

        $this->preprocessData('com_ishop.product', $data);

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

        // Преобразуем параметры в массив
        $registry      = new Registry($item->attribs);
        $item->attribs = $registry->toArray();

        // Преобразуем метаданные в массив
        $registry       = new Registry($item->metadata);
        $item->metadata = $registry->toArray();

        // Преобразуем изображения в массив
        $registry     = new Registry($item->images);
        $item->images = $registry->toArray();

        // Преобразуем комплектацию в массив
        $registry     = new Registry($item->equipment);
        $item->equipment = $registry->toArray();

        // Преобразуем список файлов в массив
        $registry     = new Registry($item->documents);
        $item->documents = $registry->toArray();

        // Преобразуем список видео в массив
        $registry     = new Registry($item->videos);
        $item->videos = $registry->toArray();

        // Преобразуем список связанных товаров
        if (!empty($item->related)) {
            $item->related = explode(',', $item->related);
        } else {
            $item->related = [];
        }

        // Преобразуем список похожих товаров
        if (!empty($item->similar)) {
            $item->similar = explode(',', $item->similar);
        } else {
            $item->similar = [];
        }

        // Преобразуем список модификаций товара
        if (!empty($item->offers)) {
            $item->offers = explode(',', $item->offers);
        } else {
            $item->offers = [];
        }

        // Преобразуем список сервисных центров
        if (!empty($item->services)) {
            $item->services = explode(',', $item->services);
        } else {
            $item->services = [];
        }

        // Преобразуем список импортеров
        if (!empty($item->importers)) {
            $item->importers = explode(',', $item->importers);
        } else {
            $item->importers = [];
        }

        // Загрузка связанных по языку товаров
        $assoc = Associations::isEnabled();
        if ($assoc) {
            $item->associations = [];

            if ($item->id != null) {
                $associations = Associations::getAssociations(
                    'com_ishop',
                    '#__ishop_products',
                    'com_ishop.product',
                    $item->id
                );

                foreach ($associations as $tag => $association) {
                    $item->associations[$tag] = $association->id;
                }
            }
        }

        if (!empty($item->id) && !empty($item->catid)) {
            $item->ishop_fields = self::getFieldList($item->catid);
        }

        if (empty($item->id)) {
            return $item;
        }

        $item->tags = new TagsHelper();
        $item->tags->getTagIds($item->id, 'com_ishop.product');

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

        if (empty($data['version'])) {
            $data['version'] = 1;
        }

        // Обработаем характеристики
        $fields = array_filter($data, function ($name) {
            return str_starts_with($name, 'ishop_field_');
        }, ARRAY_FILTER_USE_KEY);

        if ($data = parent::validate($form, $data, $group)) {
           return array_merge($data, $fields);
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

        if (isset($data['metadata']['author'])) {
            $data['metadata']['author'] = $filter->clean($data['metadata']['author'], 'TRIM');
        }

        if (isset($data['created_by_alias'])) {
            $data['created_by_alias'] = $filter->clean($data['created_by_alias'], 'TRIM');
        }

        if (isset($data['images']) && is_array($data['images'])) {
            $data['images'] = (string) new Registry($data['images']);
        }

        // Создавать категорию, если необходимо
        $createCategory = true;

        // Если нет catid, не нужно создавать
        if (is_null($data['catid'])) {
            $createCategory = false;
        }

        // Если указан ID категории, проверяем
        if (is_numeric($data['catid']) && $data['catid']) {
            $createCategory = !CategoriesHelper::validateCategoryId($data['catid'], 'com_ishop');
        }

        // Сохраняем новую категорию
        if ($createCategory && $this->canCreateCategory()) {
            $category = [
                'title'     => (str_starts_with($data['catid'], '#new#')) ? substr($data['catid'], 5) : $data['catid'],
                'parent_id' => 1,
                'extension' => 'com_ishop',
                'language'  => $data['language'],
                'published' => 1,
            ];

            /** @var \Joomla\Component\Categories\Administrator\Model\CategoryModel $categoryModel */
            $categoryModel = Factory::getApplication()->bootComponent('com_categories')
                ->getMVCFactory()->createModel('Category', 'Administrator', ['ignore_request' => true]);

            if (!$categoryModel->save($category)) {
                return false;
            }

            $data['catid'] = $categoryModel->getState('category.id');
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
                [$title, $alias] = $this->generateNewTitle($data['catid'], $data['alias'], $data['title']);
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

                if ($table->load(['alias' => $data['alias'], 'catid' => $data['catid']])) {
                    $msg = Text::_('COM_ISHOP_ALIAS_SAVE_WARNING');
                }

                [$title, $alias] = $this->generateNewTitle($data['catid'], $data['alias'], $data['title']);
                $data['title']       = $title;
                $data['alias']       = $alias;

                if (isset($msg)) {
                    $app->enqueueMessage($msg, 'warning');
                }
            }
        }

        if (parent::save($data)) {
            // Если успешно сохранили товар,
            // необходимо также сохранить характеристики товара
            return self::saveFieldList($data);
        }

        return false;
    }

    /**
     * Может ли пользователь создавать категории "на лету"?
     *
     * @return  bool
     * @since   1.0.0
     */
    private function canCreateCategory()
    {
        return $this->getCurrentUser()->authorise('core.create', 'com_ishop');
    }

    /**
     * Возвращает список характеристик товара
     *
     * @return  array  Массив характеристик
     * @throws \Exception
     * @since   1.0.0
     */
    private function getFieldList($catid)
    {
        // Результирующий массив характеристик, разбитый по группам
        $result = [];

        // Если не определен идентификатор товара
        // возвращаем пустой массив
        $pk = (int) $this->getState('product.id');
        if (!$pk) {
            return $result;
        }

        $db     = $this->getDatabase();
        $query  = $db->getQuery(true);
        $user  = $this->getCurrentUser();

        // Получаем список характеристик, разбитый по группам из
        // настроек категории
        $category = Factory::getApplication()
            ->bootComponent('com_categories')
            ->getMVCFactory()
            ->createTable('Category', 'Administrator');
        $category->load(['id' => $catid]);
        $category->params = new Registry($category->params);
        $fields = $category->params->get('fields_groups', []);
        unset($category);

        // Если не указаны характеристики по группам в настройках
        // категории - возвращаем пустой массив
        if (empty($fields)) {
            return $result;
        }

        $list = $group_list = [];
        // Перебираем все группы
        // и формируем список идентификаторов характеристик
        foreach ($fields as $property) {
            $group_list[] = (int) $property->group;
            // Проверяем, есть ли свойство field и является ли оно массивом
            if (isset($property->field) && is_array($property->field)) {
                // Объединяем текущий результат с массивом field
                $list = array_merge($list, $property->field);
            }
        }
        $list = array_unique($list);

        if (empty($list)) {
            return $result;
        }

        // Получим характеристики по группам, заданные для категории товара
        $query
            ->select([
                $db->quoteName('a.id'),
                $db->quoteName('a.title'),
                $db->quoteName('a.alias'),
                $db->quoteName('a.type'),
                $db->quoteName('a.unit'),
                ' CASE WHEN ' . $db->qn('a.type') .
                ' = 1 THEN GROUP_CONCAT(DISTINCT ' . $db->qn('values.value') .
                ' ORDER BY ' . $db->qn('values.ordering') . ' SEPARATOR ' . $db->q('||') . ')' .
                ' ELSE ' . $db->q('') .
                ' END AS ' . $db->qn('values'),
                ' CASE WHEN ' . $db->qn('a.type') .
                ' = 1 THEN GROUP_CONCAT(DISTINCT ' . $db->qn('values.id') .
                ' ORDER BY ' . $db->qn('values.ordering') . ' SEPARATOR ' . $db->q('||') . ')' .
                ' ELSE ' . $db->q('') .
                ' END AS ' . $db->qn('values_id'),
            ])
            ->from($db->quoteName('#__ishop_fields', 'a'))
            ->join('LEFT',
                $db->quoteName('#__languages', 'languages'),
                $db->quoteName('languages.lang_code') . ' = ' . $db->quoteName('a.language'))
            ->join('LEFT',
                $db->quoteName('#__viewlevels', 'levels'),
                $db->quoteName('levels.id') . ' = ' . $db->quoteName('a.access'))
            ->join('INNER',
                $db->quoteName('#__ishop_fields_map', 'fields_map'),
                $db->quoteName('fields_map.field_id') . ' = ' . $db->quoteName('a.id'))
            ->join('LEFT',
                $db->quoteName('#__ishop_values', 'values'),
                '(' .$db->quoteName('a.type') . ' = 1 AND ' .
                $db->quoteName('values.id') . ' = ' . $db->quoteName('fields_map.value') . ')');

        // Фильтр по списку характеристик для категории
        $query->whereIn($db->quoteName('a.id'), $list);
        unset($list);

        // Фильтр по уровню доступа
        if (!$user->authorise('core.admin')) {
            $groups = $user->getAuthorisedViewLevels();
            $query->whereIn($db->quoteName('a.access'), $groups);
        }

        // Фильтрация по состоянию публикации
        $published = 1;
        $query
            ->where($db->quoteName('a.state') . ' = :state')
            ->bind(':state', $published, ParameterType::INTEGER);

        // Фильтр по языку
        if (Multilanguage::isEnabled()) {
            $query->whereIn(
                $db->quoteName('a.language'),
                [Factory::getApplication()->getLanguage()->getTag(), '*'],
                ParameterType::STRING
            );
        }

        $query->order('a.ordering ASC');
        $query->group([
            $db->quoteName('a.id'),
            $db->quoteName('a.title'),
            $db->quoteName('a.type'),
            $db->quoteName('a.unit'),
        ]);
        $db->setQuery($query);
        $all = $db->loadAssocList();
        if (!$all) {
            return $result;
        }
        $all = array_combine(array_column($all, 'id'), $all);

        // Теперь нужно получить ранее установленные
        // значения характеристик текущего товара
        $query
            ->clear('select')
            ->select([
                $db->quoteName('a.id'),
                $db->quoteName('a.title'),
                $db->quoteName('a.alias'),
                $db->quoteName('a.type'),
                $db->quoteName('a.unit'),
                ' CASE WHEN ' . $db->qn('a.type') .
                ' = 1 THEN GROUP_CONCAT(DISTINCT ' . $db->qn('values.value') .
                ' ORDER BY ' . $db->qn('values.ordering') . ' SEPARATOR ' . $db->q('||') . ')' .
                ' ELSE ' . $db->qn('fields_map.value') .
                ' END AS ' . $db->qn('values'),
                ' CASE WHEN ' . $db->qn('a.type') .
                ' = 1 THEN GROUP_CONCAT(DISTINCT ' . $db->qn('values.id') .
                ' ORDER BY ' . $db->qn('values.ordering') . ' SEPARATOR ' . $db->q('||') . ')' .
                ' ELSE ' . $db->q('') .
                ' END AS ' . $db->qn('values_id'),
                $db->quoteName('fields_map.hint'),
            ])
            ->where($db->quoteName('fields_map.product_id') . ' = :id')
            ->bind(':id', $pk, ParameterType::INTEGER);
        $db->setQuery($query);
        $active = $db->loadAssocList();

        // Добавляем в список характеристик установленные значения
        foreach ($active as $field) {
            if (isset($field['values_id']) && $field['values_id'] !== '') {
                $all[$field['id']]['active'] = (int) $field['values_id'];
            } elseif (isset($field['values']) && $field['values'] !== '') {
                $all[$field['id']]['active'] = $field['values'];
            } else {
                $all[$field['id']]['active'] = false;
            }
            $all[$field['id']]['hint'] = $field['hint'];
        }

        $query
            ->clear()
            ->select([
                $db->quoteName('id'),
                $db->quoteName('title'),
                $db->quoteName('alias'),
            ])
            ->from($db->quoteName('#__ishop_groups'))
            ->whereIn($db->quoteName('id'), $group_list);
        $db->setQuery($query);
        $groups = $db->loadAssocList();
        $groups = array_combine(array_column($groups, 'id'), $groups);


        // Формируем конечный список характеристик разбитых по группам
        foreach ($fields as $group) {
            $result[$group->group] = $groups[$group->group];
            $result[$group->group]['fields'] = [];

            if (!isset($group->field) && !is_array($group->field)) {
                continue;
            }

            foreach ($group->field as $field) {
                if (empty($all[$field])) {
                    continue;
                }

                $result[$group->group]['fields'][$field] = $all[$field];
            }
        }

        return $result;
    }

    /**
     * Метод сохраняет характеристики товара
     *
     * @param   array  $data  Массив данных товара
     *
     * @return  bool  True, если удалось сохранить
     * @throws \Exception
     * @since   @since  1.0.0
     */
    private function saveFieldList(array $data): bool
    {
        $product_id = (int) $this->getState('product.id', false);

        if (!$product_id) {
            return false;
        }

        foreach ($data as $name => $value) {
            if (str_starts_with($name, 'ishop_field_hint_')) {
                continue;
            }
            if (str_starts_with($name, 'ishop_field_')) {
                // Извлекаем идентификатор характеристики
                $parts = explode('_', $name);
                $lastPart = end($parts);

                if (!is_numeric($lastPart)) {
                    continue;
                }

                $id = (int) $lastPart;


                // Зная идентификатор, нужно либо очистить запись о характеристике,
                // если поле пустое, либо записать новое значение.
                // Пробуем получить запись данной характеристики
                $table = Factory::getApplication()
                    ->bootComponent('com_ishop')
                    ->getMVCFactory()
                    ->createTable('Map', 'Administrator');

                if ($table->load(['product_id' => $product_id, 'field_id' => $id])) {
                    // Если сохраняемое значение пустое - удаляем запись
                    if ($value === '') {
                        $table->delete();
                    } else {
                        // Иначе сохраняем данные
                        $table->value = (float) $data['ishop_field_' . $id];
                        $table->hint = $data['ishop_field_hint_' . $id];
                        $table->store();
                    }

                } elseif ($value !== '') {
                    // Такой записи еще не существует
                    $table->product_id = $product_id;
                    $table->field_id = $id;
                    $table->value = (float) $data['ishop_field_' . $id];
                    $table->hint = $data['ishop_field_hint_' . $id];
                    $table->store();
                }
            }
        }

        return true;
    }

    /**
     * Метод переключения режима избранных товаров
     *
     * @param   array  $pks    Идентификаторы товаров
     * @param   int    $value  Новое значение
     *
     * @return  bool  True в случае успеха
     * @throws \Exception
     * @since   @since  1.0.0
     */
    public function featured(array $pks, int $value = 0)
    {
        $pks     = ArrayHelper::toInteger($pks);

        if (empty($pks)) {
            return false;
        }

        try {
            $db    = $this->getDatabase();
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__ishop_products'))
                ->set($db->quoteName('featured') . ' = :featured')
                ->whereIn($db->quoteName('id'), $pks)
                ->bind(':featured', $value, ParameterType::INTEGER);
            $db->setQuery($query);
            $db->execute();
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), 500);
        }

        $this->cleanCache();

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
    public function getTable($name = 'Product', $prefix = '', $options = [])
    {
        return parent::getTable($name, $prefix, $options);
    }
}