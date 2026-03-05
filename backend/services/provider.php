<?php
/**
 * @package        com_ishop
 * @author         Pavel Lange <pavel@ilange.ru>
 * @link           https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license        GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Association\AssociationExtensionInterface;
use Joomla\CMS\Categories\CategoryFactoryInterface;
use Joomla\CMS\Component\Router\RouterFactoryInterface;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\CategoryFactory;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Extension\Service\Provider\RouterFactory;
use Joomla\CMS\HTML\Registry;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Ilange\Component\Ishop\Administrator\Extension\IshopComponent;
use Ilange\Component\Ishop\Administrator\Helper\AssociationsHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

/**
 * Класс service provider компонента
 * @since 1.0.0
 */
return new class implements ServiceProviderInterface {
    /**
     * Регистрация поставщика с помощью DI контейнера
     *
     * @param   Container  $container  DI контейнер
     *
     * @return void
     * @since 1.0.0
     */
    public function register(Container $container): void
    {
        $container->set(AssociationExtensionInterface::class, new AssociationsHelper());

        $componentNamespace = '\\Ilange\\Component\\Ishop';

        $container->registerServiceProvider(new CategoryFactory($componentNamespace));
        $container->registerServiceProvider(new MVCFactory($componentNamespace));
        $container->registerServiceProvider(new ComponentDispatcherFactory($componentNamespace));
        $container->registerServiceProvider(new RouterFactory($componentNamespace));

        $container->set(
            ComponentInterface::class,
            function (Container $container) {
                $component = new IshopComponent($container->get(ComponentDispatcherFactoryInterface::class));

                $component->setRegistry($container->get(Registry::class));
                $component->setMVCFactory($container->get(MVCFactoryInterface::class));
                $component->setCategoryFactory($container->get(CategoryFactoryInterface::class));
                $component->setAssociationExtension($container->get(AssociationExtensionInterface::class));
                $component->setRouterFactory($container->get(RouterFactoryInterface::class));

                return $component;
            }
        );
    }
};