<?php

/**
 * A single/simple query.
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
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
namespace VuFindSearch\Query;

/**
 * A single/simple query.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class Query extends AbstractQuery
{
    /**
     * Name of query handler, if any.
     *
     * @var string
     */
    protected $queryHandler;

    /**
     * Query string
     *
     * @var string
     */
    protected $queryString;

    /**
     * Operator to apply to query string (null if not applicable)
     *
     * @var string
     */
    protected $operator;

    /**
     * Constructor.
     *
     * @param string $string   Search string
     * @param string $handler  Name of search handler
     * @param string $operator Operator to apply to query string (null if n/a)
     */
    public function __construct($string = null, $handler = null, $operator = null)
    {
        $this->queryHandler = $handler ? $handler : null;
        $this->queryString  = $string;
        $this->operator = $operator;
    }

    /**
     * Return search string.
     *
     * @return string
     */
    public function getString()
    {
        return $this->queryString;
    }

    /**
     * Return search string without accents and umlauts
     *
     * @return string
     */
    public function getNormalizedString()
    {
        return strtolower($this->stripDiacritics($this->queryString));
    }

    /**
     * Set the search string.
     *
     * @param string $string New search string
     *
     * @return void
     */
    public function setString($string)
    {
        $this->queryString = $string;
    }

    /**
     * Return name of search handler.
     *
     * @return string
     */
    public function getHandler()
    {
        return $this->queryHandler;
    }

    /**
     * Set name of search handler.
     *
     * @param string $handler Name of handler
     *
     * @return string
     */
    public function setHandler($handler)
    {
        $this->queryHandler = $handler;
    }

    /**
     * Return operator (null if n/a).
     *
     * @return string
     */
    public function getOperator()
    {
        return $this->operator;
    }

    /**
     * Set operator (null if n/a).
     *
     * @param string $operator Operator
     *
     * @return string
     */
    public function setOperator($operator)
    {
        $this->operator = $operator;
    }

    /**
     * Does the query contain the specified term?
     *
     * @param string $needle Term to check
     *
     * @return bool
     */
    public function containsTerm($needle)
    {
        // Escape characters with special meaning in regular expressions to avoid
        // errors:
        $needle = preg_quote($needle, '/');

        return (bool)preg_match("/\b$needle\b/u", $this->getString());
    }

    /**
     * Does the query contain the specified term?
     * Accents and umlauts are removed from both
     * $needle and query
     *
     * @param string $needle Term to check
     *
     * @return bool
     */
    public function containsNormalizedTerm($needle)
    {
        // Escape characters with special meaning in regular expressions to avoid
        // errors:
        $needle = preg_quote($needle, '/');

        $needle = strtolower($this->stripDiacritics($needle));

        return (bool)preg_match(
            "/\b$needle\b/u",
            strtolower($this->stripDiacritics($this->getString()))
        );
    }

    /**
     * Get a concatenated list of all query strings within the object.
     *
     * @return string
     */
    public function getAllTerms()
    {
        return $this->getString();
    }

    /**
     * Replace a term.
     *
     * @param string  $from             Search term to find
     * @param string  $to               Search term to insert
     * @param boolean $ignoreCase       If we should ignore the case differences
     *                                  when replacing
     * @param boolean $ignoreDiacritics If we should ignore the diacritics when
     *                                  replacing, i.e. if $from is durenmatt,
     *                                  it could replace dürenmatt in the query
     *
     * @return void
     */
    public function replaceTerm(
        $from,
        $to,
        $ignoreCase = false,
        $ignoreDiacritics = false
    ) {
        // Escape $from so it is regular expression safe (just in case it
        // includes any weird punctuation -- unlikely but possible):
        $from = preg_quote($from, '/');
        $queryString = $this->queryString;

        if ($ignoreCase) {
            $from = strtolower($from);
            $queryString = strtolower($queryString);
        }

        if ($ignoreDiacritics) {
            $from = $this->stripDiacritics($from);
            $queryString = $this->stripDiacritics($queryString);
        }

        // If our "from" pattern contains non-word characters, we can't use word
        // boundaries for matching.  We want to try to use word boundaries when
        // possible, however, to avoid the replacement from affecting unexpected
        // parts of the search query.
        if (!preg_match('/.*[^\w].*/', $from)) {
            $pattern = "/\b$from\b/i";
        } else {
            $pattern = "/$from/i";
        }

        // Perform the replacement:
        $this->queryString = preg_replace($pattern, $to, $queryString);
    }

    /**
     * Replaces a term with accents and umlauts removed
     *
     * @param string $from Search term to find
     * @param string $to   Search term to insert
     *
     * @return void
     */
    public function replaceTermIgnoringAccents($from, $to)
    {
        // Escape $from so it is regular expression safe (just in case it
        // includes any weird punctuation -- unlikely but possible):
        $from = preg_quote($from, '/');

        $from = $this->stripDiacritics($from);

        $from = strtolower($from);

        // If our "from" pattern contains non-word characters, we can't use word
        // boundaries for matching.  We want to try to use word boundaries when
        // possible, however, to avoid the replacement from affecting unexpected
        // parts of the search query.
        if (!preg_match('/.*[^\w].*/', $from)) {
            $pattern = "/\b$from\b/i";
        } else {
            $pattern = "/$from/i";
        }

        // Perform the replacement:
        $this->queryString = preg_replace(
            $pattern,
            $to,
            strtolower($this->stripDiacritics($this->queryString))
        );
    }

    /**
     * Remove accents and umlauts from a string
     * (a better alternative might be to use iconv library)
     * from https://stackoverflow.com/questions/1017599/how-do-i-remove-accents-from-characters-in-a-php-string
     *
     * @param string $string The text where we would like to remove accents
     *
     * @return string      The input text with accents removed
     */
    protected function stripDiacritics($string)
    {
        if (!preg_match('/[\x80-\xff]/', $string)) {
            return $string;
        }

        $chars = array(
            // Decompositions for Latin-1 Supplement
            chr(195).chr(128) => 'A', chr(195).chr(129) => 'A',
            chr(195).chr(130) => 'A', chr(195).chr(131) => 'A',
            chr(195).chr(132) => 'A', chr(195).chr(133) => 'A',
            chr(195).chr(135) => 'C', chr(195).chr(136) => 'E',
            chr(195).chr(137) => 'E', chr(195).chr(138) => 'E',
            chr(195).chr(139) => 'E', chr(195).chr(140) => 'I',
            chr(195).chr(141) => 'I', chr(195).chr(142) => 'I',
            chr(195).chr(143) => 'I', chr(195).chr(145) => 'N',
            chr(195).chr(146) => 'O', chr(195).chr(147) => 'O',
            chr(195).chr(148) => 'O', chr(195).chr(149) => 'O',
            chr(195).chr(150) => 'O', chr(195).chr(153) => 'U',
            chr(195).chr(154) => 'U', chr(195).chr(155) => 'U',
            chr(195).chr(156) => 'U', chr(195).chr(157) => 'Y',
            chr(195).chr(159) => 's', chr(195).chr(160) => 'a',
            chr(195).chr(161) => 'a', chr(195).chr(162) => 'a',
            chr(195).chr(163) => 'a', chr(195).chr(164) => 'a',
            chr(195).chr(165) => 'a', chr(195).chr(167) => 'c',
            chr(195).chr(168) => 'e', chr(195).chr(169) => 'e',
            chr(195).chr(170) => 'e', chr(195).chr(171) => 'e',
            chr(195).chr(172) => 'i', chr(195).chr(173) => 'i',
            chr(195).chr(174) => 'i', chr(195).chr(175) => 'i',
            chr(195).chr(177) => 'n', chr(195).chr(178) => 'o',
            chr(195).chr(179) => 'o', chr(195).chr(180) => 'o',
            chr(195).chr(181) => 'o', chr(195).chr(182) => 'o',
            chr(195).chr(182) => 'o', chr(195).chr(185) => 'u',
            chr(195).chr(186) => 'u', chr(195).chr(187) => 'u',
            chr(195).chr(188) => 'u', chr(195).chr(189) => 'y',
            chr(195).chr(191) => 'y',
            // Decompositions for Latin Extended-A
            chr(196).chr(128) => 'A', chr(196).chr(129) => 'a',
            chr(196).chr(130) => 'A', chr(196).chr(131) => 'a',
            chr(196).chr(132) => 'A', chr(196).chr(133) => 'a',
            chr(196).chr(134) => 'C', chr(196).chr(135) => 'c',
            chr(196).chr(136) => 'C', chr(196).chr(137) => 'c',
            chr(196).chr(138) => 'C', chr(196).chr(139) => 'c',
            chr(196).chr(140) => 'C', chr(196).chr(141) => 'c',
            chr(196).chr(142) => 'D', chr(196).chr(143) => 'd',
            chr(196).chr(144) => 'D', chr(196).chr(145) => 'd',
            chr(196).chr(146) => 'E', chr(196).chr(147) => 'e',
            chr(196).chr(148) => 'E', chr(196).chr(149) => 'e',
            chr(196).chr(150) => 'E', chr(196).chr(151) => 'e',
            chr(196).chr(152) => 'E', chr(196).chr(153) => 'e',
            chr(196).chr(154) => 'E', chr(196).chr(155) => 'e',
            chr(196).chr(156) => 'G', chr(196).chr(157) => 'g',
            chr(196).chr(158) => 'G', chr(196).chr(159) => 'g',
            chr(196).chr(160) => 'G', chr(196).chr(161) => 'g',
            chr(196).chr(162) => 'G', chr(196).chr(163) => 'g',
            chr(196).chr(164) => 'H', chr(196).chr(165) => 'h',
            chr(196).chr(166) => 'H', chr(196).chr(167) => 'h',
            chr(196).chr(168) => 'I', chr(196).chr(169) => 'i',
            chr(196).chr(170) => 'I', chr(196).chr(171) => 'i',
            chr(196).chr(172) => 'I', chr(196).chr(173) => 'i',
            chr(196).chr(174) => 'I', chr(196).chr(175) => 'i',
            chr(196).chr(176) => 'I', chr(196).chr(177) => 'i',
            chr(196).chr(178) => 'IJ',chr(196).chr(179) => 'ij',
            chr(196).chr(180) => 'J', chr(196).chr(181) => 'j',
            chr(196).chr(182) => 'K', chr(196).chr(183) => 'k',
            chr(196).chr(184) => 'k', chr(196).chr(185) => 'L',
            chr(196).chr(186) => 'l', chr(196).chr(187) => 'L',
            chr(196).chr(188) => 'l', chr(196).chr(189) => 'L',
            chr(196).chr(190) => 'l', chr(196).chr(191) => 'L',
            chr(197).chr(128) => 'l', chr(197).chr(129) => 'L',
            chr(197).chr(130) => 'l', chr(197).chr(131) => 'N',
            chr(197).chr(132) => 'n', chr(197).chr(133) => 'N',
            chr(197).chr(134) => 'n', chr(197).chr(135) => 'N',
            chr(197).chr(136) => 'n', chr(197).chr(137) => 'N',
            chr(197).chr(138) => 'n', chr(197).chr(139) => 'N',
            chr(197).chr(140) => 'O', chr(197).chr(141) => 'o',
            chr(197).chr(142) => 'O', chr(197).chr(143) => 'o',
            chr(197).chr(144) => 'O', chr(197).chr(145) => 'o',
            chr(197).chr(146) => 'OE',chr(197).chr(147) => 'oe',
            chr(197).chr(148) => 'R',chr(197).chr(149) => 'r',
            chr(197).chr(150) => 'R',chr(197).chr(151) => 'r',
            chr(197).chr(152) => 'R',chr(197).chr(153) => 'r',
            chr(197).chr(154) => 'S',chr(197).chr(155) => 's',
            chr(197).chr(156) => 'S',chr(197).chr(157) => 's',
            chr(197).chr(158) => 'S',chr(197).chr(159) => 's',
            chr(197).chr(160) => 'S', chr(197).chr(161) => 's',
            chr(197).chr(162) => 'T', chr(197).chr(163) => 't',
            chr(197).chr(164) => 'T', chr(197).chr(165) => 't',
            chr(197).chr(166) => 'T', chr(197).chr(167) => 't',
            chr(197).chr(168) => 'U', chr(197).chr(169) => 'u',
            chr(197).chr(170) => 'U', chr(197).chr(171) => 'u',
            chr(197).chr(172) => 'U', chr(197).chr(173) => 'u',
            chr(197).chr(174) => 'U', chr(197).chr(175) => 'u',
            chr(197).chr(176) => 'U', chr(197).chr(177) => 'u',
            chr(197).chr(178) => 'U', chr(197).chr(179) => 'u',
            chr(197).chr(180) => 'W', chr(197).chr(181) => 'w',
            chr(197).chr(182) => 'Y', chr(197).chr(183) => 'y',
            chr(197).chr(184) => 'Y', chr(197).chr(185) => 'Z',
            chr(197).chr(186) => 'z', chr(197).chr(187) => 'Z',
            chr(197).chr(188) => 'z', chr(197).chr(189) => 'Z',
            chr(197).chr(190) => 'z', chr(197).chr(191) => 's'
        );

        $string = strtr($string, $chars);

        return $string;
    }
}
