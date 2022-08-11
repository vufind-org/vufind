<?php

namespace TueFind\MetadataVocabulary;

class DSpace6 extends \VuFind\MetadataVocabulary\AbstractBase
{
    protected $dspaceMap = [
        [
            'key' => 'dc.contributor.author',
            'source' => 'author',
        ],
        [
            'key' => 'dc.date.issued',
            'source' => 'date',
        ],
        [
            'key' => 'dc.language.iso',
            'source' => 'language',
            'map' => [
                'English' => 'en',
                'German' => 'de',
            ],
        ],
        [
            'key' => 'dc.publisher',
            'source' => 'publisher',
        ],
        [
            'key' => 'dc.title',
            'source' => 'title',
        ],
    ];


    // Examples, see:
    // - https://wiki.lyrasis.org/display/DSDOC6x/REST+API#RESTAPI-ItemObject
    // - https://wiki.lyrasis.org/display/DSDOC6x/REST+API#RESTAPI-MetadataEntryObject
    public function getMappedData(\VuFind\RecordDriver\AbstractBase $driver)
    {
        $rawData = parent::getGenericData($driver);
        $dspaceItem = ['name' => $rawData['title'], 'type' => 'item', 'metadata' => []];

        foreach ($this->dspaceMap as $mapEntry) {
            $rawDataKey = $mapEntry['source'];
            if (!isset($rawData[$rawDataKey])) {
                continue;
            }

            $values = $rawData[$rawDataKey];
            if (!is_array($values)) {
                $values = [$values];
            }

            foreach ($values as $value) {
                $dspaceMetadata = ['key' => $mapEntry['key']];

                if (isset($mapEntry['map'])) {
                    if (!isset($mapEntry['map'][$value])) {
                        throw new \Exception('Could not map ' . $value . ' to a proper DSpace value for ' . $mapEntry['key']);
                    }
                    $value = $mapEntry['map'][$value];
                }
                $dspaceMetadata['value'] = $value;
                $dspaceItem['metadata'][] = $dspaceMetadata;
            }
        }

        return $dspaceItem;
    }
}
