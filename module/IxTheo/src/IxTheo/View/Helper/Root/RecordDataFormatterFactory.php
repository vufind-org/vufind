<?php

namespace IxTheo\View\Helper\Root;

use Interop\Container\ContainerInterface;
use VuFind\View\Helper\Root\RecordDataFormatter\SpecBuilder;

class RecordDataFormatterFactory extends \TueFind\View\Helper\Root\RecordDataFormatterFactory {

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
        $this->addPublishedIn($spec);
        $this->addNewerTitles($spec);
        $this->addPreviousTitles($spec);
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
        $spec->setTemplateLine(
            'PDA', 'showPDA', 'data-PDA.phtml', ['rowId' => 'pda_row']
        );
        // TAD (IxTheo-specific)
        if ($this->user != null && $this->dbTablePluginManager->get('IxTheoUser')->canUseTAD($this->user->id)) {
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
        $spec->setLine(
            'Enclosed titles', 'getEnclosedTitles'
        );
        // Subscription Bundle (IxTheo-specific)
        $spec->setTemplateLine(
            'Subscription Bundle Journals', 'isSubscriptionBundle', 'data-subscription_bundle.phtml'
        );
        $this->addVolumesAndArticles($spec);
        $this->addEdition($spec);
        $this->addSeries($spec);
        $this->addSubjects($spec);
        $this->addChildRecords($spec);
        $this->addOnlineAccess($spec);
        // Parallel Edition PPNs + Unlinked parallel Editions (IxTheo-specific)
        $spec->setTemplateLine(
                'Parallel Edition', true, 'data-parallel_edition.phtml'
        );
        $this->addRecordLinks($spec);
        $this->addTags($spec);

        return $spec->getArray();
    }
}