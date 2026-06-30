<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2026 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Site\Service;

defined('_JEXEC') or die;

use Exception;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Сервис проверки и расчета скидки по промокоду.
 *
 * Промокоды хранятся в общей таблице скидок, но не участвуют в фоновом
 * пересчете цен товаров. Поэтому этот сервис применяет их только к текущей
 * корзине или checkout-набору товаров.
 *
 * @since 1.0.19
 */
final class PromoCodeService
{
    /**
     * Контекст user state для примененного промокода.
     *
     * @var string
     * @since 1.0.19
     */
    public const STATE_KEY = 'com_ishop.checkout.code';

    /**
     * Подключение к базе данных.
     *
     * @var DatabaseInterface
     * @since 1.0.19
     */
    private DatabaseInterface $db;

    /**
     * Конструктор сервиса промокодов.
     *
     * @param   DatabaseInterface|null  $db  Подключение к базе данных.
     *
     * @since 1.0.19
     */
    public function __construct(?DatabaseInterface $db = null)
    {
        $this->db = $db ?: Factory::getContainer()->get(DatabaseInterface::class);
    }

    /**
     * Применяет сохраненный в сессии промокод к данным корзины.
     *
     * @param   object  $cart          Данные корзины.
     * @param   bool    $clearInvalid  Очищать ли сохраненный код, если он больше не подходит.
     *
     * @return object Корзина с полями промокода и итоговой суммой.
     * @since 1.0.19
     */
    public function applyStoredCodeToCart(object $cart, bool $clearInvalid = false): object
    {
        $code = $this->getStoredCode();
        $this->prepareCart($cart, $code);

        if ($code === '') {
            return $cart;
        }

        try {
            return $this->applyDiscountToCart(
                $cart,
                $this->calculatePromoCode($code, $cart),
                Text::_('COM_ISHOP_CART_PROMOCODE_APPLIED')
            );
        } catch (Exception $exception) {
            if ($clearInvalid) {
                $this->clearCode();
            }

            $cart->promo_message = Text::_('COM_ISHOP_CART_PROMOCODE_INVALID');
            $cart->promocode_message = $cart->promo_message;

            return $cart;
        }
    }

    /**
     * Применяет введенный покупателем промокод к данным корзины.
     *
     * @param   object  $cart  Данные корзины без промокода.
     * @param   string  $code  Введенный промокод.
     *
     * @return object Корзина с результатом применения промокода.
     * @since 1.0.19
     */
    public function applySubmittedCodeToCart(object $cart, string $code): object
    {
        $code = trim($code);
        $this->clearCode();
        $this->prepareCart($cart, $code);

        if ($code === '') {
            return $cart;
        }

        try {
            $discount = $this->calculatePromoCode($code, $cart);
            $this->storeCode($discount['code']);

            return $this->applyDiscountToCart($cart, $discount, Text::_('COM_ISHOP_CART_PROMOCODE_APPLIED'));
        } catch (Exception $exception) {
            $cart->promo_message = Text::_('COM_ISHOP_CART_PROMOCODE_INVALID');
            $cart->promocode_message = $cart->promo_message;

            return $cart;
        }
    }

    /**
     * Проверяет промокод и возвращает рассчитанную скидку.
     *
     * @param   string  $code  Промокод покупателя.
     * @param   object  $cart  Данные корзины или checkout.
     *
     * @return array Данные рассчитанной скидки.
     * @throws Exception
     * @since 1.0.19
     */
    public function calculatePromoCode(string $code, object $cart): array
    {
        if (trim($code) === '') {
            throw new Exception(Text::_('COM_ISHOP_CHECKOUT_CODE_REQUIRED'), 400);
        }

        $params = ComponentHelper::getParams('com_ishop');

        if (!$params->get('discounts_use', 0)) {
            throw new Exception(Text::_('COM_ISHOP_CHECKOUT_CODE_NOT_APPLICABLE'), 400);
        }

        foreach ($this->findPromoCodes($code) as $discount) {
            $result = $this->buildPromoCodeResult($discount, $cart);

            if ($result !== null) {
                return $result;
            }
        }

        throw new Exception(Text::_('COM_ISHOP_CHECKOUT_CODE_NOT_APPLICABLE'), 400);
    }

