<?php

namespace TueFind\MetadataVocabulary;

class DSpace7 extends \VuFind\MetadataVocabulary\AbstractBase
{
    protected $vocabFieldToGenericFieldsMap = [ '/sections/traditionalpageone/dc.contributor.author' => 'author',
                                                '/sections/traditionalpageone/dc.date.issued' => 'date',
                                                '/sections/traditionalpageone/dc.language.iso' => 'language',
                                                '/sections/traditionalpageone/dc.publisher' => 'publisher',
                                                '/sections/traditionalpageone/dc.title' => 'title',
                                                /*
                                                '/sections/traditionalpageone/dc.title.alternative' => 'title.alternative',
                                                '/sections/traditionalpageone/dc.identifier.citation' => 'citation',
                                                '/sections/traditionalpageone/dc.relation.ispartofseries' => 'ispartofseries',
                                                '/sections/traditionalpagetwo/dc.subject' => 'subject.keywords',
                                                '/sections/traditionalpagetwo/dc.description.abstract' => 'abstract',
                                                '/sections/traditionalpagetwo/dc.description' => 'description',
                                                '/sections/traditionalpagetwo/dc.description.sponsorship' => 'sponsorship',
                                                '/sections/traditionalpageone/dc.type' => 'type',
                                                */
                                            ];

    protected $languageMap = [
            'English' => 'en',
            'German' => 'de',
    ];

    public function getMappedData(\VuFind\RecordDriver\AbstractBase $driver)
    {
        $mappedData = parent::getMappedData($driver);

        $dspaceData = [];
        foreach ($mappedData as $key => $value) {
            if (is_array($value)) {
                $dspaceData[$key] = $value[0];
            } else {
                $dspaceData[$key] = $value;
            }
        }

        $languageKey = '/sections/traditionalpageone/dc.language.iso';
        if (isset($dspaceData[$languageKey])) {
            $dspaceData[$languageKey] = $this->languageMap[$dspaceData[$languageKey][0]] ?? 'en';
        }

        $authorKey = '/sections/traditionalpageone/dc.contributor.author';
        if (isset($dspaceData[$authorKey])) {
            // TODO: Careful here, we might need to support multiple authors and also implement an ID lookup
            $dspaceData[$authorKey] = $dspaceData[$authorKey] . ';' . '02a88394-6161-44ce-a0c0-5f1640137bf4';
        }

        $issn = $driver->tryMethod('getCleanISSN');
        if ($issn != null) {
            $dspaceData['/sections/traditionalpageone/dc.identifier'] = "issn;" . $driver->getUniqueID();
        }

        return $dspaceData;
    }
}
