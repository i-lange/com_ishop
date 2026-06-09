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

use Exception;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\ParameterType;

/**
 * Модель оформления заказа
 * @since 1.0.0
 */
class CheckoutModel extends BaseDatabaseModel
{
    /**
     * Контекст user state для примененного промокода checkout.
     *
     * @var string
     * @since 1.0.11
     */
    private const PROMO_CODE_STATE = 'com_ishop.checkout.code';

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

        if ($code === '') {
            $code = $this->getStoredPromoCode();
        }

        if ($code !== '') {
            $discount = $this->calculatePromoCode($code, $checkout);
            $this->storePromoCode($discount['code']);
        }

        $orderData = $this->buildOrderData($data, $checkout, $paymentData, $deliveryData, $discount);
        $table = $this->getMVCFactory()->createTable('Order', 'Administrator', ['dbo' => $this->getDatabase()]);

        if (!$table->save($orderData)) {
            throw new Exception($table->getError() ?: Text::_('COM_ISHOP_CHECKOUT_SAVE_ERROR'), 500);
        }

        $this->clearPromoCode();

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
        $this->clearPromoCode();

        $checkout = $this->getPreparedCheckout();
        $discount = $this->calculatePromoCode($code, $checkout);

        $this->storePromoCode($discount['code']);

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
     * Находит, проверяет и рассчитывает промокод для текущего checkout.
     *
     * @param string $code     Промокод покупателя
     * @param object $checkout Данные checkout
     *
     * @return array Результат применения промокода
     * @throws Exception
     * @since 1.0.11
     */
    private function calculatePromoCode(string $code, object $checkout): array
    {
        if (trim($code) === '') {
            throw new Exception(Text::_('COM_ISHOP_CHECKOUT_CODE_REQUIRED'), 400);
        }

        $params = ComponentHelper::getParams('com_ishop');

        if (!$params->get('discounts_use', 0)) {
            throw new Exception(Text::_('COM_ISHOP_CHECKOUT_CODE_NOT_APPLICABLE'), 400);
        }

        foreach ($this->findPromoCodes($code) as $discount) {
            $result = $this->buildPromoCodeResult($discount, $checkout);

            if ($result !== null) {
                return $result;
            }
        }

        throw new Exception(Text::_('COM_ISHOP_CHECKOUT_CODE_NOT_APPLICABLE'), 400);
    }

