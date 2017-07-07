<?php
/**
 * Factory for record driver data formatting view helper
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2016.
 * Copyright (C) The National Library of Finland 2017.
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
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture:record_data_formatter
 * Wiki
 */
namespace Finna\View\Helper\Root;

/**
 * Factory for record driver data formatting view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture:record_data_formatter
 * Wiki
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
        $spec = new \VuFind\View\Helper\Root\RecordDataFormatter\SpecBuilder();
        $spec->setTemplateLine(
            'Original Work', 'getOriginalWork', 'data-forwardFields.phtml',
            [
                'context' => ['class' => 'recordOriginalWork']
            ]
        );
        $spec->setTemplateLine(
            'Published in', 'getContainerTitle', 'data-containerTitle.phtml',
            [
                'context' => ['class' => 'record-container-link']
            ]
        );
        $spec->setTemplateLine(
            'New Title', 'getNewerTitles', 'data-titles.phtml',
            [
                'context' => ['class' => 'recordNextTitles']
            ]
        );
        $spec->setTemplateLine(
            'Previous Title', 'getPreviousTitles', 'data-titles.phtml',
            [
                'context' => ['class' => 'recordPrevTitles']
            ]
        );
        $spec->setTemplateLine(
            'Secondary Authors', 'getNonPresenterSecondaryAuthors',
            'data-contributors.phtml',
            [
                'context' => ['class' => 'recordAuthors'],
                'labelFunction' => function () {
                     return 'Contributors';
                }
            ]
        );
        $spec->setTemplateLine(
            'Actors', 'getCreditedPresenters', 'data-actors.phtml',
            [
                'context' => ['class' => 'recordPresenters']
            ]
        );
        $spec->setTemplateLine(
            'Uncredited Actors', 'getUncreditedPresenters', 'data-actors.phtml',
            [
                'context' => ['class' => 'recordPresenters']
            ]
        );
        $spec->setTemplateLine(
            'Assistants', 'getAssistants', 'data-assistants.phtml',
            [
                'context' => ['class' => 'record-assistants']
            ]
        );
        $spec->setTemplateLine(
            'Item Description FWD', 'getGeneralNotes', 'data-forwardFields.phtml',
            [
                'context' => ['class' => 'recordDescription']
            ]
        );
        $spec->setTemplateLine(
            'Description', 'getDescription', 'data-forwardFields.phtml',
            [
                'context' => ['class' => 'recordDescription']
            ]
        );
        $spec->setTemplateLine(
            'Press Reviews', 'getPressReview', 'data-forwardFields.phtml',
            [
                'context' => ['class' => 'record-press-review']
            ]
        );
        $spec->setTemplateLine(
            'Music', 'getMusicInfo', 'data-forwardFields.phtml',
            [
                'context' => ['class' => 'record-music']
            ]
        );
        $spec->setTemplateLine(
            'Projected Publication Date', 'getProjectedPublicationDate',
            'data-transEsc.phtml',
            [
                'context' => ['class' => 'coreProjectedPublicationDate']
            ]
        );
        $spec->setTemplateLine(
            'Dissertation Note', 'getDissertationNote', 'data-escapeHtml.phtml',
            [
                'context' => ['class' => 'coreDissertationNote']
            ]
        );
        $spec->setTemplateLine(
            'Other Links', 'getOtherLinks', 'data-getOtherLinks.phtml',
            [
                'labelFunction'  => function ($data) {
                    $label = isset($data[0]) ? $data[0]['heading'] : '';
                    return $label;
                },
                'context' => ['class' => 'recordOtherLink']
            ]
        );
        $spec->setTemplateLine(
            'Presenters', 'getPresenters', 'data-presenters.phtml',
            [
                'context' => ['class' => 'recordPresenters']
            ]
        );
        $spec->setTemplateLine(
            'Other Titles', 'getAlternativeTitles', 'data-escapeHtml.phtml',
            [
                'context' => ['class' => 'recordAltTitles']
            ]
        );
        $spec->setLine(
            'Format', 'getFormats', 'RecordHelper',
            [
                'helperMethod' => 'getFormatList',
                'context' => ['class' => 'recordFormat']
            ]
        );
        $spec->setTemplateLine(
            'Physical Description', 'getPhysicalDescriptions',
            'data-escapeHtml.phtml',
            [
                'context' => ['class' => 'physicalDescriptions']
            ]
        );
        $spec->setTemplateLine(
            'Extent', 'getPhysicalDescriptions',
            'data-escapeHtml.phtml',
            [
                'context' => ['class' => 'record-extent']
            ]
        );
        $spec->setTemplateLine(
            'Age Limit', 'getAgeLimit', 'data-escapeHtml.phtml',
            [
                'context' => ['class' => 'recordAgeLimit']
            ]
        );
        $spec->setTemplateLine(
            'Language', 'getLanguages', 'data-transEsc.phtml',
            [
                'context' => ['class' => 'recordLanguage']
            ]
        );
        $spec->setTemplateLine(
            'original_work_language', 'getOriginalLanguages', 'data-transEsc.phtml',
            [
                'context' => ['class' => 'originalLanguage']
            ]
        );
        $spec->setTemplateLine(
            'Item Description', 'getGeneralNotes', 'data-escapeHtml.phtml',
            [
                'context' => ['class' => 'recordDescription']
            ]
        );
        $spec->setTemplateLine(
            'Subject Detail', 'getSubjectDetails', 'data-escapeHtml.phtml',
            [
                'context' => ['class' => 'recordSubjects']
            ]
        );
        $spec->setTemplateLine(
            'Subject Place', 'getSubjectPlaces', 'data-escapeHtml.phtml',
            [
                'context' => ['class' => 'recordSubjects']
            ]
        );
        $spec->setTemplateLine(
            'Subject Date', 'getSubjectDates', 'data-escapeHtml.phtml',
            [
                'context' => ['class' => 'recordSubjects']
            ]
        );
        $spec->setTemplateLine(
            'Subject Actor', 'getSubjectActors', 'data-escapeHtml.phtml',
            [
                'context' => ['class' => 'recordSubjects']
            ]
        );
        $spec->setTemplateLine(
            'Organisation', 'getInstitutions', 'data-organisation.phtml',
            [
                'context' => ['class' => 'recordInstitution']
            ]
        );
        $spec->setTemplateLine(
            'Collection', 'getCollections', 'data-escapeHtml.phtml',
            [
                'context' => ['class' => 'recordCollection']
            ]
        );
        $spec->setTemplateLine(
            'Inventory ID', 'getIdentifier', 'data-escapeHtml.phtml',
            [
                'context' => ['class' => 'recordIdentifier']
            ]
        );
        $spec->setTemplateLine(
            'Measurements', 'getMeasurements', 'data-escapeHtml.phtml',
            [
                'context' => ['class' => 'recordMeasurements']
            ]
        );
        $spec->setTemplateLine(
            'Inscriptions', 'getInscriptions', 'data-escapeHtml.phtml',
            [
                'context' => ['class' => 'recordInscriptions']
            ]
        );
        $spec->setTemplateLine(
            'Other Classification', 'getFormatClassifications',
            'data-escapeHtml.phtml',
            [
                'context' => ['class' => 'recordClassifications']
            ]
        );
        $spec->setTemplateLine(
            'Other ID', 'getLocalIdentifiers', 'data-escapeHtml.phtml',
            [
                'context' => ['class' => 'recordIdentifiers']
            ]
        );
        $spec->setTemplateLine(
            'mainFormat', 'getEvents', 'data-mainFormat.phtml',
            [
                'context' => ['class' => 'hide']
            ]
        );
        $spec->setTemplateLine(
            'Archive Origination', 'getOrigination', 'data-origination.phtml',
            [
                'context' => ['class' => 'record-origination']
            ]
        );
        $spec->setTemplateLine(
            'Archive', true, 'data-archive.phtml',
            [
                'context' => ['class' => 'recordHierarchyLinks']
            ]
        );
        $spec->setTemplateLine(
            'Archive Series', 'isPartOfArchiveSeries', 'data-archiveSeries.phtml',
            [
                'context' => ['class' => 'recordSeries']
            ]
        );
        $spec->setTemplateLine(
            'Unit ID', 'getUnitID', 'data-escapeHtml.phtml',
            [
                'context' => ['class' => 'recordReferenceCode']
            ]
        );
        $spec->setTemplateLine(
            'Authors', 'getNonPresenterAuthors', 'data-authors.phtml',
            [
                'context' => ['class' => 'recordAuthors']
            ]
        );
        $spec->setTemplateLine(
            'Publisher', 'getPublicationDetails', 'data-publicationDetails.phtml',
            [
                'context' => ['class' => 'recordPublications']
            ]
        );
        $spec->setTemplateLine(
            'Published', 'getPublicationDetails', 'data-publicationDetails.phtml',
            [
                'context' => ['class' => 'recordPublications']
            ]
        );
        $spec->setTemplateLine(
            'Projected Publication Date', 'getProjectedPublicationDate',
            'data-transEsc.phtml',
            [
                'context' => ['class' => 'coreProjectedPublicationDate']
            ]
        );
        $spec->setTemplateLine(
            'Dissertation Note', 'getDissertationNote', 'data-escapeHtml.phtml',
            [
                'context' => ['class' => 'coreDissertationNote']
            ]
        );
        $spec->setTemplateLine(
            'Edition', 'getEdition', 'data-edition.phtml',
            [
                'context' => ['class' => 'recordEdition']
            ]
        );
        $spec->setTemplateLine(
            'Series', 'getSeries', 'data-series.phtml',
            [
                'context' => ['class' => 'recordSeries']
            ]
        );
        $spec->setTemplateLine(
            'Classification', 'getClassifications', 'data-classification.phtml',
            [
                'context' => ['class' => 'recordClassifications']
            ]
        );
        $spec->setTemplateLine(
            'Subjects', 'getAllSubjectHeadings', 'data-allSubjectHeadings.phtml',
            [
                'context' => ['class' => 'recordSubjects']
            ]
        );
        $spec->setTemplateLine(
            'Manufacturer', 'getManufacturer', 'data-transEsc.phtml',
            [
                'context' => ['class' => 'recordManufacturer']
            ]
        );
        $spec->setTemplateLine(
            'Production', 'getProducers', 'data-producers.phtml',
            [
                'context' => ['class' => 'recordManufacturer']
            ]
        );
        $spec->setTemplateLine(
            'Funding', 'getFunders', 'data-funding.phtml',
            [
                'context' => ['class' => 'record-funders']
            ]
        );
        $spec->setTemplateLine(
            'Distribution', 'getDistributors', 'data-distribution.phtml',
            [
                'context' => ['class' => 'record-distributors']
            ]
        );
        $spec->setTemplateLine(
            'Additional Information', 'getTitleStatement', 'data-addInfo.phtml',
            [
                'context' => ['class' => 'recordTitleStatement']
            ]
        );
        $spec->setTemplateLine(
            'Genre', 'getGenres', 'data-genres.phtml',
            [
                'context' => ['class' => 'recordGenres']
            ]
        );
        $spec->setTemplateLine(
            'child_records', 'getChildRecordCount', 'data-childRecords.phtml',
            [
                'allowZero' => false,
                'context' => ['class' => 'recordComponentParts']
            ]
        );
        $spec->setTemplateLine(
            'recordLinks', 'getAllRecordLinks', 'data-allRecordLinks.phtml',
            [
                'context' => ['class' => 'hide']
            ]
        );
        $spec->setTemplateLine(
            'Online Access', true, 'data-onlineAccess.phtml',
            [
                'context' => ['class' => 'webResource']
            ]
        );
        $spec->setTemplateLine(
            'Source Collection', 'getSource', 'data-escapeHtml.phtml',
            [
                'context' => ['class' => 'recordSource']
            ]
        );
        $spec->setTemplateLine(
            'Publish date', 'getDateSpan', 'data-escapeHtml.phtml',
            [
                'context' => ['class' => 'extendedDateSpan']
            ]
        );
        $spec->setTemplateLine(
            'Keywords', 'getKeywords', 'data-keywords.phtml',
            [
                'context' => ['class' => 'record-keywords']
            ]
        );
        $spec->setTemplateLine(
            'Education Programs', 'getEducationPrograms', 'data-education.phtml',
            [
                'context' => ['class' => 'record-education-programs']
            ]
        );
        $spec->setTemplateLine(
            'Publication Frequency', 'getPublicationFrequency',
            'data-escapeHtml.phtml',
            [
                'context' => ['class' => 'extendedFrequency']
            ]
        );
        $spec->setTemplateLine(
            'Playing Time', 'getPlayingTimes', 'data-escapeHtml.phtml',
            [
                'context' => ['class' => 'extendedPlayTime']
            ]
        );
        $spec->setTemplateLine(
            'Color', 'getColor', 'data-color.phtml',
            [
                'context' => ['class' => 'record-color']
            ]
        );
        $spec->setTemplateLine(
            'Sound', 'getSound', 'data-sound.phtml',
            [
                'context' => ['class' => 'record-sound']
            ]
        );
        $spec->setTemplateLine(
            'Aspect Ratio', 'getAspectRatio', 'data-escapeHtml',
            [
                'context' => ['class' => 'record-aspect-ratio']
            ]
        );
        $spec->setTemplateLine(
            'System Format', 'getSystemDetails', 'data-escapeHtml',
            [
                'context' => ['class' => 'extendedSystem']
            ]
        );
        $spec->setTemplateLine(
            'Audience', 'getTargetAudienceNotes', 'data-escapeHtml',
            [
                'context' => ['class' => 'extendedAudience']
            ]
        );
        $spec->setTemplateLine(
            'Awards', 'getAwards', 'data-forwardFields.phtml',
            [
                'context' => ['class' => 'extendedAwards']
            ]
        );
        $spec->setTemplateLine(
            'Production Credits', 'getProductionCredits', 'data-escapeHtml',
            [
                'context' => ['class' => 'extendedCredits']
            ]
        );
        $spec->setTemplateLine(
            'Bibliography', 'getBibliographyNotes', 'data-transEsc.phtml',
            [
                'context' => ['class' => 'extendedBibliography']
            ]
        );
        $spec->setTemplateLine(
            'ISBN', 'getISBNs', 'data-escapeHtml.phtml',
            [
                'context' => ['class' => 'extendedISBNs']
            ]
        );
        $spec->setTemplateLine(
            'ISSN', 'getISSNs', 'data-escapeHtml.phtml',
            [
                'context' => ['class' => 'extendedISSNs']
            ]
        );
        $spec->setTemplateLine(
            'DOI', 'getCleanDOI', 'data-escapeHtml.phtml',
            [
                'context' => ['class' => 'extended-doi']
            ]
        );
        $spec->setTemplateLine(
            'Related Items', 'getRelationshipNotes', 'data-escapeHtml.phtml',
            [
                'context' => ['class' => 'extendedRelatedItems']
            ]
        );
        $spec->setTemplateLine(
            'Access Restrictions', 'getAccessRestrictions', 'data-accrest.phtml',
            [
                'context' => ['class' => 'extendedAccess']
            ]
        );
        $spec->setTemplateLine(
            'Access', 'getAccessRestrictions', 'data-accrest.phtml',
            [
                'context' => ['class' => 'extendedAccess']
            ]
        );
        $spec->setTemplateLine(
            'Terms of Use', 'getTermsOfUse', 'data-termsOfUse.phtml',
            [
                'context' => ['class' => 'extendedTermsOfUse']
            ]
        );
        $spec->setTemplateLine(
            'Finding Aid', 'getFindingAids', 'data-escapeHtml.phtml',
            [
                'context' => ['class' => 'extendedFindingAids']
            ]
        );
        $spec->setTemplateLine(
            'Publication_Place', 'getHierarchicalPlaceNames',
            'data-escapeHtml.phtml',
            [
                'context' => ['class' => 'publicationPlace']
            ]
        );
        $spec->setTemplateLine(
            'Author Notes', true, 'data-authorNotes.phtml',
            [
                'context' => ['class' => 'extendedAuthorNotes']
            ]
        );
        $spec->setTemplateLine(
            'Location', 'getPhysicalLocations', 'data-escapeHtml.phtml',
            [
                'context' => ['class' => 'recordPhysicalLocation']
            ]
        );
        $spec->setTemplateLine(
            'Date', 'getUnitDate', 'data-escapeHtml.phtml',
            [
                'context' => ['class' => 'recordDaterange']
            ]
        );
        $spec->setTemplateLine(
            'Photo Info', 'getPhotoInfo', 'data-escapeHtml.phtml',
            [
                'context' => ['class' => 'recordPhotographer']
            ]
        );
        $spec->setTemplateLine(
            'Source of Acquisition', 'getAcquisitionSource', 'data-escapeHtml.phtml',
            [
                'context' => ['class' => 'recordAcquisition']
            ]
        );
        $spec->setTemplateLine(
            'Medium of Performance', 'getMusicComposition', 'data-escapeHtml.phtml',
            [
                'context' => ['class' => 'recordComposition']
            ]
        );
        $spec->setTemplateLine(
            'Notated Music Format', 'getNotatedMusicFormat', 'data-escapeHtml.phtml',
            [
                'context' => ['class' => 'recordNoteFormat']
            ]
        );
        $spec->setTemplateLine(
            'Event Notice', 'getEventNotice', 'data-escapeHtml.phtml',
            [
                'context' => ['class' => 'recordEventNotice']
            ]
        );
        $spec->setTemplateLine(
            'First Lyrics', 'getFirstLyrics', 'data-escapeHtml.phtml',
            [
                'context' => ['class' => 'recordFirstLyrics']
            ]
        );
        $spec->setTemplateLine(
            'Trade Availability Note',
            'getTradeAvailabilityNote',
            'data-escapeHtml.phtml',
            [
                'context' => ['class' => 'recordTradeNote']
            ]
        );
        $spec->setTemplateLine(
            'Methodology', 'getMethodology', 'data-escapeHtml.phtml',
            [
                'context' => ['class' => 'recordMethodology']
            ]
        );
        $spec->setTemplateLine(
            'Inspection Details', 'getInspectionDetails', 'data-inspection.phtml',
            [
                'context' => ['class' => 'recordInspection']
            ]
        );
        return $spec->getArray();
    }

    /**
     * Get default specifications for displaying data in the description tab.
     *
     * @return array
     */
    public function getDefaultDescriptionSpecs()
    {
        $spec = new \VuFind\View\Helper\Root\RecordDataFormatter\SpecBuilder();
        $spec->setLine('Summary', 'getSummary');
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
        $spec->setLine('ISBN', 'getISBNs');
        $spec->setLine('ISSN', 'getISSNs');
        $spec->setLine('DOI', 'getCleanDOI');
        $spec->setLine('Related Items', 'getRelationshipNotes');
        $spec->setLine('Access', 'getAccessRestrictions');
        $spec->setLine('Finding Aid', 'getFindingAids');
        $spec->setLine('Publication_Place', 'getHierarchicalPlaceNames');
        $spec->setTemplateLine('Author Notes', true, 'data-authorNotes.phtml');
        return $spec->getArray();
    }
}
