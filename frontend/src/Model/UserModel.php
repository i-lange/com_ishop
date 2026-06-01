<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Site\Model;

defined('_JEXEC') or die;

use Ilange\Component\Ishop\Site\Helper\PriceHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ItemModel;
use Joomla\CMS\User\UserHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;
use RuntimeException;

/**
 * Модель пользователя iShop
 * @since 1.0.0
 */
class UserModel extends ItemModel
{
    /**
     * Строка контекста модели
     * @var string
     * @since 1.0.0
     */
    protected $_context = 'com_ishop.user';

    /**
     * Загруженные данные текущего пользователя в рамках одного HTTP-запроса.
     *
     * @var object|false|null
     * @since 1.0.0
     */
    protected static $currentItem = null;

    /**
     * Флаг завершенной попытки загрузки текущего пользователя.
     *
     * @var bool
     * @since 1.0.0
     */
    protected static bool $currentItemLoaded = false;

    /**
     * Идентификатор покупателя из cookie или из созданной записи.
     *
     * @var string
     * @since 1.0.0
     */
    protected static string $currentPass = '';

    /**
     * Метод для автоматического заполнения модели
     * Вызов getState в этом методе приведет к рекурсии
     * @return void
     * @throws \Exception
     * @since 1.0.0
     */
    protected function populateState()
    {
        $app = Factory::getApplication();

        $user_pass = self::$currentPass ?: $app->getInput()->cookie->get('ishop_user', '');
        $this->setState('user.pass', $user_pass);

        // Текущая зона доставки
        $active_zone = $app->getInput()->getUint('active_zone', 0);
        $this->setState('active_zone', $active_zone);

        // Загружаем параметры компонента
        $params = $app->getParams();
        $this->setState('params', $params);
    }

    /**
     * Метод получения данных записи пользователя
     * @param int $pk Идентификатор записи
     * @return object|bool Объект данных записи при успехе, иначе false
     * @throws \Exception
     * @since 1.0.0
     */
    public function getItem($pk = null)
    {
        $pk = $pk ? (int) $pk : null;

        if ($pk === null && self::$currentItemLoaded) {
            if (self::$currentItem === false) {
                return false;
            }

            $this->syncCurrentItem(self::$currentItem);

            return clone self::$currentItem;
        }

        $user_id = $this->getCurrentUser()->id;

        // Уникальный идентификатор покупателя из cookie,
        // этот идентификатор имеет приоритет
        // перед идентификатором пользователя Joomla
        $user_pass = $this->getState('user.pass');

        $app	= Factory::getApplication();
        $config	= $app->getConfig();

        if (!empty($user_pass)) {
            // Если уже был установлен идентификатор пользователя ishop_user
            $where = ['pass' => $user_pass];
        } elseif ($user_id) {
            // Если ishop_user не установлен, но пользователь Joomla авторизован
            $where = ['user_id' => $user_id];
        } elseif ($pk) {
            // Если есть идентификатор записи в таблице
            $where = ['id' => $pk];
        } else {
            $where = null;
        }

        $user_data = $this->getTable();
        $loaded = $where ? $user_data->load($where) : false;

        // Если cookie устарела, для авторизованного пользователя
        // можно восстановить профиль по Joomla user_id.
        if (!$loaded && !empty($user_pass) && $user_id) {
            $loaded = $user_data->load(['user_id' => $user_id]);
        }

        // Для текущего покупателя без найденного профиля создаем новую запись.
        if (!$loaded && $pk === null) {
            $user_pass = $this->createUser();

            if ($user_pass) {
                self::$currentPass = $user_pass;
                $this->setState('user.pass', $user_pass);
                $loaded = $user_data->load(['pass' => $user_pass]);
            }
        }

        if (!$loaded) {
            if ($pk === null) {
                self::$currentItem = false;
                self::$currentItemLoaded = true;
            }

            return false;
        }

        $this->syncCurrentItem($user_data);

        // Устанавливаем данные пользователя в cookie,
        // если $user_password пуст или найден другой $user_password
        // с более свежими данными
        if (!$user_pass || ($user_pass !== $user_data->pass)) {
            $app->getInput()->cookie->set(
                'ishop_user',
                $user_data->pass,
                [
                    'expires'  => time() + 60 * 60 * 24 * 365,
                    'path'     => $config->get('cookie_path', '/'),
                    'domain'   => $config->get('cookie_domain', '')
                ]
            );
        }

        self::$currentPass = $user_data->pass;
        $this->normaliseUserData($user_data);

        if ($pk === null) {
            self::$currentItem = clone $user_data;
            self::$currentItemLoaded = true;
        }

        return clone $user_data;
    }

