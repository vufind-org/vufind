<?php
namespace TueFind\Recommend;

use Interop\Container\ContainerInterface;

class SideFacetsFactory implements \Zend\ServiceManager\Factory\FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options passed to factory.');
        }
        return new $requestedName(
            $container->get(\VuFind\Config\PluginManager::class),
            $container->get(\VuFind\Search\Solr\HierarchicalFacetHelper::class)
        );
    }
}
