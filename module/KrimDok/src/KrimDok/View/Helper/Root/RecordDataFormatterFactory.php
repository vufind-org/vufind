<?php

namespace KrimDok\View\Helper\Root;

use VuFind\View\Helper\Root\RecordDataFormatter\SpecBuilder;

class RecordDataFormatterFactory extends \TueFind\View\Helper\Root\RecordDataFormatterFactory {

    /**
     * Get default specifications for displaying data in core metadata.
     *
     * @return array
     */
    public function getDefaultCoreSpecs()
    {
        $spec = new SpecBuilder();

        $this->addFollowingTitle($spec); // TueFind specific
        $this->addPrecedingTitle($spec);  // TueFind specific
        $this->addDeduplicatedAuthors($spec);
        $this->addFormats($spec);
        $this->addLanguages($spec);
        $this->addPublications($spec);
        $this->addContainerIdsAndTitles($spec);
        $this->addEdition($spec);
        $this->addOnlineAccess($spec);
        $this->addJOP($spec);
        // Availability in Tübingen (KrimDok-specific)
        $spec->setTemplateLine(
            'Availability in Tubingen', 'showAvailabilityInTuebingen', 'data-availability_in_tuebingen.phtml'
        );
        $this->addHBZ($spec);
        // PDA (KrimDok-specific)
        $spec->setTemplateLine(
            'PDA', 'showPDA', 'data-PDA.phtml'
        );
        $this->addSubito($spec);
        $this->addVolumesAndArticles($spec);
        $this->addSubjects($spec);
        $this->addTags($spec);
        $this->addRecordLinks($spec);
        $this->addLicense($spec); // TueFind specific

        return $spec->getArray();
    }
}