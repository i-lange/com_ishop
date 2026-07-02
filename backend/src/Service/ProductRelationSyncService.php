<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Administrator\Service;

defined('_JEXEC') or die;

use InvalidArgumentException;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Utilities\ArrayHelper;
use Throwable;

/**
 * Сервис синхронизации CSV-связей между товарами.
 *
 * Поля `offers` и `similar` пока хранятся в таблице товаров как строки ID
 * через запятую. Этот сервис добавляет выбранную группу товаров в такое же
 * поле каждого товара из группы, не создавая дублей и не связывая товар
 * сам с собой.
 *
 * @since 1.0.0
 */
final class ProductRelationSyncService
{
    private const SUPPORTED_FIELDS = ['offers', 'similar'];

    /**
     * Конструктор сервиса.
     *
     * @param   DatabaseInterface  $db  Объект подключения к базе данных.
     *
     * @since 1.0.0
     */
    public function __construct(private DatabaseInterface $db)
    {
    }

    /**
     * Нормализует список идентификаторов товаров.
     *
     * Метод принимает массив из формы, CSV-строку из базы или одиночное
     * значение. Все ID приводятся к integer, пустые значения удаляются,
     * дубли убираются, а `$excludedId` дополнительно исключается из списка.
     * Порядок первого появления ID сохраняется, чтобы админская форма не
     * меняла порядок выбранных товаров без необходимости.
     *
     * @param   mixed  $value       Сырые ID или список ID.
     * @param   int    $excludedId  ID товара, который нужно исключить.
     *
     * @return array<int, int> Список уникальных положительных ID.
     * @since 1.0.0
     */
    public static function normalizeIds(mixed $value, int $excludedId = 0): array
    {
        if (is_string($value)) {
            $value = $value === '' ? [] : explode(',', $value);
        } elseif (!is_array($value)) {
            $value = [$value];
        }

        $ids = [];

        foreach (ArrayHelper::toInteger($value) as $id) {
            if ($id <= 0 || $id === $excludedId || in_array($id, $ids, true)) {
                continue;
            }

            $ids[] = $id;
        }

        return $ids;
    }

    /**
     * Добавляет товары из выбранной группы в CSV-связи каждого товара группы.
     *
     * Синхронизация работает в добавляющем режиме: существующие связи у
     * связанных товаров сохраняются, а недостающие ID из текущей группы
     * дописываются. Связи, удаленные в форме текущего товара, не удаляются
     * автоматически у других товаров.
     *
     * @param   int     $productId    ID сохраненного товара.
     * @param   string  $field        Имя CSV-поля: `offers` или `similar`.
     * @param   mixed   $selectedIds  Список выбранных товаров из формы.
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws Throwable
     * @since 1.0.0
     */
    public function sync(int $productId, string $field, mixed $selectedIds): void
    {
        if ($productId <= 0) {
            return;
        }

        $this->assertSupportedField($field);

        $selectedIds = self::normalizeIds($selectedIds, $productId);
        $groupIds    = self::normalizeIds(array_merge([$productId], $selectedIds));

        if (empty($groupIds)) {
            return;
        }

        $rows = $this->loadRelationRows($groupIds, $field);

        if (empty($rows) || !isset($rows[$productId])) {
            return;
        }

        $existingGroupIds = array_values(array_intersect($groupIds, array_keys($rows)));

        $this->db->transactionStart();

        try {
            foreach ($existingGroupIds as $rowProductId) {
                $currentIds = self::normalizeIds($rows[$rowProductId][$field] ?? '', $rowProductId);
                $groupLinks = array_values(array_diff($existingGroupIds, [$rowProductId]));
                $nextIds    = self::normalizeIds(array_merge($currentIds, $groupLinks), $rowProductId);
                $nextValue  = implode(',', $nextIds);

                if ($nextValue === ($rows[$rowProductId][$field] ?? '')) {
                    continue;
                }

                $this->updateRelationField($rowProductId, $field, $nextValue);
            }

            $this->db->transactionCommit();
        } catch (Throwable $exception) {
            $this->db->transactionRollback();

            throw $exception;
        }
    }

    /**
     * Проверяет, что сервис работает только с поддерживаемыми CSV-полями.
     *
     * @param   string  $field  Имя поля таблицы товаров.
     *
     * @return void
     * @throws InvalidArgumentException
     * @since 1.0.0
     */
    private function assertSupportedField(string $field): void
    {
        if (!in_array($field, self::SUPPORTED_FIELDS, true)) {
            throw new InvalidArgumentException('Неподдерживаемое поле связи товаров: ' . $field);
        }
    }

    /**
     * Загружает текущие значения CSV-связи для группы товаров.
     *
     * @param   array<int, int>  $productIds  ID товаров группы.
     * @param   string           $field       Имя CSV-поля.
     *
     * @return array<int, array{id:int, offers?:string, similar?:string}> Данные товаров по ID.
     * @since 1.0.0
     */
    private function loadRelationRows(array $productIds, string $field): array
    {
        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('id'),
                $this->db->quoteName($field),
            ])
            ->from($this->db->quoteName('#__ishop_products'))
            ->whereIn($this->db->quoteName('id'), $productIds, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $rows = $this->db->loadAssocList('id');

        return is_array($rows) ? $rows : [];
    }

    /**
     * Обновляет CSV-поле связи у одного товара.
     *
     * @param   int     $productId  ID товара.
     * @param   string  $field      Имя CSV-поля.
     * @param   string  $value      Новое CSV-значение.
     *
     * @return void
     * @since 1.0.0
     */
    private function updateRelationField(int $productId, string $field, string $value): void
    {
        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__ishop_products'))
            ->set($this->db->quoteName($field) . ' = :value')
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':value', $value, ParameterType::STRING)
            ->bind(':id', $productId, ParameterType::INTEGER);

        $this->db->setQuery($query)->execute();
    }
}
