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

use DateTime;
use InvalidArgumentException;
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Registry\Registry;

/**
 * Таблица зон доставки
 * @since 1.0.0
 */
class ZoneTable extends Table
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
        'current',
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
     * @param   ?DispatcherInterface  $dispatcher  Диспетчер событий для этой таблицы
     * @since 1.0.0
     */
	public function __construct(DatabaseInterface $db, ?DispatcherInterface $dispatcher = null)
	{
        $this->typeAlias = 'com_ishop.zone';
		parent::__construct('#__ishop_delivery_zones', 'id', $db, $dispatcher);

        // Установим псевдоним, так как колонка называется state
        $this->setColumnAlias('published', 'state');
	}

    /**
     * Метод привязки ассоциативного массива или объекта к экземпляру Table.
     * Этот метод привязывает только public-свойства.
     *
     * @param   array|object  $src     Ассоциативный массив или объект
     * @param   array|string  $ignore  Массив свойств, которые следует игнорировать, необязательно
     *
     * @return bool True on success.
     * @throws InvalidArgumentException|\Exception
     * @since 1.0.0
     */
    public function bind($src, $ignore = [])
    {
        if (isset($src['current']) && is_array($src['current'])) {
            foreach ($src['current'] as $key => $row) {
                $src['current'][$key]['date'] = (new DateTime($row['date']))->format('Y-m-d');
            }
        }

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
            throw new InvalidArgumentException('Не указан заголовок');
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

            // Параметры по умолчанию пустая строка json
            if (!isset($this->current)) {
                $this->current = '{}';
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
}
