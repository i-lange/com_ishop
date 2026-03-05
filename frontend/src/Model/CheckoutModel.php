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

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Модель оформления заказа
 * @since 1.0.0
 */
class CheckoutModel extends BaseDatabaseModel
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
        $params	= $app->getParams();
        $this->setState('params', $params);

        // Устанавливаем параметры из запроса

        $pks = $app->getUserStateFromRequest('com_ishop.checkout.products', 'products');
        if (!empty($pks)) {
            $this->setState('checkout.list', $pks);
        }
    }

    /**
     * Метод получения данных заказа
     *
     * @return object данные текущего заказа
     * @throws \Exception
     * @since 1.0.0
     */
    public function getCheckout()
    {
        $cart = $this->getMVCFactory()->createModel('Cart', 'Site');
        $pks = $this->getState('checkout.list', []);

        $checkout = $cart->getCart($pks, false);

        $date = new \DateTime();
        foreach ($checkout->products as $i => $product) {
            if (!$product->available) {
                unset($checkout->products[$i]);
                continue;
            }

            $current = new \DateTime($product->delivery_date);
            if ($current > $date) {
                $date = $current;
            }
        }

        $checkout->delivery_date = $date;

        return $checkout;
    }

    /**
     * Метод получения текущей зоны доставки
     *
     * @return object данные текущей зоны доставки
     * @throws \Exception
     * @since 1.0.0
     */
    public function getZone()
    {
        return $this->getMVCFactory()->createModel('Zones', 'Site')->getZone();
    }

    /**
     * Метод получения доступных способов доставки
     *
     * @return array список способов доставки
     * @throws \Exception
     * @since 1.0.0
     */
    public function getDeliveries()
    {
        $deliveries = $this->getMVCFactory()->createModel('Deliveries', 'Site');
        return $deliveries->getItems();
    }

    /**
     * Метод получения доступных способов оплаты
     *
     * @return array список способов оплаты
     * @throws \Exception
     * @since 1.0.0
     */
    public function getPayments()
    {
        $payments = $this->getMVCFactory()->createModel('Payments', 'Site');
        return $payments->getItems();
    }

    /**
     * Метод получения списка ПВЗ
     *
     * @return array список ПВЗ
     * @throws \Exception
     * @since 1.0.0
     */
    public function getWarehouses()
    {
        $warehouses = $this->getMVCFactory()->createModel('Warehouses', 'Site');
        return $warehouses->getItems();
    }
}
