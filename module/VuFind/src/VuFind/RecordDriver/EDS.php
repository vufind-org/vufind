<?php
/**
 * Model for EDS records.
 *
 * PHP version 5
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
namespace VuFind\RecordDriver;

/**
 * Model for EDS records.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class EDS extends SolrDefault
{
    /**
     * Document types that are treated as PDF links.
     *
     * @var array
     */
    protected $pdfTypes = ['ebook-pdf', 'pdflink'];
    
    protected $prioritizedFields  = [
        'Languages' => [
            ['RecordInfo', 'BibRecord', 'BibEntity', 'Languages', 0, 'Text'],
            ['Items', 'Languages'],
        ],
        'ContainerPages' => [
            ['RecordInfo','BibRecord','BibEntity','PhysicalDescription',
                'Pagination']
        ],
        'ContainerYear' => [
            ['RecordInfo','BibRecord','BibRelationships',
             'IsPartOfRelationships',0,'BibEntity','Dates','0','Y']
        ],
        'ContainerVolume' => [
            ['RecordInfo','BibRecord','BibRelationships',
             'IsPartOfRelationships',0,'BibEntity','Numbering']
        ],
        'ContainerIssue' => [
            ['RecordInfo','BibRecord','BibRelationships',
             'IsPartOfRelationships',0,'BibEntity','Numbering']
        ],
        'ContainerTitle' => [
            ['RecordInfo','BibRecord','BibRelationships',
             'IsPartOfRelationships',0,'BibEntity','Titles',0,'TitleFull']
            
        ],
        'ISSNs' => [
            ['RecordInfo','BibRecord','BibRelationships',
             'IsPartOfRelationships',0,'BibEntity','Identifiers'],
        ],
        'ISBNs' => [
            ['RecordInfo','BibRecord','BibRelationships','IsPartOfRelationships'
             ,0,'BibEntity','Identifiers'],
        ],
    ];

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
        if (mb_strlen($title, 'UTF-8') > 20) {
            $title = mb_substr($title, 0, 17, 'UTF-8') . '...';
        }
        return $title;
    }

    /**
     * Get the abstract (summary) of the record.
     *
     * @return string
     */
    public function getItemsAbstract()
    {
        if (isset($this->fields['Items'])) {
            foreach ($this->fields['Items'] as $item) {
                if ('Ab' == $item['Group']) {
                    return $this->toHTML($item['Data'], $item['Group']);
                }
            }
        }
        return '';
    }

    /**
     * Get the access level of the record.
     *
     * @return string
     */
    public function getAccessLevel()
    {
        return isset($this->fields['Header']['AccessLevel'])
            ? $this->fields['Header']['AccessLevel'] : '';
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
        $authors = [];
        if (isset($this->fields['Items'])) {
            foreach ($this->fields['Items'] as $item) {
                if ('Au' == $item['Group']) {
                    $authors[] = $this->toHTML($item['Data'], $item['Group']);
                }
            }
        }
        return $authors;
    }

    /**
     * Get the custom links of the record.
     *
     * @return array
     */
    public function getCustomLinks()
    {
        return isset($this->fields['CustomLinks'])
            ? $this->fields['CustomLinks'] : [];
    }

    /**
     * Get the full text custom links of the record.
     *
     * @return array
     */
    public function getFTCustomLinks()
    {
        return isset($this->fields['FullText']['CustomLinks'])
            ? $this->fields['FullText']['CustomLinks'] : [];
    }

    /**
     * Get the database label of the record.
     *
     * @return string
     */
    public function getDbLabel()
    {
        return isset($this->fields['Header']['DbLabel'])
            ? $this->fields['Header']['DbLabel'] : '';
    }

    /**
     * Get the full text of the record.
     *
     * @return string
     */
    public function getHTMLFullText()
    {
        return isset($this->fields['FullText']['Text']['Value'])
            ? $this->toHTML($this->fields['FullText']['Text']['Value']) : '';
    }

    /**
     * Get the full text availability of the record.
     *
     * @return bool
     */
    public function hasHTMLFullTextAvailable()
    {
        return isset($this->fields['FullText']['Text']['Availability'])
            && ('1' == $this->fields['FullText']['Text']['Availability']);
    }

    /**
     * Get the items of the record.
     * 
     * @param string $label Filter items by label     
     * 
     * @return array
     */
    public function getItems($label = null)
    {
        $items = [];
        if (isset($this->fields['Items']) && !empty($this->fields['Items'])) {
            foreach ($this->fields['Items'] as $item) {
                $tmp = [
                    'Label' => isset($item['Label']) ? $item['Label'] : '',
                    'Group' => isset($item['Group']) ? $item['Group'] : '',
                    'Data'  => isset($item['Data'])
                        ? $this->toHTML($item['Data'], $item['Group']) : ''
                ];
                if (is_null($label)) {
                    $items[] = $tmp;
                } elseif ($item['Label'] == $label) {
                    $items[] = $tmp;
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
        return isset($this->fields['PLink']) ? $this->fields['PLink'] : '';
    }

    /**
     * Get the publication type of the record.
     *
     * @return string
     */
    public function getPubType()
    {
        return isset($this->fields['Header']['PubType'])
            ? $this->fields['Header']['PubType'] : '';
    }

    /**
     * Get the publication type id of the record.
     *
     * @return string
     */
    public function getPubTypeId()
    {
        return isset($this->fields['Header']['PubTypeId'])
            ? $this->fields['Header']['PubTypeId'] : '';
    }

    /**
     * Get the PDF availability of the record.
     *
     * @return bool
     */
    public function hasPdfAvailable()
    {
        if (isset($this->fields['FullText']['Links'])) {
            foreach ($this->fields['FullText']['Links'] as $link) {
                if (isset($link['Type'])
                    && in_array($link['Type'], $this->pdfTypes)
                ) {
                    return true;
                }
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
        if (isset($this->fields['FullText']['Links'])) {
            foreach ($this->fields['FullText']['Links'] as $link) {
                if (isset($link['Type'])
                    && in_array($link['Type'], $this->pdfTypes)
                ) {
                    return $link['Url']; // return PDF link
                }
            }
        }
        return false;
    }

    /**
     * Get the subject data of the record.
     *
     * @return string
     */
    public function getItemsSubjects()
    {
        $subjects = [];
        if (isset($this->fields['Items'])) {
            foreach ($this->fields['Items'] as $item) {
                if ('Su' == $item['Group']) {
                    $subjects[] = $this->toHTML($item['Data'], $item['Group']);
                }
            }
        }
        return empty($subjects) ? '' : implode(', ', $subjects);
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
        if (!empty($this->fields['ImageInfo'])) {
            foreach ($this->fields['ImageInfo'] as $image) {
                if (isset($image['Size']) && $size == $image['Size']) {
                    return (isset($image['Target'])) ? $image['Target'] : '';
                }
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
        if (isset($this->fields['Items'])) {
            foreach ($this->fields['Items'] as $item) {
                if ('Ti' == $item['Group']) {
                    return $this->toHTML($item['Data']);
                }
            }
        }
        return '';
    }

    /**
     * Obtain the title of the record from the record info section
     *
     * @return string
     */
    public function getTitle()
    {
        if (isset($this->fields['RecordInfo']['BibRecord']['BibEntity']['Titles'])) {
            foreach ($this->fields['RecordInfo']['BibRecord']['BibEntity']['Titles']
                as $titleRecord
            ) {
                if (isset($titleRecord['Type']) && 'main' == $titleRecord['Type']) {
                    return $titleRecord['TitleFull'];
                }
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
        $authors = [];
        if (isset($this->fields['RecordInfo']['BibRecord']['BibRelationships'])) {
            $bibRels
                = & $this->fields['RecordInfo']['BibRecord']['BibRelationships'];
        }
        if (isset($bibRels['HasContributorRelationships'])
            && !empty($bibRels['HasContributorRelationships'])
        ) {
            foreach ($bibRels['HasContributorRelationships'] as $entry) {
                if (isset($entry['PersonEntity']['Name']['NameFull'])) {
                    $authors[] = $entry['PersonEntity']['Name']['NameFull'];
                }
            }
        }
        return $authors;
    }

    /**
     * Get the source of the record.
     *
     * @return string
     */
    public function getItemsTitleSource()
    {
        if (isset($this->fields['Items'])) {
            foreach ($this->fields['Items'] as $item) {
                if ('Src' == $item['Group']) {
                    return $this->toHTML($item['Data']);
                }
            }

        }
        return '';
    }

    /**
     * Performs a regex and replaces any url's with links containing themselves
     * as the text
     *
     * @param string $string String to process
     *
     * @return string        HTML string
     */
    public function linkUrls($string)
    {
        $linkedString = preg_replace_callback(
            "/\b(https?):\/\/([-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|]*)\b/i",
            create_function(
                '$matches',
                'return "<a href=\'".($matches[0])."\'>".($matches[0])."</a>";'
            ),
            $string
        );
        return $linkedString;
    }

    /**
     * Parse a SimpleXml element and
     * return it's inner XML as an HTML string
     *
     * @param SimpleXml $data  A SimpleXml DOM
     * @param string    $group Group identifier
     *
     * @return string          The HTML string
     */
    protected function toHTML($data, $group = null)
    {
        // Map xml tags to the HTML tags
        // This is just a small list, the total number of xml tags is far greater

        // Any group can be added here, but we only use Au (Author)
        // Other groups, not present here, won't be transformed to HTML links
        $allowed_searchlink_groups = ['au','su'];

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
                '<anid'        => '<p',
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
                '</relatesTo'  => '</sup'
        ];

        //  The XML data is escaped, let's unescape html entities (e.g. &lt; => <)
        $data = html_entity_decode($data, ENT_QUOTES, "utf-8");

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

            //$searchBase = $this->url('eds-search');
            // Parse searchLinks
            if (!empty($group)) {
                $group = strtolower($group);
                if (in_array($group, $allowed_searchlink_groups)) {
                    $type = strtoupper($group);
                    $link_xml = '/<searchLink fieldCode="([^\"]*)" '
                        . 'term="%22([^\"]*)%22">/';
                    $link_html
                        = "<a href=\"../EDS/Search?lookfor=$2&amp;type={$type}\">";
                    $data = preg_replace($link_xml, $link_html, $data);
                    $data = str_replace('</searchLink>', '</a>', $data);
                }
            }

            // Replace the rest of searchLinks with simple spans
            $link_xml = '/<searchLink fieldCode="([^\"]*)" term="%22([^\"]*)%22">/';
            $link_html = '<span>';
            $data = preg_replace($link_xml, $link_html, $data);
            $data = str_replace('</searchLink>', '</span>', $data);

            // Parse bibliography (anchors and links)
            $data = preg_replace('/<a idref="([^\"]*)"/', '<a href="#$1"', $data);
            $data = preg_replace(
                '/<a id="([^\"]*)" idref="([^\"]*)" type="([^\"]*)"/',
                '<a id="$1" href="#$2"', $data
            );

            $data = $this->replaceBRWithCommas($data, $group);
        }

        return $data;
    }

    /**
     * Replace <br> tags that are embedded in data to commas
     *
     * @param string $data  Data to process
     * @param string $group Group identifier
     *
     * @return string
     */
    protected function replaceBRWithCommas($data, $group)
    {
        $groupsToReplace = ['au','su'];
        if (in_array($group, $groupsToReplace)) {
            $br =  '/<br \/>/';
            $comma = ', ';
            return preg_replace($br, $comma, $data);
        }
        return $data;
    }

    /**
     * Get an array of strings representing citation formats supported
     * by this record's data (empty if none).  For possible legal values,
     * see /application/themes/root/helpers/Citation.php, getCitation()
     * method.
     *
     * @return array Strings representing citation formats.
     */
    protected function getSupportedCitationFormats()
    {
        return [];
    }

    /**
     * Indicate whether export is disabled for a particular format.
     *
     * @param string $format Export format
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function exportDisabled($format)
    {
        // at the moment we only support RIS
        return strtolower($format) != 'ris';
    }
    
     /**
     * This method can fetch fields out of the deeply nested RecordInfo array. 
     * many fields are here in a more readable and atomic format
     * 
     * The latter two params are only for internal use (recursion)
     *  
     * @param array $arrayKeys Key path to the needed value
     * @param int   $level     only used for recursion
     * @param array $fields    only used for recursion
      * 
     * @return array|string
     */
    protected function getFieldRecursive($arrayKeys, $level = 0, $fields = null)
    {
        // special case: we have to iterate through items array
        if ($arrayKeys[0] == 'Items' && isset($arrayKeys[1])) {
            $items = $this->getItems($arrayKeys[1]);
            $output = [];
            foreach ($items as $item) {
                $output[] = $item['Data'];
            }
            return implode('; ', $output);

        }

        if (!$fields) {
            $fields = $this->fields;
        }
        
        if (isset($fields[$arrayKeys[$level]])) {
            $newFields = $fields[$arrayKeys[$level]];
            $level++;
            if ($level < count($arrayKeys)) {
                return $this->getFieldRecursive($arrayKeys, $level, $newFields);
            } else {
                // end of recursion
                return $newFields;
            }
        }
        return '';
    }
    
    /**
     * Tries to get a piece of information from different locations, configured
     * in the prioritizedFields property. 
     * 
     * @param string $method array key from $this-prioritizedFields
     * 
     * @return string
     */
    protected function prioritizedFields($method)
    {
        $result = '';
        if (isset($this->prioritizedFields[$method])) {
            foreach ($this->prioritizedFields[$method] as $prio => $arrayKeys) {
                if (empty($result)) {
                    $result = $this->getFieldRecursive($arrayKeys);
                }
            }

        }
        return $result;
    }
    
    /**
     * Get title of containing record
     * 
     * @return string
     */
    public function getContainerTitle()
    {
        return $this->prioritizedFields('ContainerTitle');
    }
    /**
     * Get issue of containing record
     * 
     * @return string
     */
    public function getContainerIssue()
    {
        $numbering = $this->prioritizedFields('ContainerIssue');
        if (is_array($numbering)) {
            foreach ($numbering as $key => $data) {
                if (isset($data['Type']) && strtolower($data['Type']) == 'issue') {
                    return isset($data['Value']) ? $data['Value'] : '';
                }
            }
        }
        return '';
    }
    /**
     * Get volume of containing record
     * 
     * @return string
     */
    public function getContainerVolume()
    {
        $numbering = $this->prioritizedFields('ContainerVolume');
        if (is_array($numbering)) {
            foreach ($numbering as $key => $data) {
                if (isset($data['Type']) && strtolower($data['Type']) == 'volume') {
                    return isset($data['Value']) ? $data['Value'] : '';
                }
            }
        }
        return '';
    }
    
    /**
     * Get ISSNs (of containing record)
     * 
     * @return array
     */
    public function getISSNs()
    {
        $issns = parent::getIssns();
       
        $identifiers = $this->prioritizedFields('ISSNs');
        if (is_array($identifiers)) {
            
            foreach ($identifiers as $key => $data) {
                
                $type = strtolower($data['Type']);
                if (isset($data['Type']) && isset($data['Value'])
                    && ($type == 'issn-print' || $type == 'issn-electronic')
                ) {
                    $issns[] = $data['Value'];
                }
            }
        }
        return $issns;
    }
    
    /**
     * Get an array of ISBNs
     * 
     * @return array
     */
    public function getISBNs()
    {
        $isbns = parent::getISBNs();
       
        $identifiers = $this->prioritizedFields('ISBNs');
        if (is_array($identifiers)) {
            
            foreach ($identifiers as $key => $data) {
                
                $type = strtolower($data['Type']);
                if (isset($data['Type']) && isset($data['Value'])
                    && ($type == 'isbn-print' || $type == 'isbn-electronic')
                ) {
                    $issns[] = $data['Value'];
                }
            }
        }
        return $issns;
        
    }
        
    /**
     * Get year of containing record
     * 
     * @return string
     */
    public function getContainerYear()
    {
        return $this->prioritizedFields('ContainerYear');
    }
    
    /**
     * Get year of containing record
     * 
     * @return string
     */
    public function getContainerPages()
    {
        $pages = '';
        $pagination = $this->prioritizedFields('ContainerPages');
        if (isset($pagination['StartPage'])) {
            $pages = $pagination['StartPage'];
        }
        // TODO add end page parsing
        return $pages;
        
    }
    
    /**
     * Get record languages
     * 
     * @return array
     */
    public function getLanguages()
    {
        return explode(',', $this->prioritizedFields('Languages'));
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
        switch ($pubType) {
        case 'academic journal': $formats[] = 'Journal';
            break;
        case 'report': $formats[] = 'Report';
            break;
        case 'aook': $formats[] = 'Book';
            break;
        case 'article': $formats[] = 'Journal';
            break;
        case 'ebook': $formats[] = 'EBook';
            break;
        case 'periodical': $formats[] = 'Magazine';
            break;
        case 'dissertation/thesis': $formats[] = 'Thesis';
            break;
        default: $formats[] = 'Generic';
        }
        
        return $formats;
    }
    

}
