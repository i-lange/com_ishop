<?php
/**
 * @package        com_ishop
 * @author         Pavel Lange <pavel@ilange.ru>
 * @link           https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license        GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Administrator\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Association\AssociationServiceInterface;
use Joomla\CMS\Association\AssociationServiceTrait;
use Joomla\CMS\Categories\CategoryServiceInterface;
use Joomla\CMS\Categories\CategoryServiceTrait;
use Joomla\CMS\Component\Router\RouterServiceInterface;
use Joomla\CMS\Component\Router\RouterServiceTrait;
use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\Factory;
use Joomla\CMS\Fields\FieldsServiceInterface;
use Joomla\CMS\Fields\FieldsServiceTrait;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Helper\ContentHelper as LibraryContentHelper;
use Joomla\CMS\HTML\HTMLRegistryAwareTrait;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Schemaorg\SchemaorgServiceInterface;
use Joomla\CMS\Schemaorg\SchemaorgServiceTrait;
use Joomla\CMS\Tag\TagServiceInterface;
use Joomla\CMS\Tag\TagServiceTrait;
use Joomla\Component\Content\Administrator\Helper\ContentHelper;
use Ilange\Component\Ishop\Administrator\Service\Html\AdministratorService;
use Psr\Container\ContainerInterface;

/**
 * Класс компонента com_ishop
 * @since 1.0.0
 */
