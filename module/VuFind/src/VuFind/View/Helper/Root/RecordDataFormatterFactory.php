<?php
/**
 * Record driver data formatting view helper
 *
 * PHP version 5
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
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\View\Helper\Root;

/**
 * Record driver data formatting view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class RecordDataFormatterFactory
{
    /**
     * Create the helper.
     *
     * @return RecordDataFormatter
     */
    public function __invoke()
    {
        $helper = new RecordDataFormatter();
        $helper->setDefaults('core', $this->getDefaultCoreSpecs());
        $helper->setDefaults('description', $this->getDefaultDescriptionSpecs());
        return $helper;
    }

    /**
     * Get default specifications for displaying data in core metadata.
     *
     * @return array
     */
    public function getDefaultCoreSpecs()
    {
        return [
            'Published in' => [
                'getContainerTitle', 'RecordDriverTemplate',
                ['template' => 'data-containerTitle.phtml']
            ],
            'New Title' => ['getNewerTitles', null, ['recordLink' => 'title']],
            'Previous Title' => [
                'getPreviousTitles', null, ['recordLink' => 'title']
            ],
            'Main Authors' => [
                'getDeduplicatedAuthors', 'RecordDriverTemplate',
                [
                    'useCache' => true,
                    'labelFunction' => function ($data) {
                        return count($data['main']) > 1
                            ? 'Main Authors' : 'Main Author';
                    },
                    'template' => 'data-authors.phtml',
                    'context' => ['type' => 'main', 'schemaLabel' => 'author'],
                ]
            ],
            'Corporate Authors' => [
                'getDeduplicatedAuthors', 'RecordDriverTemplate',
                [
                    'useCache' => true,
                    'labelFunction' => function ($data) {
                        return count($data['corporate']) > 1
                            ? 'Corporate Authors' : 'Corporate Author';
                    },
                    'template' => 'data-authors.phtml',
                    'context' => ['type' => 'corporate', 'schemaLabel' => 'creator'],
                ]
            ],
            'Other Authors' => [
                'getDeduplicatedAuthors', 'RecordDriverTemplate',
                [
                    'useCache' => true,
                    'template' => 'data-authors.phtml',
                    'context' => [
                        'type' => 'secondary', 'schemaLabel' => 'contributor'
                    ],
                ]
            ],
            'Format' => [
                'getFormats', 'RecordHelper', ['method' => 'getFormatList']
            ],
            'Language' => ['getLanguages'],
            'Published' => [
                'getPublicationDetails', 'RecordDriverTemplate',
                ['template' => 'data-publicationDetails.phtml']
            ],
            'Edition' => [
                'getEdition', null,
                ['prefix' => '<span property="bookEdition">', 'suffix' => '</span>']
            ],
            'Series' => [
                'getSeries', 'RecordDriverTemplate',
                ['template' => 'data-series.phtml']
            ],
            'Subjects' => [
                'getAllSubjectHeadings', 'RecordDriverTemplate',
                ['template' => 'data-allSubjectHeadings.phtml']
            ],
            'child_records' => [
                'getChildRecordCount', 'RecordDriverTemplate',
                ['template' => 'data-childRecords.phtml']
            ],
            'Online Access' => [
                true, 'RecordDriverTemplate',
                ['template' => 'data-onlineAccess.phtml']
            ],
            'Related Items' => [
                'getAllRecordLinks', 'RecordDriverTemplate',
                ['template' => 'data-allRecordLinks.phtml']
            ],
            'Tags' => [
                true, 'RecordDriverTemplate', ['template' => 'data-tags.phtml']
            ],
        ];
    }

    /**
     * Get default specifications for displaying data in the description tab.
     *
     * @return array
     */
    public function getDefaultDescriptionSpecs()
    {
        return [
            'Summary' => ['getSummary'],
            'Published' => ['getDateSpan'],
            'Item Description' => ['getGeneralNotes'],
            'Physical Description' => ['getPhysicalDescriptions'],
            'Publication Frequency' => ['getPublicationFrequency'],
            'Playing Time' => ['getPlayingTimes'],
            'Format' => ['getSystemDetails'],
            'Audience' => ['getTargetAudienceNotes'],
            'Awards' => ['getAwards'],
            'Production Credits' => ['getProductionCredits'],
            'Bibliography' => ['getBibliographyNotes'],
            'ISBN' => ['getISBNs'],
            'ISSN' => ['getISSNs'],
            'DOI' => ['getCleanDOI'],
            'Related Items' => ['getRelationshipNotes'],
            'Access' => ['getAccessRestrictions'],
            'Finding Aid' => ['getFindingAids'],
            'Publication_Place' => ['getHierarchicalPlaceNames'],
            'Author Notes' => [
                true, 'RecordDriverTemplate',
                ['template' => 'data-authorNotes.phtml']
            ],
        ];
    }
}
