<?php

namespace IxTheo\View\Helper\Root;

use Interop\Container\ContainerInterface;
use VuFind\View\Helper\Root\RecordDataFormatter\SpecBuilder;

class RecordDataFormatterFactory extends \TueFind\View\Helper\Root\RecordDataFormatterFactory {

    /**
     * User Account Capabilites Service
     * @var \VuFind\Config\AccountCapabilities
     */
    protected $accountCapabilities;

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
        $this->accountCapabilities = $container->get(\VuFind\Config\AccountCapabilities::class);
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
        $this->addPublishedIn($spec);
        $this->addFollowingTitle($spec); // TueFind specific
        $this->addPrecedingTitle($spec);  // TueFind specific
        // Other Titles (IxTheo-specific)
        $spec->setLine(
            'Other Titles', 'getOtherTitles', null, ['recordLink' => 'title']
        );
        $this->addDeduplicatedAuthors($spec);
        $this->addFormats($spec);
        $this->addLanguages($spec);
        $this->addSubito($spec);
        $this->addHBZ($spec);
        $this->addJOP($spec);
        // PDA (IxTheo-specific)
        if ($this->accountCapabilities->getPdaSetting()) {
            $spec->setTemplateLine(
                'PDA', 'showPDA', 'data-PDA.phtml', ['rowId' => 'pda_row']
            );
        }
        // TAD (IxTheo-specific)
        if ($this->user != null && $this->dbTablePluginManager->get('user')->canUseTAD($this->user->id)) {
            $spec->setTemplateLine(
                'TAD', 'workIsTADCandidate', 'data-TAD.phtml'
            );
        }
        $this->addPublications($spec);
        $this->addContainerIdsAndTitles($spec);
        // Reviews (IxTheo-specific)
        $spec->setTemplateLine(
            'Reviews', 'getReviews', 'data-reviews.phtml'
        );
        // Reviewed Records (IxTheo-specific)
        $spec->setTemplateLine(
            'Reviewed', 'getReviewedRecords', 'data-reviewed_records.phtml'
        );
        // Enclosed Titles (IxTheo-specific)
        $spec->setTemplateLine(
            'Enclosed titles', 'getEnclosedTitlesWithAuthors', 'data-enclosed_titles.phtml'
        );
        // Subscription Bundle (IxTheo-specific)
        $spec->setTemplateLine(
            'Subscription Bundle Journals', 'isSubscriptionBundle', 'data-subscription_bundle.phtml'
        );
        $this->addVolumesAndArticles($spec);
        $this->addEdition($spec);
        $this->addSeries($spec);
        // Standardized Subjects (IxTheo-specific)
        $spec->setTemplateLine(
            'Standardized Subjects', 'getAllStandardizedSubjectHeadings', 'data-allStandardizedSubjectHeadings.phtml'
        );

        // Classification (IxTheo-specific)
        $spec->setTemplateLine(
            'IxTheo Classification', 'getAllClassification', 'data-classification.phtml'
        );

        // Non-standardized Subjects (IxTheo-specific)
        $spec->setTemplateLine(
            'Nonstandardized Subjects', 'getAllNonStandardizedSubjectHeadings', 'data-allNonStandardizedSubjectHeadings.phtml'
        );
        $this->addChildRecords($spec);
        $this->addOnlineAccess($spec);
        $this->addLicense($spec); // TueFind specific
        // Parallel Edition PPNs + Unlinked parallel Editions (IxTheo-specific)
        $spec->setTemplateLine(
                'Parallel Edition', true, 'data-parallel_edition.phtml'
        );
        $this->addRecordLinks($spec);
        $this->addTags($spec);

        return $spec->getArray();
    }

    public function getDefaultDescriptionSpecs()
    {
        $spec = new SpecBuilder();
        $spec->setTemplateLine('Summary', true, 'data-summary.phtml');
        $spec->setLine('Published', 'getDateSpan');
        // Item Description (IxTheo-specific)
        $spec->setTemplateLine('Item Description', 'getGeneralNotes', 'data-general-notes.phtml');
        $spec->setLine('Physical Description', 'getPhysicalDescriptions');
        $spec->setLine('Publication Frequency', 'getPublicationFrequency');
        $spec->setLine('Playing Time', 'getPlayingTimes');
        $spec->setLine('Format', 'getSystemDetails');
        $spec->setLine('Audience', 'getTargetAudienceNotes');
        $spec->setLine('Awards', 'getAwards');
        $spec->setLine('Production Credits', 'getProductionCredits');
        $spec->setLine('Bibliography', 'getBibliographyNotes');
        // Clean ISBN with schema.org-property (IxTheo-specific)
        $spec->setLine(
            'ISBN', 'getCleanISBN', null,
            ['prefix' => '<span property="isbn">', 'suffix' => '</span>']
        );
        // ISSN with schema.org-property (IxTheo-specific)
        $spec->setLine(
            'ISSN', 'getISSNs', null,
            ['prefix' => '<span property="issn">', 'suffix' => '</span>']
        );
        $spec->setLine('Related Items', 'getRelationshipNotes');
        $spec->setLine('Access', 'getAccessRestrictions');
        $spec->setLine('Finding Aid', 'getFindingAids');
        $spec->setLine('Publication_Place', 'getHierarchicalPlaceNames');
        $spec->setTemplateLine('Author Notes', true, 'data-authorNotes.phtml');
        // References (IxTheo-specific)
        $spec->setTemplateLine('Reference', 'getReferenceInformation', 'data-references.phtml');
        // Contains (IxTheo-specific)
        $spec->setTemplateLine('Contains', 'getContainsInformation', 'data-contains.phtml');
        // Persistent identifiers (IxTheo-specific)
        $spec->setTemplateLine('Persistent identifiers', 'getTypesAndPersistentIdentifiers', 'data-persistent_identifiers.phtml');
        return $spec->getArray();
    }
}
