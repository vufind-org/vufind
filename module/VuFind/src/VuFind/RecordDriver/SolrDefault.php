<?php

/**
 * Default model for Solr records -- used when a more specific model based on
 * the record_format field cannot be found.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010, 2022.
 * Copyright (C) The National Library of Finland 2019.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace VuFind\RecordDriver;

use VuFindSearch\Command\SearchCommand;

use function count;
use function in_array;
use function is_array;

/**
 * Default model for Solr records -- used when a more specific model based on
 * the record_format field cannot be found.
 *
 * This should be used as the base class for all Solr-based record models.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 *
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 */
class SolrDefault extends DefaultRecord implements
    Feature\PreviousUniqueIdInterface,
    Feature\VersionAwareInterface
{
    use Feature\HierarchyAwareTrait;
    use Feature\PreviousUniqueIdTrait;
    use Feature\VersionAwareTrait;

    /**
     * These Solr fields should be used for snippets if available (listed in order
     * of preference).
     *
     * @var array
     */
    protected $preferredSnippetFields = [
        'contents', 'topic',
    ];

    /**
     * These Solr fields should NEVER be used for snippets. (We exclude author
     * and title because they are already covered by displayed fields; we exclude
     * spelling because it contains lots of fields jammed together and may cause
     * glitchy output; we exclude ID because random numbers are not helpful).
     *
     * @var array
     */
    protected $forbiddenSnippetFields = [
        'author', 'title', 'title_short', 'title_full',
        'title_full_unstemmed', 'title_auth', 'title_sub', 'spelling', 'id',
        'ctrlnum', 'author_variant', 'author2_variant', 'fullrecord',
        'work_keys_str_mv',
    ];

    /**
     * These are captions corresponding with Solr fields for use when displaying
     * snippets.
     *
     * @var array
     */
    protected $snippetCaptions = [];

    /**
     * Should we include snippets in search results?
     *
     * @var bool
     */
    protected $snippet = false;

    /**
     * Highlighting details
     *
     * @var array
     */
    protected $highlightDetails = [];

    /**
     * Should we use hierarchy fields for simple container-child records linking?
     *
     * @var bool
     */
    protected $containerLinking = false;

    /**
     * Search results plugin manager
     *
     * @var \VuFindSearch\Service
     */
    protected $searchService = null;

    /**
     * If the explain feature is enabled
     *
     * @var bool
     */
    protected $explainEnabled = false;

    /**
     * Constructor
     *
     * @param \Laminas\Config\Config $mainConfig     VuFind main configuration (omit
     * for built-in defaults)
     * @param \Laminas\Config\Config $recordConfig   Record-specific configuration
     * file (omit to use $mainConfig as $recordConfig)
     * @param \Laminas\Config\Config $searchSettings Search-specific configuration
     * file
     */
    public function __construct(
        $mainConfig = null,
        $recordConfig = null,
        $searchSettings = null
    ) {
        $this->setSourceIdentifiers('Solr');
        // Load snippet settings:
        $this->snippet = !isset($searchSettings->General->snippets)
            ? false : $searchSettings->General->snippets;
        if (
            isset($searchSettings->Snippet_Captions)
            && count($searchSettings->Snippet_Captions) > 0
        ) {
            foreach ($searchSettings->Snippet_Captions as $key => $value) {
                $this->snippetCaptions[$key] = $value;
            }
        }
        // Container-contents linking
        $this->containerLinking
            = !isset($mainConfig->Hierarchy->simpleContainerLinks)
            ? false : $mainConfig->Hierarchy->simpleContainerLinks;

        $this->explainEnabled = $searchSettings->Explain->enabled ?? false;

        parent::__construct($mainConfig, $recordConfig, $searchSettings);
    }

    /**
     * Get the date this record was first indexed (if set).
     *
     * @return string
     */
    public function getFirstIndexed()
    {
        return $this->fields['first_indexed'] ?? '';
    }

    /**
     * Get highlighting details from the object.
     *
     * @return array
     */
    public function getHighlightDetails()
    {
        return $this->highlightDetails;
    }

    /**
     * Add highlighting details to the object.
     *
     * @param array $details Details to add
     *
     * @return void
     */
    public function setHighlightDetails($details)
    {
        $this->highlightDetails = $details;
    }

    /**
     * Get highlighted author data, if available.
     *
     * @return array
     */
    public function getRawAuthorHighlights()
    {
        // Don't check for highlighted values if highlighting is disabled:
        return ($this->highlight && isset($this->highlightDetails['author']))
            ? $this->highlightDetails['author'] : [];
    }

    /**
     * Given a Solr field name, return an appropriate caption.
     *
     * @param string $field Solr field name
     *
     * @return mixed        Caption if found, false if none available.
     */
    public function getSnippetCaption($field)
    {
        return $this->snippetCaptions[$field] ?? false;
    }

    /**
     * Pick one line from the highlighted text (if any) to use as a snippet.
     *
     * @return mixed False if no snippet found, otherwise associative array
     * with 'snippet' and 'caption' keys.
     */
    public function getHighlightedSnippet()
    {
        // Only process snippets if the setting is enabled:
        if ($this->snippet) {
            // First check for preferred fields:
            foreach ($this->preferredSnippetFields as $current) {
                foreach ($this->highlightDetails[$current] ?? [] as $hl) {
                    if (!empty($hl)) {
                        return [
                            'snippet' => $hl,
                            'caption' => $this->getSnippetCaption($current),
                        ];
                    }
                }
            }

            // No preferred field found, so try for a non-forbidden field:
            if (
                isset($this->highlightDetails)
                && is_array($this->highlightDetails)
            ) {
                foreach ($this->highlightDetails as $key => $value) {
                    if ($value && !in_array($key, $this->forbiddenSnippetFields)) {
                        foreach ($value as $hl) {
                            if (!empty($hl)) {
                                return [
                                    'snippet' => $hl,
                                    'caption' => $this->getSnippetCaption($key),
                                ];
                            }
                        }
                    }
                }
            }
        }

        // If we got this far, no snippet was found:
        return false;
    }

    /**
     * Get a highlighted title string, if available.
     *
     * @return string
     */
    public function getHighlightedTitle()
    {
        // Don't check for highlighted values if highlighting is disabled:
        if (!$this->highlight) {
            return '';
        }
        return $this->highlightDetails['title'][0] ?? '';
    }

    /**
     * Attach a Search Results Plugin Manager connection and related logic to
     * the driver
     *
     * @param \VuFindSearch\Service $service Search Service Manager
     *
     * @return void
     */
    public function attachSearchService(\VuFindSearch\Service $service)
    {
        $this->searchService = $service;
    }

    /**
     * Get the number of child records belonging to this record
     *
     * @return int Number of records
     */
    public function getChildRecordCount()
    {
        // Shortcut: if this record is not the top record, let's not find out the
        // count. This assumes that contained records cannot contain more records.
        if (
            !$this->containerLinking
            || empty($this->fields['is_hierarchy_id'])
            || null === $this->searchService
        ) {
            return 0;
        }

        $safeId = addcslashes($this->fields['is_hierarchy_id'], '"');
        $query = new \VuFindSearch\Query\Query(
            'hierarchy_parent_id:"' . $safeId . '"'
        );
        // Disable highlighting for efficiency; not needed here:
        $params = new \VuFindSearch\ParamBag(['hl' => ['false']]);
        $command = new SearchCommand($this->sourceIdentifier, $query, 0, 0, $params);
        return $this->searchService
            ->invoke($command)->getResult()->getTotal();
    }

    /**
     * Get the container record id.
     *
     * @return string Container record id (empty string if none)
     */
    public function getContainerRecordID()
    {
        return $this->containerLinking
            && !empty($this->fields['hierarchy_parent_id'])
            ? $this->fields['hierarchy_parent_id'][0] : '';
    }

    /**
     * Get work identification keys
     *
     * @return array
     */
    public function getWorkKeys()
    {
        return $this->fields['work_keys_str_mv'] ?? [];
    }

    /**
     * Get if the explain features is enabled.
     *
     * @return bool
     */
    public function explainEnabled()
    {
        return $this->explainEnabled;
    }
}
