<?php

namespace TueFind\Record;

use VuFind\Exception\RecordMissing as RecordMissingException;
use VuFindSearch\ParamBag;

class Loader extends \VuFind\Record\Loader {
    public function load($id, $source = DEFAULT_SEARCH_BACKEND,
        $tolerateMissing = false, ParamBag $params = null
    ) {
        if (null !== $id && '' !== $id) {
            $results = [];
            if (null !== $this->recordCache
                && $this->recordCache->isPrimary($source)
            ) {
                $results = $this->recordCache->lookup($id, $source);
            }
            if (empty($results)) {
                $results = $this->searchService->retrieve($source, $id, $params)
                    ->getRecords();
            }
            if (empty($results) && null !== $this->recordCache
                && $this->recordCache->isFallback($source)
            ) {
                $results = $this->recordCache->lookup($id, $source);
            }

            if (count($results) == 1) {
                return $results[0];
            }

            // TueFind: use fallback like in parent's "loadBatchForSource" function
            // (this change might also be sent to vufind.org for future versions)
            if ($this->fallbackLoader
                && $this->fallbackLoader->has($source)
            ) {
                $fallbackRecords = $this->fallbackLoader->get($source)
                    ->load([$id]);

                if (count($fallbackRecords) == 1) {
                    return $fallbackRecords[0];
                }
            }
        }
        if ($tolerateMissing) {
            $record = $this->recordFactory->get('Missing');
            $record->setRawData(['id' => $id]);
            $record->setSourceIdentifier($source);
            return $record;
        }
        throw new RecordMissingException(
            'Record ' . $source . ':' . $id . ' does not exist.'
        );
    }

    public function loadAuthorityRecordByGNDNumber($gndNumber) {
        $source = 'SolrAuth';

        if (null !== $gndNumber && '' !== $gndNumber) {
            $results = [];

            // no primary cache

            // use search instead of lookup logic
            if (empty($results)) {
                $query = new \VuFindSearch\Query\Query('gnd:' . $gndNumber);
                $results = $this->searchService->search($source, $query);
                if ($results->first() !== null)
                    return $results->first();
                $results = [];
            }

            // no fallback cache

            if (!empty($results)) {
                return $results[0];
            }

            // no fallback loader
        }
        // no "tolerate missing" logic

        throw new RecordMissingException(
            'Record ' . $source . ':' . $gndNumber . ' does not exist.'
        );
    }
}
