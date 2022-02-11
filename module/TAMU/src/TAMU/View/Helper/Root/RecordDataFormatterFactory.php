<?php
/**
 * Factory for record driver data formatting view helper
 *
 * PHP version 7
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
namespace TAMU\View\Helper\Root;

use VuFind\View\Helper\Root\RecordDataFormatter\SpecBuilder;

/**
 * TAMU Customized Factory for record driver data formatting view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture:record_data_formatter
 * Wiki
 */
class RecordDataFormatterFactory extends
        \VuFind\View\Helper\Root\RecordDataFormatterFactory
{
    /**
     * Get default specifications for displaying data in core metadata.
     *
     * @return array
     */
    public function getDefaultCoreSpecs()
    {
        $spec = new SpecBuilder();
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
            ['itemPrefix' => '<span property="availableLanguage" typeof="Language">'
                           . '<span property="name">',
             'itemSuffix' => '</span></span>']
        );
        $spec->setLine(
            'Language Notes',
            'getLanguageNote',
            null,
            ['itemPrefix' => '<span property="notesLanguage">',
             'itemSuffix' => '</span>']
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
            ['itemPrefix' => '<span property="bookEdition">',
             'itemSuffix' => '</span>']
        );
        $spec->setTemplateLine('Series', 'getSeries', 'data-series.phtml');
        $spec->setTemplateLine(
            'Subjects',
            'getAllSubjectHeadings',
            'data-allSubjectHeadings.phtml'
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
     * Get default specifications for displaying data in collection-info metadata.
     *
     * @return array
     */
    public function getDefaultCollectionInfoSpecs()
    {
        $spec = new SpecBuilder();
        $spec->setMultiLine(
            'Authors',
            'getDeduplicatedAuthors',
            $this->getAuthorFunction()
        );
        $spec->setLine('Summary', 'getSummary');
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
            ['itemPrefix' => '<span property="availableLanguage" typeof="Language">'
                           . '<span property="name">',
             'itemSuffix' => '</span></span>']
        );
        $spec->setLine(
            'Language Notes',
            'getLanguageNote',
            null,
            ['itemPrefix' => '<span property="notesLanguage">',
             'itemSuffix' => '</span>']
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
            ['itemPrefix' => '<span property="bookEdition">',
             'itemSuffix' => '</span>']
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
}
