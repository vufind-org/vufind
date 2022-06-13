<?php
namespace TueFind\Search\Search3;

use Interop\Container\ContainerInterface;
use Interop\Container\Exception\ContainerException;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;

class ResultsFactory extends \VuFind\Search\Results\ResultsFactory
{
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        array $options = null
    ) {
        $solr = parent::__invoke($container, $requestedName, $options);
        $config = $container->get(\VuFind\Config\PluginManager::class)
            ->get('Search3');
        $solr->setSpellingProcessor(
            new \VuFind\Search\Solr\SpellingProcessor(
                $config->Spelling ?? null,
                $solr->getOptions()->getSpellingNormalizer()
            )
        );
        $solr->setHierarchicalFacetHelper(
            $container->get(\VuFind\Search\Solr\HierarchicalFacetHelper::class)
        );
        return $solr;
    }
}
