<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Factory;
use Joomla\Filesystem\Path;
use Joomla\Filesystem\Folder;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScript;
use Joomla\CMS\Language\Text;

defined('_JEXEC') or die;

class Com_IshopInstallerScript extends InstallerScript
{
    /**
     * Название устанавливаемого расширения Joomla!
     * @var string
     * @since 1.0.0
     */
    protected $extension = 'com_ishop';
    
    /**
     * Минимальная версия PHP, необходимая для установки модуля
     * @var string
     * @since 1.0.0
     */
    protected $minimumPhp = '8.3';

    /**
     * Минимальная версия Joomla, необходимая для установки модуля
     * @var string
     * @since 1.0.0
     */
    protected $minimumJoomla = '6.0.0';

    /**
     * Список файлов, которые необходимо удалить
     * @var array
     * @since 1.0.0
     */
    protected $deleteFiles = [];

    /**
     * Список папок, которые необходимо удалить
     * @var array
     * @since 1.0.0
     */
    protected $deleteFolders = [];

    /**
     * Объект приложения
     * @var object
     * @since 1.0.0
     */
    protected $app = null;

    /**
     * DBO
     * @var object
     * @since 1.0.0
     */
    protected $db = null;

    /**
     * Конструктор
     * @throws Exception
     * @since 1.0.0
     */
    public function __construct()
    {
        // Получаем объект приложения
        $this->app = Factory::getApplication();
        
        // Получаем DBO
        $this->db = Factory::getContainer()->get('DatabaseDriver');
    }

    /**
     * Метод запускается непосредственно перед установкой/обновлением/удалением модуля
     * @param string $type Тип действия, которое выполняется (install|uninstall|discover_install|update)
     * @param InstallerAdapter $parent Класс, вызывающий этот метод.
     * @return bool Возвращает True для продолжения, False для отмены установки/обновления/удаления
     * @throws Exception
     * @since 1.0.0
     */
    public function preflight($type, $parent): bool
    {
        if (!parent::preflight($type, $parent)) {
            return false;
        }

        return true;
    }

    /**
     * Метод запускается непосредственно после установки/обновления/удаления модуля
     * @param string $type Тип действия, которое выполняется (install|uninstall|discover_install|update)
     * @param InstallerAdapter $parent Класс, вызывающий этот метод.
     * @return bool True при успешном выполнении
     * @throws Exception
     * @since 1.0.0
     */
    public function postflight(string $type, InstallerAdapter $parent): bool
    {
        // Удаляем файлы и папки, в которых больше нет необходимости
        $this->removeFiles();

	    // Создаем папки для хранения данных магазина
	    $folders = [
		    '/images/products',
		    '/images/categories',
            '/files/manuals',
		    '/files/video',
	    ];

	    foreach ($folders as $folder) {
		    if (!is_dir(Path::clean(JPATH_ROOT . $folder))) {
			    Folder::create(JPATH_ROOT . $folder);
		    }
	    }

        $this->setContentTypes();

        if ($type === 'install' || $type === 'discover_install') {
            if (!$this->categoryCreate()) {
                echo Text::_('COM_ISHOP_CATEGORY_ADD_ERROR');
            }
        } elseif ($type === 'update') {
            // Получаем данные из xml файла компонента
            $xml = $parent->getManifest();

            // Пишем сообщение со ссылками на сайт автора и на репозиторий
            $message[] = '<p class="fs-2 mb-2">' . Text::_('COM_ISHOP') . ' [' . $xml->name . ']</p>';
            $message[] = '<ul>';
            $message[] = '<li>' . Text::_('COM_ISHOP_VERSION') . ': ' . $xml->version . '</li>';
            $message[] = '<li>' . Text::_('COM_ISHOP_AUTHOR') . ': ' . $xml->author . '</li>';
            $message[] = "<li><a href='https://ilange.ru' target='_blank'>https://ilange.ru</a></li>";
            $message[] = "<li><a href='https://github.com/i-lange/" . $xml->name . "' target='_blank'>GitHub</a></li>";
            $message[] = '</ul>';
            $message[] = '<p class="mb-2">' . Text::_('COM_ISHOP_DONATE') . ': </p>';
            $message[] = "<a href='" . 
                Text::_('COM_ISHOP_DONATE_URL') . "' target='_blank' class='btn btn-primary'>" .
                Text::_('COM_ISHOP_DONATE_BTN') . "</a>";

            // Объединяем все в строку
            $msgStr = implode($message);

            // Показываем сообщение
            echo $msgStr;
        } else {
            $this->app->enqueueMessage(
                Text::_('COM_ISHOP_XML_UNINSTALL_OK'),
                'warning'
            );
        }

        return true;
    }