    /**
     * Применяет уже рассчитанную скидку к корзине.
     *
     * @param   object  $cart      Данные корзины.
     * @param   array   $discount  Рассчитанная скидка.
     * @param   string  $message   Сообщение для интерфейса.
     *
     * @return object Обновленные данные корзины.
     * @since 1.0.19
     */
    public function applyDiscountToCart(object $cart, array $discount, string $message = ''): object
    {
        $this->prepareCart($cart, (string) ($discount['code'] ?? ''));

        $amount = $this->roundPrice((float) ($discount['amount'] ?? 0));
        $cart->promo = $discount;
        $cart->promo_discount = $amount;
        $cart->promo_valid = $cart->promocode_valid = true;
        $cart->promo_message = $message;
        $cart->promocode_message = $message;
        $cart->summary = $this->roundPrice(max((float) $cart->summary_before_promo - $amount, 0));

        return $cart;
    }

    /**
     * Возвращает сохраненный в user state промокод.
     *
     * @return string Промокод.
     * @since 1.0.19
     */
    public function getStoredCode(): string
    {
        return trim((string) Factory::getApplication()->getUserState(self::STATE_KEY, ''));
    }

    /**
     * Сохраняет примененный промокод в user state.
     *
     * @param   string  $code  Промокод.
     *
     * @return void
     * @since 1.0.19
     */
    public function storeCode(string $code): void
    {
        Factory::getApplication()->setUserState(self::STATE_KEY, trim($code));
    }

    /**
     * Очищает примененный промокод из user state.
     *
     * @return void
     * @since 1.0.19
     */
    public function clearCode(): void
    {
        Factory::getApplication()->setUserState(self::STATE_KEY, '');
    }

    /**
     * Инициализирует поля корзины, связанные с промокодом.
     *
     * @param   object  $cart  Данные корзины.
     * @param   string  $code  Текущий код для отображения.
     *
     * @return void
     * @since 1.0.19
     */
    private function prepareCart(object $cart, string $code = ''): void
    {
        $cart->summary_before_promo = $this->roundPrice((float) ($cart->summary_before_promo ?? $cart->summary ?? 0));
        $cart->summary = $cart->summary_before_promo;
        $cart->promo = null;
        $cart->promo_discount = 0.0;
        $cart->promo_code = $code;
        $cart->promocode = $code;
        $cart->promo_valid = false;
        $cart->promocode_valid = false;
        $cart->promo_message = '';
        $cart->promocode_message = '';
    }