class IshopComponent extends MVCComponent implements
    BootableExtensionInterface,
    CategoryServiceInterface,
    FieldsServiceInterface,
    AssociationServiceInterface,
    SchemaorgServiceInterface,
    RouterServiceInterface,
    TagServiceInterface
{
    use AssociationServiceTrait;
    use RouterServiceTrait;
    use HTMLRegistryAwareTrait;
    use SchemaorgServiceTrait;
    use CategoryServiceTrait, TagServiceTrait, FieldsServiceTrait {
        CategoryServiceTrait::getTableNameForSection insteadof TagServiceTrait;
        CategoryServiceTrait::getStateColumnForSection insteadof TagServiceTrait;
        CategoryServiceTrait::prepareForm insteadof FieldsServiceTrait;
    }

    /**
     * @var array Поддерживаемая функциональность
     * @since 1.0.0
     */
    protected array $supportedFunctionality = [
        'core.featured' => true,
        'core.state'    => true,
    ];

    /**
     * Состояния элементов
     * @since 1.0.0
     */
    public const array CONDITION_NAMES = [
        self::CONDITION_PUBLISHED   => 'JPUBLISHED',
        self::CONDITION_UNPUBLISHED => 'JUNPUBLISHED',
        self::CONDITION_ARCHIVED    => 'JARCHIVED',
        self::CONDITION_TRASHED     => 'JTRASHED',
    ];

    /**
     * В архиве
     * @since 1.0.0
     */
    public const int CONDITION_ARCHIVED = 2;

    /**
     * Опубликовано
     * @since 1.0.0
     */
    public const int CONDITION_PUBLISHED = 1;

    /**
     * Снято с публикации
     * @since 1.0.0
     */
    public const int CONDITION_UNPUBLISHED = 0;

    /**
     * В корзине
     * @since 1.0.0
     */
    public const int CONDITION_TRASHED = -2;

    /**
     * Загрузка расширения. Это функция для настройки среды расширения,
     * например, регистрация новых загрузчиков классов и т.д.
     * При необходимости, некоторые начальные настройки могут быть выполнены
     * из служб контейнера, например, регистрация служб HTML.
     *
     * @param   ContainerInterface  $container  Контейнер
     *
     * @return void
     * @since 1.0.0
     */
    public function boot(ContainerInterface $container)
    {
        $this->getRegistry()->register('ishopadministrator', new AdministratorService());
    }

    /**
     * Возвращает действительный раздел для заданной секции.
     * Если он недействителен, то возвращается null.
     *
     * @param   string  $section  Раздел для получения отображения
     * @param   object  $item     Элемент
     *
     * @return  string|null  Новый раздел
     * @throws \Exception
     * @since 1.0.0
     */
    public function validateSection($section, $item = null)
    {
        if (Factory::getApplication()->isClient('site')) {
            switch ($section) {
                // Редактирование товара
                case 'form':
                    // Список товаров
                case 'featured':
                case 'category':
                    $section = 'product';
            }
        }

        if ($section != 'product') {
            return null;
        }

        return $section;
    }

    /**
     * Возвращает контексты
     *
     * @return  array
     * @throws \Exception
     * @since 1.0.0
     */
    public function getContexts(): array
    {
        Factory::getApplication()->getLanguage()->load('com_ishop', JPATH_ADMINISTRATOR);

        return [
            'com_ishop.product'    => Text::_('COM_ISHOP'),
            'com_ishop.categories' => Text::_('JCATEGORY')
        ];
    }

    /**
     * Возвращает допустимые контексты для schema.org
     *
     * @return  array
     *
     * @throws \Exception
     * @since  5.0.0
     */
    public function getSchemaorgContexts(): array
    {
        Factory::getApplication()->getLanguage()->load('com_ishop', JPATH_ADMINISTRATOR);

        return [
            'com_ishop.product' => Text::_('COM_ISHOP'),
        ];
    }

    /**
     * Возвращает контексты
     *
     * @return  array
     * @throws \Exception
     * @since 1.0.0
     */
    public function getWorkflowContexts(): array
    {
        Factory::getApplication()->getLanguage()->load('com_ishop', JPATH_ADMINISTRATOR);

        return ['com_ishop.product' => Text::_('COM_ISHOP')];
    }

    /**
     * Возвращает контекст рабочего процесса, по заданной категории
     *
     * @param   string|null  $section  Категория
     *
     * @return string
     * @throws \Exception
     * @since 1.0.0
     */
    public function getCategoryWorkflowContext(?string $section = null): string
    {
        $context = $this->getWorkflowContexts();

        return array_key_first($context);
    }

    /**
     * Возвращает таблицу товаров
     *
     * @param   string|null  $section  Секция
     *
     * @return  string|null
     * @since 1.0.0
     */
    protected function getTableNameForSection(?string $section = null)
    {
        return '#__ishop_products';
    }

    /**
     * Возвращает таблицу товаров
     *
     * @param   string|null  $section  Секция
     *
     * @return  string
     * @since 1.0.0
     */
    public function getWorkflowTableBySection(?string $section = null): string
    {
        return '#__ishop_products';
    }

    /**
     * Возвращает имя модели, основанное на контексте
     *
     * @param   string  $context  Контекст рабочего процесса
     *
     * @return string
     * @throws \Exception
     * @since 1.0.0
     */
    public function getModelName(string $context): string
    {
        $parts = explode('.', $context);

        if (\count($parts) < 2) {
            return '';
        }

        array_shift($parts);

        $modelName = array_shift($parts);

        if ($modelName === 'product' && Factory::getApplication()->isClient('site')) {
            return 'Form';
        } elseif ($modelName === 'featured' && Factory::getApplication()->isClient('administrator')) {
            return 'Product';
        }

        return ucfirst($modelName);
    }

    /**
     * Метод фильтрации переходов по идентификатору состояния
     *
     * @param   array  $transitions  Переходы для фильтрации
     * @param   int    $pk           Идентификатор состояния
     *
     * @return  array
     * @throws \Exception
     * @since 1.0.0
     */
    public function filterTransitions(array $transitions, int $pk): array
    {
        return ContentHelper::filterTransitions($transitions, $pk);
    }

    /**
     * Добавляет подсчет элементов для менеджера категорий
     *
     * @param   \stdClass[]  $items    Категории
     * @param   string       $section  Секция
     *
     * @return  void
     * @throws \Exception
     * @since 1.0.0
     */
    public function countItems(array $items, string $section)
    {
        $config = (object)[
            'related_tbl'    => 'ishop_products',
            'state_col'      => 'state',
            'group_col'      => 'catid',
            'relation_type'  => 'category_or_group',
            'uses_workflows' => true,
        ];

        LibraryContentHelper::countRelations($items, $config);
    }

    /**
     * Добавляет элементы подсчета для менеджера тегов
     *
     * @param   \stdClass[]  $items      The content objects
     * @param   string       $extension  The name of the active view.
     *
     * @return  void
     * @throws  \Exception
     * @since 1.0.0
     */
    public function countTagItems(array $items, string $extension)
    {
        $parts   = explode('.', $extension);
        $section = \count($parts) > 1 ? $parts[1] : null;

        $config = (object)[
            'related_tbl'   => ($section === 'category' ? 'categories' : 'content'),
            'state_col'     => ($section === 'category' ? 'published' : 'state'),
            'group_col'     => 'tag_id',
            'extension'     => $extension,
            'relation_type' => 'tag_assigments',
        ];

        LibraryContentHelper::countRelations($items, $config);
    }

    /**
     * Подготавливает форму категории
     *
     * @param   Form          $form  форма
     * @param   array|object  $data  данные
     *
     * @return void
     * @throws  \Exception
     * @since 1.0.0
     */
    public function prepareForm(Form $form, $data)
    {
        ContentHelper::onPrepareForm($form, $data);
    }
}
