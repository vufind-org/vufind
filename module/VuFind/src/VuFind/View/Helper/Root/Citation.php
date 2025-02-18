<?php

/**
 * Citation view helper
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

use VuFind\Date\DateException;
use VuFind\I18n\Translator\TranslatorAwareInterface;

use function count;
use function function_exists;
use function in_array;
use function is_array;
use function sprintf;
use function strlen;

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
class Citation extends \Laminas\View\Helper\AbstractHelper implements TranslatorAwareInterface
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
     * List of words to never capitalize when using title case.
     *
     * Some words that were considered for this list, but excluded due to their
     * potential ambiguity: down, near, out, past, up
     *
     * Some words that were considered, but excluded because they were five or
     * more characters in length: about, above, across, after, against, along,
     * among, around, before, behind, below, beneath, beside, between, beyond,
     * despite, during, except,  inside, opposite, outside, round, since, through,
     * towards, under, underneath, unlike, until, within, without
     *
     * @var string[]
     */
    protected $uncappedWords = [
        'a', 'an', 'and', 'as', 'at', 'but', 'by', 'for', 'from', 'from', 'in',
        'into', 'like', 'nor', 'of', 'off', 'on', 'onto', 'or', 'over', 'so',
        'than', 'the', 'to', 'upon', 'via', 'with', 'yet',
    ];

    /**
     * List of multi-word phrases to never capitalize when using title case.
     *
     * @var string[]
     */
    protected $uncappedPhrases = [
        'even if', 'if only', 'now that', 'on top of',
    ];

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
        // Store the driver first, since we will need it for prepareAuthors checks.
        $this->driver = $driver;

        // Build author list:
        $authors = $this->prepareAuthors(
            (array)$driver->tryMethod('getPrimaryAuthors')
        );
        $corporateAuthors = [];
        if (empty($authors)) {
            // Corporate authors are more likely to have inappropriate trailing
            // punctuation; strip it off, unless the last word is short, like
            // "Co.", "Ltd.," etc.:
            $trimmer = function ($str) {
                return preg_match('/\s+.{1,3}\.$/', $str)
                    ? $str : rtrim($str, '.');
            };
            $corporateAuthors = $authors = $this->prepareAuthors(
                array_map(
                    $trimmer,
                    (array)$driver->tryMethod('getCorporateAuthors')
                ),
                true
            );
        }
        $secondary = $this->prepareAuthors(
            (array)$driver->tryMethod('getSecondaryAuthors')
        );
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
            [$title, $subtitle] = explode(':', $title, 2);
        }

        // Extract the additional details from the record driver:
        $publishers = $driver->tryMethod('getPublishers');
        $pubDates = $driver->tryMethod('getPublicationDates');
        $pubPlaces = $driver->tryMethod('getPlacesOfPublication');
        $edition = $driver->tryMethod('getEdition');

        // Store all the collected details:
        $this->details = [
            'authors' => $authors,
            'corporateAuthors' => $corporateAuthors,
            'title' => trim($title ?? ''),
            'subtitle' => trim($subtitle ?? ''),
            'pubPlace' => $pubPlaces[0] ?? null,
            'pubName' => $publishers[0] ?? null,
            'pubDate' => $pubDates[0] ?? null,
            'edition' => empty($edition) ? [] : [$edition],
            'journal' => $driver->tryMethod('getContainerTitle'),
        ];

        return $this;
    }

    /**
     * The code in this module expects authors in "Last Name, First Name" format.
     * This support method (used by the main citation() method) attempts to fix
     * any non-compliant names.
     *
     * @param array $authors     Authors to process.
     * @param bool  $isCorporate Is this a list of corporate authors?
     *
     * @return array
     */
    protected function prepareAuthors($authors, $isCorporate = false)
    {
        $callables = [];

        // If this data comes from a MARC record, we can probably assume that
        // anything without a comma is supposed to be formatted that way. We
        // also know if we have a valid corporate author name that it should be
        // left alone... otherwise, it's worth trying to reverse names (for example,
        // this may be dirty data from Summon):
        if (
            !($this->driver instanceof \VuFind\RecordDriver\SolrMarc)
            && !$isCorporate
        ) {
            $callables[] = function (string $name): string {
                $name = $this->cleanNameDates($name);
                if (!strstr($name, ',')) {
                    $parts = explode(' ', $name);
                    if (count($parts) > 1) {
                        $last = array_pop($parts);
                        $first = implode(' ', $parts);
                        return rtrim($last, '.') . ', ' . $first;
                    }
                }
                return $name;
            };
        }

        // We always want to apply these standard cleanup routines to non-corporate
        // authors:
        if (!$isCorporate) {
            $callables[] = function (string $name): string {
                // Eliminate parenthetical information:
                $strippedName = trim(preg_replace('/\s\(.*\)/', '', $name));

                // Split the text into words:
                $parts = explode(' ', empty($strippedName) ? $name : $strippedName);

                // If we have exactly two parts, we should trim any trailing
                // punctuation from the second part (this reduces the odds of
                // accidentally trimming a "Jr." or "Sr."):
                if (count($parts) == 2) {
                    $parts[1] = rtrim($parts[1], '.');
                }
                // Put the parts back together; eliminate stray commas:
                return rtrim(implode(' ', $parts), ',');
            };
        }

        // Now apply all of the functions we collected to all of the strings:
        return array_map(
            function (string $value) use ($callables): string {
                foreach ($callables as $current) {
                    $value = $current($value);
                }
                return $value;
            },
            $authors
        );
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
            'edition' => $this->getEdition(),
        ];

        // Show a period after the title if it does not already have punctuation
        // and is not followed by an edition statement:
        $apa['periodAfterTitle']
            = (!$this->isPunctuated($apa['title']) && empty($apa['edition']));
        if ($doi = $this->driver->tryMethod('getCleanDOI')) {
            $apa['doi'] = $doi;
        }

        $partial = $this->getView()->plugin('partial');
        // Behave differently for books vs. journals:
        if (empty($this->details['journal'])) {
            $apa['publisher'] = $this->getPublisher(false);
            $apa['year'] = $this->getYear();
            return $partial('Citation/apa.phtml', $apa);
        }

        // If we got this far, it's the default article case:
        [$apa['volume'], $apa['issue'], $apa['date']]
            = $this->getAPANumbersAndDate();
        $apa['journal'] = $this->details['journal'];
        $apa['pageRange'] = $this->getPageRange();
        return $partial('Citation/apa-article.phtml', $apa);
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
        return $this->getCitationMLA(
            9,
            ', no. ',
            ' ',
            '',
            ' (%s)',
            ':',
            true,
            'https://doi.org/',
            false,
            false
        );
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
     * @param string $numPrefix       String to display in front of numbering
     * @param string $volPrefix       String to display in front of volume
     * @param string $yearFormat      Format string for year display
     * @param string $pageNoSeparator Separator between date / page no.
     * @param bool   $includePubPlace Should we include the place of publication?
     * @param string $doiPrefix       Prefix to display in front of DOI; set to
     * false to omit DOIs.
     * @param bool   $labelPageRange  Should we include p./pp. before page ranges?
     * @param bool   $doiArticleComma Should we put a comma instead of period before
     * a DOI in an article-style citation?
     *
     * @return string
     */
    public function getCitationMLA(
        $etAlThreshold = 2,
        $volNumSeparator = ', no. ',
        $numPrefix = ', ',
        $volPrefix = 'vol. ',
        $yearFormat = ', %s',
        $pageNoSeparator = ',',
        $includePubPlace = false,
        $doiPrefix = 'https://doi.org/',
        $labelPageRange = true,
        $doiArticleComma = true
    ) {
        $mla = [
            'title' => $this->getMLATitle(),
            'authors' => $this->getMLAAuthors($etAlThreshold),
            'labelPageRange' => $labelPageRange,
            'pageNumberSeparator' => $pageNoSeparator,
        ];
        $mla['periodAfterTitle'] = !$this->isPunctuated($mla['title']);
        if ($doiPrefix && $doi = $this->driver->tryMethod('getCleanDOI')) {
            $mla['doi'] = $doi;
            $mla['doiPrefix'] = $doiPrefix;
        }

        // Behave differently for books vs. journals:
        $partial = $this->getView()->plugin('partial');
        if (empty($this->details['journal'])) {
            $mla['publisher'] = $this->getPublisher($includePubPlace);
            $mla['year'] = $this->getYear();
            $mla['edition'] = $this->getEdition();
            return $partial('Citation/mla.phtml', $mla);
        }
        // If we got this far, we should add other journal-specific details:
        $mla['doiArticleComma'] = $doiArticleComma;
        $mla['pageRange'] = $this->getPageRange();
        $mla['journal'] = $this->capitalizeTitle($this->details['journal']);
        $mla['numberAndDate'] = $numPrefix . $this->getMLANumberAndDate(
            $volNumSeparator,
            $volPrefix,
            $yearFormat
        );
        return $partial('Citation/mla-article.phtml', $mla);
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
     * @param string $volPrefix       String to display in front of volume
     * @param string $yearFormat      Format string for year display
     *
     * @return string
     */
    protected function getMLANumberAndDate(
        $volNumSeparator = '.',
        $volPrefix = '',
        $yearFormat = ', %s'
    ) {
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

        // If vol/num is set, we need to display one format...
        if (!empty($vol) || !empty($num)) {
            // If volume and number are both non-empty, separate them with a
            // period; otherwise just use the one that is set.
            $volNum = (!empty($vol) && !empty($num))
                ? $vol . $volNumSeparator . $num : $vol . $num;
            return (empty($vol) ? '' : $volPrefix)
                . $volNum . sprintf($yearFormat, $year);
        }
        // If we got this far, there's no vol/num, so we need to supply additional
        // date information...
        // Right now, we'll assume if day == 1, this is a monthly publication;
        // that's probably going to result in some bad citations, but it's the
        // best we can do without writing extra record driver methods.
        return (($day > 1) ? $day . ' ' : '')
            . (empty($month) ? '' : $month . ' ')
            . $year;
    }

    /**
     * Construct volume/issue/date portion of APA citation. Returns an array with
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
            if (empty($vol)) {
                $vol = $num;
                $num = '';
            }
            return [$vol, $num, $year];
        }
        // Right now, we'll assume if day == 1, this is a monthly publication;
        // that's probably going to result in some bad citations, but it's the
        // best we can do without writing extra record driver methods.
        $finalDate = $year
            . (empty($month) ? '' : ', ' . $month)
            . (($day > 1) ? ' ' . $day : '');
        return ['', '', $finalDate];
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
        $parts = explode(', ', $this->cleanNameDates($name));
        $name = $parts[0];

        // Attach initials...
        if (isset($parts[1])) {
            $fnameParts = explode(' ', $parts[1]);
            for ($i = 0; $i < count($fnameParts); $i++) {
                // Use the multi-byte substring function if available to avoid
                // problems with accented characters:
                $fnameParts[$i] = function_exists('mb_substr')
                    ? mb_substr($fnameParts[$i], 0, 1, 'utf8') . '.'
                    : substr($fnameParts[$i], 0, 1) . '.';
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
        if (
            strlen($str) == 1
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
        // For good measure, strip out any remaining date ranges lurking in
        // non-standard places.
        return preg_replace(
            '/\s+(\d{4}\-\d{4}|b\. \d{4}|\d{4}-)[,.]*$/',
            '',
            $name
        );
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
        $words = explode(' ', $str);
        $newwords = [];
        $followsColon = false;
        foreach ($words as $word) {
            // Capitalize words unless they are in the exception list... but even
            // exceptional words get capitalized if they follow a colon. Note that
            // we need to strip non-word characters (like punctuation) off of words
            // in order to reliably look them up in the uncappedWords list.
            $baseWord = preg_replace('/\W/', '', $word);
            if (!in_array($baseWord, $this->uncappedWords) || $followsColon) {
                // Includes special case to properly capitalize words in quotes:
                $firstChar = substr($word, 0, 1);
                $word = in_array($firstChar, ['"', "'"])
                    ? $firstChar . ucfirst(substr($word, 1))
                    : ucfirst($word);
            }
            array_push($newwords, $word);

            $followsColon = str_ends_with($word, ':');
        }

        // We've dealt with capitalization of words; now we need to deal with
        // multi-word phrases:
        $adjustedTitle = ucfirst(implode(' ', $newwords));
        foreach ($this->uncappedPhrases as $phrase) {
            // We need to cover two cases: the phrase at the start of a title,
            // and the phrase in the middle of a title:
            $adjustedTitle = preg_replace(
                '/^' . $phrase . '\b/i',
                strtoupper(substr($phrase, 0, 1)) . substr($phrase, 1),
                $adjustedTitle
            );
            $adjustedTitle = preg_replace(
                '/(.+)\b' . $phrase . '\b/i',
                '$1' . $phrase,
                $adjustedTitle
            );
        }
        return $adjustedTitle;
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
        if (
            isset($this->details['authors'])
            && is_array($this->details['authors'])
        ) {
            $i = 0;
            $ellipsis = false;
            $authorCount = count($this->details['authors']);
            foreach ($this->details['authors'] as $author) {
                // Do not abbreviate corporate authors:
                $author = in_array($author, $this->details['corporateAuthors'])
                    ? $author : $this->abbreviateName($author);
                if (($i + 1 == $authorCount) && ($i > 0)) { // Last
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
                } elseif ($authorCount > 1) {
                    // If this is the second-to-last author, and we have not found
                    // any commas yet, we can skip the comma. Otherwise, add one to
                    // the list. Useful for two-item lists including corporate
                    // authors as the first entry.
                    $skipComma = ($i + 2 == $authorCount)
                        && (!str_contains($authorStr . $author, ','));
                    $authorStr .= $author . ($skipComma ? ' ' : ', ');
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
        if (
            isset($this->details['edition'])
            && is_array($this->details['edition'])
        ) {
            foreach ($this->details['edition'] as $edition) {
                // Strip punctuation from the edition to get rid of unwanted
                // junk... but if there is nothing left after stripping, put
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
     * Format an author name for inclusion as the first name in an MLA citation.
     *
     * @param string $author Name to reformat.
     *
     * @return string
     */
    protected function formatPrimaryMLAAuthor($author)
    {
        // Corporate authors should not be reformatted:
        return in_array($author, $this->details['corporateAuthors'])
            ? $author : $this->cleanNameDates($author);
    }

    /**
     * Format an author name for inclusion in an MLA citation (after the primary
     * name, which gets formatted differently).
     *
     * @param string $author Name to reformat.
     *
     * @return string
     */
    protected function formatSecondaryMLAAuthor($author)
    {
        // If there is no comma in the name, we don't need to reverse it and
        // should leave its punctuation alone (since it was adjusted earlier).
        return !str_contains($author, ',')
            ? $author : $this->reverseName($this->stripPunctuation($author));
    }

    /**
     * Get an array of authors for an MLA or Chicago Style citation.
     *
     * @param int $etAlThreshold The number of authors to abbreviate with 'et al.'
     * This is a major difference between MLA/Chicago Style.
     *
     * @return array
     */
    protected function getMLAAuthors($etAlThreshold = 2)
    {
        $authorStr = '';
        if (
            isset($this->details['authors'])
            && is_array($this->details['authors'])
        ) {
            $i = 0;
            if (count($this->details['authors']) > $etAlThreshold) {
                $author = $this->details['authors'][0];
                $authorStr = $this->formatPrimaryMLAAuthor($author) . ', et al.';
            } else {
                foreach ($this->details['authors'] as $rawAuthor) {
                    $author = $this->formatPrimaryMLAAuthor($rawAuthor);
                    if (($i + 1 == count($this->details['authors'])) && ($i > 0)) {
                        // Last
                        // Only add a comma if there are commas already in the
                        // preceding text. This helps, for example, with cases where
                        // the first author is a corporate author.
                        $finalJoin = str_contains($authorStr, ',') ? ', ' : ' ';
                        $authorStr .= $finalJoin . $this->translate('and') . ' '
                            . $this->formatSecondaryMLAAuthor($author);
                    } elseif ($i > 0) {
                        $authorStr .= ', '
                            . $this->formatSecondaryMLAAuthor($author);
                    } else {
                        // First
                        $authorStr .= $author;
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
     * @param bool $includePubPlace Should we include the place of publication?
     *
     * @return string
     */
    protected function getPublisher($includePubPlace = true)
    {
        $parts = [];
        if (
            $includePubPlace && !empty($this->details['pubPlace'])
        ) {
            $parts[] = $this->stripPunctuation($this->details['pubPlace']);
        }
        if (
            !empty($this->details['pubName'])
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
            $numericDate = preg_replace('/\D/', '', $this->details['pubDate']);
            if (strlen($numericDate) > 4) {
                try {
                    return $this->dateConverter->convertFromDisplayDate(
                        'Y',
                        $this->details['pubDate']
                    );
                } catch (\Exception $e) {
                    // Ignore date errors -- no point in dying here:
                    return false;
                }
            }
            return $numericDate;
        }
        return false;
    }
}
