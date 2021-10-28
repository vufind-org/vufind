<?php

namespace TueFind\Search\SolrAuth;

class Params extends \VuFind\Search\SolrAuth\Params
{
    protected function formatFilterListEntry($field, $value, $operator, $translate)
    {
        $entry = parent::formatFilterListEntry($field, $value, $operator, $translate);
        if ($field == 'type')
            $entry['displayText'] = $this->translate('authority_type_' . $value);
        return $entry;
    }
}
