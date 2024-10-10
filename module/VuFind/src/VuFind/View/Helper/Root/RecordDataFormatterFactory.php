<?php

/**
 * Factory for record driver data formatting view helper
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2016.
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture:record_data_formatter
 * Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;

use function count;

/**
 * Factory for record driver data formatting view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture:record_data_formatter
 * Wiki
 */
class RecordDataFormatterFactory implements FactoryInterface
{
    /**
     * Schema.org view helper
     *
     * @var SchemaOrg
     */
    protected $schemaOrgHelper = null;

    /**
     * The order in which groups of authors are displayed.
     *
     * The dictionary keys here correspond to the dictionary keys in the $labels
     * array in getAuthorFunction()
     *
     * @var array<string, int>
     */
    protected $authorOrder = ['primary' => 1, 'corporate' => 2, 'secondary' => 3];

    /**
     * Create an object
     *
     * @param ContainerInterface $container     Service manager
     * @param string             $requestedName Service being created
     * @param null|array         $options       Extra options (optional)
     *
     * @return object
     *
     * @throws ServiceNotFoundException if unable to resolve the service.
     * @throws ServiceNotCreatedException if an exception is raised when
     * creating a service.
     * @throws ContainerException&\Throwable if any other error occurs
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options sent to factory.');
        }
        $this->schemaOrgHelper = $container->get('ViewHelperManager')->get('schemaOrg');
        $config = $container
            ->get(\VuFind\Config\PluginManager::class)
            ->get('RecordDataFormatter');
        $helper = new $requestedName($config);
        $helper->setDefaults(
            'collection-info',
            [$this, 'getDefaultCollectionInfoSpecs']
        );
        $helper->setDefaults(
            'collection-record',
            [$this, 'getDefaultCollectionRecordSpecs']
        );
        $helper->setDefaults('core', [$this, 'getDefaultCoreSpecs']);
        $helper->setDefaults('description', [$this, 'getDefaultDescriptionSpecs']);
        return $helper;
    }

    /**
     * Get the callback function for processing authors.
     *
     * @return callable
     */
    protected function getAuthorFunction()
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
    protected function getLanguageLineSettings(): array
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
    public function getDefaultCollectionInfoSpecs()
    {
        $spec = new RecordDataFormatter\SpecBuilder();
        $spec->setMultiLine(
            'Authors',
            'getDeduplicatedAuthors',
            $this->getAuthorFunction()
        );
        $spec->setLine('Summary', 'getSummary');
        $spec->setLine('Abstract', 'getAbstractNotes');
        $spec->setLine(
            'Format',
            'getFormats',
            'RecordHelper',
            ['helperMethod' => 'getFormatList']
        );
        $spec->setLine(
            'Language',
            'getLanguages',
            null,
            $this->getLanguageLineSettings()
        );
        $spec->setTemplateLine(
            'Published',
            'getPublicationDetails',
            'data-publicationDetails.phtml'
        );
        $spec->setLine(
            'Edition',
            'getEdition',
            null,
            [
                'itemPrefix' => '<span property="bookEdition">',
                'itemSuffix' => '</span>',
            ]
        );
        $spec->setTemplateLine('Series', 'getSeries', 'data-series.phtml');
        $spec->setTemplateLine(
            'Subjects',
            'getAllSubjectHeadings',
            'data-allSubjectHeadings.phtml'
        );
        $spec->setTemplateLine('Online Access', true, 'data-onlineAccess.phtml');
        $spec->setTemplateLine(
            'Related Items',
            'getAllRecordLinks',
            'data-allRecordLinks.phtml'
        );
        $spec->setLine('Notes', 'getGeneralNotes');
        $spec->setLine('Production Credits', 'getProductionCredits');
        $spec->setLine(
            'ISBN',
            'getISBNs',
            null,
            ['itemPrefix' => '<span property="isbn">', 'itemSuffix' => '</span>']
        );
        $spec->setLine(
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
    public function getDefaultCollectionRecordSpecs()
    {
        $spec = new RecordDataFormatter\SpecBuilder();
        $spec->setLine('Summary', 'getSummary');
        $spec->setLine('Abstract', 'getAbstractNotes');
        $spec->setMultiLine(
            'Authors',
            'getDeduplicatedAuthors',
            $this->getAuthorFunction()
        );
        $spec->setLine(
            'Language',
            'getLanguages',
            null,
            $this->getLanguageLineSettings()
        );
        $spec->setLine(
            'Format',
            'getFormats',
            'RecordHelper',
            ['helperMethod' => 'getFormatList']
        );
        $spec->setLine('Access', 'getAccessRestrictions');
        $spec->setLine('Related Items', 'getRelationshipNotes');
        return $spec->getArray();
    }

    /**
     * Get default specifications for displaying data in core metadata.
     *
     * @return array
     */
    public function getDefaultCoreSpecs()
    {
        $spec = new RecordDataFormatter\SpecBuilder();
        $spec->setTemplateLine(
            'Published in',
            'getContainerTitle',
            'data-containerTitle.phtml'
        );
        $spec->setLine(
            'New Title',
            'getNewerTitles',
            null,
            ['recordLink' => 'title']
        );
        $spec->setLine(
            'Previous Title',
            'getPreviousTitles',
            null,
            ['recordLink' => 'title']
        );
        $spec->setMultiLine(
            'Authors',
            'getDeduplicatedAuthors',
            $this->getAuthorFunction()
        );
        $spec->setLine(
            'Format',
            'getFormats',
            'RecordHelper',
            ['helperMethod' => 'getFormatList']
        );
        $spec->setLine(
            'Language',
            'getLanguages',
            null,
            $this->getLanguageLineSettings()
        );
        $spec->setTemplateLine(
            'Published',
            'getPublicationDetails',
            'data-publicationDetails.phtml'
        );
        $spec->setLine(
            'Edition',
            'getEdition',
            null,
            [
                'itemPrefix' => '<span property="bookEdition">',
                'itemSuffix' => '</span>',
            ]
        );
        $spec->setTemplateLine('Series', 'getSeries', 'data-series.phtml');
        $spec->setTemplateLine(
            'Subjects',
            'getAllSubjectHeadings',
            'data-allSubjectHeadings.phtml'
        );
        $spec->setTemplateLine(
            'Citations',
            'getCitations',
            'data-citations.phtml',
        );
        $spec->setTemplateLine(
            'child_records',
            'getChildRecordCount',
            'data-childRecords.phtml',
            ['allowZero' => false]
        );
        $spec->setTemplateLine('Online Access', true, 'data-onlineAccess.phtml');
        $spec->setTemplateLine(
            'Related Items',
            'getAllRecordLinks',
            'data-allRecordLinks.phtml'
        );
        $spec->setTemplateLine('Tags', true, 'data-tags.phtml');
        return $spec->getArray();
    }

    /**
     * Get default specifications for displaying data in the description tab.
     *
     * @return array
     */
    public function getDefaultDescriptionSpecs()
    {
        $spec = new RecordDataFormatter\SpecBuilder();
        $spec->setTemplateLine('Summary', true, 'data-summary.phtml');
        $spec->setLine('Abstract', 'getAbstractNotes');
        $spec->setLine('Published', 'getDateSpan');
        $spec->setLine('Item Description', 'getGeneralNotes');
        $spec->setLine('Physical Description', 'getPhysicalDescriptions');
        $spec->setLine('Publication Frequency', 'getPublicationFrequency');
        $spec->setLine('Playing Time', 'getPlayingTimes');
        $spec->setLine('Format', 'getSystemDetails');
        $spec->setLine('Audience', 'getTargetAudienceNotes');
        $spec->setLine('Awards', 'getAwards');
        $spec->setLine('Production Credits', 'getProductionCredits');
        $spec->setLine('Bibliography', 'getBibliographyNotes');
        $spec->setLine(
            'ISBN',
            'getISBNs',
            null,
            ['itemPrefix' => '<span property="isbn">', 'itemSuffix' => '</span>']
        );
        $spec->setLine(
            'ISSN',
            'getISSNs',
            null,
            ['itemPrefix' => '<span property="issn">', 'itemSuffix' => '</span>']
        );
        $spec->setLine(
            'DOI',
            'getCleanDOI',
            null,
            [
                'itemPrefix' => '<span property="identifier">',
                'itemSuffix' => '</span>',
            ]
        );
        $spec->setLine('Related Items', 'getRelationshipNotes');
        $spec->setLine('Access', 'getAccessRestrictions');
        $spec->setLine('Finding Aid', 'getFindingAids');
        $spec->setLine('Publication_Place', 'getHierarchicalPlaceNames');
        $spec->setTemplateLine('Author Notes', true, 'data-authorNotes.phtml');
        return $spec->getArray();
    }
}
