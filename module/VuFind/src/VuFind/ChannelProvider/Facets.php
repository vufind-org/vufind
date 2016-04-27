<?php

namespace VuFind\ChannelProvider;

class Facets
{
    public function configureSearchParams($params)
    {
        $params->addFacet('topic_facet');
        $params->addFacet('author_facet');
    }

    public function getFromSearch($results)
    {
        $maxFieldsToSuggest = 2;
        $maxValuesToSuggestPerField = 2;

        $channels = [];
        $fieldCount = 0;
        foreach ($results->getFacetList() as $field => $details) {
            $currentValueCount = 0;
            foreach ($details['list'] as $current) {
                if (!$current['isApplied']) {
                    $channels[] = $this
                        ->buildChannelFromFacet($results, $field, $current);
                    $currentValueCount++;
                }
                if ($currentValueCount >= $maxValuesToSuggestPerField) {
                    break;
                }
            }
            if ($currentValueCount >= $maxValuesToSuggestPerField) {
                $fieldCount++;
            }
            if ($fieldCount >= $maxFieldsToSuggest) {
                break;
            }
        }
        return $channels;
    }

    protected function summarizeResults($results)
    {
        $summary = [];
        foreach ($results->getResults() as $current) {
            $summary[] = [
                'title' => $current->getTitle(),
                'source' => $current->getSourceIdentifier(),
                'thumbnail' => $current->getThumbnail('medium'),
                'id' => $current->getUniqueId(),
            ];
        }
        return $summary;
    }

    protected function buildChannelFromFacet($results, $field, $value)
    {
        $newResults = clone($results);
        $params = $newResults->getParams();
        $params->addFilter("$field:{$value['value']}");
        $newResults->performAndProcessSearch();
        return [
            'title' => "$field is {$value['displayText']}",
            'contents' => $this->summarizeResults($newResults)
        ];
    }
}