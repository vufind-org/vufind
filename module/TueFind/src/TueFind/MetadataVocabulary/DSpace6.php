<?php

namespace TueFind\MetadataVocabulary;

class DSpace6 extends \VuFind\MetadataVocabulary\AbstractBase
{
    protected $dspaceMap = [
        [
            'key' => 'dc.contributor.author',
            'schema' => 'dc',
            'source' => 'author',
            'element' => 'contributor',
            'qualifier' => 'author',
            'language' => '',
        ],
        [
            'key' => 'dc.date.issued',
            'schema' => 'dc',
            'source' => 'date',
            'element' => 'date',
            'qualifier' => 'issued',
            'language' => '',
        ],
        [
            'key' => 'dc.language.iso',
            'schema' => 'dc',
            'source' => 'language',
            'element' => 'language',
            'qualifier' => 'iso',
            'language' => '',
            'map' => [
                'English' => 'en',
                'German' => 'de',
            ],
        ],
        [
            'key' => 'dc.publisher',
            'schema' => 'dc',
            'source' => 'publisher',
            'element' => 'publisher',
            'qualifier' => '',
            'language' => '',
        ],
        [
            'key' => 'dc.title',
            'schema' => 'dc',
            'source' => 'title',
            'element' => 'title',
            'qualifier' => '',
            'language' => '',
        ],
    ];


    protected function getMetadataTemplateFromMapEntry(array $mapEntry)
    {
        $keysToKeep = ['key', 'language', 'schema', 'element', 'qualifier'];
        $template = [];
        foreach ($keysToKeep as $key) {
            if (isset($mapEntry[$key])) {
                $template[$key] = $mapEntry[$key];
            }
        }
        return $template;
    }

    // Example, see: https://wiki.lyrasis.org/display/DSDOC6x/REST+API#RESTAPI-ItemObject
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
                $dspaceMetadata = $this->getMetadataTemplateFromMapEntry($mapEntry);

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
