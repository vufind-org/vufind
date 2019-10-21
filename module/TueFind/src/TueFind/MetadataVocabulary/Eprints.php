<?php

namespace TueFind\MetadataVocabulary;

class Eprints extends AbstractBase {
    protected $vocabFieldToGenericFieldsMap = ['eprints.creators_name' => 'author',
                                               'eprints.date' => 'date',
                                               'eprints.issn' => 'issn',
                                               'eprints.number' => 'volume',
                                               'eprints.publication' => 'container_title',
                                               'eprints.publisher' => 'publisher',
                                               'eprints.title' => 'title',
                                            ];

    public function addMetatags(\VuFind\RecordDriver\DefaultRecord $driver) {
        parent::addMetatags($driver);

        // special handling for pagerange
        $startpage = $driver->getContainerStartPage();
        $endpage = $driver->getContainerEndPage();

        if ($startpage) {
            $pagerange = $startpage;
            if ($endpage != '' && $endpage != $startpage)
                $pagerange = $startpage . '-' . $endpage;
            $this->metaHelper->appendName('eprints.pagerange', $pagerange);
        }
    }
}
