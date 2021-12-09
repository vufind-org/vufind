<?php
/**
 * Citation view helper
 *
 * PHP version 7
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
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\View\Helper\Root;

use Seboettg\CiteProc\CiteProc;
use Seboettg\CiteProc\StyleSheet;
use VuFind\Date\DateException;
use VuFind\I18n\Translator\TranslatorAwareInterface;

/**
 * Citation view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Citation extends \Laminas\View\Helper\AbstractHelper
    implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Citation details
     *
     * @var array
     */
    protected $details = [];

    /**
     * Record driver
     *
     * @var \VuFind\RecordDriver\AbstractBase
     */
    protected $driver;

    /**
     * Date converter
     *
     * @var \VuFind\Date\Converter
     */
    protected $dateConverter;

    /**
     * Constructor
     *
     * @param \VuFind\Date\Converter $converter Date converter
     */
    public function __construct(\VuFind\Date\Converter $converter)
    {
        $this->dateConverter = $converter;
    }

    /**
     * Store a record driver object and return this object so that the appropriate
     * template can be rendered.
     *
     * @param \VuFind\RecordDriver\Base $driver Record driver object.
     *
     * @return Citation
     */
    public function __invoke($driver)
    {
        // Build author list:
        $authors = (array)$driver->tryMethod('getPrimaryAuthors');
        if (empty($authors)) {
            $authors = (array)$driver->tryMethod('getCorporateAuthors');
        }
        $secondary = (array)$driver->tryMethod('getSecondaryAuthors');
        if (!empty($secondary)) {
            $authors = array_unique(array_merge($authors, $secondary));
        }

        // Get best available title details:
        $title = $driver->tryMethod('getShortTitle');
        $subtitle = $driver->tryMethod('getSubtitle');
        if (empty($title)) {
            $title = $driver->tryMethod('getTitle');
        }
        if (empty($title)) {
            $title = $driver->getBreadcrumb();
        }
        // Find subtitle in title if they're not separated:
        if (empty($subtitle) && strstr($title, ':')) {
            list($title, $subtitle) = explode(':', $title, 2);
        }

        // Extract the additional details from the record driver:
        $publishers = $driver->tryMethod('getPublishers');
        $pubDates = $driver->tryMethod('getPublicationDates');
        $pubPlaces = $driver->tryMethod('getPlacesOfPublication');
        $edition = $driver->tryMethod('getEdition');

        // Store everything:
        $this->driver = $driver;
        $this->details = [
            'authors' => $this->prepareAuthors($authors),
            'title' => trim($title), 'subtitle' => trim($subtitle),
            'pubPlace' => $pubPlaces[0] ?? null,
            'pubName' => $publishers[0] ?? null,
            'pubDate' => $pubDates[0] ?? null,
            'edition' => empty($edition) ? [] : [$edition],
            'journal' => $driver->tryMethod('getContainerTitle')
        ];

        return $this;
    }

    /**
     * The code in this module expects authors in "Last Name, First Name" format.
     * This support method (used by the main citation() method) attempts to fix
     * any non-compliant names.
     *
     * @param array $authors Authors to process.
     *
     * @return array
     */
    protected function prepareAuthors($authors)
    {
        // If this data comes from a MARC record, we can probably assume that
        // anything without a comma is a valid corporate author that should be
        // left alone...
        if (is_a($this->driver, 'VuFind\RecordDriver\SolrMarc')) {
            return $authors;
        }

        // If we got this far, it's worth trying to reverse names (for example,
        // this may be dirty data from Summon):
        $processed = [];
        foreach ($authors as $name) {
            if (!strstr($name, ',')) {
                $parts = explode(' ', $name);
                if (count($parts) > 1) {
                    $last = array_pop($parts);
                    $first = implode(' ', $parts);
                    $name = $last . ', ' . $first;
                }
            }
            $processed[] = $name;
        }
        return $processed;
    }

    /**
     * Retrieve a citation in a particular format
     *
     * Returns the citation in the format specified
     *
     * @param string $format Citation format ('APA' or 'MLA')
     *
     * @return string        Formatted citation
     */
    public function getCitation($format)
    {
        // Construct method name for requested format:
        $method = 'getCitation' . $format;

        // Avoid calls to inappropriate/missing methods:
        if (!empty($format) && method_exists($this, $method)) {
            return $this->$method();
        }

        // Return blank string if no valid method found:
        return '';
    }

    protected function trimPunctuation($text)
    {
        return trim($text, " \n\r\t\v\0,:;/");
    }

    protected function removeDateRange($name)
    {
        return preg_replace('/\d+[ ]*\-[ ]*\d*/', '', $name);
    }

    protected function nameToGivenFamily($name)
    {
        if (strpos($name, ', ') !== false) {
            [$family, $given] = explode(', ', $this->removeDateRange($name));

            return [
                'given' => $this->trimPunctuation($given),
                'family' => $this->trimPunctuation($family),
            ];
        }

        $parts = explode(' ', $this->removeDateRange($name));

        $family = array_pop($parts);
        $given = implode(' ', $parts);

        return [
            'given' => $this->trimPunctuation($given),
            'family' => $this->trimPunctuation($family),
        ];
    }

    protected function addIfNotEmpty(&$item, $pairs)
    {
        foreach ($pairs as $key => $value) {
            if (!empty($value)) {
                $item[$key] = $this->trimPunctuation(((array) $value)[0]);
            }
        }
    }

    /**
     * Map data about the current record to the CSL JSON schema defined here:
     * https://github.com/citation-style-language/schema/blob/master/csl-data.json
     *
     * @return string
     */
    public function getDataCSL()
    {
        // id
        $item = ['id' => $this->driver->getUniqueID()];

        // type
        switch ($this->driver->getFormats()[0]) {
        case 'Thesis':
            $item['type'] = 'thesis';
            break;
        case 'Video':
            $item['type'] = 'motion_picture';
            break;
        case 'Score':
            $item['type'] = 'musical_score';
            break;
        case 'Map':
            $item['type'] = 'map';
            break;
        case 'Book':
        default:
            $item['type'] = 'book';
        }

        // title
        $this->addIfNotEmpty(
            $item,
            [
                'title' => $this->driver->tryMethod('getTitle'),
                'title-short' => $this->driver->tryMethod('getShortTitle'),
            ]
        );

        // meta
        $this->addIfNotEmpty(
            $item,
            [
                'call-number' => $this->driver->getCallNumbers(),
                'edition' => $this->details['edition'],
                'ISBN' => $this->driver->getISBNs(),
                'language' => $this->driver->getLanguages(),
                'publisher' => $this->driver->getPublishers(),
                'publisher-place' => $this->driver->getPlacesOfPublication(),
            ]
        );

        // journal meta
        $this->addIfNotEmpty(
            $item,
            [
                'ISSN' => $this->driver->getISSNs(),
                'volume' => $this->driver->getContainerIssue(),
                'volume-title' => $this->driver->getContainerVolume(),
                // TODO: journalAbbreviation
            ]
        );
        $pageFirst = $this->driver->tryMethod('getContainerStartPage');
        $pageLast = $this->driver->tryMethod('getContainerEndPage');
        if (!empty($pageFirst)) {
            if (!empty($pageLast)) {
                $item['page-first'] = $pageFirst;
                $item['number-of-pages'] = $pageLast - $pageFirst;
            } else {
                $item['page'] = $pageFirst;
            }
        }

        // pubDate -> issued (date)
        if (!empty($this->details['pubDate'])) {
            $item['issued'] = ['date-parts' => [[$this->getYear()]]];
        }

        // today -> accessed (date)
        $item['accessed'] = ['raw' => date('Y-m-d\TH:i:s')];

        // authors
        if (!empty($this->details['authors'])) {
            foreach ($this->details['authors'] as $i => $author) {
                $item['author'][] = array_merge(
                    ['literal' => $author],
                    $this->nameToGivenFamily($author)
                );
            }
        }

        // TODO: editors
        // var_dump($this->driver->getProductionCredits());

        // TODO: directors

        // URL
        if (!empty($this->driver->getURLs())) {
            $item['URL'] = $this->driver->getURLs()[0]['url'];
        }

        return json_encode([$item]);
    }

    /**
     * Get APA citation.
     *
     * This function assigns all the necessary variables and then returns an APA
     * citation.
     *
     * @return string
     */
    public function getCitationAPA()
    {
        $apa = [
            'title' => $this->getAPATitle(),
            'authors' => $this->getAPAAuthors(),
            'edition' => $this->getEdition()
        ];
        // Show a period after the title if it does not already have punctuation
        // and is not followed by an edition statement:
        $apa['periodAfterTitle']
            = (!$this->isPunctuated($apa['title']) && empty($apa['edition']));

        // Behave differently for books vs. journals:
        $partial = $this->getView()->plugin('partial');
        if (empty($this->details['journal'])) {
            $apa['publisher'] = $this->getPublisher();
            $apa['year'] = $this->getYear();
            return $partial('Citation/apa.phtml', $apa);
        } else {
            list($apa['volume'], $apa['issue'], $apa['date'])
                = $this->getAPANumbersAndDate();
            $apa['journal'] = $this->details['journal'];
            $apa['pageRange'] = $this->getPageRange();
            if ($doi = $this->driver->tryMethod('getCleanDOI')) {
                $apa['doi'] = $doi;
            }
            return $partial('Citation/apa-article.phtml', $apa);
        }
    }

    /**
     * Get Chicago Style citation.
     *
     * This function returns a Chicago Style citation using a modified version
     * of the MLA logic.
     *
     * @return string
     */
    public function getCitationChicago()
    {
        return $this->getCitationMLA(9, ', no. ');
    }

    /**
     * Get MLA citation.
     *
     * This function assigns all the necessary variables and then returns an MLA
     * citation. By adjusting the parameters below, it can also render a Chicago
     * Style citation.
     *
     * @param int    $etAlThreshold   The number of authors to abbreviate with 'et
     * al.'
     * @param string $volNumSeparator String to separate volume and issue number
     * in citation.
     *
     * @return string
     */
    public function getCitationMLA($etAlThreshold = 4, $volNumSeparator = '.')
    {
        $mla = [
            'title' => $this->getMLATitle(),
            'authors' => $this->getMLAAuthors($etAlThreshold)
        ];
        $mla['periodAfterTitle'] = !$this->isPunctuated($mla['title']);

        // Behave differently for books vs. journals:
        $partial = $this->getView()->plugin('partial');
        if (empty($this->details['journal'])) {
            $mla['publisher'] = $this->getPublisher();
            $mla['year'] = $this->getYear();
            $mla['edition'] = $this->getEdition();
            return $partial('Citation/mla.phtml', $mla);
        } else {
            // Add other journal-specific details:
            $mla['pageRange'] = $this->getPageRange();
            $mla['journal'] = $this->capitalizeTitle($this->details['journal']);
            $mla['numberAndDate'] = $this->getMLANumberAndDate($volNumSeparator);
            return $partial('Citation/mla-article.phtml', $mla);
        }
    }

    /**
     * Get Vancouver citation.
     *
     * @return string
     */
    public function getCitationVancouver()
    {
        $data = $this->getDataCSL();

        $locale = $this->getView()->layout()->userLang;

        echo '<ul>';

        $processor = new CiteProc(StyleSheet::loadStyleSheet('apa'), $locale);
        echo '<li>' . $processor->render(json_decode($data), 'bibliography') . '</li>';

        $processor = new CiteProc(StyleSheet::loadStyleSheet('chicago-annotated-bibliography'), $locale);
        echo '<li>' . $processor->render(json_decode($data), 'bibliography') . '</li>';

        $processor = new CiteProc(StyleSheet::loadStyleSheet('modern-language-association'), $locale);
        echo '<li>' . $processor->render(json_decode($data), 'bibliography') . '</li>';

        echo '</ul>';

        $processor = new CiteProc(StyleSheet::loadStyleSheet('vancouver'), $locale);
        return $processor->render(json_decode($data), 'bibliography');
    }

    /**
     * Construct page range portion of citation.
     *
     * @return string
     */
    protected function getPageRange()
    {
        $start = $this->driver->tryMethod('getContainerStartPage');
        $end = $this->driver->tryMethod('getContainerEndPage');
        return ($start == $end || empty($end))
            ? $start : $start . '-' . $end;
    }

    /**
     * Construct volume/issue/date portion of MLA or Chicago Style citation.
     *
     * @param string $volNumSeparator String to separate volume and issue number
     * in citation (only difference between MLA/Chicago Style).
     *
     * @return string
     */
    protected function getMLANumberAndDate($volNumSeparator = '.')
    {
        $vol = $this->driver->tryMethod('getContainerVolume');
        $num = $this->driver->tryMethod('getContainerIssue');
        $date = $this->details['pubDate'];
        if (strlen($date) > 4) {
            try {
                $year = $this->dateConverter->convertFromDisplayDate('Y', $date);
                $month = $this->dateConverter->convertFromDisplayDate('M', $date)
                    . '.';
                $day = $this->dateConverter->convertFromDisplayDate('j', $date);
            } catch (DateException $e) {
                // If conversion fails, use raw date as year -- not ideal,
                // but probably better than nothing:
                $year = $date;
                $month = $day = '';
            }
        } else {
            $year = $date;
            $month = $day = '';
        }

        // We need to supply additional date information if no vol/num:
        if (!empty($vol) || !empty($num)) {
            // If volume and number are both non-empty, separate them with a
            // period; otherwise just use the one that is set.
            $volNum = (!empty($vol) && !empty($num))
                ? $vol . $volNumSeparator . $num : $vol . $num;
            return $volNum . ' (' . $year . ')';
        } else {
            // Right now, we'll assume if day == 1, this is a monthly publication;
            // that's probably going to result in some bad citations, but it's the
            // best we can do without writing extra record driver methods.
            return (($day > 1) ? $day . ' ' : '')
                . (empty($month) ? '' : $month . ' ')
                . $year;
        }
    }

    /**
     * Construct volume/issue/date portion of APA citation.  Returns an array with
     * three elements: volume, issue and date (since these end up in different areas
     * of the final citation, we don't return a single string, but since their
     * determination is related, we need to do the work in a single function).
     *
     * @return array
     */
    protected function getAPANumbersAndDate()
    {
        $vol = $this->driver->tryMethod('getContainerVolume');
        $num = $this->driver->tryMethod('getContainerIssue');
        $date = $this->details['pubDate'];
        if (strlen($date) > 4) {
            try {
                $year = $this->dateConverter->convertFromDisplayDate('Y', $date);
                $month = $this->dateConverter->convertFromDisplayDate('F', $date);
                $day = $this->dateConverter->convertFromDisplayDate('j', $date);
            } catch (DateException $e) {
                // If conversion fails, use raw date as year -- not ideal,
                // but probably better than nothing:
                $year = $date;
                $month = $day = '';
            }
        } else {
            $year = $date;
            $month = $day = '';
        }

        // We need to supply additional date information if no vol/num:
        if (!empty($vol) || !empty($num)) {
            // If only the number is non-empty, move the value to the volume to
            // simplify template behavior:
            if (empty($vol) && !empty($num)) {
                $vol = $num;
                $num = '';
            }
            return [$vol, $num, $year];
        } else {
            // Right now, we'll assume if day == 1, this is a monthly publication;
            // that's probably going to result in some bad citations, but it's the
            // best we can do without writing extra record driver methods.
            $finalDate = $year
                . (empty($month) ? '' : ', ' . $month)
                . (($day > 1) ? ' ' . $day : '');
            return ['', '', $finalDate];
        }
    }

    /**
     * Is the string a valid name suffix?
     *
     * @param string $str The string to check.
     *
     * @return bool       True if it's a name suffix.
     */
    protected function isNameSuffix($str)
    {
        $str = $this->stripPunctuation($str);

        // Is it a standard suffix?
        $suffixes = ['Jr', 'Sr'];
        if (in_array($str, $suffixes)) {
            return true;
        }

        // Is it a roman numeral?  (This check could be smarter, but it's probably
        // good enough as it is).
        if (preg_match('/^[MDCLXVI]+$/', $str)) {
            return true;
        }

        // If we got this far, it's not a suffix.
        return false;
    }

    /**
     * Is the string a date range?
     *
     * @param string $str The string to check.
     *
     * @return bool       True if it's a date range.
     */
    protected function isDateRange($str)
    {
        $str = trim($str);
        return preg_match('/^([0-9]+)-([0-9]*)\.?$/', $str);
    }

    /**
     * Abbreviate a first name.
     *
     * @param string $name The name to abbreviate
     *
     * @return string      The abbreviated name.
     */
    protected function abbreviateName($name)
    {
        $parts = explode(', ', $name);
        $name = $parts[0];

        // Attach initials... but if we encountered a date range, the name
        // ended earlier than expected, and we should stop now.
        if (isset($parts[1]) && !$this->isDateRange($parts[1])) {
            $fnameParts = explode(' ', $parts[1]);
            for ($i = 0; $i < count($fnameParts); $i++) {
                // Use the multi-byte substring function if available to avoid
                // problems with accented characters:
                if (function_exists('mb_substr')) {
                    $fnameParts[$i] = mb_substr($fnameParts[$i], 0, 1, 'utf8') . '.';
                } else {
                    $fnameParts[$i] = substr($fnameParts[$i], 0, 1) . '.';
                }
            }
            $name .= ', ' . implode(' ', $fnameParts);
            if (isset($parts[2]) && $this->isNameSuffix($parts[2])) {
                $name = trim($name) . ', ' . $parts[2];
            }
        }

        return trim($name);
    }

    /**
     * Fix bad punctuation on abbreviated name letters.
     *
     * @param string $str String to fix.
     *
     * @return string
     */
    protected function fixAbbreviatedNameLetters($str)
    {
        // Fix abbreviated letters.
        if (strlen($str) == 1
            || preg_match('/\s[a-zA-Z]/', substr($str, -2))
        ) {
            return $str . '.';
        }
        return $str;
    }

    /**
     * Strip the dates off the end of a name.
     *
     * @param string $str Name to clean.
     *
     * @return string     Cleaned name.
     */
    protected function cleanNameDates($str)
    {
        $arr = explode(', ', $str);
        $name = $arr[0];
        if (isset($arr[1]) && !$this->isDateRange($arr[1])) {
            $name .= ', ' . $this->fixAbbreviatedNameLetters($arr[1]);
            if (isset($arr[2]) && $this->isNameSuffix($arr[2])) {
                $name .= ', ' . $arr[2];
            }
        }
        return $name;
    }

    /**
     * Does the string end in punctuation that we want to retain?
     *
     * @param string $string String to test.
     *
     * @return bool          Does string end in punctuation?
     */
    protected function isPunctuated($string)
    {
        $punctuation = ['.', '?', '!'];
        return in_array(substr($string, -1), $punctuation);
    }

    /**
     * Strip unwanted punctuation from the right side of a string.
     *
     * @param string $text Text to clean up.
     *
     * @return string      Cleaned up text.
     */
    protected function stripPunctuation($text)
    {
        $punctuation = ['.', ',', ':', ';', '/'];
        $text = trim($text);
        if (in_array(substr($text, -1), $punctuation)) {
            $text = substr($text, 0, -1);
        }
        return trim($text);
    }

    /**
     * Turn a "Last, First" name into a "First Last" name.
     *
     * @param string $str Name to reverse.
     *
     * @return string     Reversed name.
     */
    protected function reverseName($str)
    {
        $arr = explode(', ', $str);

        // If the second chunk is a date range, there is nothing to reverse!
        if (!isset($arr[1]) || $this->isDateRange($arr[1])) {
            return $arr[0];
        }

        $name = $this->fixAbbreviatedNameLetters($arr[1]) . ' ' . $arr[0];
        if (isset($arr[2]) && $this->isNameSuffix($arr[2])) {
            $name .= ', ' . $arr[2];
        }
        return $name;
    }

    /**
     * Capitalize all words in a title, except for a few common exceptions.
     *
     * @param string $str Title to capitalize.
     *
     * @return string     Capitalized title.
     */
    protected function capitalizeTitle($str)
    {
        $exceptions = ['a', 'an', 'the', 'against', 'between', 'in', 'of',
            'to', 'and', 'but', 'for', 'nor', 'or', 'so', 'yet', 'to'];

        $words = explode(' ', $str);
        $newwords = [];
        $followsColon = false;
        foreach ($words as $word) {
            // Capitalize words unless they are in the exception list...  but even
            // exceptional words get capitalized if they follow a colon.
            if (!in_array($word, $exceptions) || $followsColon) {
                $word = ucfirst($word);
            }
            array_push($newwords, $word);

            $followsColon = substr($word, -1) == ':';
        }

        return ucfirst(join(' ', $newwords));
    }

    /**
     * Get the full title for an APA citation.
     *
     * @return string
     */
    protected function getAPATitle()
    {
        // Create Title
        $title = $this->stripPunctuation($this->details['title']);
        if (isset($this->details['subtitle'])) {
            $subtitle = $this->stripPunctuation($this->details['subtitle']);
            // Capitalize subtitle and apply it, assuming it really exists:
            if (!empty($subtitle)) {
                $subtitle
                    = strtoupper(substr($subtitle, 0, 1)) . substr($subtitle, 1);
                $title .= ': ' . $subtitle;
            }
        }

        return $title;
    }

    /**
     * Get an array of authors for an APA citation.
     *
     * @return array
     */
    protected function getAPAAuthors()
    {
        $authorStr = '';
        if (isset($this->details['authors'])
            && is_array($this->details['authors'])
        ) {
            $i = 0;
            $ellipsis = false;
            foreach ($this->details['authors'] as $author) {
                $author = $this->abbreviateName($author);
                if (($i + 1 == count($this->details['authors']))
                    && ($i > 0)
                ) { // Last
                    // Do we already have periods of ellipsis?  If not, we need
                    // an ampersand:
                    $authorStr .= $ellipsis ? ' ' : '& ';
                    $authorStr .= $this->stripPunctuation($author) . '.';
                } elseif ($i > 5) {
                    // If we have more than seven authors, we need to skip some:
                    if (!$ellipsis) {
                        $authorStr .= '. . .';
                        $ellipsis = true;
                    }
                } elseif (count($this->details['authors']) > 1) {
                    $authorStr .= $author . ', ';
                } else { // First and only
                    $authorStr .= $this->stripPunctuation($author) . '.';
                }
                $i++;
            }
        }
        return empty($authorStr) ? false : $authorStr;
    }

    /**
     * Get edition statement for inclusion in a citation.  Shared by APA and
     * MLA functionality.
     *
     * @return string
     */
    protected function getEdition()
    {
        // Find the first edition statement that isn't "1st ed."
        if (isset($this->details['edition'])
            && is_array($this->details['edition'])
        ) {
            foreach ($this->details['edition'] as $edition) {
                // Strip punctuation from the edition to get rid of unwanted
                // junk...  but if there is nothing left after stripping, put
                // back at least one period!
                $edition = $this->stripPunctuation($edition);
                if (empty($edition)) {
                    continue;
                }
                if (!$this->isPunctuated($edition)) {
                    $edition .= '.';
                }
                if ($edition !== '1st ed.') {
                    return $edition;
                }
            }
        }

        // No edition statement found:
        return false;
    }

    /**
     * Get the full title for an MLA citation.
     *
     * @return string
     */
    protected function getMLATitle()
    {
        // MLA titles are just like APA titles, only capitalized differently:
        return $this->capitalizeTitle($this->getAPATitle());
    }

    /**
     * Get an array of authors for an MLA or Chicago Style citation.
     *
     * @param int $etAlThreshold The number of authors to abbreviate with 'et al.'
     * This is the only difference between MLA/Chicago Style.
     *
     * @return array
     */
    protected function getMLAAuthors($etAlThreshold = 4)
    {
        $authorStr = '';
        if (isset($this->details['authors'])
            && is_array($this->details['authors'])
        ) {
            $i = 0;
            if (count($this->details['authors']) > $etAlThreshold) {
                $author = $this->details['authors'][0];
                $authorStr = $this->cleanNameDates($author) . ', et al.';
            } else {
                foreach ($this->details['authors'] as $author) {
                    if (($i + 1 == count($this->details['authors'])) && ($i > 0)) {
                        // Last
                        $authorStr .= ', ' . $this->translate('and') . ' ' .
                            $this->reverseName($this->stripPunctuation($author));
                    } elseif ($i > 0) {
                        $authorStr .= ', ' .
                            $this->reverseName($this->stripPunctuation($author));
                    } else {
                        // First
                        $authorStr .= $this->cleanNameDates($author);
                    }
                    $i++;
                }
            }
        }
        return empty($authorStr) ? false : $this->stripPunctuation($authorStr);
    }

    /**
     * Get publisher information (place: name) for inclusion in a citation.
     * Shared by APA and MLA functionality.
     *
     * @return string
     */
    protected function getPublisher()
    {
        $parts = [];
        if (isset($this->details['pubPlace'])
            && !empty($this->details['pubPlace'])
        ) {
            $parts[] = $this->stripPunctuation($this->details['pubPlace']);
        }
        if (isset($this->details['pubName'])
            && !empty($this->details['pubName'])
        ) {
            $parts[] = $this->details['pubName'];
        }
        if (empty($parts)) {
            return false;
        }
        return $this->stripPunctuation(implode(': ', $parts));
    }

    /**
     * Get the year of publication for inclusion in a citation.
     * Shared by APA and MLA functionality.
     *
     * @return string
     */
    protected function getYear()
    {
        if (isset($this->details['pubDate'])) {
            if (strlen($this->details['pubDate']) > 4) {
                try {
                    return $this->dateConverter->convertFromDisplayDate(
                        'Y', $this->details['pubDate']
                    );
                } catch (\Exception $e) {
                    // Ignore date errors -- no point in dying here:
                    return false;
                }
            }
            return preg_replace('/[^0-9]/', '', $this->details['pubDate']);
        }
        return false;
    }
}
