<?php
/**
 * Citation view helper
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) Universitätsbibliothek Tübingen 2017
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
 * @author   Johannes Riedl <johannes.riedl@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace IxTheo\View\Helper\Root;

use VuFind\Date\DateException;

/**
 * Citation view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Citation extends \VuFind\View\Helper\Root\Citation implements \VuFind\I18n\Translator\TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;


    public function getContainerTitle() {

        $transEsc = $this->getView()->plugin('transEsc');
        $escapeHtml = $this->getView()->plugin('escapeHtml');
        $ids_and_titles = $this->driver->getContainerIDsAndTitles();
        $i=0;
        $container_information = '';
        if (!empty($ids_and_titles)):
            foreach ($ids_and_titles as $id => $title):
                ++$i;
                $container_information .= $escapeHtml($title[0]) . ($i < count($ids_and_titles)  ? ' ' : '');
            endforeach;
        endif;
        return $container_information;
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
        $doiPrefix = false,
        $labelPageRange = true
    ) {
        // IxTheo: Always show DOI
        if ($doiPrefix == false)
            $doiPrefix = 'doi: ';

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
        $mla['pageRange'] = $this->getPageRange();
        $mla['journal'] = $this->capitalizeTitle($this->details['journal']);
        $mla['numberAndDate'] = $numPrefix . $this->getMLANumberAndDate(
            $volNumSeparator,
            $volPrefix,
            $yearFormat
        );

        $urls_and_types =  $this->driver->tryMethod('getURLsAndMaterialTypes');
        if (!empty($urls_and_types)) {
            // Choose first available Fulltext URL
            foreach ($urls_and_types as $url => $type) {
                if ($type == "Free Access" && !empty($url)) {
                    $mla['url'] = $url;
                    break;
                }
            }
        }

        return $partial('Citation/mla-article.phtml', $mla);
    }

    /**
     * Construct page range portion of citation.
     *
     * @return string
     */
    protected function getPageRange()
    {
         return $this->driver->getPages();
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
        $vol = $this->driver->tryMethod('getVolume');
        $num = $this->driver->tryMethod('getIssue');
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
}
