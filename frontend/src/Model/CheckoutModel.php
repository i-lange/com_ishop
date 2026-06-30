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

use Ilange\Component\Ishop\Site\Service\PromoCodeService;
use Exception;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
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

        // Устанавливаем список товаров checkout только из массива ID.
        $stored = $app->getUserState('com_ishop.checkout.products', []);
        $requestProducts = $app->getInput()->get('products', null, 'raw');
        $pks = is_array($requestProducts) ? $requestProducts : $stored;

        if (!empty($pks) && is_array($pks)) {
            $pks = array_values(array_filter(array_map('intval', $pks)));
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

        $checkout = $cart->getCart($pks, false, false);

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

    /**
     * Сохраняет заказ по данным checkout, пересчитанным на стороне сервера.
     *
     * @param   array  $data  Данные формы оформления заказа
     *
     * @return  array  Данные сохраненного заказа для JSON-ответа
     * @throws  Exception
     * @since   1.0.11
     */
    public function saveOrder(array $data): array
    {
        $checkout = $this->getPreparedCheckout();

        $this->validateOrderData($data);

        $payment = $this->findPayment($data['payment'] ?? '');
        $delivery = $this->findDelivery($data['shipping'] ?? '');
        $deliveryData = $this->buildDeliveryData($delivery, $data);
        $paymentData = $this->buildPaymentData($payment);

        $discount = null;
        $code = trim((string) ($data['code'] ?? ''));
        $promoCodeService = new PromoCodeService($this->getDatabase());

        if ($code === '') {
            $code = $promoCodeService->getStoredCode();
        }

        if ($code !== '') {
            $discount = $promoCodeService->calculatePromoCode($code, $checkout);
            $promoCodeService->storeCode($discount['code']);
        }

        $orderData = $this->buildOrderData($data, $checkout, $paymentData, $deliveryData, $discount);
        $table = $this->getMVCFactory()->createTable('Order', 'Administrator', ['dbo' => $this->getDatabase()]);

        if (!$table->save($orderData)) {
            throw new Exception($table->getError() ?: Text::_('COM_ISHOP_CHECKOUT_SAVE_ERROR'), 500);
        }

        (new PromoCodeService($this->getDatabase()))->clearCode();

        return [
            'orderId'  => (int) $table->id,
            'summary'  => $this->buildSummaryData($checkout, $discount),
            'products' => $orderData['products'],
            'payment'  => $paymentData,
            'delivery' => $deliveryData,
            'discount' => $discount,
        ];
    }

    /**
     * Применяет промокод к текущему checkout и сохраняет код в user state.
     *
     * @param   string  $code  Промокод покупателя
     *
     * @return  array  Результат применения промокода
     * @throws  Exception
     * @since   1.0.11
     */
    public function applyCode(string $code): array
    {
        $promoCodeService = new PromoCodeService($this->getDatabase());
        $promoCodeService->clearCode();

        $checkout = $this->getPreparedCheckout();
        $discount = $promoCodeService->calculatePromoCode($code, $checkout);

        $promoCodeService->storeCode($discount['code']);

        return $discount;
    }

    /**
     * Возвращает текущий checkout и проверяет наличие товаров к заказу.
     *
     * @return object Данные checkout
     * @throws Exception
     * @since 1.0.11
     */
    private function getPreparedCheckout(): object
    {
        $checkout = $this->getCheckout();

        if (empty($checkout->products)) {
            throw new Exception(Text::_('COM_ISHOP_CHECKOUT_EMPTY'), 400);
        }

        return $checkout;
    }

    /**
     * Проверяет обязательные поля формы заказа.
     *
     * @param array $data Данные формы оформления заказа
     *
     * @return void
     * @throws Exception
     * @since 1.0.11
     */
    private function validateOrderData(array $data): void
    {
        if (trim((string) ($data['phone'] ?? '')) === '') {
            throw new Exception(Text::_('COM_ISHOP_CHECKOUT_PHONE_REQUIRED'), 400);
        }

        if (empty($data['confirm'])) {
            throw new Exception(Text::_('COM_ISHOP_CHECKOUT_CONFIRM_REQUIRED'), 400);
        }

        if (trim((string) ($data['payment'] ?? '')) === '') {
            throw new Exception(Text::_('COM_ISHOP_CHECKOUT_PAYMENT_REQUIRED'), 400);
        }

        if (trim((string) ($data['shipping'] ?? '')) === '') {
            throw new Exception(Text::_('COM_ISHOP_CHECKOUT_DELIVERY_REQUIRED'), 400);
        }
    }

    /**
     * Находит выбранный способ оплаты среди опубликованных способов.
     *
     * @param string $value Значение из формы оплаты
     *
     * @return object Данные способа оплаты
     * @throws Exception
     * @since 1.0.11
     */
    private function findPayment(string $value): object
    {
        $value = $this->normalizeString($value);

        foreach ($this->getPayments() as $payment) {
            if ($this->normalizeString($payment->title) === $value ||
                $this->normalizeString($payment->alias) === $value) {
                return $payment;
            }
        }

        throw new Exception(Text::_('COM_ISHOP_CHECKOUT_PAYMENT_INVALID'), 400);
    }

    /**
     * Находит выбранный способ доставки среди опубликованных способов.
     *
     * @param string $value Значение из формы доставки
     *
     * @return object Данные способа доставки
     * @throws Exception
     * @since 1.0.11
     */
    private function findDelivery(string $value): object
    {
        $value = $this->normalizeString($value);

        foreach ($this->getDeliveries() as $delivery) {
            if ($this->normalizeString($delivery->title) === $value ||
                $this->normalizeString($delivery->alias) === $value) {
                return $delivery;
            }
        }

        throw new Exception(Text::_('COM_ISHOP_CHECKOUT_DELIVERY_INVALID'), 400);
    }

    /**
     * Находит выбранный ПВЗ среди опубликованных складов.
     *
     * @param string $value Значение из формы ПВЗ
     *
     * @return object Данные ПВЗ
     * @throws Exception
     * @since 1.0.11
     */
    private function findWarehouse(string $value): object
    {
        $value = $this->normalizeString($value);

        foreach ($this->getWarehouses() as $warehouse) {
            if ($this->normalizeString($warehouse->title) === $value ||
                $this->normalizeString($warehouse->alias) === $value ||
                $this->normalizeString($warehouse->address) === $value) {
                return $warehouse;
            }
        }

        throw new Exception(Text::_('COM_ISHOP_CHECKOUT_POINT_INVALID'), 400);
    }

    /**
     * Подготавливает данные способа оплаты для хранения в заказе.
     *
     * @param object $payment Данные способа оплаты
     *
     * @return array Данные оплаты
     * @since 1.0.11
     */
    private function buildPaymentData(object $payment): array
    {
        return [
            'id'    => (int) $payment->id,
            'title' => (string) $payment->title,
            'alias' => (string) $payment->alias,
        ];
    }

    /**
     * Подготавливает данные доставки и получателя для хранения в заказе.
     *
     * @param object $delivery Данные способа доставки
     * @param array  $data     Данные формы оформления заказа
     *
     * @return array Данные доставки
     * @throws Exception
     * @since 1.0.11
     */
    private function buildDeliveryData(object $delivery, array $data): array
    {
        $result = [
            'id'      => (int) $delivery->id,
            'title'   => (string) $delivery->title,
            'alias'   => (string) $delivery->alias,
            'point'   => (int) $delivery->point,
            'client'  => [
                'name'  => trim((string) ($data['name'] ?? '')),
                'phone' => trim((string) ($data['phone'] ?? '')),
            ],
            'address' => trim((string) ($data['address'] ?? '')),
        ];

        if ((int) $delivery->point === 1) {
            if (trim((string) ($data['point'] ?? '')) === '') {
                throw new Exception(Text::_('COM_ISHOP_CHECKOUT_POINT_REQUIRED'), 400);
            }

            $warehouse = $this->findWarehouse((string) $data['point']);
            $result['address'] = (string) $warehouse->address;
            $result['warehouse'] = [
                'id'      => (int) $warehouse->id,
                'title'   => (string) $warehouse->title,
                'alias'   => (string) $warehouse->alias,
                'address' => (string) $warehouse->address,
            ];

            return $result;
        }

        if ($result['address'] === '') {
            throw new Exception(Text::_('COM_ISHOP_CHECKOUT_ADDRESS_REQUIRED'), 400);
        }

        return $result;
    }

    /**
     * Подготавливает массив товаров для хранения в заказе.
     *
     * @param object $checkout Данные checkout
     *
     * @return array Товары заказа
     * @since 1.0.11
     */
    private function buildProductsData(object $checkout): array
    {
        $products = [];

        foreach ($checkout->products as $product) {
            $quantity = (int) ($product->count ?? 1);
            $price = $this->getProductPrice($product);

            $products[] = [
                'id'                 => (int) $product->id,
                'title'              => (string) ($product->title ?? ''),
                'fullname'           => trim((string) ($product->fullname ?? $product->title ?? '')),
                'gtin'               => (string) ($product->gtin ?? ''),
                'catid'              => (int) ($product->catid ?? 0),
                'category_title'     => (string) ($product->category_title ?? ''),
                'manufacturer_id'    => (int) ($product->manufacturer_id ?? 0),
                'manufacturer_title' => (string) ($product->manufacturer_title ?? ''),
                'supplier_id'        => (int) ($product->supplier_id ?? 0),
                'supplier_title'     => (string) ($product->supplier_title ?? ''),
                'price'              => $this->roundPrice((float) ($product->price ?? 0)),
                'old_price'          => $this->roundPrice((float) ($product->old_price ?? 0)),
                'sale_price'         => $this->roundPrice((float) ($product->sale_price ?? 0)),
                'checkout_price'     => $this->roundPrice($price),
                'quantity'           => $quantity,
                'total'              => $this->roundPrice($price * $quantity),
                'delivery_date'      => (string) ($product->delivery_date ?? ''),
            ];
        }

        return $products;
    }

    /**
     * Собирает данные заказа для таблицы #__ishop_orders.
     *
     * @param array      $data         Данные формы оформления заказа
     * @param object     $checkout     Данные checkout
     * @param array      $paymentData  Данные оплаты
     * @param array      $deliveryData Данные доставки
     * @param array|null $discount     Данные промокода
     *
     * @return array Данные заказа
     * @throws Exception
     * @since 1.0.11
     */
    private function buildOrderData(
        array $data,
        object $checkout,
        array $paymentData,
        array $deliveryData,
        ?array $discount
    ): array {
        $app = Factory::getApplication();
        $date = Factory::getDate();
        $dateSql = $date->toSql();
        $user = $app->getIdentity();
        $ishopUser = $this->getMVCFactory()->createModel('User', 'Site')->getItem();

        return [
            'title'            => Text::sprintf('COM_ISHOP_CHECKOUT_ORDER_TITLE', $date->format('Y-m-d H:i:s')),
            'alias'            => 'order-' . $date->format('Y-m-d-H-i-s') . '-' . random_int(1000, 9999),
            'state'            => 1,
            'ishop_user_id'    => (int) ($ishopUser->id ?? 0),
            'products'         => $this->buildProductsData($checkout),
            'discounts'        => $discount ? [$discount] : [],
            'payment'          => $paymentData,
            'delivery'         => $deliveryData,
            'created'          => $dateSql,
            'created_by'       => (int) $user->id,
            'created_by_alias' => trim((string) ($data['name'] ?? '')),
            'canceled'         => '1000-01-01 00:00:00',
            'modified'         => $dateSql,
            'modified_by'      => (int) $user->id,
            'access'           => (int) $app->get('access', 1),
        ];
    }

    /**
     * Возвращает итоговые суммы checkout с учетом промокода.
     *
     * @param object     $checkout Данные checkout
     * @param array|null $discount Данные промокода
     *
     * @return array Суммы заказа
     * @since 1.0.11
     */
    private function buildSummaryData(object $checkout, ?array $discount): array
    {
        $discountAmount = $discount ? (float) $discount['amount'] : 0.0;

        return [
            'total'         => $this->roundPrice((float) $checkout->total),
            'cartDiscount'  => $this->roundPrice((float) $checkout->total_discount),
            'promoDiscount' => $this->roundPrice($discountAmount),
            'before'        => $this->roundPrice((float) $checkout->summary),
            'after'         => $this->roundPrice(max((float) $checkout->summary - $discountAmount, 0)),
            'currency'      => $this->getCurrency(),
        ];
    }

    /**
     * Возвращает цену товара, используемую в checkout.
     *
     * @param object $product Данные товара
     *
     * @return float Цена товара
     * @since 1.0.11
     */
    private function getProductPrice(object $product): float
    {
        return ((float) ($product->sale_price ?? 0) > 0)
            ? (float) $product->sale_price
            : (float) ($product->price ?? 0);
    }

    /**
     * Нормализует строковое значение для сравнения данных формы.
     *
     * @param string $value Строка
     *
     * @return string Нормализованная строка
     * @since 1.0.11
     */
    private function normalizeString(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    /**
     * Округляет денежное значение по настройкам компонента.
     *
     * @param float $value Значение
     *
     * @return float Округленное значение
     * @since 1.0.11
     */
    private function roundPrice(float $value): float
    {
        $params = ComponentHelper::getParams('com_ishop');

        return round($value, (int) $params->get('roundPrice', 0));
    }

    /**
     * Возвращает код валюты checkout из настроек компонента.
     *
     * @return string Код валюты
     * @since 1.0.11
     */
    private function getCurrency(): string
    {
        $params = ComponentHelper::getParams('com_ishop');

        return mb_strtoupper((string) $params->get('defaultCurrency', 'BYN'));
    }
}
