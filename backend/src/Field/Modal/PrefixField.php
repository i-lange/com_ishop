<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Administrator\Field\Modal;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ModalSelectField;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\ParameterType;

/**
 * Класс поля Префикс
 * @since 1.0.0
 */
class PrefixField extends ModalSelectField
{
    /**
     * Тип поля формы
     * @var string
     * @since 1.0.0
     */
    protected $type = 'Modal_Prefix';

    /**
     * Метод прикрепления объекта формы к полю
     * @param   \SimpleXMLElement  $element  Объект SimpleXMLElement представляющий тег `<field>` для объекта поля формы.
     * @param   mixed              $value    Значение поля формы для проверки.
     * @param   string             $group    Имя группы полей. Оно действует как массив-контейнер для поля.
     *                                       Например, если поле имеет значение name="foo", а имя группы равно "bar", то
     *                                       полное имя поля в конечном итоге будет "bar[foo]".
     * @return  bool  True в случае успеха
     * @throws \Exception
     * @since   1.0.0
     */
    public function setup(\SimpleXMLElement $element, $value, $group = null)
    {
        // Проверим, совпадает ли значение с форматом id:alias,
        // тогда получим только идентификатор
        if ($value && str_contains($value, ':')) {
            [$id]  = explode(':', $value, 2);
            $value = (int) $id;
        }

        $result = parent::setup($element, $value, $group);

        if (!$result) {
            return $result;
        }

        Factory::getApplication()->getLanguage()->load('com_ishop', JPATH_ADMINISTRATOR);

        $languages = LanguageHelper::getContentLanguages([0, 1], false);
        $language  = (string) $this->element['language'];

        // Подготовка разрешенных действий
        $this->canDo['propagate']  = ((string) $this->element['propagate'] == 'true') && count($languages) > 2;

        // Подготовка Urls
        $linkItems = (new Uri())->setPath(Uri::base(true) . '/index.php');
        $linkItems->setQuery([
            'option'                => 'com_ishop',
            'view'                  => 'prefixes',
            'layout'                => 'modal',
            'tmpl'                  => 'component',
            Session::getFormToken() => 1,
        ]);
        $linkItem = clone $linkItems;
        $linkItem->setVar('view', 'prefix');
        $linkCheckin = (new Uri())->setPath(Uri::base(true) . '/index.php');
        $linkCheckin->setQuery([
            'option'                => 'com_ishop',
            'task'                  => 'prefixes.checkin',
            'format'                => 'json',
            Session::getFormToken() => 1,
        ]);

        if ($language) {
            $linkItems->setVar('forcedLanguage', $language);
            $linkItem->setVar('forcedLanguage', $language);

            $modalTitle = Text::_('COM_ISHOP_SELECT_PREFIX') . ' &#8212; ' . $this->getTitle();

            $this->dataAttributes['data-language'] = $language;
        } else {
            $modalTitle = Text::_('COM_ISHOP_SELECT_PREFIX');
        }

        $urlSelect = $linkItems;
        $urlEdit   = clone $linkItem;
        $urlEdit->setVar('task', 'prefix.edit');
        $urlNew    = clone $linkItem;
        $urlNew->setVar('task', 'prefix.add');

        $this->urls['select']  = (string) $urlSelect;
        $this->urls['new']     = (string) $urlNew;
        $this->urls['edit']    = (string) $urlEdit;
        $this->urls['checkin'] = (string) $linkCheckin;

        // Подготовка заголовков
        $this->modalTitles['select']  = $modalTitle;
        $this->modalTitles['new']     = Text::_('COM_ISHOP_NEW_PREFIX');
        $this->modalTitles['edit']    = Text::_('COM_ISHOP_EDIT_PREFIX');

        $this->hint = $this->hint ?: Text::_('COM_ISHOP_SELECT_PREFIX');

        return $result;
    }

    /**
     * Способ получения названия выбранного элемента
     * @return string
     * @throws \Exception
     * @since 1.0.0
     */
    protected function getValueTitle()
    {
        $value = (int) $this->value ?: '';
        $title = '';

        if ($value) {
            try {
                $db    = $this->getDatabase();
                $query = $db->createQuery()
                    ->select($db->quoteName('title'))
                    ->from($db->quoteName('#__ishop_prefixes'))
                    ->where($db->quoteName('id') . ' = :value')
                    ->bind(':value', $value, ParameterType::INTEGER);
                $db->setQuery($query);

                $title = $db->loadResult();
            } catch (\Throwable $e) {
                Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            }
        }

        return $title ?: $value;
    }

    /**
     * Метод получения данных, которые будут переданы в макет для рендеринга
     * @return array
     * @since 1.0.0
     */
    protected function getLayoutData()
    {
        $data             = parent::getLayoutData();
        $data['language'] = (string) $this->element['language'];

        return $data;
    }

    /**
     * Рендер
     * @param   string  $layoutId  Id для загрузки
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
}
