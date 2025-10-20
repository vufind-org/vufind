<?php

/**
 * Model for EDS records.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace VuFind\RecordDriver;

use function count;
use function in_array;
use function is_array;
use function is_callable;
use function strlen;

/**
 * Model for EDS records.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class EDS extends DefaultRecord
{
    /**
     * Document types that are treated as ePub links.
     *
     * @var array
     */
    protected $epubTypes = ['ebook-epub'];

    /**
     * Document types that are treated as PDF links.
     *
     * @var array
     */
    protected $pdfTypes = ['ebook-pdf', 'pdflink'];

    /**
     * Return the unique identifier of this record within the Solr index;
     * useful for retrieving additional information (like tags and user
     * comments) from the external MySQL database.
     *
     * @return string Unique identifier.
     */
    public function getUniqueID()
    {
        $dbid = $this->fields['Header']['DbId'];
        $an = $this->fields['Header']['An'];
        return $dbid . ',' . $an;
    }

    /**
     * Get the short (pre-subtitle) title of the record.
     *
     * @return string
     */
    public function getShortTitle()
    {
        $title = $this->getTitle();
        if (null == $title) {
            return '';
        }
        $parts = explode(':', $title);
        return trim(current($parts));
    }

    /**
     * Get the subtitle (if any) of the record.
     *
     * @return string
     */
    public function getSubtitle()
    {
        $title = $this->getTitle();
        if (null == $title) {
            return '';
        }
        $parts = explode(':', $title, 2);
        return count($parts) > 1 ? trim(array_pop($parts)) : '';
    }

    /**
     * Get the abstract notes.
     * For EDS, returns the abstract in an array or an empty array.
     *
     * @return array
     */
    public function getAbstractNotes()
    {
        $abstracts = $this->getItem('Group', 'Ab');
        return array_filter(array_map(fn ($abstract) => ($abstract['Data'] ?? null), $abstracts));
    }

    /**
     * Get the access level of the record.
     *
     * @return string If not empty, will contain a numerical value corresponding to these levels of access:
     *                0 - Not Available to search via Guest Access
     *                1 - Metadata is searched, but only a placeholder record is displayed
     *                2 - Display record in the results but no access to detailed record or full text
     *                3 - Full access: search/display all content to guests
     *                6 - Display full record but no access to full text
     */
    public function getAccessLevel()
    {
        return $this->fields['Header']['AccessLevel'] ?? '';
    }

    /**
     * Get the authors of the record
     *
     * @return string
     */
    public function getItemsAuthors()
    {
        $authors = $this->getItemsAuthorsArray();
        return empty($authors) ? '' : implode('; ', $authors);
    }

    /**
     * Obtain an array or authors indicated on the record
     *
     * @return array
     */
    protected function getItemsAuthorsArray()
    {
        return array_map(
            function ($data) {
                $elements = array_map(
                    fn ($element) => $element['SearchLink'] ?? $element['Data'],
                    $data['Elements']
                );

                if (!empty($elements)) {
                    return implode(', ', $elements);
                } else {
                    return $data['Data'];
                }
            },
            $this->getItem('Group', 'Au')
        );
    }

    /**
     * Get the custom links of the record.
     *
     * @return array
     */
    public function getCustomLinks()
    {
        return $this->fields['CustomLinks'] ?? [];
    }

    /**
     * Get the full text custom links of the record.
     *
     * @return array
     */
    public function getFTCustomLinks()
    {
        return $this->fields['FullText']['CustomLinks'] ?? [];
    }

    /**
     * Get the database label of the record.
     *
     * @return string
     */
    public function getDbLabel()
    {
        return $this->fields['Header']['DbLabel'] ?? '';
    }

    /**
     * Get the full text of the record.
     *
     * @return string
     */
    public function getHTMLFullText()
    {
        return $this->toHTML($this->fields['FullText']['Text']['Value'] ?? '');
    }

    /**
     * Get the full text availability of the record.
     *
     * @return bool
     */
    public function hasHTMLFullTextAvailable()
    {
        return '1' == ($this->fields['FullText']['Text']['Availability'] ?? '0');
    }

    /**
     * Support method for getItems, used to apply filters.
     * Filters are multidimensional arrays. The first dimension
     * defines if the values should be excluded or are the only ones
     * to be included. The second dimension defines in which item
     * key (e.g. Label or Group) is used for filtering.
     *
     * @param array $item   Item to check
     * @param array $filter Filters
     *
     * @return bool
     */
    protected function itemIsIncluded(array $item, array $filter): bool
    {
        $globalFilter = isset($this->recordConfig->ItemGlobalFilter)
            ? $this->recordConfig->ItemGlobalFilter->toArray() : [];

        $filter['exclude']['Label'] =
            array_merge($globalFilter['excludeLabel'] ?? [], $filter['exclude']['Label'] ?? []);
        $filter['include']['Label'] =
            array_merge($globalFilter['includeLabel'] ?? [], $filter['include']['Label'] ?? []);
        $filter['exclude']['Group'] =
            array_merge($globalFilter['excludeGroup'] ?? [], $filter['exclude']['Group'] ?? []);
        $filter['include']['Group'] =
            array_merge($globalFilter['includeGroup'] ?? [], $filter['include']['Group'] ?? []);

        foreach ($filter['exclude'] ?? [] as $itemKey => $filteredItemValues) {
            if (isset($item[$itemKey]) && in_array($item[$itemKey], $filteredItemValues)) {
                return false;
            }
        }

        foreach ($filter['include'] ?? [] as $itemKey => $filteredItemValues) {
            if (isset($item[$itemKey]) && in_array($item[$itemKey], $filteredItemValues)) {
                return true;
            }
        }

        return empty(array_filter($filter['include']));
    }

    /**
     * Get the items of the record based on a value of a specific key.
     * E.g. Label and Authors or Group and Au.
     *
     * @param string $itemKey   Key of item used for selection
     * @param string $itemValue Value to be selected
     *
     * @return array
     */
    public function getItem(string $itemKey, string $itemValue): array
    {
        $filter = ['include' => [$itemKey => [$itemValue]]];
        return $this->getItems($filter);
    }

    /**
     * Get the items of the record.
     *
     * @param array $filter Filter (see itemIsIncluded)
     *
     * @return array
     */
    public function getItems(
        array $filter = []
    ): array {
        $items = [];
        if (is_array($this->fields['Items'] ?? null)) {
            $itemGlobalOrderConfig = $this->recordConfig?->ItemGlobalOrder?->toArray() ?? [];
            $origItems = $this->fields['Items'];
            // Only sort by label if we have a sort config:
            if (!empty($itemGlobalOrderConfig)) {
                // We want unassigned labels to appear AFTER configured labels:
                $nextPos = max(array_keys($itemGlobalOrderConfig));
                foreach (array_keys($origItems) as $key) {
                    $label = $origItems[$key]['Label'] ?? '';
                    $configuredPos = array_search($label, $itemGlobalOrderConfig);
                    $origItems[$key]['Pos'] = $configuredPos === false
                        ? ++$nextPos : $configuredPos;
                }
                $positions = array_column($origItems, 'Pos');
                array_multisort($positions, SORT_ASC, $origItems);
            }

            foreach ($origItems as $item) {
                $nextItem = $this->parseItem([
                    'Label' => $item['Label'] ?? '',
                    'Group' => $item['Group'] ?? '',
                    'Name' => $item['Name'] ?? '',
                    'RawData'  => $item['Data'] ?? '',
                ]);
                if ($this->itemIsIncluded($nextItem, $filter)) {
                    $items[] = $nextItem;
                }
            }
        }
        return $items;
    }

    /**
     * Get the full text url of the record.
     *
     * @return string
     */
    public function getPLink()
    {
        return $this->fields['PLink'] ?? '';
    }

    /**
     * Get the publication type of the record.
     *
     * @return string
     */
    public function getPubType()
    {
        return $this->fields['Header']['PubType'] ?? '';
    }

    /**
     * Get the publication type id of the record.
     *
     * @return string
     */
    public function getPubTypeId()
    {
        return $this->fields['Header']['PubTypeId'] ?? '';
    }

    /**
     * Get the ebook availability of the record.
     *
     * @param array $types Types that we are interested in checking for
     *
     * @return bool
     */
    protected function hasEbookAvailable(array $types)
    {
        foreach ($this->fields['FullText']['Links'] ?? [] as $link) {
            if (in_array($link['Type'] ?? '', $types)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get the PDF availability of the record.
     *
     * @return bool
     */
    public function hasPdfAvailable()
    {
        return $this->hasEbookAvailable($this->pdfTypes);
    }

    /**
     * Get the ePub availability of the record.
     *
     * @return bool
     */
    public function hasEpubAvailable()
    {
        return $this->hasEbookAvailable($this->epubTypes);
    }

    /**
     * Get the linked full text availability of the record.
     *
     * @return bool
     */
    public function hasLinkedFullTextAvailable()
    {
        return $this->hasEbookAvailable(['other']);
    }

    /**
     * Get the ebook url of the record. If missing, return false
     *
     * @param array $types Types that we are interested in checking for
     *
     * @return string
     */
    public function getEbookLink(array $types)
    {
        foreach ($this->fields['FullText']['Links'] ?? [] as $link) {
            if (
                !empty($link['Type']) && !empty($link['Url'])
                && in_array($link['Type'], $types)
            ) {
                return $link['Url'];
            }
        }
        return false;
    }

    /**
     * Get the PDF url of the record. If missing, return false
     *
     * @return string
     */
    public function getPdfLink()
    {
        return $this->getEbookLink($this->pdfTypes);
    }

    /**
     * Get the ePub url of the record. If missing, return false
     *
     * @return string
     */
    public function getEpubLink()
    {
        return $this->getEbookLink($this->epubTypes);
    }

    /**
     * Get the linked full text url of the record. If missing, return false
     *
     * @return string
     */
    public function getLinkedFullTextLink()
    {
        return $this->getEbookLink(['other']);
    }

    /**
     * Get the subject headings as a flat array of strings.
     *
     * @return array Subject headings
     */
    public function getAllSubjectHeadingsFlattened()
    {
        $subject_arrays = array_map(
            function ($data) {
                $elements = $data['Elements'] ?? [];
                return array_map(
                    fn ($element) => rtrim(strip_tags($element['Data']), '.'),
                    $elements
                );
            },
            $this->getItem('Group', 'Su')
        );
        return array_merge(...$subject_arrays);
    }

    /**
     * Return a URL to a thumbnail preview of the record, if available; false
     * otherwise.
     *
     * @param string $size Size of thumbnail (small, medium or large -- small is
     * default).
     *
     * @return string
     */
    public function getThumbnail($size = 'small')
    {
        foreach ($this->fields['ImageInfo'] ?? [] as $image) {
            if ($size == ($image['Size'] ?? '')) {
                return $image['Target'] ?? '';
            }
        }
        return false;
    }

    /**
     * Get the title of the record.
     *
     * @return string
     */
    public function getItemsTitle()
    {
        $title = $this->getItem('Group', 'Ti');
        return $title[0]['Data'] ?? '';
    }

    /**
     * Obtain the title of the record from the record info section
     *
     * @return string
     */
    public function getTitle()
    {
        $list = $this->extractEbscoDataFromRecordInfo('BibRecord/BibEntity/Titles');
        foreach ($list as $titleRecord) {
            if ('main' == ($titleRecord['Type'] ?? '')) {
                return $titleRecord['TitleFull'];
            }
        }
        return '';
    }

    /**
     * Obtain the authors from a record from the RecordInfo section
     *
     * @return array
     */
    public function getPrimaryAuthors()
    {
        $authors = $this->extractEbscoDataFromRecordInfo(
            'BibRecord/BibRelationships/HasContributorRelationships/*/'
                . 'PersonEntity/Name/NameFull'
        );
        return array_unique(array_filter($authors));
    }

    /**
     * Get highlighted author data, if available.
     *
     * @return array
     */
    public function getRawAuthorHighlights()
    {
        $authors = $this->getItem('Group', 'Au')[0] ?? [];
        $highlightedAuthors = [];
        foreach ($authors['Elements'] ?? [] as $author) {
            if (preg_match('/<span class="highlight">(((?!<\/span>).)*)<\/span>/', $author['Data'] ?? '', $matches)) {
                $highlightedAuthors[] = '{{{{START_HILITE}}}}' . $matches[1] . '{{{{END_HILITE}}}}';
            }
        }
        return $highlightedAuthors;
    }

    /**
     * Get the source of the record.
     *
     * @return string
     */
    public function getItemsTitleSource()
    {
        $title = $this->getItem('Group', 'Src');
        return $title[0]['Data'] ?? '';
    }

    /**
     * Parse a SimpleXml element and
     * return it's inner XML as an HTML string.
     *
     * @param string $data A SimpleXml DOM
     *
     * @return string      The HTML string
     */
    protected function toHTML(string $data): string
    {
        $xml_to_html_tags = [
                '<jsection'    => '<section',
                '</jsection'   => '</section',
                '<highlight'   => '<span class="highlight"',
                '<highligh'    => '<span class="highlight"', // Temporary bug fix
                '</highlight>' => '</span>', // Temporary bug fix
                '</highligh'   => '</span>',
                '<text'        => '<div',
                '</text'       => '</div',
                '<title'       => '<h2',
                '</title'      => '</h2',
                '<anid'        => '<p class="eds_html_anid"',
                '</anid'       => '</p',
                '<aug'         => '<p class="aug"',
                '</aug'        => '</p',
                '<hd'          => '<h3',
                '</hd'         => '</h3',
                '<linebr'      => '<br',
                '</linebr'     => '',
                '<olist'       => '<ol',
                '</olist'      => '</ol',
                '<reflink'     => '<a',
                '</reflink'    => '</a',
                '<blist'       => '<p class="blist"',
                '</blist'      => '</p',
                '<bibl'        => '<a',
                '</bibl'       => '</a',
                '<bibtext'     => '<span',
                '</bibtext'    => '</span',
                '<ref'         => '<div class="ref"',
                '</ref'        => '</div',
                '<ulink'       => '<a',
                '</ulink'      => '</a',
                '<superscript' => '<sup',
                '</superscript' => '</sup',
                '<relatesTo'   => '<sup',
                '</relatesTo'  => '</sup',
        ];

        //  The XML data is escaped, let's unescape html entities (e.g. &lt; => <)
        $data = html_entity_decode($data, ENT_QUOTES, 'utf-8');

        // Start parsing the xml data
        if (!empty($data)) {
            // Replace the XML tags with HTML tags
            $search = array_keys($xml_to_html_tags);
            $replace = array_values($xml_to_html_tags);
            $data = str_replace($search, $replace, $data);

            // Temporary : fix unclosed tags
            $data = preg_replace('/<\/highlight/', '</span>', $data);
            $data = preg_replace('/<\/span>>/', '</span>', $data);
            $data = preg_replace('/<\/searchLink/', '</searchLink>', $data);
            $data = preg_replace('/<\/searchLink>>/', '</searchLink>', $data);

            // Replace the rest of searchLinks with simple spans
            $link_xml = '/<searchLink fieldCode="([^\"]*)" term="%22([^\"]*)%22">/';
            $link_html = '<span>';
            $data = preg_replace($link_xml, $link_html, $data);
            $data = str_replace('</searchLink>', '</span>', $data);

            // Parse bibliography (anchors and links)
            $data = preg_replace('/<a idref="([^\"]*)"/', '<a href="#$1"', $data);
            $data = preg_replace(
                '/<a id="([^\"]*)" idref="([^\"]*)" type="([^\"]*)"/',
                '<a id="$1" href="#$2"',
                $data
            );

            // Avoid tables & mathML markup from showing as "raw html"
            // - Tables and mathML markup are wrapped in <ephtml> tags
            // - their content is urlencoded (i.e. double-encoding)
            // - through this function we decode the content and wrap it in a
            //   div/span tag and give it a class for styling
            if (strpos($data, 'ephtml') > -1) {
                $pattern = "/<\/?ephtml>/";
                $splitParts = preg_split($pattern, $data);
                $data = '';
                foreach ($splitParts as $part) {
                    if (strpos($part, '&lt;table') > -1) {
                        $data .= '<div class="eds_html_table">';
                        $data .= html_entity_decode($part, ENT_QUOTES, 'utf-8');
                        $data .= '</div>';
                    } elseif (strpos($part, '&lt;math') > -1) {
                        $data .= '<span class="eds_html_math">';
                        $data .= html_entity_decode($part, ENT_QUOTES, 'utf-8');
                        $data .= '</span>';
                    } else {
                        $data .= $part;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Parse an item.
     *
     * @param array $item Item
     *
     * @return array Parsed Item
     */
    protected function parseItem(array $item): array
    {
        $data = $item['RawData'] ?? '';
        $type = strtoupper($item['Group'] ?? '');
        $elements = [];
        foreach (explode('&lt;br /&gt;', $data) as $elementData) {
            if (empty($elementData)) {
                continue;
            }
            // Parse searchLinks
            $excludeQuotationMark = '(((?!&quot;)[^"])*)';
            $link_xml = '/&lt;searchLink fieldCode=(&quot;|")' . $excludeQuotationMark
                . '(&quot;|") term=(&quot;|")%22' . $excludeQuotationMark . '%22(&quot;|")&gt;/';
            if (!empty($type) && preg_match($link_xml, $elementData, $matches)) {
                $link_html = '&lt;a href=&quot;../EDS/Search?lookfor=%22$6%22&amp;amp;type='
                    . urlencode($type) . '&quot;&gt;';
                $link = preg_replace($link_xml, $link_html, $elementData);
                $link = str_replace('&lt;/searchLink&gt;', '&lt;/a&gt;', $link);
                $element['SearchLink'] = $this->toHTML($link);
            }
            $element['Data'] = $this->toHTML($elementData);
            if (
                preg_match(
                    '/^<link linkTarget="URL" linkTerm="([^"]+)"[^<]*<\/link>$/',
                    $element['Data'],
                    $matches
                )
            ) {
                $element['Link'] = $matches[1];
            }
            $elements[] = $element;
        }

        $item['Data'] = $this->toHTML($data);
        $item['Elements'] = $elements;

        return $item;
    }

    /**
     * Return the first valid DOI found in the record (false if none).
     *
     * @return mixed
     */
    public function getCleanDOI()
    {
        $doi = $this->getItem('Name', 'DOI');
        if (isset($doi[0]['Data'])) {
            $cleanDoi = strip_tags($doi[0]['Data']);
            $cleanDoi = preg_replace('/https?:\/\/.*doi.org\//', '', $cleanDoi);
            return $cleanDoi;
        }
        $dois = $this->getFilteredIdentifiers(['doi']);
        return $dois[0] ?? false;
    }

    /**
     * Get record languages
     *
     * @return array
     */
    public function getLanguages()
    {
        return $this->extractEbscoData(
            [
                'RecordInfo:BibRecord/BibEntity/Languages/*/Text',
                'Items:Languages',
                'Items:Language',
            ]
        );
    }

    /**
     * Retrieve identifiers from the EBSCO record and retrieve values filtered by
     * type.
     *
     * @param array $filter Type values to retrieve.
     *
     * @return array
     */
    protected function getFilteredIdentifiers($filter)
    {
        $raw = array_merge(
            $this->extractEbscoDataFromRecordInfo(
                'BibRecord/BibRelationships/IsPartOfRelationships/*'
                . '/BibEntity/Identifiers'
            ),
            $this->extractEbscoDataFromRecordInfo(
                'BibRecord/BibEntity/Identifiers'
            )
        );
        $ids = [];
        foreach ($raw as $data) {
            $type = strtolower($data['Type'] ?? '');
            if (isset($data['Value']) && in_array($type, $filter)) {
                $ids[] = $data['Value'];
            }
        }
        return $ids;
    }

    /**
     * Get ISSNs (of containing record)
     *
     * @return array
     */
    public function getISSNs()
    {
        return $this->getFilteredIdentifiers(['issn-print', 'issn-electronic']);
    }

    /**
     * Get an array of ISBNs
     *
     * @return array
     */
    public function getISBNs()
    {
        return $this->getFilteredIdentifiers(['isbn-print', 'isbn-electronic']);
    }

    /**
     * Get title of containing record
     *
     * @return string
     */
    public function getContainerTitle()
    {
        // If there is no source, we don't want to identify a container
        // (in this situation, it is likely redundant data):
        if (count($this->extractEbscoDataFromItems('Source')) === 0) {
            return '';
        }
        $data = $this->extractEbscoDataFromRecordInfo(
            'BibRecord/BibRelationships/IsPartOfRelationships/0'
            . '/BibEntity/Titles/0/TitleFull'
        );
        return $data[0] ?? '';
    }

    /**
     * Extract numbering data of a particular type.
     *
     * @param string $type Numbering type to return, if present.
     *
     * @return string
     */
    protected function getFilteredNumbering($type)
    {
        $numbering = $this->extractEbscoDataFromRecordInfo(
            'BibRecord/BibRelationships/IsPartOfRelationships/*/BibEntity/Numbering'
        );
        foreach ($numbering as $data) {
            if (
                strtolower($data['Type'] ?? '') == $type
                && !empty($data['Value'])
            ) {
                return $data['Value'];
            }
        }
        return '';
    }

    /**
     * Get issue of containing record
     *
     * @return string
     */
    public function getContainerIssue()
    {
        return $this->getFilteredNumbering('issue');
    }

    /**
     * Get volume of containing record
     *
     * @return string
     */
    public function getContainerVolume()
    {
        return $this->getFilteredNumbering('volume');
    }

    /**
     * Get the publication dates of the record.  See also getDateSpan().
     *
     * @return array
     */
    public function getPublicationDates()
    {
        $pubDates = array_map(
            function ($data) {
                return $data->getDate();
            },
            $this->getRawEDSPublicationDetails()
        );
        return !empty($pubDates) ? $pubDates : $this->extractEbscoDataFromRecordInfo(
            'BibRecord/BibRelationships/IsPartOfRelationships/0/BibEntity/Dates/0/Y'
        );
    }

    /**
     * Get year of containing record
     *
     * @return string
     */
    public function getContainerStartPage()
    {
        $pagination = $this->extractEbscoDataFromRecordInfo(
            'BibRecord/BibEntity/PhysicalDescription/Pagination'
        );
        return $pagination['StartPage'] ?? '';
    }

    /**
     * Get the end page of the item that contains this record.
     *
     * @return string
     */
    public function getContainerEndPage()
    {
        // EBSCO doesn't make this information readily available, but in some
        // cases we can abstract it from an OpenURL.
        $startPage = $this->getContainerStartPage();
        if (!empty($startPage)) {
            $startPage = preg_quote($startPage, '/');
            $regex = "/&pages={$startPage}-(\d+)/";
            foreach ($this->getFTCustomLinks() as $link) {
                if (preg_match($regex, $link['Url'] ?? '', $matches)) {
                    if (isset($matches[1])) {
                        return $matches[1];
                    }
                }
            }
        }
        return '';
    }

    /**
     * Returns an array of formats based on publication type.
     *
     * @return array
     */
    public function getFormats()
    {
        $formats = [];
        $pubType = $this->getPubType();
        switch (strtolower($pubType)) {
            case 'academic journal':
            case 'periodical':
            case 'report':
                // Add "article" format for better OpenURL generation
                $formats[] = $pubType;
                $formats[] = 'Article';
                break;
            case 'ebook':
                // Treat eBooks as both "Books" and "Electronic" items
                $formats[] = 'Book';
                $formats[] = 'Electronic';
                break;
            case 'dissertation/thesis':
                // Simplify wording for consistency with other drivers
                $formats[] = 'Thesis';
                break;
            default:
                $formats[] = $pubType;
        }

        return $formats;
    }

    /**
     * Get the publishers of the record.
     *
     * @return array
     */
    public function getPublishers()
    {
        return array_map(
            function ($data) {
                return $data->getName();
            },
            $this->getRawEDSPublicationDetails()
        );
    }

    /**
     * Get the item's place of publication.
     *
     * @return array
     */
    public function getPlacesOfPublication()
    {
        return array_map(
            function ($data) {
                return $data->getPlace();
            },
            $this->getRawEDSPublicationDetails()
        );
    }

    /**
     * Get an array of publication detail lines combining information from
     * getPublicationDates(), getPublishers() and getPlacesOfPublication().
     *
     * @return array
     */
    public function getPublicationDetails()
    {
        $details = $this->getRawEDSPublicationDetails();
        return !empty($details) ? $details : parent::getPublicationDetails();
    }

    /**
     * Attempt to build up publication details from raw EDS data.
     *
     * @return array
     */
    protected function getRawEDSPublicationDetails()
    {
        $details = [];
        foreach ($this->getItem('Label', 'Publication Information') as $pub) {
            // Try to extract place, publisher and date:
            if (preg_match('/^(.+):(.*)\.\s*(\d{4})$/', $pub['Data'], $matches)) {
                [$place, $pub, $date] = [trim($matches[1]), trim($matches[2]), $matches[3]];
            } elseif (preg_match('/^(.+):(.*)$/', $pub['Data'], $matches)) {
                [$place, $pub, $date] = [trim($matches[1]), trim($matches[2]), ''];
            } else {
                [$place, $pub, $date] = ['', $pub['Data'], ''];
            }

            // In some cases, the place may have noise on the front that needs
            // to be removed...
            $placeParts = explode('.', $place);
            $shortPlace = array_pop($placeParts);
            $details[] = new Response\PublicationDetails(
                strlen($shortPlace) > 5 ? $shortPlace : $place,
                $pub,
                $date
            );
        }
        return $details;
    }

    /**
     * Get class name for RecordDataFormatter spec.
     *
     * @return ?string
     */
    public function getRecordDataFormatterSpecClass(): ?string
    {
        return \VuFind\RecordDataFormatter\Specs\EDS::class;
    }

    /**
     * Extract data from EBSCO API response using a prioritized list of selectors.
     * Selectors can be of the form Items:Label to invoke extractEbscoDataFromItems,
     * or RecordInfo:Path/To/Data/Element to invoke extractEbscoDataFromRecordInfo.
     *
     * @param array $selectors Array of selector strings for extracting data.
     *
     * @return array
     */
    protected function extractEbscoData($selectors)
    {
        $result = [];
        foreach ($selectors as $selector) {
            [$method, $params] = explode(':', $selector, 2);
            $fullMethod = 'extractEbscoDataFrom' . ucwords($method);
            if (!is_callable([$this, $fullMethod])) {
                throw new \Exception('Undefined method: ' . $fullMethod);
            }
            $result = $this->$fullMethod($params);
            if (!empty($result)) {
                break;
            }
        }
        return $result;
    }

    /**
     * Extract data from the record's "Items" array, based on a label.
     *
     * @param string $label Label to filter on.
     *
     * @return array
     */
    protected function extractEbscoDataFromItems($label)
    {
        $items = $this->getItem('Label', $label);
        $output = [];
        foreach ($items as $item) {
            $output[] = $item['Data'];
        }
        return $output;
    }

    /**
     * Extract data from the record's "RecordInfo" array, based on a path.
     *
     * @param string $path Path to select with (slash-separated element names,
     * with special * selector to iterate through all children).
     *
     * @return array
     */
    protected function extractEbscoDataFromRecordInfo($path)
    {
        return (array)$this->recurseIntoRecordInfo(
            $this->fields['RecordInfo'] ?? [],
            explode('/', $path)
        );
    }

    /**
     * Recursive support method for extractEbscoDataFromRecordInfo().
     *
     * @param array $data Data to recurse into
     * @param array $path Array representing path into data
     *
     * @return array
     */
    protected function recurseIntoRecordInfo($data, $path)
    {
        $nextField = array_shift($path);
        $keys = $nextField === '*' ? array_keys($data) : [$nextField];
        $values = [];
        foreach ($keys as $key) {
            if (isset($data[$key])) {
                $values[] = empty($path)
                    ? $data[$key]
                    : $this->recurseIntoRecordInfo($data[$key], $path);
            }
        }
        return count($values) == 1 ? $values[0] : $values;
    }
}
