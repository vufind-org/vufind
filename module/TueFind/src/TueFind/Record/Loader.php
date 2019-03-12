<?php

namespace TueFind\Record;

use VuFind\Exception\RecordMissing as RecordMissingException;

class Loader extends \VuFind\Record\Loader {
    public function load($id, $source = DEFAULT_SEARCH_BACKEND,
        $tolerateMissing = false
    ) {
        if (null !== $id && '' !== $id) {
            $results = [];
            if (null !== $this->recordCache
                && $this->recordCache->isPrimary($source)
            ) {
                $results = $this->recordCache->lookup($id, $source);
            }
            if (empty($results)) {
                $results = $this->searchService->retrieve($source, $id)
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
        }

        // TueFind: use fallback like in parent's "loadBatchForSource" function
        // (this change might also be sent to vufind.org for future versions)
        if ($this->fallbackLoader
            && $this->fallbackLoader->has($source)
        ) {
            $fallbackRecords = $this->fallbackLoader->get($source)
                ->load([$id]);

            if (!empty($fallbackRecords)) {
                return $fallbackRecords[0];
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
}
