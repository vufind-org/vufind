<?php

namespace KrimDok\View\Helper\Root;

use Interop\Container\ContainerInterface;
use VuFind\View\Helper\Root\RecordDataFormatter\SpecBuilder;

class RecordDataFormatterFactory extends \VuFind\View\Helper\Root\RecordDataFormatterFactory {

    /**
     * Get default specifications for displaying data in core metadata.
     *
     * @return array
     */
    public function getDefaultCoreSpecs()
    {
        $spec = new SpecBuilder();

        // Published in (Journal Title)
        $spec->setTemplateLine(
            'Published in', 'getContainerTitle', 'data-containerTitle.phtml'
        );
        // Newer Titles
        $spec->setLine(
            'New Title', 'getNewerTitles', null, ['recordLink' => 'title']
        );
        // Prev Titles
        $spec->setLine(
            'Previous Title', 'getPreviousTitles', null, ['recordLink' => 'title']
        );
        // Deduplicated Authors (primary, corporate, secondary)
        $spec->setMultiLine(
            'Authors', 'getDeduplicatedAuthors', $this->getAuthorFunction()
        );
        // Formats
        $spec->setLine(
            'Format', 'getFormats', 'RecordHelper',
            ['helperMethod' => 'getFormatList']
        );
        // Languages
        $spec->setLine('Language', 'getLanguages');
        // Publications
        $spec->setTemplateLine(
            'Published', 'getPublicationDetails', 'data-publicationDetails.phtml'
        );
        // Container IDs and Titles
        $spec->setTemplateLine(
            'In', 'showContainerIdsAndTitles', 'data-container_ids_and_titles.phtml'
        );
        // Edition
        $spec->setLine(
            'Edition', 'getEdition', null,
            ['prefix' => '<span property="bookEdition">', 'suffix' => '</span>']
        );
        // Online Access (URLS and material types)
        $spec->setTemplateLine('Online Access', true, 'data-onlineAccess.phtml');
        // JOP
        $spec->setTemplateLine(
            'Journals Online & Print', 'showJOP', 'data-JOP.phtml'
        );
        // Availability
        $spec->setTemplateLine(
            'Availability in Tubingen', 'showAvailability', 'data-availability.phtml'
        );
        // HBZ
        $spec->setTemplateLine(
            'Check Availability', 'showHBZ', 'data-HBZ.phtml'
        );
        // PDA (KrimDok-specific)
        $spec->setTemplateLine(
            'PDA', 'isAvailableForPDA', 'data-PDA.phtml'
        );
        // Subito
        $spec->setTemplateLine(
            'Subito Delivery Service', 'showSubito', 'data-subito.phtml'
        );
        // Volumes / Articles (Superior Work)
        $spec->setTemplateLine(
            'Volumes / Articles', 'isRealSuperiorWork', 'data-volumes_articles.phtml'
        );
        // Subjects
        $spec->setTemplateLine(
            'Subjects', 'getAllSubjectHeadings', 'data-allSubjectHeadings.phtml'
        );
        // Tags
        $spec->setTemplateLine('Tags', true, 'data-tags.phtml');
        // Record Links
        $spec->setTemplateLine(
            'Related Items', 'getAllRecordLinks', 'data-allRecordLinks.phtml'
        );
        
        return $spec->getArray();
    }
}