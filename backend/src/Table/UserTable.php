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

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\CMS\User\UserHelper;
use Joomla\Database\DatabaseInterface;

/**
 * Таблица пользователя
 * @since 1.0.0
 */
class UserTable extends Table
{
    /**
     * Конструктор
     * @param   DatabaseInterface  $db  Объект подключения базы данных
     * @since 1.0.0
     */
	public function __construct(DatabaseInterface $db)
	{
        $this->typeAlias = 'com_ishop.user';
		parent::__construct('#__ishop_users', 'id', $db);
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

        // Устанавливаем некоторые поля по умолчанию,
        // если создается новая запись пользователя
        if (!$this->id) {
            // Пользователь Joomla по умолчанию не установлен
            if (!isset($this->user_id)) {
                $this->user_id = 0;
            }

            // Зона доставки по умолчанию не установлена
            if (!isset($this->zone_id)) {
                $this->zone_id = 0;
            }

            // Уникальный идентификатор по умолчанию
            if (!isset($this->pass)) {
                // Создаем уникальный идентификатор пользователя
                $this->pass = UserHelper::genRandomPassword(32);
            }

            // Товары по умолчанию пустая строка json
            if (!isset($this->cart)) {
                $this->cart = '{}';
            }

            // Категории по умолчанию пустая строка json
            if (!isset($this->wishlist)) {
                $this->wishlist = '{}';
            }

            // Производители по умолчанию пустая строка json
            if (!isset($this->viewed)) {
                $this->viewed = '{}';
            }

            // Поставщики по умолчанию пустая строка json
            if (!isset($this->modified)) {
                $this->modified = Factory::getDate()->toSql();
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
        if ($this->id) {
            // Устанавливаем автора изменений и дату редактирования
            $this->modified = Factory::getDate()->toSql();
        }

        return parent::store($updateNulls);
    }
}
