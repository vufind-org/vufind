<?php

/**
 * Model for AIPA LRMI records.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022-2025.
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
 * @package  RecordDrivers
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace Finna\RecordDriver;

use Finna\RecordDriver\Feature\ContainerFormatInterface;
use Finna\RecordDriver\Feature\ContainerFormatTrait;
use Finna\RecordDriver\Feature\EncapsulatedRecordInterface;
use Finna\RecordDriver\Feature\EncapsulatedRecordTrait;
use NatLibFi\FinnaCodeSets\FinnaCodeSets;

use function is_callable;

/**
 * Model for AIPA LRMI records.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class AipaLrmi extends SolrLrmi implements
    ContainerFormatInterface,
    EncapsulatedRecordInterface
{
    use ContainerFormatTrait;
    use EncapsulatedRecordTrait;

    /**
     * Finna Code Sets library instance.
     *
     * @var FinnaCodeSets
     */
    protected FinnaCodeSets $codeSets;

    /**
     * Attach Finna Code Sets library instance.
     *
     * @param FinnaCodeSets $codeSets Finna Code Sets library instance
     *
     * @return void
     */
    public function attachCodeSetsLibrary(FinnaCodeSets $codeSets): void
    {
        $this->codeSets = $codeSets;
    }

    /**
     * Get an array of formats/extents for the record
     *
     * @return array
     */
    public function getPhysicalDescriptions(): array
    {
        return [];
    }

    /**
     * Return educational levels
     *
     * @return array
     */
    public function getEducationalLevels()
    {
        $xml = $this->getXmlRecord();
        $levels = [];
        foreach ($xml->learningResource->educationalLevel ?? [] as $level) {
            $levels[] = (string)$level->name;
        }
        return $levels;
    }

    /**
     * Get educational subjects
     *
     * @return array
     */
    public function getEducationalSubjects()
    {
        $xml = $this->getXmlRecord();
        $subjects = [];
        foreach ($xml->learningResource->educationalAlignment ?? [] as $alignment) {
            foreach ($alignment->educationalSubject ?? [] as $subject) {
                $subjects[] = (string)$subject->targetName;
            }
        }
        return $subjects;
    }

    /**
     * Return an array of image URLs associated with this record with keys:
     * - url         Image URL
     * - description Description text
     * - rights      Rights
     *   - copyright   Copyright (e.g. 'CC BY 4.0') (optional)
     *   - description Human readable description (array)
     *   - link        Link to copyright info
     *
     * @param string $language   Language for copyright information
     * @param bool   $includePdf Whether to include first PDF file when no image
     * links are found
     *
     * @return mixed
     */
    public function getAllImages($language = 'fi', $includePdf = false)
    {
        // AIPA LRMI records do not directly contain PDF files.
        return parent::getAllImages($language, false);
    }

    /**
     * Get educational aim
     *
     * @return array
     */
    public function getEducationalAim()
    {
        $xml = $this->getXmlRecord();
        $contentsAndObjectives = [];
        foreach ($xml->learningResource->teaches ?? [] as $teaches) {
            $contentsAndObjectives[] = (string)$teaches->name;
        }
        return $contentsAndObjectives;
    }

    /**
     * Get all authors apart from presenters
     *
     * Only returns non-presenter authors if they differ from the container record.
     * This is a strict comparison: even the same authors in a different order is
     * considered a difference.
     *
     * @return array
     */
    public function getNonPresenterAuthors()
    {
        $nonPresenterAuthors = parent::getNonPresenterAuthors();
        if (!is_callable([$this->getContainerRecord(), 'getNonPresenterAuthors'])) {
            return $nonPresenterAuthors;
        }
        $containerNonPresenterAuthors
            = $this->getContainerRecord()->getNonPresenterAuthors();
        foreach ($nonPresenterAuthors as $i => $author) {
            if (
                !empty(array_diff_assoc(
                    $nonPresenterAuthors[$i],
                    $containerNonPresenterAuthors[$i] ?? []
                ))
            ) {
                return $nonPresenterAuthors;
            }
        }
        return [];
    }

    /**
     * Return study objectives, or null if not found in record.
     *
     * @return ?string
     */
    public function getStudyObjectives(): ?string
    {
        $studyObjectives = null;
        $xml = $this->getXmlRecord();
        foreach ($xml->learningResource as $learningResource) {
            if ($learningResource->studyObjectives) {
                if (null === $studyObjectives) {
                    $studyObjectives = '';
                }
                $studyObjectives .= (string)$learningResource->studyObjectives;
            }
        }
        return $studyObjectives;
    }

    /**
     * Return assignment ideas, or null if not found in record.
     *
     * @return ?string
     */
    public function getAssignmentIdeas(): ?string
    {
        $xml = $this->getXmlRecord();
        if ($xml->assignmentIdeas) {
            return (string)$xml->assignmentIdeas;
        }
        return null;
    }

    /**
     * Get rich educational data, or false if not possible.
     *
     * @return array|false
     */
    public function getEducationalData(): array|false
    {
        $xml = $this->getXmlRecord();
        try {
            $educationalLevels = [];
            $educationalSubjects = [];
            $teaches = [];
            foreach ($xml->learningResource->educationalLevel ?? [] as $level) {
                $educationalLevels[(string)$level->termCode]
                    = (string)$level->inDefinedTermSet->url;
            }
            foreach ($xml->learningResource->educationalAlignment ?? [] as $alignment) {
                foreach ($alignment->educationalSubject ?? [] as $subject) {
                    $educationalSubjects[(string)$subject->identifier]
                        = (string)$subject->targetUrl;
                }
            }
            foreach ($xml->learningResource->teaches ?? [] as $xmlTeaches) {
                $teaches[(string)$xmlTeaches->identifier]
                    = (string)$xmlTeaches->inDefinedTermSet->url;
            }
            return $this->codeSets->getEducationalData()->getLrmiEducationalData(
                $educationalLevels,
                $educationalSubjects,
                $teaches
            );
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Return record type.
     *
     * @return string
     */
    public function getType(): string
    {
        return (string)($this->getXmlRecord()->type ?? 'content');
    }

    /**
     * Returns the tag name of XML elements containing an encapsulated record.
     *
     * @return string
     */
    protected function getEncapsulatedRecordElementTagName(): string
    {
        return 'material';
    }

    /**
     * Return format for an encapsulated record.
     *
     * @param mixed $item Encapsulated record item
     *
     * @return string
     */
    protected function getEncapsulatedRecordFormat($item): string
    {
        return 'CuratedRecord';
    }

    /**
     * Return full record as a filtered SimpleXMLElement for public APIs.
     *
     * @return \SimpleXMLElement
     */
    public function getFilteredXMLElement(): \SimpleXMLElement
    {
        $record = parent::getFilteredXMLElement();
        $this->doFilterFields($record, ['abstract', 'description', 'assignmentIdeas']);
        foreach ($record->learningResource as $learningResource) {
            $this->doFilterFields($learningResource, ['studyObjectives']);
            foreach ($learningResource->educationalLevel as $educationalLevel) {
                $this->doFilterFields($educationalLevel, ['name']);
                foreach ($educationalLevel->inDefinedTermSet as $inDefinedTermSet) {
                    $this->doFilterFields($inDefinedTermSet, ['name']);
                }
            }
            foreach ($learningResource->educationalAlignment as $educationalAlignment) {
                foreach ($educationalAlignment->educationalSubject as $educationalSubject) {
                    $this->doFilterFields(
                        $educationalSubject,
                        ['educationalFramework', 'targetName']
                    );
                }
            }
            foreach ($learningResource->teaches as $teaches) {
                $this->doFilterFields($teaches, ['name']);
            }
        }
        return $this->filterEncapsulatedRecords($record);
    }

    /**
     * Helper method for filtering fields.
     *
     * @param \SimpleXMLElement $baseElement  Base element
     * @param array             $filterFields Fields to filter
     *
     * @return void
     */
    protected function doFilterFields(
        \SimpleXMLElement $baseElement,
        array $filterFields
    ): void {
        foreach ($filterFields as $filterField) {
            while ($baseElement->{$filterField}) {
                unset($baseElement->{$filterField}[0]);
            }
        }
    }
}
