<?php
namespace TueFind\View\Helper\Root;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class SearchTabsFactory extends \VuFind\View\Helper\Root\SearchTabsFactory
{
    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options sent to factory.');
        }
        return new $requestedName(
            $container->get(\VuFind\Search\Results\PluginManager::class),
            $container->get('ViewHelperManager')->get('url'),
            $container->get(\VuFind\Search\SearchTabsHelper::class)
        );
    }
}
