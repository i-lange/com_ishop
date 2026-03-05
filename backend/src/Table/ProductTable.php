<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Administrator\Table;

defined('_JEXEC') or die;

use InvalidArgumentException;
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Tag\TaggableTableInterface;
use Joomla\CMS\Tag\TaggableTableTrait;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Registry\Registry;
use Joomla\String\StringHelper;

/**
 * Таблица товаров нашего магазина
 * @since 1.0.0
 */
class ProductTable extends Table implements TaggableTableInterface
{
    use TaggableTableTrait;

    /**
     * Массив имен ключей, которые должны быть
     * закодированы в формате json в функции bind
     *
     * @var    array
     * @since  1.0.0
     */
    protected $_jsonEncode = [
        'images',
        'reach_icons',
        'reach_features',
        'videos',
        'documents',
        'attribs',
        'metadata',
        'equipment',
        'delivery',
    ];

    /**
     * Указывает, что столбцы полностью поддерживают значение NULL в базе данных
     * @var    bool
     * @since  1.0.0
     */
    protected $_supportNullValue = true;

    /**
     * Конструктор
     * @param   DatabaseInterface  $db  Объект подключения базы данных
     * @param   ?DispatcherInterface  $dispatcher  Диспетчер событий для этой таблицы
     * @since 1.0.0
     */
	public function __construct(DatabaseInterface $db, ?DispatcherInterface $dispatcher = null)
	{
        $this->typeAlias = 'com_ishop.product';
		parent::__construct('#__ishop_products', 'id', $db, $dispatcher);
        
        // Установим псевдоним, так как колонка называется state
        $this->setColumnAlias('published', 'state');
	}

    /**
     * Метод привязки ассоциативного массива или объекта к экземпляру Table. 
     * Этот метод привязывает только public-свойства.
     * @param array|object $src Ассоциативный массив или объект
     * @param array|string $ignore Массив свойств, которые следует игнорировать, необязательно
     * @return bool True on success.
     * @since 1.0.0
     * @throws InvalidArgumentException
     */
    public function bind($src, $ignore = [])
    {
        return parent::bind($src, $ignore);
    }

