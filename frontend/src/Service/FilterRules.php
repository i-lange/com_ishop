<?php
/**
 * @package        com_ishop
 * @author         Pavel Lange <pavel@ilange.ru>
 * @link           https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license        GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Site\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\Rules\RulesInterface;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Специальные правила обработки URL-адресов в компоненте com_ishop
 * @since 1.0.0
 */
class FilterRules implements RulesInterface
{
    /**
     * Роутер, к которому привязано это правило
     * @var RouterView
     * @since 1.0.0
     */
    protected RouterView $router;

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
        if (empty($segments)) {
            return;
        }

        $segment = array_shift($segments);

        if (str_starts_with($segment, 'brand:')) {
            $brands = substr($segment, 6);

            if (!empty($brands)) {
                $aliases         = explode(':', $brands);
                $manufacturerIds = [];

                foreach ($aliases as $alias) {
                    $id = $this->getManufacturerId($alias);
                    if ($id) {
                        $manufacturerIds[] = $id;
                    }
                }

                if (!empty($manufacturerIds)) {
                    sort($manufacturerIds);

                    if (count($manufacturerIds) === 1) {
                        $vars['manufacturers'] = [$manufacturerIds[0]];
                    } else {
                        $vars['manufacturers'] = $manufacturerIds;
                    }
                }
            }
        } else {
            array_unshift($segments, $segment);
        }
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
        if (isset($query['manufacturers']) && is_array($query['manufacturers'])) {
            $manufacturerIds = $query['manufacturers'];
            $manufacturerIds = array_filter($manufacturerIds);

            if (!empty($manufacturerIds)) {
                $aliases = [];
                foreach ($manufacturerIds as $id) {
                    $alias = $this->getManufacturerAlias((int)$id);
                    if ($alias) {
                        $aliases[] = $alias;
                    }
                }

                if (!empty($aliases)) {
                    $segments[] = 'brand:' . implode(':', $aliases);
                }
            }
        } elseif (isset($query['manufacturer_id']) && (int) $query['manufacturer_id'] > 0) {
            $manufacturerId = (int) $query['manufacturer_id'];
            $alias          = $this->getManufacturerAlias($manufacturerId);

            if ($alias) {
                $segments[] = 'brand:' . $alias;
            }
        }
    }

    /**
     * Получение алиаса производителя по ID
     *
     * @param   int  $id  Идентификатор производителя
     *
     * @return string|null Алиас производителя или null
     * @since 1.0.0
     */
    private function getManufacturerAlias(int $id): ?string
    {
        if ($id === 0) {
            return null;
        }

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true);
            $query->select($db->quoteName('alias'))
                ->from($db->quoteName('#__ishop_manufacturers'))
                ->where($db->quoteName('id') . ' = :id')
                ->bind(':id', $id, ParameterType::INTEGER);

            $alias = $db->setQuery($query)->loadResult();

            return $alias ? (string)$alias : null;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Получение ID производителя по алиасу
     *
     * @param   string  $alias  Алиас производителя
     *
     * @return int|null Идентификатор производителя или null
     * @since 1.0.0
     */
    private function getManufacturerId(string $alias): ?int
    {
        if (empty($alias)) {
            return null;
        }

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true);
            $query->select($db->quoteName('id'))
                ->from($db->quoteName('#__ishop_manufacturers'))
                ->where($db->quoteName('alias') . ' = :alias')
                ->bind(':alias', $alias);

            $id = (int)$db->setQuery($query)->loadResult();

            return $id > 0 ? $id : null;
        } catch (\Exception) {
            return null;
        }
    }
}