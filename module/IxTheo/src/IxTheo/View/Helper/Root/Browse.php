<?php
namespace IxTheo\View\Helper\Root;

class Browse extends \VuFind\View\Helper\Root\Browse
{
    /**
     * Get the Solr field associated with a particular browse action.
     *
     * @param string $action Browse action
     * @param string $backup Backup browse action if no match is found for $action
     *
     * @return string
     */
    public function getSolrField($action, $backup = null)
    {
        $action = strToLower($action);
        $backup = strToLower($backup);
        switch($action) {
        case 'ixtheo-classification':
            return 'ixtheo_notation_facet';
        }
        return parent::getSolrField($action, $backup);
    }
}
