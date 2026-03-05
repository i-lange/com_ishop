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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Registry\Registry;

/**
 * Модель корзины com_iShop
 * @since 1.0.0
 */
class CartModel extends BaseDatabaseModel
{
    /**
     * Метод для автоматического заполнения модели
     * Вызов getState в этом методе приведет к рекурсии
     * @return void
     * @throws \Exception
     * @since 1.0.0
     */
    protected function populateState($ordering = null, $direction = null)
    {
        $app = Factory::getApplication();
        $this->setState('filter.language', Multilanguage::isEnabled());

        $params	= $app->getParams();
        $this->setState('params', $params);

        // Фильтрация по уровню доступа
        $this->setState('filter.access', true);

        // Фильтрация по состоянию публикации
        $this->setState('filter.published', 1);

        // Количество товаров на странице, все
        $this->setState('list.limit', 0);
    }

    /**
     * Метод получения количества товаров в корзине
     *
     * @return int Объект данных
     * @throws \Exception
     * @since 1.0.0
     */
    public function getCount()
    {
        return array_sum($this->getCartList());
    }

    /**
     * Метод получения списка идентификаторов товаров в корзине
     *
     * @return array Массив идентификаторов
     * @throws \Exception
     * @since 1.0.0
     */
    public function getCartList()
    {
        // Получаем данные пользователя
        $userData = $this->getMVCFactory()->createModel('User', 'Site')->getItem();

        if (empty($userData) || empty($userData->cart)) {
            return [];
        }

        return $userData->cart;
    }

    /**
     * Метод получения данных списка товаров в корзине
     *
     * @return array данные списка товаров в корзине
     * @throws \Exception
     * @since 1.0.0
     */
    public function getProducts(array $pks, bool $full = false)
    {
        if (empty($pks)) {
            return [];
        }

        $model = $this->getMVCFactory()->createModel('Products', 'Site', ['ignore_request' => true]);
        $model->setState('filter.warehouse_id', false);
        $model->setState('params', Factory::getApplication()->getParams());

        $model->setState('filter.published', $this->getState('filter.published'));
        $model->setState('filter.access', $this->getState('filter.access'));
        $model->setState('filter.language', $this->getState('filter.language'));

        // Добавляем фильтрацию по списку товаров в корзине
        $model->setState('filter.products', $pks);

        $model->setState('list.ordering', 'FIELD(a.id, '. implode(',', $pks) . ')');
        $model->setState('list.direction', '');
        $model->setState('list.limit', $this->getState('list.limit'));

        if (!$full) {
            $db = $this->getDatabase();
            $model->setState(
                'list.select',
                [
                    $db->quoteName('a.id'),
                    $db->quoteName('a.title'),
                    $db->quoteName('a.stock'),
                    $db->quoteName('a.catid'),
                    $db->quoteName('a.manufacturer_id'),
                    $db->quoteName('a.price'),
                    $db->quoteName('a.old_price'),
                    $db->quoteName('a.sale_price'),
                    $db->quoteName('a.cost_price'),
                    $db->quoteName('a.delivery'),
                    $db->quoteName('a.gtin'),
                    $db->quoteName('a.attribs'),
                ]
            );
        }

        return $model->getItems();
    }

    /**
     * Метод возвращает текущую зону доставки
     *
     * @return array Объект данных при успехе, иначе false
     * @throws \Exception
     * @since 1.0.0
     */
    public function getZone()
    {
        return $this->getMVCFactory()->createModel('Zones', 'Site')->getZone();
    }

    /**
     * Метод получения данных корзины текущего пользователя
     *
     * @return object Объект данных
     * @throws \Exception
     * @since 1.0.0
     */
    public function getCart(array $filter = [], bool $full = true)
    {
        $cart = new \stdClass();
        $cart->products = [];
        $cart->total = $cart->total_discount = $cart->summary = $cart->count = 0;
        $list = $this->getCartList();
        $pks = (!empty($filter)) ? $filter : array_keys($list);

        if (empty($pks)) {
            return $cart;
        }

        $products = $this->getProducts($pks, $full);

        foreach ($products as $product) {
            // количество данного товара в корзине
            if (empty($list[$product->id])) {
                $count = 1;
            } else {
                $count = $list[$product->id];
            }

            // рассчитываем цены только для товаров,
            // которые доступны к заказу
            if ($product->available) {

                // выбираем цену для расчета (со скидкой, если есть)
                $price = ($product->sale_price > 0) ? $product->sale_price : $product->price;
                // итоговая сумма по корзине
                $cart->summary += ($price * $count);
                // итоговая сумма по товару
                $product->incart_total = ($price * $count);

                // Если у товара установлена старая цена,
                // нужно рассчитать размер скидки
                if ($product->old_price > 0) {
                    // итоговая сумма без скидки по товару
                    $product->incart_old_total = ($product->old_price * $count);
                    // итоговая сумма без скидки по корзине
                    $cart->total += $product->incart_old_total;
                    // итоговый размер скидки по корзине
                    $cart->total_discount += ($product->incart_old_total - $product->incart_total);
                } else {
                    // итоговая сумма без скидки по корзине
                    $cart->total += ($product->price * $count);
                }

                $product->count = $count;
            } else {
                // Если товар в корзине недоступен к заказу,
                // установим принудительно его количество = 1
                $count = 1;
            }

            $cart->count += $count;
            $cart->products[$product->id] = $product;
        }

        return $cart;
    }