    /**
     * Создает корневую категорию для товаров магазина
     * @return bool True при успешном выполнении
     * @throws Exception
     * @since 1.0.0
     */
    private function categoryCreate(): bool
    {
        // Загружаем таблицу категорий Joomla
        $category = $this->app
            ->bootComponent('com_categories')
            ->getMVCFactory()
            ->createTable('Category', 'Administrator', ['dbo' => $this->db]);

	    $title = 'iShop Main Category';
	    // Проверяем, существует ли категория товаров для компонента
	    if ($category->load(['extension' => 'com_ishop', 'title' => $title])){
			return true;
	    }

	    $lang = $this->app->getLanguage()->getTag();
	    $alias = ApplicationHelper::stringURLSafe($title, $lang);
	    $user_id = $this->getAdminId();

	    // Поля новой категории
        $data = [
	        'parent_id' => 1,
	        'path' => $alias,
            'extension' => $this->extension,
            'title' => $title,
	        'alias' => $alias,
	        'note' => '',
            'description' => '',
            'published' => 1,
            'access' => 1,
            'params' => '{"category_layout":"","image":""}',
            'metadesc' => '',
            'metakey' => '',
            'metadata' => '{"author":"","robots":""}',
	        'created_user_id' => $user_id,
            'created_time' => Factory::getDate()->toSql(),
            'language' => '*',
        ];

        try {
            // Установка местоположения узла
            $category->setLocation(1, 'last-child');
            
            // Устанавливаем данные в таблицу
            $category->bind($data);
            
            // Проверяем, что данные корректны
            $category->check();

            // Сохраняем категорию            
             $category->store(true);
            
            if (!$category->id) {
                return false;
            }

            // Построение пути для категории
            $category->rebuildPath($category->id);
            
        } catch (Exception $e) {
            $this->app->enqueueMessage(
                Text::_('COM_ISHOP_XML_CATEGORY_ADD_ERROR') . ' Error: ' . $e->getMessage(),
                'danger'
            );

            return false;
        }

        return true;
    }

    /**
     * Возвращает Id супер администратора
     * @return int Id пользователя при успешном выполнении
     * @since 1.0.0
     */
    private function getAdminId(): int
    {
        $db = $this->db;
        $query = $db->getQuery(true);

        // Выбираем все Id пользователей с правами администратора
        $query
            ->select($db->quoteName('user.id'))
            ->from($db->quoteName('#__users', 'user'))
            ->join(
                'LEFT',
                $db->quoteName('#__user_usergroup_map', 'map'),
                $db->quoteName('map.user_id') . ' = ' . $db->quoteName('user.id')
            )
            ->join(
                'LEFT',
                $db->quoteName('#__usergroups', 'grp'),
                $db->quoteName('map.group_id') . ' = ' . $db->quoteName('grp.id')
            )
            ->where(
                $db->quoteName('grp.title') . ' = ' . $db->quote('Super Users')
            );

        $db->setQuery($query);

        // Берем первый из найденных
        $id = $db->loadResult();        

        if (!$id || $id instanceof Exception) {
            return 0;
        }        

        return $id;
    }

