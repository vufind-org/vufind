<?php

namespace TueFind\AjaxHandler;

use Laminas\Mvc\Controller\Plugin\Params;
use VuFind\I18n\Translator\TranslatorAwareInterface;

class GetSubscriptionBundleEntries extends \VuFind\AjaxHandler\AbstractBase implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    protected $searchResultsManager;

    public function __construct(\VuFind\Search\Results\PluginManager $searchResultsManager) {
        $this->searchResultsManager = $searchResultsManager;
    }

    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        $bundle_id = $params->fromQuery('bundle_id');
        $results = $this->searchResultsManager->get('Solr');
        $params = $results->getParams();
        $params->getOptions()->spellcheckEnabled(false);
        $params->getOptions()->disableHighlighting();
        $params->setOverrideQuery('bundle_id:' . str_replace(':', '\\:', $bundle_id));
        $results->setParams($params);
        $result_set = $results->getResults();
        $titles = [];
        foreach ($result_set as $record)
            $titles[] = ['id' => $record->getUniqueID(), 'title' => $record->getTitle()];
        return $this->formatResponse($titles);
    }
}