    /**
     * Загружает опубликованные записи промокода из базы данных.
     *
     * @param   string  $code  Промокод покупателя.
     *
     * @return array Данные скидок.
     * @throws Exception
     * @since 1.0.19
     */
    private function findPromoCodes(string $code): array
    {
        $code = trim($code);

        if ($code === '') {
            throw new Exception(Text::_('COM_ISHOP_CHECKOUT_CODE_REQUIRED'), 400);
        }

        $published = 1;
        $type = 2;
        $now = Factory::getDate()->toSql();
        $normalizedCode = mb_strtolower($code);

        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('id'),
                $this->db->quoteName('title'),
                $this->db->quoteName('code'),
                $this->db->quoteName('percent'),
                $this->db->quoteName('products'),
                $this->db->quoteName('cats'),
                $this->db->quoteName('manufacturers'),
                $this->db->quoteName('suppliers'),
                $this->db->quoteName('min_price'),
                $this->db->quoteName('min_amount'),
            ])
            ->from($this->db->quoteName('#__ishop_discounts'))
            ->where($this->db->quoteName('state') . ' = :published')
            ->where($this->db->quoteName('type') . ' = :type')
            ->where('LOWER(' . $this->db->quoteName('code') . ') = :code')
            ->where('(' . $this->db->quoteName('publish_up') . ' IS NULL OR ' . $this->db->quoteName('publish_up') . ' <= :publishUp)')
            ->where('(' . $this->db->quoteName('publish_down') . ' IS NULL OR ' . $this->db->quoteName('publish_down') . ' >= :publishDown)')
            ->bind(':published', $published, ParameterType::INTEGER)
            ->bind(':type', $type, ParameterType::INTEGER)
            ->bind(':code', $normalizedCode)
            ->bind(':publishUp', $now)
            ->bind(':publishDown', $now)
            ->order([
                $this->db->quoteName('percent') . ' DESC',
                $this->db->quoteName('ordering') . ' ASC',
                $this->db->quoteName('id') . ' ASC',
            ]);

        $this->db->setQuery($query);
        $discounts = $this->db->loadObjectList();

        if (empty($discounts)) {
            throw new Exception(Text::_('COM_ISHOP_CHECKOUT_CODE_NOT_FOUND'), 404);
        }

        return $discounts;
    }

    /**
     * Рассчитывает результат применения одной записи промокода.
     *
     * @param   object  $discount  Данные промокода.
     * @param   object  $cart      Данные корзины или checkout.
     *
     * @return array|null Результат применения или null, если запись не подходит.
     * @since 1.0.19
     */
    private function buildPromoCodeResult(object $discount, object $cart): ?array
    {
        $percent = (float) $discount->percent;

        if ($percent <= 0) {
            return null;
        }

        $baseSummary = $this->getBaseSummary($cart);

        if ((float) $discount->min_amount > 0 && $baseSummary < (float) $discount->min_amount) {
            return null;
        }

        $products = [];
        $amount = 0.0;

        foreach ($cart->products as $product) {
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
                'before'   => $this->roundPrice($baseSummary),
                'discount' => $amount,
                'after'    => $this->roundPrice(max($baseSummary - $amount, 0)),
                'currency' => $this->getCurrency(),
            ],
        ];
    }

    /**
     * Проверяет, подходит ли товар под ограничения промокода.
     *
     * @param   object  $product   Данные товара.
     * @param   object  $discount  Данные промокода.
     *
     * @return bool Подходит ли товар.
     * @since 1.0.19
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
     * Возвращает цену товара, к которой применяется промокод.
     *
     * @param   object  $product  Данные товара.
     *
     * @return float Цена товара.
     * @since 1.0.19
     */
    private function getProductPrice(object $product): float
    {
        return ((float) ($product->sale_price ?? 0) > 0)
            ? (float) $product->sale_price
            : (float) ($product->price ?? 0);
    }

    /**
     * Возвращает сумму корзины до применения промокода.
     *
     * @param   object  $cart  Данные корзины.
     *
     * @return float Сумма до промокода.
     * @since 1.0.19
     */
    private function getBaseSummary(object $cart): float
    {
        return (float) ($cart->summary_before_promo ?? $cart->summary ?? 0);
    }

    /**
     * Преобразует строку идентификаторов через запятую в массив чисел.
     *
     * @param   string  $value  Строка идентификаторов.
     *
     * @return array Массив идентификаторов.
     * @since 1.0.19
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
     * Округляет денежное значение по настройкам компонента.
     *
     * @param   float  $value  Значение.
     *
     * @return float Округленное значение.
     * @since 1.0.19
     */
    private function roundPrice(float $value): float
    {
        $params = ComponentHelper::getParams('com_ishop');

        return round($value, (int) $params->get('roundPrice', 0));
    }

    /**
     * Возвращает код валюты из настроек компонента.
     *
     * @return string Код валюты.
     * @since 1.0.19
     */
    private function getCurrency(): string
    {
        $params = ComponentHelper::getParams('com_ishop');

        return mb_strtoupper((string) $params->get('defaultCurrency', 'BYN'));
    }
}
