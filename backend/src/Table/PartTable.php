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
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

/**
 * Таблица оплаты частями
 * @since 1.0.0
 */
class PartTable extends Table
{
    /**
     * Массив имен ключей, которые должны быть
     * закодированы в формате json в функции bind
     *
     * @var    array
     * @since  1.0.0
     */
    protected $_jsonEncode = [
        'images',
        'attribs',
    ];

    /**
     * Указывает, что столбцы полностью поддерживают значение NULL в базе данных
     *
     * @var    bool
     * @since  1.0.0
     */
    protected $_supportNullValue = true;

    /**
     * Конструктор
     * @param   DatabaseInterface  $db  Объект подключения базы данных
     * @since 1.0.0
     */
	public function __construct(DatabaseInterface $db)
	{
        $this->typeAlias = 'com_ishop.part';
		parent::__construct('#__ishop_payment_parts', 'id', $db);
        
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

        // Проверяем заголовок
        if (trim($this->title) == '') {
            throw new InvalidArgumentException('Не указан заголовок скидки');
        }

        // Проверяем псевдоним
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

        if (trim(str_replace('&nbsp;', '', $this->introtext)) == '') {
            $this->introtext = '';
        }

        if (trim(str_replace('&nbsp;', '', $this->fulltext)) == '') {
            $this->fulltext = '';
        }

        // Устанавливаем некоторые поля по умолчанию, если создается новая запись
        if (!$this->id) {
            // Изображения по умолчанию пустая строка json
            if (!isset($this->images)) {
                $this->images = '{}';
            }

            // Параметры по умолчанию пустая строка json
            if (!isset($this->attribs)) {
                $this->attribs = '{}';
            }
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
        if (!$this->isUniqAlias()) {
            Factory::getApplication()->enqueueMessage(
                Text::_('COM_ISHOP_ERROR_UNIQUE_ALIAS'),
                'danger'
            );

            return false;
        }

        return parent::store($updateNulls);
    }

    /**
     * Возвращает уникальную строку для псевдонима
     * @return bool Уникален ли псевдоним записи
     * @throws \Exception
     * @since 1.0.0
     */
    public function isUniqAlias(string $alias = ''):bool
    {
        $alias = ($alias === '') ? $this->alias : $alias;
        $table = Factory::getApplication()
            ->bootComponent('com_ishop')
            ->getMVCFactory()
            ->createTable('Part', 'Administrator', ['dbo' => $this->getDatabase()]);

        // Проверяем среди всех записей, кроме элемента с тем же Id, если он существует
        if ($table->load(['alias' => $alias]) && 
            ((int)$table->id !== (int)$this->id || (int)$this->id === 0)) {

            return false;
        }

        return true;
    }
}
