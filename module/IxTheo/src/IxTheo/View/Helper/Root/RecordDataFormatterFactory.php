<?php

namespace IxTheo\View\Helper\Root;

use Interop\Container\ContainerInterface;
use VuFind\View\Helper\Root\RecordDataFormatter\SpecBuilder;

class RecordDataFormatterFactory extends \VuFind\View\Helper\Root\RecordDataFormatterFactory {

    /**
     * Db Table Plugin Manager (e.g. to check user-specific rights)
     * @var \VuFind\Db\Table\PluginManager
     */
    protected $dbTablePluginManager;

    /**
     * The logged in user, or null if not logged in
     * @var \VuFind\Db\Row\User
     */
    protected $user;

    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        $this->user = $container->get('ViewHelperManager')->get('auth')->getManager()->isLoggedIn();
        $this->dbTablePluginManager = $container->get('VuFind\Db\Table\PluginManager');
        return parent::__invoke($container, $requestedName, $options);
    }

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
        // Other Titles
        $spec->setLine(
            'Other Titles', 'getOtherTitles', null, ['recordLink' => 'title']
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
        // Subito
        $spec->setTemplateLine(
            'Subito Delivery Service', 'showSubito', 'data-subito.phtml'
        );
        // HBZ
        $spec->setTemplateLine(
            'Check Availability', 'showHBZ', 'data-HBZ.phtml'
        );
        // JOP
        $spec->setTemplateLine(
            'Journals Online & Print', 'showJOP', 'data-JOP.phtml'
        );
        // PDA (IxTheo-specific)
        $spec->setTemplateLine(
            'PDA', 'showPDA', 'data-PDA.phtml', ['rowId' => 'pda_row']
        );
        // TAD
        if ($this->user != null && $this->dbTablePluginManager->get('IxTheoUser')->canUseTAD($this->user->id)) {
            $spec->setTemplateLine(
                'TAD', 'workIsTADCandidate', 'data-TAD.phtml'
            );
        }
        // publications
        $spec->setTemplateLine(
            'Published', 'getPublicationDetails', 'data-publicationDetails.phtml'
        );
        // ContainerIDsandTitles
        $spec->setTemplateLine(
            'In', 'showContainerIdsAndTitles', 'data-container_ids_and_titles.phtml'
        );
        // Reviews
        $spec->setTemplateLine(
            'Reviews', 'getReviews', 'data-reviews.phtml'
        );
        // Reviewed Records
        $spec->setTemplateLine(
            'Reviewed', 'getReviewedRecords', 'data-reviewed_records.phtml'
        );
        // Enclosed Titles
        $spec->setTemplateLine(
            'Enclosed titles', 'getEnclosedTitles', 'data-reviewed_records.phtml'
        );
        // Subscription Bundle
        $spec->setTemplateLine(
            'Subscription Bundle Journals', 'isSubscriptionBundle', 'data-subscription_bundle.phtml'
        );
        // Volumes / Articles (Superior Work)
        $spec->setTemplateLine(
            'Volumes / Articles', 'isRealSuperiorWork', 'data-volumes_articles.phtml'
        );
        // Edition
        $spec->setLine(
            'Edition', 'getEdition', null,
            ['prefix' => '<span property="bookEdition">', 'suffix' => '</span>']
        );
        // Series
        $spec->setTemplateLine('Series', 'getSeries', 'data-series.phtml');
        // Subjects (Standardized / Non-Standardized)
        $spec->setTemplateLine(
            'Subjects', 'getAllSubjectHeadings', 'data-allSubjectHeadings.phtml'
        );
        // Child records
        $spec->setTemplateLine(
            'child_records', 'getChildRecordCount', 'data-childRecords.phtml',
            ['allowZero' => false]
        );
        // Online Access (URLS and material types)
        $spec->setTemplateLine('Online Access', true, 'data-onlineAccess.phtml');
        // Parallel Edition PPNs + Unlinked parallel Editions
        $spec->setTemplateLine('Parallel Edition', true, 'data-parallel_edition.phtml');
        // recordLinks
        $spec->setTemplateLine(
            'Related Items', 'getAllRecordLinks', 'data-allRecordLinks.phtml'
        );
        // Tags
        $spec->setTemplateLine('Tags', true, 'data-tags.phtml');

        return $spec->getArray();
    }
}