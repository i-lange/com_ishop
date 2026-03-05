<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Site\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\Rules\RulesInterface;

/**
 * Специальные правила обработки URL-адресов в компоненте com_ishop
 * @since 1.0.0
 */
class NomenuRules implements RulesInterface
{
    /**
     * Роутер, к которому привязано это правило
     * @var RouterView
     * @since 1.0.0
     */
    protected $router;

    /**
     * Конструктор
     * @param RouterView $router Роутер
     * @since 1.0.0
     */
    public function __construct(RouterView $router)
    {
        $this->router = $router;
    }

    /**
     * Заглушка метода для выполнения требований интерфейса
     * @param array &$query Массив запроса
     * @return void
     * @since 1.0.0
     */
    public function preprocess(&$query)
    {
    }

    /**
     * Разбор URL-адреса без пункта меню
     * @param array &$segments Сегменты URL для разбора
     * @param array &$vars Параметры, получаемые в результате разбора
     * @return void
     * @since 1.0.0
     */
    public function parse(&$segments, &$vars)
    {
    }

    /**
     * Составляем ЧПУ URL только из необходимых сегментов
     * @param array &$query Параметры, которые нужно обработать
     * @param array &$segments Сегменты URL для создания ЧПУ адреса
     * @return void
     * @since 1.0.0
     */
    public function build(&$query, &$segments)
    {
    }
}