    /**
     * Метод создает нового пользователя
     *
     * @throws \Exception
     * @since 1.0.0
     */
    public function createUser()
    {
        $app	= Factory::getApplication();
        $config	= $app->getConfig();

        $params = ComponentHelper::getParams('com_ishop');
        $zone_id = $params->get('default_zone');

        $date = Factory::getDate()->toSql();
        // Создаем уникальный идентификатор пользователя
        $ishop_user = UserHelper::genRandomPassword(32);

        // Делаем новую запись в базе данных
        $user_data = $this->getTable();
        $ok = $user_data->save([
            'user_id' => 0,
            'zone_id' => $zone_id,
            'pass' => $ishop_user,
            'modified' => $date,
        ]);

        if (!$ok) {
            return false;
        }

        // Устанавливаем ishop_user в cookie
        $app->getInput()->cookie->set(
            'ishop_user',
            $ishop_user,
            [
                'expires'  => time() + 60 * 60 * 24 * 365,
                'path'     => $config->get('cookie_path', '/'),
                'domain'   => $config->get('cookie_domain', '')
            ]
        );

        return $ishop_user;
    }

    /**
     * Записываем набор данных пользователя
     *
     * @param object $data Данные пользователя
     * @param string $type Тип списка
     *
     * @return bool удалось ли записать
     * @throws \Exception
     * @since 1.0.0
     */
    public function setData(object $data, string $type = 'cart'): bool
    {
        $db		= Factory::getContainer()->get(DatabaseInterface::class);
        $date   = Factory::getDate()->toSql();
        $query  = $db->getQuery(true)->update('#__ishop_users');

        // Что будем обновлять указано в $type
        if ($type === 'all') {
            $query->set($db->qn('user_id')  . ' = ' . (int) $data->user_id);
            $query->set($db->qn('zone_id')  . ' = ' . (int) $data->zone_id);
            $query->set($db->qn('wishlist') . ' = ' . $db->q($this->registryToString($data->wishlist ?? [])));
            $query->set($db->qn('compare')  . ' = ' . $db->q($this->registryToString($data->compare ?? [])));
            $query->set($db->qn('viewed')   . ' = ' . $db->q($this->registryToString($data->viewed ?? [])));
            $query->set($db->qn('cart')     . ' = ' . $db->q($this->registryToString($data->cart ?? [])));
        } elseif ($type === 'user_id') {
            $query->set($db->qn('user_id')  . ' = ' . (int) $data->user_id);
        } elseif ($type === 'zone_id') {
            $query->set($db->qn('zone_id')  . ' = ' . (int) $data->zone_id);
        } elseif ($type === 'wishlist') {
            $query->set($db->qn('wishlist') . ' = ' . $db->q($this->registryToString($data->wishlist ?? [])));
        } elseif ($type === 'compare') {
            $query->set($db->qn('compare')  . ' = ' . $db->q($this->registryToString($data->compare ?? [])));
        } elseif ($type === 'viewed') {
            $query->set($db->qn('viewed')   . ' = ' . $db->q($this->registryToString($data->viewed ?? [])));
        } else {
            $query->set($db->qn('cart')     . ' = ' . $db->q($this->registryToString($data->cart ?? [])));
        }
        $query->set('modified = ' . $db->q($date));

        // Обновить нужно во всех записях,
        // которые связаны либо по id, либо по pass, либо по user_id
        $where = $db->qn('id') . ' = ' . (int) $data->id;
        if (!empty($data->pass)) {
            $where .= ' OR ' . $db->qn('pass') . ' = ' . $db->q($data->pass);
        }
        if ((int) $data->user_id > 0) {
            $where .= ' OR ' . $db->qn('user_id') . ' = ' . (int) $data->user_id;
        }
        $query->where($where);
        $db->setQuery($query);

        try {
            $db->execute();
        } catch (RuntimeException $e) {
            return false;
        }

        $this->updateCachedItem($data, $type, $date);

        return true;
    }

