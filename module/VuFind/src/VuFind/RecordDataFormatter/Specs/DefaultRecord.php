<?php

/**
 * DefaultRecord RecordDataFormatter specs.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  RecordDataFormatter
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture:record_data_formatter
 * Wiki
 */

namespace VuFind\RecordDataFormatter\Specs;

use VuFind\View\Helper\Root\RecordDataFormatter\SpecBuilder;
use VuFind\View\Helper\Root\SchemaOrg;

use function count;

/**
 * DefaultRecord RecordDataFormatter specs.
 *
 * @category VuFind
 * @package  RecordDataFormatter
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture:record_data_formatter
 * Wiki
 */
class DefaultRecord extends AbstractBase
{
    /**
     * The order in which groups of authors are displayed.
     *
     * The dictionary keys here correspond to the dictionary keys in the $labels
     * array in getAuthorFunction()
     *
     * @var array<string, int>
     */
    protected array $authorOrder = ['primary' => 1, 'corporate' => 2, 'secondary' => 3];

    /**
     * Constructor
     *
     * @param array      $config          Config
     * @param ?SchemaOrg $schemaOrgHelper schema.org helper
     */
    public function __construct(array $config, protected ?SchemaOrg $schemaOrgHelper = null)
    {
        parent::__construct($config);
    }

    /**
     * Initialize specs.
     *
     * @return void
     */
    protected function init(): void
    {
        $this->setDefaults('collection-info', [$this, 'getDefaultCollectionInfoSpecs'])
            ->setDefaults('collection-record', [$this, 'getDefaultCollectionRecordSpecs'])
            ->setDefaults('core', [$this, 'getDefaultCoreSpecs'])
            ->setDefaults('description', [$this, 'getDefaultDescriptionSpecs']);
    }

    /**
     * Get the callback function for processing authors.
     *
     * @return callable
     */
    public function getAuthorFunction(): callable
    {
        return function ($data, $options) {
            // Lookup array of singular/plural labels (note that Other is always
            // plural right now due to lack of translation strings).
            $labels = [
                'primary' => ['Main Author', 'Main Authors'],
                'corporate' => ['Corporate Author', 'Corporate Authors'],
                'secondary' => ['Other Authors', 'Other Authors'],
            ];
            // Lookup array of schema labels.
            $schemaLabels = [
                'primary' => 'author',
                'corporate' => 'creator',
                'secondary' => 'contributor',
            ];

            // Sort the data:
            $final = [];
            foreach ($data as $type => $values) {
                $final[] = [
                    'label' => $labels[$type][count($values) == 1 ? 0 : 1],
                    'values' => [$type => $values],
                    'options' => [
                        'pos' => $options['pos'] + $this->authorOrder[$type],
                        'renderType' => 'RecordDriverTemplate',
                        'template' => 'data-authors.phtml',
                        'context' => [
                            'type' => $type,
                            'schemaLabel' => $schemaLabels[$type],
                            'requiredDataFields' => [
                                ['name' => 'role', 'prefix' => 'CreatorRoles::'],
                            ],
                        ],
                    ],
                ];
            }
            return $final;
        };
    }

    /**
     * Get the settings for formatting language lines.
     *
     * @return array
     */
    public function getLanguageLineSettings(): array
    {
        if ($this->schemaOrgHelper) {
            $langSpan = $this->schemaOrgHelper
                ->getTag('span', ['property' => 'availableLanguage', 'typeof' => 'Language']);
            $nameSpan = $this->schemaOrgHelper->getTag('span', ['property' => 'name']);
            $itemPrefix = $langSpan . $nameSpan;
            $itemSuffix = ($nameSpan ? '</span>' : '') . ($langSpan ? '</span>' : '');
        } else {
            $itemPrefix = $itemSuffix = '';
        }
        return compact('itemPrefix', 'itemSuffix') + [
            'translate' => true,
            'translationTextDomain' => 'ISO639-3::',
        ];
    }

    /**
     * Get default specifications for displaying data in collection-info metadata.
     *
     * @return array
     */
    protected function getDefaultCollectionInfoSpecs(): array
    {
        $spec = new SpecBuilder();
        $spec
            ->setMultiLine(
                'Authors',
                'getDeduplicatedAuthors',
                $this->getAuthorFunction()
            )->setLine('Summary', 'getSummary')
            ->setLine('Abstract', 'getAbstractNotes')
            ->setLine(
                'Format',
                'getFormats',
                'RecordHelper',
                ['helperMethod' => 'getFormatList']
            )->setLine(
                'Language',
                'getLanguages',
                null,
                $this->getLanguageLineSettings()
            )->setTemplateLine(
                'Published',
                'getPublicationDetails',
                'data-publicationDetails.phtml'
            )->setLine(
                'Edition',
                'getEdition',
                null,
                [
                    'itemPrefix' => '<span property="bookEdition">',
                    'itemSuffix' => '</span>',
                ]
            )->setTemplateLine('Series', 'getSeries', 'data-series.phtml')
            ->setTemplateLine(
                'Subjects',
                'getAllSubjectHeadings',
                'data-allSubjectHeadings.phtml'
            )->setTemplateLine('Online Access', true, 'data-onlineAccess.phtml')
            ->setTemplateLine(
                'Related Items',
                'getAllRecordLinks',
                'data-allRecordLinks.phtml'
            )->setLine('Notes', 'getGeneralNotes')
            ->setLine('Production Credits', 'getProductionCredits')
            ->setLine(
                'ISBN',
                'getISBNs',
                null,
                ['itemPrefix' => '<span property="isbn">', 'itemSuffix' => '</span>']
            )->setLine(
                'ISSN',
                'getISSNs',
                null,
                ['itemPrefix' => '<span property="issn">', 'itemSuffix' => '</span>']
            );
        return $spec->getArray();
    }

