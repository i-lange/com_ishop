<?php
/**
 * @package        com_ishop
 * @author         Pavel Lange <pavel@ilange.ru>
 * @link           https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license        GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Associations;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
use Joomla\Registry\Registry;
use Joomla\String\StringHelper;

/**
 * Модель значения характеристики
 * @since 1.0.0
 */
class ValueModel extends AdminModel
{
    /**
     * Префикс для языковых констант
     * @var string
     * @since 1.0.0
     */
    protected $text_prefix = 'COM_ISHOP';

    /**
     * Псевдоним типа для данного типа контента
     * @var string
     * @since 1.0.0
     */
    public $typeAlias = 'com_ishop.value';

    /**
     * Контекст, используемый для таблицы ассоциаций
     * @var    string
     * @since  1.0.0
     */
    protected $associationsContext = 'com_ishop.value';

    /**
     * Подготавливает и обрабатывает данные таблицы перед сохранением
     *
     * @param   Table  $table  Объект таблицы
     *
     * @return  void
     * @since   1.0.0
     */
    protected function prepareTable($table)
    {
    }

    /**
     * Метод получения формы редактирования
     *
     * @param   array  $data      Данные для формы
     * @param   bool   $loadData  True, если форма должна загружать свои собственные данные (по умолчанию), false - если нет
     *
     * @return Form|bool Объект формы при успехе, false при неудаче
     * @throws \Exception
     * @since 1.0.0
     */
    public function getForm($data = [], $loadData = true)
    {
        $form = $this->loadForm(
            'com_ishop.value',
            'value',
            ['control' => 'jform', 'load_data' => $loadData]
        );

        if (empty($form)) {
            return false;
        }

        return $form;
    }

    /**
     * Метод для получения данных, которые должны быть введены в форму
     *
     * @return mixed Данные для формы
     * @throws \Exception
     * @since 1.0.0
     */
    protected function loadFormData()
    {
        // Проверяем сессию на наличие ранее введенных данных формы.
        $data = Factory::getApplication()->getUserState('com_ishop.edit.value.data', []);

        if (empty($data)) {
            $data = $this->getItem();
        }

        // Если в форме есть наборы полей params типа Registry, преобразуем в массив
        if (isset($data->params) && $data->params instanceof Registry) {
            $data->params = $data->params->toArray();
        }

        $this->preprocessData('com_ishop.value', $data);

        return $data;
    }

    /**
     * Метод получения записи
     *
     * @param   int  $pk  Идентификатор
     *
     * @return object|bool Object при успехе, false при неудаче
     * @throws \Exception
     * @since 1.0.0
     */
    public function getItem($pk = null)
    {
        $item = parent::getItem($pk);

        if (!$item) {
            return false;
        }

        return $item;
    }

    /**
     * Возвращает ссылку на объект Table, всегда создавая его
     *
     * @param   string  $name     Тип таблицы
     * @param   string  $prefix   Префикс для имени класса таблицы, необязательно
     * @param   array   $options  Массив параметров для модели, необязательно
     *
     * @return Table объект базы данных
     * @throws \Exception
     * @since 1.0.0
     */
    public function getTable($name = 'Value', $prefix = 'Table', $options = [])
    {
        return parent::getTable($name, $prefix, $options);
    }
}