    /**
     * Переопределяем метод проверки данных
     * @return bool True при успехе, false при неудаче
     * @see Table::check()
     * @since 1.0.0
     */
    public function check()
    {
        parent::check();

        // Проверяем заголовок товара
        if (trim($this->title) == '') {
            throw new InvalidArgumentException('Не указан заголовок товара');
        }

        // Проверяем псевдоним товара
        if ($this->alias == '') {
            $this->alias = $this->title;
        } elseif (trim($this->alias) == '') {
            $this->alias = $this->title;
        }

        // Обрабатываем псевдоним для URL
        $this->alias = ApplicationHelper::stringURLSafe($this->alias);
        if (trim(str_replace('-', '', $this->alias)) == '') {
            $this->alias = Factory::getDate()->format('Y-m-d-H-i-s');
        }

        // Проверяем идентификатор категории
        if (!$this->catid) {
            throw new InvalidArgumentException('Не указан идентификатор категории');
        }

        if (trim(str_replace('&nbsp;', '', $this->introtext)) == '') {
            $this->introtext = '';
        }

        if (trim(str_replace('&nbsp;', '', $this->fulltext)) == '') {
            $this->fulltext = '';
        }
        
        // Устанавливаем некоторые поля по умолчанию, если создается новый товар
        if (!$this->id) {
            // Изображения по умолчанию пустая строка json
            if (!isset($this->images)) {
                $this->images = '{}';
            }

            // Иконки с описанием функций товара по умолчанию пустая строка json
            if (!isset($this->reach_icons)) {
                $this->reach_icons = '{}';
            }

            // Главные особенности товара по умолчанию пустая строка json
            if (!isset($this->reach_features)) {
                $this->reach_features = '{}';
            }

            // Комплектация по умолчанию пустая строка json
            if (!isset($this->equipment)) {
                $this->equipment = '{}';
            }

            // Видео по умолчанию пустая строка json
            if (!isset($this->videos)) {
                $this->videos = '{}';
            }

            // Документы по умолчанию пустая строка json
            if (!isset($this->documents)) {
                $this->documents = '{}';
            }

            // Параметры по умолчанию пустая строка json
            if (!isset($this->attribs)) {
                $this->attribs = '{}';
            }

            // Метаданные по умолчанию пустая строка json
            if (!isset($this->metadata)) {
                $this->metadata = '{}';
            }

            $this->hits = 0;
        }

        // Set publish_up to null if not set
        if (!$this->publish_up) {
            $this->publish_up = null;
        }

        // Set publish_down to null if not set
        if (!$this->publish_down) {
            $this->publish_down = null;
        }

        // Проверяем, что дата окончания публикации указана не ранее, чем дата начала публикации
        if (!is_null($this->publish_up) && !is_null($this->publish_down) && $this->publish_down < $this->publish_up) {
            $temp               = $this->publish_up;
            $this->publish_up   = $this->publish_down;
            $this->publish_down = $temp;
        }

        // Очистить ключевые слова - убрать лишние пробелы между фразами
        // и символами cr (\r) и lf (\n) из строк метаданных
        if (!empty($this->metakey)) {
            // Массив символов для удаления
            $badCharacters = ["\n", "\r", "\"", '<', '>'];

            // Удаляем символы
            $afterClean = StringHelper::str_ireplace($badCharacters, '', $this->metakey);

            // Создаем массив, разделитель запятая
            $keys = explode(',', $afterClean);

            $cleanKeys = [];

            foreach ($keys as $key) {
                if (trim($key)) {
                    $cleanKeys[] = trim($key);
                }
            }

            // Собираем строку разделяя ключи запятой с пробелом ", "
            $this->metakey = implode(', ', $cleanKeys);
        } else {
            $this->metakey = '';
        }

        if ($this->metadesc === null) {
            $this->metadesc = '';
        }

        return true;
    }

    /**
     * Переопределяем метод сохранения данных
     * @param bool $updateNulls True для обновления полей, даже если они равны null.
     * @return bool True при успешном выполнении
     * @see Table::store()
     * @throws \Exception
     * @since 1.0.0
     */
    public function store($updateNulls = true)
    {
        $date = Factory::getDate()->toSql();
        $user = Factory::getApplication()->getIdentity();

        // Устанавливаем дату создания, если не установлена
        if (!(int) $this->created) {
            $this->created = $date;
        }

        if ($this->id) {
            // Устанавливаем автора изменений и дату редактирования
            $this->modified_by = $user->id;
            $this->modified    = $date;
        } else {
            // Поле автор может быть установлено пользователем, поэтому не трогаем его, если оно установлено
            if (empty($this->created_by)) {
                $this->created_by = $user->id;
            }

            // Установить дату изменения равной дате создания, если она не установлена
            if (!(int) $this->modified) {
                $this->modified = $this->created;
            }

            // Установим поле автора изменений равным создателю, если оно не установлено
            if (empty($this->modified_by)) {
                $this->modified_by = $this->created_by;
            }
        }

        // Проверяем, что псевдоним уникален, если нет - выводим сообщение
        $table = new self($this->getDatabase(), $this->getDispatcher());
        if ($table->load(['alias' => $this->alias]) && ($table->id != $this->id || $this->id == 0)) {
            Factory::getApplication()->enqueueMessage(
                Text::_('COM_ISHOP_ERROR_UNIQUE_ALIAS'),
                'danger'
            );

            return false;
        }

        return parent::store($updateNulls);
    }

    /**
     * Возвращает псевдоним типа таблицы
     * @return  string  The alias as described above
     * @since   1.0.0
     */
    public function getTypeAlias()
    {
        return $this->typeAlias;
    }
}
