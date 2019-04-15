<?php

namespace TueFind\Search\Factory;

use VuFindSearch\Backend\Solr\Backend;
use VuFindSearch\Backend\Solr\Connector;
use TueFindSearch\Backend\Solr\Response\Json\RecordCollectionFactory;

class SolrDefaultBackendFactory extends AbstractSolrBackendFactory {

    /**
     * Create the SOLR backend.
     *
     * @param Connector $connector Connector
     *
     * @return Backend
     */
    protected function createBackend(Connector $connector)
    {
        $backend = parent::createBackend($connector);
        $manager = $this->serviceLocator->get('VuFind\RecordDriver\PluginManager');
        $factory = new RecordCollectionFactory([$manager, 'getSolrRecord']);
        $backend->setRecordCollectionFactory($factory);
        return $backend;
    }
}
