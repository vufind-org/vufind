<?php
/**
 * Factory for record driver data formatting view helper
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
 * Factory for record driver data formatting view helper
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
     * Construct a generic spec line.
     *
     * @param string $dataMethod Method of data retrieval for rendering element
     * @param string $renderType Type of rendering to use to generate output
     * @param array  $options    Additional options
     *
     * @return array
     */
    protected function getSpecLine($dataMethod, $renderType = null, $options = [])
    {
        $options['dataMethod'] = $dataMethod;
        $options['renderType'] = $renderType;
        return $options;
    }

    /**
     * Construct a record driver template spec line.
     *
     * @param string $dataMethod Method of data retrieval for rendering element
     * @param string $template   Record driver template to render with data
     * @param array  $options    Additional options
     *
     * @return array
     */
    protected function getTemplateSpecLine($dataMethod, $template, $options = [])
    {
        $options['template'] = $template;
        return $this->getSpecLine($dataMethod, 'RecordDriverTemplate', $options);
    }

    /**
     * Get default specifications for displaying data in core metadata.
     *
     * @return array
     */
    public function getDefaultCoreSpecs()
    {
        return [
            'Published in' => $this->getTemplateSpecLine(
                'getContainerTitle', 'data-containerTitle.phtml'
            ),
            'New Title' => $this->getSpecLine(
                'getNewerTitles', null, ['recordLink' => 'title']
            ),
            'Previous Title' => $this->getSpecLine(
                'getPreviousTitles', null, ['recordLink' => 'title']
            ),
            'Main Authors' => $this->getTemplateSpecLine(
                'getDeduplicatedAuthors', 'data-authors.phtml',
                [
                    'useCache' => true,
                    'labelFunction' => function ($data) {
                        return count($data['main']) > 1
                            ? 'Main Authors' : 'Main Author';
                    },
                    'context' => ['type' => 'main', 'schemaLabel' => 'author'],
                ]
            ),
            'Corporate Authors' => $this->getTemplateSpecLine(
                'getDeduplicatedAuthors', 'data-authors.phtml',
                [
                    'useCache' => true,
                    'labelFunction' => function ($data) {
                        return count($data['corporate']) > 1
                            ? 'Corporate Authors' : 'Corporate Author';
                    },
                    'context' => ['type' => 'corporate', 'schemaLabel' => 'creator'],
                ]
            ),
            'Other Authors' => $this->getTemplateSpecLine(
                'getDeduplicatedAuthors', 'data-authors.phtml',
                [
                    'useCache' => true,
                    'context' => [
                        'type' => 'secondary', 'schemaLabel' => 'contributor'
                    ],
                ]
            ),
            'Format' => $this->getSpecLine(
                'getFormats', 'RecordHelper', ['method' => 'getFormatList']
            ),
            'Language' => $this->getSpecLine('getLanguages'),
            'Published' => $this->getTemplateSpecLine(
                'getPublicationDetails', 'data-publicationDetails.phtml'
            ),
            'Edition' => $this->getSpecLine(
                'getEdition', null,
                ['prefix' => '<span property="bookEdition">', 'suffix' => '</span>']
            ),
            'Series' => $this->getTemplateSpecLine(
                'getSeries', 'data-series.phtml'
            ),
            'Subjects' => $this->getTemplateSpecLine(
                'getAllSubjectHeadings', 'data-allSubjectHeadings.phtml'
            ),
            'child_records' => $this->getTemplateSpecLine(
                'getChildRecordCount', 'data-childRecords.phtml'
            ),
            'Online Access' => $this->getTemplateSpecLine(
                true, 'data-onlineAccess.phtml'
            ),
            'Related Items' => $this->getTemplateSpecLine(
                'getAllRecordLinks', 'data-allRecordLinks.phtml'
            ),
            'Tags' => $this->getTemplateSpecLine(true, 'data-tags.phtml'),
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
            'Summary' => $this->getSpecLine('getSummary'),
            'Published' => $this->getSpecLine('getDateSpan'),
            'Item Description' => $this->getSpecLine('getGeneralNotes'),
            'Physical Description' => $this->getSpecLine('getPhysicalDescriptions'),
            'Publication Frequency' => $this->getSpecLine('getPublicationFrequency'),
            'Playing Time' => $this->getSpecLine('getPlayingTimes'),
            'Format' => $this->getSpecLine('getSystemDetails'),
            'Audience' => $this->getSpecLine('getTargetAudienceNotes'),
            'Awards' => $this->getSpecLine('getAwards'),
            'Production Credits' => $this->getSpecLine('getProductionCredits'),
            'Bibliography' => $this->getSpecLine('getBibliographyNotes'),
            'ISBN' => $this->getSpecLine('getISBNs'),
            'ISSN' => $this->getSpecLine('getISSNs'),
            'DOI' => $this->getSpecLine('getCleanDOI'),
            'Related Items' => $this->getSpecLine('getRelationshipNotes'),
            'Access' => $this->getSpecLine('getAccessRestrictions'),
            'Finding Aid' => $this->getSpecLine('getFindingAids'),
            'Publication_Place' => $this->getSpecLine('getHierarchicalPlaceNames'),
            'Author Notes' => $this->getTemplateSpecLine(
                true, 'data-authorNotes.phtml'
            ),
        ];
    }
}