    /**
     * Создает новые типы контента
     * @return void
     * @since 1.0.0
     */
    private function setContentTypes()
    {
        // Добавляем новые типы контента, если таких еще не существует
        $db = $this->db;

        // Проверяем, существует ли тип com_ishop.product
        $query = $db->getQuery(true);
        $query->select($db->quoteName('type_id'))
            ->from($db->quoteName('#__content_types'))
            ->where($db->quoteName('type_alias') . ' = ' . $db->quote('com_ishop.product'));
        $db->setQuery($query);
        $productTypeId = $db->loadResult();

        // Проверяем, существует ли тип com_ishop.category
        $query->clear('where');
        $query->where($db->quoteName('type_alias') . ' = ' . $db->quote('com_ishop.category'));
        $db->setQuery($query);
        $categoryTypeId = $db->loadResult();

        // Список столбцов для записей типа контента
        $columnsArray = [
            $db->quoteName('type_title'),
            $db->quoteName('type_alias'),
            $db->quoteName('table'),
            $db->quoteName('rules'),
            $db->quoteName('field_mappings'),
            $db->quoteName('router'),
            $db->quoteName('content_history_options'),
        ];

        // Создает тип com_ishop.product
        if (!$productTypeId) {
            $query->clear();
            $query->insert($db->quoteName('#__content_types'));
            $query->columns($columnsArray);
            $query->values([
                $db->quote('IshopProduct')  . ', ' .
                $db->quote('com_ishop.product')  . ', ' .
                $db->quote('{"special"{"dbtable":"#__ishop_products","key":"id","type":"ProductTable","prefix":"Ilange\\Component\\Ishop\\Administrator\\Table","config":"array()"},"common":{"dbtable":"#__ucm_content","key":"ucm_id","type":"Corecontent","prefix":"Joomla\\CMS\\Table\\","config":"array()"}}')  . ', ' .
                $db->quote('')  . ', ' .
                $db->quote('{"common":{"core_content_item_id":"id","core_title":"title","core_state":"state","core_alias":"alias","core_created_time":"created","core_modified_time":"modified","core_body":"introtext", "core_hits":"hits","core_publish_up":"publish_up","core_publish_down":"publish_down","core_access":"access", "core_params":"attribs", "core_featured":"featured", "core_metadata":"metadata", "core_language":"language", "core_images":"images", "core_urls":"null", "core_version":"version", "core_ordering":"ordering", "core_metakey":"metakey", "core_metadesc":"metadesc", "core_catid":"catid", "asset_id":"null", "note":"null"},"special":{"fulltext":"fulltext"}}')  . ', ' .
                $db->quote('iShopHelperRoute::getProductRoute')  . ', ' .
                $db->quote('{"formFile":"administrator\/components\/com_ishop\/forms\/product.xml","hideFields":["checked_out","checked_out_time","version"],"ignoreChanges":["modified_by", "modified", "checked_out", "checked_out_time", "version", "hits", "ordering"],"convertToInt":["publish_up","publish_down","featured","ordering"],"displayLookup":[{"sourceColumn":"catid","targetTable":"#__categories","targetColumn":"id","displayColumn":"title"},{"sourceColumn":"created_by","targetTable":"#__users","targetColumn":"id","displayColumn":"name"},{"sourceColumn":"access","targetTable":"#__viewlevels","targetColumn":"id","displayColumn":"title"},{"sourceColumn":"modified_by","targetTable":"#__users","targetColumn":"id","displayColumn":"name"}]}')
            ]);
            $db->setQuery($query);
            $db->execute();
        }

        // Создает тип com_ishop.category
        if (!$categoryTypeId) {
            $query->clear();
            $query->insert($db->quoteName('#__content_types'));
            $query->columns($columnsArray);
            $query->values(
                $db->quote('IshopProduct Category') . ', ' .
                $db->quote('com_ishop.category') . ', ' .
                $db->quote('{"special":{"dbtable":"#__categories","key":"id","type":"CategoryTable","prefix":"Joomla\\Component\\Categories\\Administrator\\Table\\","config":"array()"},"common":{"dbtable":"#__ucm_content","key":"ucm_id","type":"Corecontent","prefix":"Joomla\\CMS\\Table\\","config":"array()"}}') . ', ' .
                $db->quote('') . ', ' .
                $db->quote('{"common":{"core_content_item_id":"id","core_title":"title","core_state":"published","core_alias":"alias","core_created_time":"created_time","core_modified_time":"modified_time","core_body":"description", "core_hits":"hits","core_publish_up":"null","core_publish_down":"null","core_access":"access", "core_params":"params", "core_featured":"null", "core_metadata":"metadata", "core_language":"language", "core_images":"null", "core_urls":"null", "core_version":"version", "core_ordering":"null", "core_metakey":"metakey", "core_metadesc":"metadesc", "core_catid":"parent_id", "asset_id":"asset_id"},"special":{"parent_id":"parent_id","lft":"lft","rgt":"rgt","level":"level","path":"path","extension":"extension","note":"note"}}') . ', ' .
                $db->quote('iShopHelperRoute::getCategoryRoute') . ', ' .
                $db->quote('{"formFile":"administrator\/components\/com_categories\/forms\/category.xml","hideFields":["asset_id","checked_out","checked_out_time","version","lft","rgt","level","path","extension"],"ignoreChanges":["modified_user_id", "modified_time", "checked_out", "checked_out_time", "version", "hits", "path"],"convertToInt":["publish_up", "publish_down"], "displayLookup":[{"sourceColumn":"created_user_id","targetTable":"#__users","targetColumn":"id","displayColumn":"name"},{"sourceColumn":"access","targetTable":"#__viewlevels","targetColumn":"id","displayColumn":"title"},{"sourceColumn":"modified_user_id","targetTable":"#__users","targetColumn":"id","displayColumn":"name"},{"sourceColumn":"parent_id","targetTable":"#__categories","targetColumn":"id","displayColumn":"title"}]}')
            );
            $db->setQuery($query);
            $db->execute();
        }
    }
}