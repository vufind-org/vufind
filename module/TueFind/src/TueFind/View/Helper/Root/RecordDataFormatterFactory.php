<?php

namespace TueFind\View\Helper\Root;

/**
 * Wraps native VuFind fields from parent,
 * as well as shared TueFind fields like HBZ, JOP, Subito, ...
 */
class RecordDataFormatterFactory extends \VuFind\View\Helper\Root\RecordDataFormatterFactory {

    protected function addChildRecords(&$spec) {
        $spec->setTemplateLine(
            'child_records', 'getChildRecordCount', 'data-childRecords.phtml',
            ['allowZero' => false]
        );
    }

    protected function addContainerIdsAndTitles(&$spec) {
        $spec->setTemplateLine(
            'In', 'showContainerIdsAndTitles', 'data-container_ids_and_titles.phtml'
        );
    }

    protected function addDeduplicatedAuthors(&$spec) {
        $spec->setMultiLine(
            'Authors', 'getDeduplicatedAuthors', $this->getAuthorFunction()
        );
    }

    protected function addEdition(&$spec) {
        $spec->setLine(
            'Edition', 'getEdition', null,
            ['prefix' => '<span property="bookEdition">', 'suffix' => '</span>']
        );
    }

    protected function addFormats(&$spec) {
        $spec->setLine(
            'Format', 'getFormats', 'RecordHelper',
            ['helperMethod' => 'getFormatList']
        );
    }

    protected function addHBZ(&$spec) {
        $spec->setTemplateLine(
            'Check Availability', 'showHBZ', 'data-HBZ.phtml'
        );
    }

    protected function addJOP(&$spec) {
        $spec->setTemplateLine(
            'Journals Online & Print', 'showJOP', 'data-JOP.phtml'
        );
    }

    protected function addLanguages(&$spec) {
        // note: translation added, will probably be fixed in VuFind 6
        $spec->setLine('Language', 'getLanguages', null, ['translate' => true]);
    }

    protected function addFollowingTitle(&$spec) {
        // We use the "New Title" display text here because we can re-use
        // vufind-org translations for it.
        $spec->setTemplateLine(
            'New Title', 'getFollowingPPNAndTitle', 'data-following_title.phtml'
        );
    }

    protected function addLicense(&$spec) {
        $spec->setTemplateLine(
            'License', 'getLicense', 'data-license.phtml'
        );
    }

    protected function addOnlineAccess(&$spec) {
        // = URLs and material types
        $spec->setTemplateLine('Online Access', true, 'data-onlineAccess.phtml');
    }

    protected function addPrecedingTitle(&$spec) {
        $spec->setTemplateLine(
            'Previous Title', 'getPrecedingPPNAndTitle', 'data-preceding_title.phtml'
        );
    }

    protected function addPublications(&$spec) {
        $spec->setTemplateLine(
            'Published', 'getPublicationDetails', 'data-publicationDetails.phtml'
        );
    }

    protected function addPublishedIn(&$spec) {
        $spec->setTemplateLine(
            'Published in', 'getContainerTitle', 'data-containerTitle.phtml'
        );
    }

    protected function addRecordLinks(&$spec) {
        $spec->setTemplateLine(
            'Related Items', 'getAllRecordLinks', 'data-allRecordLinks.phtml'
        );
    }

    protected function addSeries(&$spec) {
        $spec->setTemplateLine('Series', 'getSeries', 'data-series.phtml');
    }

    protected function addSubito(&$spec) {
        $spec->setTemplateLine(
            'Subito Delivery Service', 'showSubito', 'data-subito.phtml'
        );
    }

    protected function addSubjects(&$spec) {
        $spec->setTemplateLine(
            'Subjects', 'getAllSubjectHeadings', 'data-allSubjectHeadings.phtml'
        );
    }

    protected function addTags(&$spec) {
        $spec->setTemplateLine('Tags', true, 'data-tags.phtml');
    }

    protected function addVolumesAndArticles(&$spec) {
        $spec->setTemplateLine(
            'Volumes / Articles', 'hasInferiorWorksInCurrentSubsystem', 'data-volumes_articles.phtml'
        );
    }
}