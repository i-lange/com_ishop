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
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseInterface;

/**
 * Таблица карты значений характеристик
 * @since 1.0.0
 */
class MapTable extends Table
{
    /**
     * Конструктор
     * @param   DatabaseInterface  $db  Объект подключения базы данных
     * @since 1.0.0
     */
	public function __construct(DatabaseInterface $db)
	{
        $this->typeAlias = 'com_ishop.map';
		parent::__construct('#__ishop_fields_map', 'id', $db);
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

        // Проверяем идентификатор товара
        if (!$this->product_id) {
            throw new InvalidArgumentException('Не указан ID товара');
        }

        // Проверяем идентификатор характеристики
        if (!$this->field_id) {
            throw new InvalidArgumentException('Не указан ID характеристики');
        }

        // Проверяем значение характеристики
        if (is_null($this->value)) {
            throw new InvalidArgumentException('Не указано значение характеристики');
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