    /**
     * Синхронизирует данные текущего пользователя с контекстом запроса.
     *
     * @param object $user_data Данные пользователя
     *
     * @return void
     * @throws \Exception
     * @since 1.0.0
     */
    private function syncCurrentItem(object $user_data): void
    {
        $user_id = (int) $this->getCurrentUser()->id;
        $user_pass = (string) $this->getState('user.pass');

        // Проверяем, не изменил ли пользователь зону доставки.
        $active_zone = (int) $this->getState('active_zone', 0);
        if ($active_zone && ($active_zone !== (int) $user_data->zone_id)) {
            $user_data->zone_id = $active_zone;
            $this->setData($user_data, 'zone_id');
        }

        // Если нашли оба идентификатора, но user_id Joomla не записан,
        // нужно дополнить все записи идентификатором Joomla.
        if ($user_pass && $user_id && empty($user_data->user_id)) {
            $user_data->pass = $user_pass;
            $user_data->user_id = $user_id;
            $this->setData($user_data, 'user_id');
        }
        // Если нашли оба идентификатора, но user_id Joomla
        // не совпадает с текущим - обновим данные по значению pass.
        elseif ($user_pass && $user_id && ($user_id !== (int) $user_data->user_id)) {
            $user_data->pass = $user_pass;
            $user_data->user_id = $user_id;
            $this->setData($user_data, 'all');
        }
    }

    /**
     * Преобразует сериализованные списки пользователя в массивы.
     *
     * @param object $user_data Данные пользователя
     *
     * @return void
     * @since 1.0.0
     */
    private function normaliseUserData(object $user_data): void
    {
        $user_data->id = (int) $user_data->id;
        $user_data->user_id = (int) $user_data->user_id;
        $user_data->zone_id = (int) $user_data->zone_id;
        $user_data->cart = $this->registryToArray($user_data->cart ?? []);
        $user_data->wishlist = $this->registryToArray($user_data->wishlist ?? []);
        $user_data->compare = $this->registryToArray($user_data->compare ?? []);
        $user_data->viewed = $this->registryToArray($user_data->viewed ?? []);
    }

    /**
     * Обновляет кэш текущего пользователя после записи в базу.
     *
     * @param object $data Данные пользователя
     * @param string $type Тип списка
     * @param string $date Дата изменения
     *
     * @return void
     * @since 1.0.0
     */
    private function updateCachedItem(object $data, string $type, string $date): void
    {
        if (!self::$currentItemLoaded || self::$currentItem === false || !$this->isCachedItem($data)) {
            return;
        }

        if ($type === 'all' || $type === 'user_id') {
            self::$currentItem->user_id = (int) $data->user_id;
        }

        if ($type === 'all' || $type === 'zone_id') {
            self::$currentItem->zone_id = (int) $data->zone_id;
        }

        foreach (['cart', 'wishlist', 'compare', 'viewed'] as $field) {
            if ($type === 'all' || $type === $field) {
                self::$currentItem->$field = $this->registryToArray($data->$field ?? []);
            }
        }

        self::$currentItem->modified = $date;
    }

    /**
     * Проверяет, относится ли объект данных к текущему кэшированному профилю.
     *
     * @param object $data Данные пользователя
     *
     * @return bool
     * @since 1.0.0
     */
    private function isCachedItem(object $data): bool
    {
        if (!empty($data->id) && (int) $data->id === (int) self::$currentItem->id) {
            return true;
        }

        if (!empty($data->pass) && $data->pass === self::$currentItem->pass) {
            return true;
        }

        return !empty($data->user_id)
            && (int) $data->user_id > 0
            && (int) $data->user_id === (int) self::$currentItem->user_id;
    }

    /**
     * Преобразует значение Joomla Registry в массив.
     *
     * @param mixed $value Значение
     *
     * @return array
     * @since 1.0.0
     */
    private function registryToArray($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value instanceof Registry) {
            return $value->toArray();
        }

        return (new Registry($value ?: []))->toArray();
    }

    /**
     * Преобразует значение списка пользователя в строку для хранения.
     *
     * @param mixed $value Значение
     *
     * @return string
     * @since 1.0.0
     */
    private function registryToString($value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if ($value instanceof Registry) {
            return (string) $value;
        }

        return (string) new Registry($value ?: []);
    }
}
