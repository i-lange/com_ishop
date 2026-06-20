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
 * Класс модального поля для выбора нескольких товаров.
 * @since 1.0.0
 */
class ProductsField extends FormField
{
    /**
     * Тип поля формы
     * @var string
     * @since 1.0.0
     */
    protected $type = 'Modal_Products';

    /**
     * Шаблон вывода
     * @var string
     * @since 1.0.0
     */
    protected $layout = 'joomla.form.field.modal-products';

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
     * Выбранные товары
     * @var array
     * @since 1.0.0
     */
    protected array $selectedProducts = [];

    /**
     * Метод прикрепления объекта формы к полю.
     *
     * @param   \SimpleXMLElement  $element  Объект SimpleXMLElement представляющий тег `<field>` для объекта поля формы.
     * @param   mixed              $value    Значение поля формы для проверки.
     * @param   string|null        $group    Имя группы полей.
     *
     * @return  bool  True в случае успеха
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
            'view'                  => 'products',
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

        $modalTitle = Text::_('COM_ISHOP_SELECT_PRODUCTS');
        if ($this->getTitle()) {
            $modalTitle .= ' &#8212; ' . $this->getTitle();
        }

        $this->modalTitles['select'] = $modalTitle;
        $this->hint                  = $this->hint ?: Text::_('COM_ISHOP_SELECT_PRODUCTS');
        $this->selectedProducts      = $this->getSelectedProducts();

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
        $data['selectedProducts'] = $this->selectedProducts;
        $data['language']         = (string) ($this->dataAttributes['data-language'] ?? '');

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
     * Нормализует значение поля в массив положительных ID товаров.
     *
     * @param   mixed  $value  Значение поля
     *
     * @return  array
     * @since   1.0.0
     */
    private function normalizeValue($value): array
    {
        if (is_string($value)) {
            $value = $value === '' ? [] : explode(',', $value);
        } elseif (!is_array($value)) {
            $value = $value ? [$value] : [];
        }

        $value = ArrayHelper::toInteger($value);
        $value = array_values(array_unique(array_filter($value)));

        return $value;
    }

    /**
     * Загружает данные только выбранных товаров.
     *
     * @return  array
     * @throws \Exception
     * @since   1.0.0
     */
    private function getSelectedProducts(): array
    {
        $ids = $this->normalizeValue($this->value);

        if (empty($ids)) {
            return [];
        }

        $db    = $this->getDatabase();
        $query = $db->createQuery()
            ->select([
                $db->quoteName('a.id'),
                'CONCAT('
                . 'COALESCE(' . $db->quoteName('prefixes.title') . ', ' . $db->quote('') . '), '
                . $db->quote(' ') . ', '
                . 'COALESCE(' . $db->quoteName('manufacturers.title') . ', ' . $db->quote('') . '), '
                . $db->quote(' ') . ', '
                . $db->quoteName('a.title') .
                ') AS ' . $db->quoteName('title'),
            ])
            ->from($db->quoteName('#__ishop_products', 'a'))
            ->join(
                'LEFT',
                $db->quoteName('#__ishop_prefixes', 'prefixes'),
                $db->quoteName('prefixes.id') . ' = ' . $db->quoteName('a.prefix_id')
            )
            ->join(
                'LEFT',
                $db->quoteName('#__ishop_manufacturers', 'manufacturers'),
                $db->quoteName('manufacturers.id') . ' = ' . $db->quoteName('a.manufacturer_id')
            )
            ->whereIn($db->quoteName('a.id'), $ids, ParameterType::INTEGER);
        $db->setQuery($query);

        $products = $db->loadObjectList('id') ?: [];
        $selected = [];

        foreach ($ids as $id) {
            if (!isset($products[$id])) {
                continue;
            }

            $selected[] = [
                'id'    => $id,
                'title' => trim((string) $products[$id]->title) ?: (string) $id,
            ];
        }

        return $selected;
    }
}
