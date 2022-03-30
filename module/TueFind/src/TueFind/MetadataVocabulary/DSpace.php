<?php

namespace TueFind\MetadataVocabulary;

class DSpace extends \VuFind\MetadataVocabulary\AbstractBase
{
    protected $vocabFieldToGenericFieldsMap = [ '/sections/traditionalpageone/dc.contributor.author' => 'author',
                                                '/sections/traditionalpageone/dc.date.issued' => 'date',
                                                '/sections/traditionalpageone/dc.language.iso' => 'language',
                                                '/sections/traditionalpageone/dc.publisher' => 'publisher',
                                                '/sections/traditionalpageone/dc.title' => 'title',
                                                /*
                                                'DC.relation.ispartof' => 'container_title',
                                                'DC.citation.epage' => 'endpage',
                                                'DC.citation.issue' => 'issue',
                                                'DC.citation.spage' => 'startpage',
                                                'DC.citation.volume' => 'volume',
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
            $dspaceData[$authorKey] = $dspaceData[$authorKey][0] . ';' . '02a88394-6161-44ce-a0c0-5f1640137bf4';
        }

        $issn = $driver->tryMethod('getCleanISSN');
        if ($issn != null) {
            $dspaceData['/sections/traditionalpageone/dc.identifier'] = "issn;" . $controlNumber;
        }

        /*
        switch ($metaKey) {
            break;
            case"title.alternative":
                $path = '/sections/traditionalpageone/dc.title.alternative';
            break;
            case"citation":
                $path = '/sections/traditionalpageone/dc.identifier.citation';
            break;
            case"ispartofseries":
                $path = '/sections/traditionalpageone/dc.relation.ispartofseries';
            break;
            case"subject.keywords":
                $path = '/sections/traditionalpagetwo/dc.subject';
            break;
            case"abstract":
                $path = '/sections/traditionalpagetwo/dc.description.abstract';
            break;
            case"description":
                $path = '/sections/traditionalpagetwo/dc.description';
            break;
            case"sponsorship":
                $path = '/sections/traditionalpagetwo/dc.description.sponsorship';
            break;
            case"type":
                $path = '/sections/traditionalpageone/dc.type';
            break;
            case"author":
                $path = '/sections/traditionalpageone/dc.contributor.author';

            break;
            case"identifiers":
                $explodeValue = explode(';', $metaValue);
                $metaValue = $explodeValue[1];
                $identifierType = $explodeValue[0];
                if ($identifierType == 'issn') {
                    $path = '/sections/traditionalpageone/dc.identifier.issn';
                } else {
                    $path = '/sections/traditionalpageone/dc.identifier.other';
                }
            break;
        }*/
        return $dspaceData;
    }
}