    /**
     * Добавляет товар в корзину по его идентификатору
     *
     * @param   int  $id        Идентификатор товара
     * @param   int  $quantity  Количество товара для добавления
     *
     * @return object|false объект с данными корзины
     * @throws \Exception
     * @since 1.0.0
     */
    public function cartAdd(int $id = 0, int $quantity = 1)
    {
        if (!$id) {
            return false;
        }

        $params = ComponentHelper::getParams('com_ishop');
        // Проверим, включена ли корзина товаров пользователя
        // в настройках компонента
        $use_cart = $params->get('use_cart', false);
        if (!$use_cart) {
            return false;
        }

        // Получим значение максимального числа
        // товаров в корзине пользователя
        $max = $params->get('cart_max_count', 999);
        if (!$max) {
            return false;
        }

        $user = $this->getMVCFactory()->createModel('User', 'Site');
        $data = $user->getItem();
        if ($data === false) {
            return false;
        }

        $cart = (new Registry($data->cart))->toArray();
        if (empty($cart) && $quantity > 0) {
            // Корзина была пуста, теперь в ней товар
            $cart = [$id => $quantity];
        } elseif (in_array($id, array_keys($cart))) {
            // Итоговое значение
            $quantity += $cart[$id];
            // Удаляем товар и корзины
            unset($cart[$id]);
            // Добавляем его вновь уже в начало списка
            $cart = [$id => $quantity] + $cart;
        } elseif (count($cart) < $max) {
            // Добавляем новый товар всегда в начало списка
            $cart = [$id => $quantity] + $cart;
        }

        // Сохраняем корзину пользователя
        $data->cart = (string) new Registry($cart);
        $user->setData($data, 'cart');

        // Возвращаем данные обновленной корзины
        return $this->getCart([], false);
    }

    /**
     * Изменяет количество товаров в корзине по его идентификатору
     *
     * @param int $id Идентификатор товара
     * @param int $quantity Количество
     *
     * @return object|false объект с данными корзины
     * @throws \Exception
     * @since 1.0.0
     */
    public function cartChange(int $id = 0, int $quantity = 1)
    {
        if (!$id) {
            return false;
        }

        $params = ComponentHelper::getParams('com_ishop');
        // Проверим, включена ли корзина товаров пользователя
        // в настройках компонента
        $use_cart = $params->get('use_cart', false);
        if (!$use_cart) {
            return false;
        }

        if ($quantity > 0) {
            // Если количество больше нуля,
            // просто добавляем товар в корзину
            return $this->cartAdd($id, $quantity);
        } elseif ($quantity === 0) {
            // Если количество равно нулю,
            // просто удаляем товар из корзины
            return $this->cartRemove($id);
        }

        // Если количество меньше нуля,
        // нужно попытаться уменьшить
        $user = $this->getMVCFactory()->createModel('User', 'Site');
        $data = $user->getItem();
        if ($data === false) {
            return false;
        }

        $cart = (new Registry($data->cart))->toArray();

        // Если корзина пуста или в корзине нет такого товара,
        // ничего делать не нужно
        if (empty($cart) || empty($cart[$id])) {
            // Возвращаем данные корзины
            return $this->getCart([], false);
        }

        // Если товар в корзине есть,
        // но при уменьшении будет нуль
        // или отрицательное число, просто удаляем товар
        if (($result = $cart[$id] + $quantity) <= 0) {
            return $this->cartRemove($id);
        }

        $cart[$id] = $result;
        // Сохраняем корзину пользователя
        $data->cart = (string) new Registry($cart);
        $user->setData($data, 'cart');

        // Возвращаем данные обновленной корзины
        return $this->getCart([], false);
    }

    /**
     * Удаляет товар в корзине по его идентификатору
     * или очищает корзину, если id не задан
     *
     * @param int $id Идентификатор товара
     *
     * @return object|false объект с данными корзины
     * @throws \Exception
     * @since 1.0.0
     */
    public function cartRemove(int $id = 0)
    {
        $params = ComponentHelper::getParams('com_ishop');
        // Проверим, включена ли корзина товаров пользователя
        // в настройках компонента
        $use_cart = $params->get('use_cart', false);
        if (!$use_cart) {
            return false;
        }

        $user = $this->getMVCFactory()->createModel('User', 'Site');
        $data = $user->getItem();
        if ($data === false) {
            return false;
        }

        $cart = (new Registry($data->cart))->toArray();

        // Если не указан идентификатор товара,
        // очищаем всю корзину
        if (!$id || empty($cart)) {
            $data->cart = (string) new Registry([]);
            $user->setData($data, 'cart');

            // Возвращаем данные обновленной корзины
            return $this->getCart([], false);
        }

        if (in_array($id, array_keys($cart))) {
            unset($cart[$id]);
        }

        // Сохраняем корзину пользователя
        $data->cart = (string) new Registry($cart);
        $user->setData($data, 'cart');

        // Возвращаем данные обновленной корзины
        return $this->getCart([], false);
    }
}
