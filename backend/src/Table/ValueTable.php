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
use Joomla\Database\DatabaseInterface;

/**
 * Таблица значений характеристик
 * @since 1.0.0
 */
class ValueTable extends Table
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
    ];

    /**
     * Конструктор
     * @param   DatabaseInterface  $db  Объект подключения базы данных
     * @since 1.0.0
     */
	public function __construct(DatabaseInterface $db)
	{
        $this->typeAlias = 'com_ishop.value';
		parent::__construct('#__ishop_values', 'id', $db);
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
        if (trim($this->value) == '') {
            throw new InvalidArgumentException('Не указано наименование значения характеристики');
        }

        // Проверяем псевдоним
        if ($this->alias == '') {
            $this->alias = $this->value;
        } elseif (trim($this->alias) == '') {
            $this->alias = $this->value;
        }

        // Обрабатываем псевдоним для URL
        $this->alias = ApplicationHelper::stringURLSafe($this->alias);
        if (trim(str_replace('-', '', $this->alias)) == '') {
            $this->alias = Factory::getDate()->format('Y-m-d-H-i-s');
        }

        // Устанавливаем некоторые поля по умолчанию, если создается новая запись
        if (!$this->id) {
            // Изображения по умолчанию пустая строка json
            if (!isset($this->images)) {
                $this->images = '{}';
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
        return parent::store($updateNulls);
    }
}