    /**
     * Загружает опубликованные записи промокода из базы данных.
     *
     * @param string $code Промокод покупателя
     *
     * @return array Данные скидок
     * @throws Exception
     * @since 1.0.11
     */
    private function findPromoCodes(string $code): array
    {
        $code = trim($code);

        if ($code === '') {
            throw new Exception(Text::_('COM_ISHOP_CHECKOUT_CODE_REQUIRED'), 400);
        }

        $db = $this->getDatabase();
        $published = 1;
        $type = 2;
        $now = Factory::getDate()->toSql();
        $normalizedCode = mb_strtolower($code);

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('title'),
                $db->quoteName('code'),
                $db->quoteName('percent'),
                $db->quoteName('products'),
                $db->quoteName('cats'),
                $db->quoteName('manufacturers'),
                $db->quoteName('suppliers'),
                $db->quoteName('min_price'),
                $db->quoteName('min_amount'),
            ])
            ->from($db->quoteName('#__ishop_discounts'))
            ->where($db->quoteName('state') . ' = :published')
            ->where($db->quoteName('type') . ' = :type')
            ->where('LOWER(' . $db->quoteName('code') . ') = :code')
            ->where('(' . $db->quoteName('publish_up') . ' IS NULL OR ' . $db->quoteName('publish_up') . ' <= :publishUp)')
            ->where('(' . $db->quoteName('publish_down') . ' IS NULL OR ' . $db->quoteName('publish_down') . ' >= :publishDown)')
            ->bind(':published', $published, ParameterType::INTEGER)
            ->bind(':type', $type, ParameterType::INTEGER)
            ->bind(':code', $normalizedCode)
            ->bind(':publishUp', $now)
            ->bind(':publishDown', $now)
            ->order([
                $db->quoteName('percent') . ' DESC',
                $db->quoteName('ordering') . ' ASC',
                $db->quoteName('id') . ' ASC',
            ]);

        $db->setQuery($query);
        $discounts = $db->loadObjectList();

        if (empty($discounts)) {
            throw new Exception(Text::_('COM_ISHOP_CHECKOUT_CODE_NOT_FOUND'), 404);
        }

        return $discounts;
    }

    /**
     * Рассчитывает результат применения одной записи промокода.
     *
     * @param object $discount Данные промокода
     * @param object $checkout Данные checkout
     *
     * @return array|null Результат применения или null, если запись не подходит
     * @since 1.0.11
     */
    private function buildPromoCodeResult(object $discount, object $checkout): ?array
    {
        $percent = (float) $discount->percent;

        if ($percent <= 0) {
            return null;
        }

        if ((float) $discount->min_amount > 0 && (float) $checkout->summary < (float) $discount->min_amount) {
            return null;
        }

        $products = [];
        $amount = 0.0;

        foreach ($checkout->products as $product) {
            if (!$this->isProductAllowedByDiscount($product, $discount)) {
                continue;
            }

            $quantity = (int) ($product->count ?? 1);
            $price = $this->getProductPrice($product);
            $lineTotal = $price * $quantity;
            $lineDiscount = $this->roundPrice($lineTotal * $percent / 100);
            $amount += $lineDiscount;

            $products[] = [
                'id'       => (int) $product->id,
                'quantity' => $quantity,
                'price'    => $this->roundPrice($price),
                'total'    => $this->roundPrice($lineTotal),
                'discount' => $lineDiscount,
            ];
        }

        $amount = $this->roundPrice($amount);

        if ($amount <= 0 || empty($products)) {
            return null;
        }

        return [
            'id'       => (int) $discount->id,
            'title'    => (string) $discount->title,
            'code'     => (string) $discount->code,
            'percent'  => $this->roundPrice($percent),
            'amount'   => $amount,
            'products' => $products,
            'summary'  => [
                'before'   => $this->roundPrice((float) $checkout->summary),
                'discount' => $amount,
                'after'    => $this->roundPrice(max((float) $checkout->summary - $amount, 0)),
                'currency' => $this->getCurrency(),
            ],
        ];
    }

    /**
     * Проверяет, подходит ли товар под ограничения промокода.
     *
     * @param object $product  Данные товара
     * @param object $discount Данные промокода
     *
     * @return bool Подходит ли товар
     * @since 1.0.11
     */
    private function isProductAllowedByDiscount(object $product, object $discount): bool
    {
        if ((float) $discount->min_price > 0 && $this->getProductPrice($product) < (float) $discount->min_price) {
            return false;
        }

        $products = $this->csvToIntegers((string) $discount->products);

        if (!empty($products)) {
            return in_array((int) $product->id, $products, true);
        }

        $cats = $this->csvToIntegers((string) $discount->cats);
        $manufacturers = $this->csvToIntegers((string) $discount->manufacturers);
        $suppliers = $this->csvToIntegers((string) $discount->suppliers);

        return (empty($cats) || in_array((int) $product->catid, $cats, true))
            && (empty($manufacturers) || in_array((int) $product->manufacturer_id, $manufacturers, true))
            && (empty($suppliers) || in_array((int) ($product->supplier_id ?? 0), $suppliers, true));
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
     * Возвращает сохраненный в user state промокод.
     *
     * @return string Промокод
     * @throws Exception
     * @since 1.0.11
     */
    private function getStoredPromoCode(): string
    {
        return trim((string) Factory::getApplication()->getUserState(self::PROMO_CODE_STATE, ''));
    }

    /**
     * Сохраняет примененный промокод в user state checkout.
     *
     * @param   string  $code  Промокод
     *
     * @return void
     * @throws Exception
     * @since 1.0.11
     */
    private function storePromoCode(string $code): void
    {
        Factory::getApplication()->setUserState(self::PROMO_CODE_STATE, trim($code));
    }

    /**
     * Очищает примененный промокод из user state checkout.
     *
     * @return void
     * @throws Exception
     * @since 1.0.11
     */
    private function clearPromoCode(): void
    {
        Factory::getApplication()->setUserState(self::PROMO_CODE_STATE, '');
    }

    /**
     * Преобразует строку идентификаторов через запятую в массив чисел.
     *
     * @param string $value Строка идентификаторов
     *
     * @return array Массив идентификаторов
     * @since 1.0.11
     */
    private function csvToIntegers(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        $items = array_map('intval', explode(',', $value));

        return array_values(array_filter($items, static fn (int $item): bool => $item > 0));
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
