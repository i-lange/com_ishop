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

use Ilange\Component\Ishop\Administrator\Service\FilterSeoKey;
use InvalidArgumentException;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;

/**
 * Table-класс SEO-страницы фильтра.
 *
 * Инкапсулирует работу с таблицей `#__ishop_filters`: нормализацию условий
 * фильтра, построение канонического ключа, проверку обязательных полей,
 * заполнение системных дат/пользователей и защиту от дублей.
 *
 * @since 1.0.0
 */
class FilterTable extends Table
{
    /**
     * Разрешает сохранение NULL-значений для поддерживаемых колонок таблицы.
     *
     * @var bool
     *
     * @since 1.0.0
     */
    protected $_supportNullValue = true;

    /**
     * Создает table-класс и настраивает алиасы колонок Joomla.
     *
     * @param   DatabaseInterface          $db          Объект подключения к БД.
     * @param   DispatcherInterface|null   $dispatcher  Диспетчер событий.
     *
     * @since 1.0.0
     */
    public function __construct(DatabaseInterface $db, ?DispatcherInterface $dispatcher = null)
    {
        $this->typeAlias = 'com_ishop.filter';
        parent::__construct('#__ishop_filters', 'id', $db, $dispatcher);

        $this->setColumnAlias('published', 'state');
    }

    /**
     * Привязывает данные к объекту таблицы.
     *
     * Перед стандартной привязкой нормализует фильтр, пересобирает JSON-поля
     * и числовые условия, чтобы форма и сохранение использовали один
     * канонический формат.
     *
     * @param   array|object  $src     Исходные данные.
     * @param   array|string  $ignore  Поля, которые нужно игнорировать.
     *
     * @return  bool
     *
     * @since 1.0.0
     */
    public function bind($src, $ignore = [])
    {
        if (is_array($src)) {
            $src = $this->prepareFilterData($src);
        } elseif (is_object($src)) {
            $src = (object) $this->prepareFilterData((array) $src);
        }

        return parent::bind($src, $ignore);
    }

    /**
     * Проверяет и подготавливает запись перед сохранением.
     *
     * Метод валидирует обязательную категорию, нормализует условия фильтра,
     * пересчитывает `filter_key` и гарантирует значение языка по умолчанию.
     *
     * @return  bool
     *
     * @throws  InvalidArgumentException
     * @since   1.0.0
     */
    public function check()
    {
        parent::check();

        $data = $this->prepareFilterData((array) $this);

        $this->category_id = (int) ($data['category_id'] ?? 0);
        if ($this->category_id <= 0) {
            throw new InvalidArgumentException(Text::_('COM_ISHOP_FILTER_ERROR_CATEGORY_REQUIRED'));
        }

        $this->manufacturers = $data['manufacturers'];
        $this->warehouses = $data['warehouses'];
        $this->ishop_fields = $data['ishop_fields'];
        $this->filter_key = $data['filter_key'];
        $this->min_price = (int) $data['min_price'];
        $this->max_price = (int) $data['max_price'];
        $this->good_price = (int) $data['good_price'];

        foreach (['width', 'height', 'depth', 'weight'] as $dimension) {
            $this->{'min_' . $dimension} = (int) $data['min_' . $dimension];
            $this->{'max_' . $dimension} = (int) $data['max_' . $dimension];
        }

        $this->language = $this->language ?: '*';

        return true;
    }

    /**
     * Сохраняет запись SEO-страницы фильтра.
     *
     * Заполняет системные поля создания/изменения и перед записью в БД
     * проверяет уникальность канонического ключа фильтра в рамках категории и
     * языка.
     *
     * @param   bool  $updateNulls  Обновлять ли поля значением NULL.
     *
     * @return  bool
     *
     * @throws \Exception
     * @since 1.0.0
     */
    public function store($updateNulls = true)
    {
        $date = Factory::getDate()->toSql();
        $user = Factory::getApplication()->getIdentity();

        if (!(int) $this->created) {
            $this->created = $date;
        }

        if ($this->id) {
            $this->modified_by = $user->id;
            $this->modified    = $date;
        } else {
            if (empty($this->created_by)) {
                $this->created_by = $user->id;
            }

            if (!(int) $this->modified) {
                $this->modified = $this->created;
            }

            if (empty($this->modified_by)) {
                $this->modified_by = $this->created_by;
            }
        }

        if (!$this->isUniqueFilterKey()) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_ISHOP_FILTER_ERROR_DUPLICATE'), 'danger');

            return false;
        }

        return parent::store($updateNulls);
    }

    /**
     * Нормализует входные условия фильтра для хранения.
     *
     * Возвращает данные с сериализованными JSON-полями производителей,
     * складов и характеристик, числовыми диапазонами цены/габаритов/веса,
     * флагом скидки и рассчитанным каноническим ключом `filter_key`.
     *
     * @param   array  $data  Исходные данные формы или объекта таблицы.
     *
     * @return  array
     *
     * @since 1.0.0
     */
    private function prepareFilterData(array $data): array
    {
        $normalized = FilterSeoKey::normalize($data);

        $data['category_id'] = (int) ($normalized['category_id'] ?? 0);
        $data['manufacturers'] = json_encode($normalized['manufacturers'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $data['warehouses'] = json_encode($normalized['warehouses'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $data['ishop_fields'] = json_encode($normalized['ishop_fields'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $data['filter_key'] = FilterSeoKey::build($normalized);
        $data['min_price'] = (int) ($normalized['min_price'] ?? 0);
        $data['max_price'] = (int) ($normalized['max_price'] ?? 0);
        $data['good_price'] = (int) ($normalized['good_price'] ?? 0);

        foreach (['width', 'height', 'depth', 'weight'] as $dimension) {
            $data['min_' . $dimension] = (int) ($normalized['min_' . $dimension] ?? 0);
            $data['max_' . $dimension] = (int) ($normalized['max_' . $dimension] ?? 0);
        }

        return $data;
    }

    /**
     * Проверяет, что такая комбинация фильтра еще не существует.
     *
     * Дублем считается запись с той же категорией, тем же `filter_key` и тем
     * же языком. При редактировании текущая запись исключается из проверки.
     *
     * @return  bool
     *
     * @since 1.0.0
     */
    private function isUniqueFilterKey(): bool
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__ishop_filters'))
            ->where($db->quoteName('category_id') . ' = :category_id')
            ->where($db->quoteName('filter_key') . ' = :filter_key')
            ->where($db->quoteName('language') . ' = :language')
            ->bind(':category_id', $this->category_id, ParameterType::INTEGER)
            ->bind(':filter_key', $this->filter_key)
            ->bind(':language', $this->language);

        if ((int) $this->id > 0) {
            $query
                ->where($db->quoteName('id') . ' <> :id')
                ->bind(':id', $this->id, ParameterType::INTEGER);
        }

        return (int) $db->setQuery($query)->loadResult() === 0;
    }
}
