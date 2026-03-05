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
     * Метод для автоматического заполнения модели
     * Вызов getState в этом методе приведет к рекурсии
     * @return void
     * @throws \Exception
     * @since 1.0.0
     */
    protected function populateState()
    {
        $app = Factory::getApplication();

        // Проверяем, существует ли такой пользователь в базе
        $user_pass = $app->getInput()->cookie->get('ishop_user', '');
        $user_exist = $this->getTable()->load(['pass' => $user_pass]);
        if (empty($user_pass) || !$user_exist) {
            $user_pass = $this->createUser();
        }
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
        }
        elseif ($pk) {
            // Если есть идентификатор записи в таблице
            $where = ['id' => $pk];
        } else {
            return false;
        }

        $user_data = $this->getTable();
        if (!$user_data->load($where)) {
            return false;
        }

        // Проверяем, не изменил ли пользователь зону доставки
        $active_zone = $this->getState('active_zone', 0);
        if ($active_zone && ($active_zone !== $user_data->zone_id)) {
            $user_data->zone_id = $active_zone;
            $this->setData($user_data, 'zone_id');
        }

        // Если нашли оба идентификатора, но user_id Joomla не записан,
        // нужно дополнить все записи идентификатором Joomla
        if ($user_pass && $user_id && empty($user_data->user_id)) {
            $user_data->pass = $user_pass;
            $this->setData($user_data, 'user_id');
        }
        // Если нашли оба идентификатора, но user_id Joomla
        // не совпадает с текущим - обновим данные по значению pass
        elseif ($user_pass && $user_id && ($user_id !== $user_data->user_id)) {
            $user_data->pass = $user_pass;
            $user_data->user_id = $user_id;
            $this->setData($user_data, 'all');
        }

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

        // преобразуем сериализованные данные в массивы
        $user_data->cart = (new Registry($user_data->cart))->toArray();
        $user_data->wishlist = (new Registry($user_data->wishlist))->toArray();
        $user_data->compare = (new Registry($user_data->compare))->toArray();
        $user_data->viewed = (new Registry($user_data->viewed))->toArray();

        return $user_data;
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
            $query->set($db->qn('user_id')  . ' = ' . $data->user_id);
            $query->set($db->qn('zone_id')  . ' = ' . $data->zone_id);
            $query->set($db->qn('wishlist') . ' = ' . $db->q($data->wishlist));
            $query->set($db->qn('compare')  . ' = ' . $db->q($data->compare));
            $query->set($db->qn('viewed')   . ' = ' . $db->q($data->viewed));
            $query->set($db->qn('cart')     . ' = ' . $db->q($data->cart));
        } elseif ($type === 'user_id') {
            $query->set($db->qn('user_id')  . ' = ' . $data->user_id);
        } elseif ($type === 'zone_id') {
            $query->set($db->qn('zone_id')  . ' = ' . $data->zone_id);
        } elseif ($type === 'wishlist') {
            $query->set($db->qn('wishlist') . ' = ' . $db->q($data->wishlist));
        } elseif ($type === 'compare') {
            $query->set($db->qn('compare')  . ' = ' . $db->q($data->compare));
        } elseif ($type === 'viewed') {
            $query->set($db->qn('viewed')   . ' = ' . $db->q($data->viewed));
        } else {
            $query->set($db->qn('cart')     . ' = ' . $db->q($data->cart));
        }
        $query->set('modified = ' . $db->q($date));

        // Обновить нужно во всех записях,
        // которые связаны либо по id, либо по pass, либо по user_id
        $where = $db->qn('id') . ' = ' . $data->id;
        if (!empty($data->pass)) {
            $where .= ' OR ' . $db->qn('pass') . ' = ' . $db->q($data->pass);
        }
        if ($data->user_id > 0) {
            $where .= ' OR ' . $db->qn('user_id') . ' = ' . $data->user_id;
        }
        $query->where($where);
        $db->setQuery($query);

        try {
            $db->execute();
        } catch (RuntimeException $e) {
            return false;
        }

        return true;
    }
}