    /**
     * Get default specifications for displaying data in collection-record metadata.
     *
     * @return array
     */
    protected function getDefaultCollectionRecordSpecs(): array
    {
        $spec = new SpecBuilder();
        $spec
            ->setLine('Summary', 'getSummary')
            ->setLine('Abstract', 'getAbstractNotes')
            ->setMultiLine(
                'Authors',
                'getDeduplicatedAuthors',
                $this->getAuthorFunction()
            )->setLine(
                'Language',
                'getLanguages',
                null,
                $this->getLanguageLineSettings()
            )->setLine(
                'Format',
                'getFormats',
                'RecordHelper',
                ['helperMethod' => 'getFormatList']
            )->setLine('Access', 'getAccessRestrictions')
            ->setLine('Related Items', 'getRelationshipNotes');
        return $spec->getArray();
    }

    /**
     * Get default specifications for displaying data in core metadata.
     *
     * @return array
     */
    protected function getDefaultCoreSpecs(): array
    {
        $spec = new SpecBuilder();
        $spec
            ->setTemplateLine(
                'Published in',
                'getContainerTitle',
                'data-containerTitle.phtml'
            )->setLine(
                'New Title',
                'getNewerTitles',
                null,
                ['recordLink' => 'title']
            )->setLine(
                'Previous Title',
                'getPreviousTitles',
                null,
                ['recordLink' => 'title']
            )->setMultiLine(
                'Authors',
                'getDeduplicatedAuthors',
                $this->getAuthorFunction()
            )->setLine(
                'Format',
                'getFormats',
                'RecordHelper',
                ['helperMethod' => 'getFormatList']
            )->setLine(
                'Language',
                'getLanguages',
                null,
                $this->getLanguageLineSettings()
            )->setTemplateLine(
                'Published',
                'getPublicationDetails',
                'data-publicationDetails.phtml'
            )->setLine(
                'Edition',
                'getEdition',
                null,
                [
                    'itemPrefix' => '<span property="bookEdition">',
                    'itemSuffix' => '</span>',
                ]
            )->setTemplateLine('Series', 'getSeries', 'data-series.phtml')
            ->setTemplateLine(
                'Subjects',
                'getAllSubjectHeadings',
                'data-allSubjectHeadings.phtml'
            )->setTemplateLine(
                'Citations',
                'getCitations',
                'data-citations.phtml',
            )->setTemplateLine(
                'child_records',
                'getChildRecordCount',
                'data-childRecords.phtml',
                ['allowZero' => false]
            )->setTemplateLine('Online Access', true, 'data-onlineAccess.phtml')
            ->setTemplateLine(
                'Related Items',
                'getAllRecordLinks',
                'data-allRecordLinks.phtml'
            )->setTemplateLine('Tags', true, 'data-tags.phtml');
        return $spec->getArray();
    }

    /**
     * Get default specifications for displaying data in the description tab.
     *
     * @return array
     */
    protected function getDefaultDescriptionSpecs(): array
    {
        $spec = new SpecBuilder();
        $spec
            ->setTemplateLine('Summary', true, 'data-summary.phtml')
            ->setLine('Abstract', 'getAbstractNotes')
            ->setLine('Review', 'getReviewNotes')
            ->setLine('Content Advice', 'getContentAdviceNotes')
            ->setLine('Published', 'getDateSpan')
            ->setLine('Item Description', 'getGeneralNotes')
            ->setLine('Physical Description', 'getPhysicalDescriptions')
            ->setLine('Publication Frequency', 'getPublicationFrequency')
            ->setLine('Playing Time', 'getPlayingTimes')
            ->setLine('Format', 'getSystemDetails')
            ->setLine('Audience', 'getTargetAudienceNotes')
            ->setLine('Awards', 'getAwards')
            ->setLine('Production Credits', 'getProductionCredits')
            ->setLine('Bibliography', 'getBibliographyNotes')
            ->setLine(
                'ISBN',
                'getISBNs',
                null,
                ['itemPrefix' => '<span property="isbn">', 'itemSuffix' => '</span>']
            )->setLine(
                'ISSN',
                'getISSNs',
                null,
                ['itemPrefix' => '<span property="issn">', 'itemSuffix' => '</span>']
            )->setLine(
                'DOI',
                'getCleanDOI',
                null,
                [
                    'itemPrefix' => '<span property="identifier">',
                    'itemSuffix' => '</span>',
                ]
            )->setLine('Related Items', 'getRelationshipNotes')
            ->setLine('Access', 'getAccessRestrictions')
            ->setLine('Finding Aid', 'getFindingAids')
            ->setLine('Publication_Place', 'getHierarchicalPlaceNames')
            ->setLine('Source', 'getSource')
            ->setTemplateLine('Author Notes', true, 'data-authorNotes.phtml');
        return $spec->getArray();
    }
}
