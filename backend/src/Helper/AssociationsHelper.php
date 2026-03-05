<?php
/**
 * @package        com_ishop
 * @author         Pavel Lange <pavel@ilange.ru>
 * @link           https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license        GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Association\AssociationExtensionHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Associations;
use Joomla\CMS\Table\Table;
use Ilange\Component\Ishop\Site\Helper\AssociationHelper;

/**
 * Класс helper компонента
 * методы для работы с языковыми связями
 * @since 1.0.0
 */
class AssociationsHelper extends AssociationExtensionHelper
{
    /**
     * Имя расширения
     * @var     string $extension
     * @since 1.0.0
     */
    protected $extension = 'com_ishop';

    /**
     * Массив типов элементов
     * @var     array $itemTypes
     * @since 1.0.0
     */
    protected $itemTypes = ['product', 'field', 'group', 'value', 'manufacturer', 'supplier', 'prefix'];

    /**
     * Поддерживает ли расширение ассоциации
     * @var     bool $associationsSupport
     * @since 1.0.0
     */
    protected $associationsSupport = true;

    /**
     * Метод получения ассоциаций для указанного элемента
     *
     * @param   int     $id    Идентификатор элемента
     * @param   string  $view  Имя представления
     *
     * @return  array   Массив ассоциаций элемента
     * @throws \Exception
     * @since  1.0.0
     */
    public function getAssociationsForItem($id = 0, $view = null): array
    {
        return AssociationHelper::getAssociations($id, $view);
    }

    /**
     * Получение связанных элементов для элемента
     *
     * @param   string  $typeName  Тип элемента
     * @param   int     $id        Идентификатор элемента, для которого нам нужны связанные элементы
     *
     * @return  array
     * @throws \Exception
     * @since 1.0.0
     */
    public function getAssociations(string $typeName, int $id): array
    {
        $type = $this->getType($typeName);

        $context    = $this->extension . '.' . $typeName;
        $catidField = '';

        if ($typeName === 'product') {
            $catidField = 'catid';
        }

        // Get the associations.
        return Associations::getAssociations(
            $this->extension,
            $type['tables']['a'],
            $context,
            $id,
            'id',
            'alias',
            $catidField
        );
    }

    /**
     * Получить информацию об элементе
     *
     * @param   string  $typeName  Тип элемента
     * @param   int     $id        Идентификатор элемента, для которого нам нужны связанные элементы
     *
     * @return  Table|null
     * @throws \Exception
     * @since 1.0.0
     */
    public function getItem(string $typeName, int $id)
    {
        if (empty($id)) {
            return null;
        }

        $table = null;

        switch ($typeName) {
            case 'product':
                $table = Factory::getApplication()
                    ->bootComponent('com_ishop')
                    ->getMVCFactory()
                    ->createTable('Product', 'Administrator');
                break;

            case 'category':
                $table = Factory::getApplication()
                    ->bootComponent('com_categories')
                    ->getMVCFactory()
                    ->createTable('Category', 'Administrator');
                break;

            case 'field':
                $table = Factory::getApplication()
                    ->bootComponent('com_ishop')
                    ->getMVCFactory()
                    ->createTable('Field', 'Administrator');
                break;

            case 'group':
                $table = Factory::getApplication()
                    ->bootComponent('com_ishop')
                    ->getMVCFactory()
                    ->createTable('Group', 'Administrator');
                break;

            case 'value':
                $table = Factory::getApplication()
                    ->bootComponent('com_ishop')
                    ->getMVCFactory()
                    ->createTable('Value', 'Administrator');
                break;

            case 'manufacturer':
                $table = Factory::getApplication()
                    ->bootComponent('com_ishop')
                    ->getMVCFactory()
                    ->createTable('Manufacturer', 'Administrator');
                break;

            case 'supplier':
                $table = Factory::getApplication()
                    ->bootComponent('com_ishop')
                    ->getMVCFactory()
                    ->createTable('Supplier', 'Administrator');
                break;

            case 'prefix':
                $table = Factory::getApplication()
                    ->bootComponent('com_ishop')
                    ->getMVCFactory()
                    ->createTable('Prefix', 'Administrator');
                break;
        }

        if (empty($table)) {
            return null;
        }

        $table->load($id);

        return $table;
    }

    /**
     * Получение информации о типе
     *
     * @param   string  $typeName  Тип элемента
     *
     * @return  array  Массив типов элементов
     * @throws \Exception
     * @since 1.0.0
     */
    public function getType($typeName = ''): array
    {
        $fields  = $this->getFieldsTemplate();
        $tables  = [];
        $joins   = [];
        $support = $this->getSupportTemplate();
        $title   = '';

        if (in_array($typeName, $this->itemTypes)) {
            switch ($typeName) {
                case 'product':
                    $support['state']     = true;
                    $support['acl']       = true;
                    $support['checkout']  = true;
                    $support['category']  = true;
                    $support['save2copy'] = true;

                    $tables = [
                        'a' => '#__ishop_products',
                    ];

                    $title = 'product';
                    break;

                case 'category':
                    $fields['created_user_id'] = 'a.created_user_id';
                    $fields['ordering']        = 'a.lft';
                    $fields['level']           = 'a.level';
                    $fields['catid']           = '';
                    $fields['state']           = 'a.published';

                    $support['state']    = true;
                    $support['acl']      = true;
                    $support['checkout'] = true;
                    $support['level']    = true;

                    $tables = [
                        'a' => '#__categories',
                    ];

                    $title = 'category';
                    break;

                case 'field':
                    $support['state']     = true;
                    $support['acl']       = true;
                    $support['checkout']  = true;
                    $support['category']  = false;
                    $support['save2copy'] = true;

                    $tables = [
                        'a' => '#__ishop_fields',
                    ];

                    $title = 'field';
                    break;

                case 'group':
                    $support['state']     = true;
                    $support['acl']       = false;
                    $support['checkout']  = true;
                    $support['category']  = false;
                    $support['save2copy'] = true;

                    $tables = [
                        'a' => '#__ishop_groups',
                    ];

                    $title = 'group';
                    break;

                case 'value':
                    $support['state']     = false;
                    $support['acl']       = false;
                    $support['checkout']  = false;
                    $support['category']  = false;
                    $support['save2copy'] = false;

                    $tables = [
                        'a' => '#__ishop_values',
                    ];

                    $title = 'value';
                    break;

                case 'manufacturer':
                    $support['state']     = true;
                    $support['acl']       = true;
                    $support['checkout']  = true;
                    $support['category']  = false;
                    $support['save2copy'] = true;

                    $tables = [
                        'a' => '#__ishop_manufacturers',
                    ];

                    $title = 'manufacturer';
                    break;

                case 'supplier':
                    $support['state']     = true;
                    $support['acl']       = true;
                    $support['checkout']  = true;
                    $support['category']  = false;
                    $support['save2copy'] = true;

                    $tables = [
                        'a' => '#__ishop_suppliers',
                    ];

                    $title = 'supplier';
                    break;

                case 'prefix':
                    $support['state']     = true;
                    $support['acl']       = false;
                    $support['checkout']  = true;
                    $support['category']  = false;
                    $support['save2copy'] = true;

                    $tables = [
                        'a' => '#__ishop_prefixes',
                    ];

                    $title = 'prefix';
                    break;
            }
        }

        return [
            'fields'  => $fields,
            'support' => $support,
            'tables'  => $tables,
            'joins'   => $joins,
            'title'   => $title,
        ];
    }
}
