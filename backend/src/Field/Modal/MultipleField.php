<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2026 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Administrator\Field\Modal;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\ParameterType;
use Joomla\Utilities\ArrayHelper;

/**
 * Базовый класс модального поля для выбора нескольких элементов.
 * @since 1.0.0
 */
abstract class MultipleField extends FormField
{
    /**
     * Шаблон вывода
     * @var string
     * @since 1.0.0
     */
    protected $layout = 'joomla.form.field.modal-items';

    /**
     * Представление списка элементов
     * @var string
     * @since 1.0.0
     */
    protected string $itemsView = '';

    /**
     * Таблица элементов
     * @var string
     * @since 1.0.0
     */
    protected string $itemsTable = '';

    /**
     * Колонка заголовка элемента
     * @var string
     * @since 1.0.0
     */
    protected string $titleColumn = 'title';

    /**
     * Ключ заголовка модального окна
     * @var string
     * @since 1.0.0
     */
    protected string $selectTitleKey = 'JSELECT';

    /**
     * Ключ текста одного выбранного элемента
     * @var string
     * @since 1.0.0
     */
    protected string $selectedOneKey = '';

    /**
     * Ключ текста нескольких выбранных элементов
     * @var string
     * @since 1.0.0
     */
    protected string $selectedManyKey = '';

    /**
     * Ключ текста предупреждения о пустом выборе
     * @var string
     * @since 1.0.0
     */
    protected string $emptySelectionKey = '';

    /**
     * CSS-класс обертки поля
     * @var string
     * @since 1.0.0
     */
    protected string $fieldClass = '';

    /**
     * Подготовленные URL-адреса для модального окна
     * @var array
     * @since 1.0.0
     */
    protected array $urls = [];

    /**
     * Заголовки модального окна
     * @var array
     * @since 1.0.0
     */
    protected array $modalTitles = [];

    /**
     * Выбранные элементы
     * @var array
     * @since 1.0.0
     */
    protected array $selectedItems = [];

    /**
     * Метод прикрепления объекта формы к полю.
     *
     * @param   \SimpleXMLElement  $element  Объект SimpleXMLElement представляющий тег `<field>` для объекта поля формы.
     * @param   mixed              $value    Значение поля формы для проверки.
     * @param   string|null        $group    Имя группы полей.
     *
     * @return  bool
     * @throws \Exception
     * @since   1.0.0
     */
    public function setup(\SimpleXMLElement $element, $value, $group = null)
    {
        $value  = $this->normalizeValue($value);
        $result = parent::setup($element, $value, $group);

        if (!$result) {
            return $result;
        }

        Factory::getApplication()->getLanguage()->load('com_ishop', JPATH_ADMINISTRATOR);

        $language = (string) $this->element['language'];

        if (!$language && $this->form) {
            $language = (string) $this->form->getValue('language');
        }

        $linkItems = (new Uri())->setPath(Uri::base(true) . '/index.php');
        $linkItems->setQuery([
            'option'                => 'com_ishop',
            'view'                  => $this->itemsView,
            'layout'                => 'modal',
            'tmpl'                  => 'component',
            'multi'                 => 1,
            Session::getFormToken() => 1,
        ]);

        if ($language) {
            $linkItems->setVar('forcedLanguage', $language);
            $this->dataAttributes['data-language'] = $language;
        }

        $this->urls['select'] = (string) $linkItems;

        $modalTitle = Text::_($this->selectTitleKey);
        if ($this->getTitle()) {
            $modalTitle .= ' &#8212; ' . $this->getTitle();
        }

        $this->modalTitles['select'] = $modalTitle;
        $this->hint                  = $this->hint ?: Text::_($this->selectTitleKey);
        $this->selectedItems         = $this->getSelectedItems();

        return $result;
    }

    /**
     * Метод получения данных, которые будут переданы в макет для рендеринга.
     *
     * @return array
     * @since 1.0.0
     */
    protected function getLayoutData()
    {
        $data                     = parent::getLayoutData();
        $data['urls']             = $this->urls;
        $data['modalTitles']      = $this->modalTitles;
        $data['selectedItems']    = $this->selectedItems;
        $data['language']         = (string) ($this->dataAttributes['data-language'] ?? '');
        $data['fieldClass']       = $this->fieldClass;
        $data['selectTitleKey']   = $this->selectTitleKey;
        $data['selectedOneKey']   = $this->selectedOneKey;
        $data['selectedManyKey']  = $this->selectedManyKey;
        $data['emptySelectionKey'] = $this->emptySelectionKey;

        return $data;
    }

    /**
     * Метод получения поля ввода.
     *
     * @return string
     * @since 1.0.0
     */
    protected function getInput()
    {
        if (empty($this->layout)) {
            throw new \UnexpectedValueException(sprintf('%s has no layout assigned.', $this->name));
        }

        return $this->getRenderer($this->layout)->render($this->collectLayoutData());
    }

    /**
     * Рендер
     *
     * @param   string  $layoutId  Id для загрузки
     *
     * @return  FileLayout
     * @since   1.0.0
     */
    protected function getRenderer($layoutId = 'default')
    {
        $layout = parent::getRenderer($layoutId);
        $layout->setComponent('com_ishop');
        $layout->setClient(1);

        return $layout;
    }

    /**
     * Нормализует значение поля в массив положительных ID.
     *
     * @param   mixed  $value  Значение поля
     *
     * @return  array
     * @since   1.0.0
     */
    protected function normalizeValue($value): array
    {
        if (is_string($value)) {
            $value = $value === '' ? [] : explode(',', $value);
        } elseif (!is_array($value)) {
            $value = $value ? [$value] : [];
        }

        $value = ArrayHelper::toInteger($value);
        $value = array_filter($value, static fn($id) => $id > 0);

        return array_values(array_unique($value));
    }

    /**
     * Загружает данные только выбранных элементов.
     *
     * @return  array
     * @throws \Exception
     * @since   1.0.0
     */
    protected function getSelectedItems(): array
    {
        $ids = $this->normalizeValue($this->value);

        if (empty($ids)) {
            return [];
        }

        $db    = $this->getDatabase();
        $query = $db->createQuery()
            ->select([
                $db->quoteName('a.id'),
                $this->getTitleExpression() . ' AS ' . $db->quoteName('title'),
            ])
            ->from($db->quoteName($this->itemsTable, 'a'))
            ->whereIn($db->quoteName('a.id'), $ids, ParameterType::INTEGER);

        $this->extendSelectedItemsQuery($query);

        $db->setQuery($query);

        $items    = $db->loadObjectList('id') ?: [];
        $selected = [];

        foreach ($ids as $id) {
            if (!isset($items[$id])) {
                continue;
            }

            $selected[] = [
                'id'    => $id,
                'title' => trim((string) $items[$id]->title) ?: (string) $id,
            ];
        }

        return $selected;
    }

    /**
     * Возвращает SQL-выражение заголовка элемента.
     *
     * @return string
     * @since 1.0.0
     */
    protected function getTitleExpression(): string
    {
        return $this->getDatabase()->quoteName('a.' . $this->titleColumn);
    }

    /**
     * Позволяет дочерним полям расширить запрос выбранных элементов.
     *
     * @param   \Joomla\Database\QueryInterface  $query  Запрос
     *
     * @return  void
     * @since   1.0.0
     */
    protected function extendSelectedItemsQuery($query): void
    {
    }
}
