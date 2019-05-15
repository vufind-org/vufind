<?php
/**
 * Citation view helper
 *
 * PHP version 5
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
        $authors = [];
        $primary = $driver->tryMethod('getPrimaryAuthor');
        if (empty($primary)) {
            $primary = $driver->tryMethod('getCorporateAuthor');
        }
        if (!empty($primary)) {
            $authors[] = $primary;
        }
        $secondary = $driver->tryMethod('getSecondaryAuthors');
        if (is_array($secondary) && !empty($secondary)) {
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
            'pubPlace' => isset($pubPlaces[0]) ? $pubPlaces[0] : null,
            'pubName' => isset($publishers[0]) ? $publishers[0] : null,
            'pubDate' => isset($pubDates[0]) ? $pubDates[0] : null,
            'edition' => empty($edition) ? [] : [$edition],
            'journal' => $this->getContainerTitle()
        ];

        return $this;
    }


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
     * Construct page range portion of citation.
     *
     * @return string
     */
    protected function getPageRange()
    {
         return $this->driver->getPages();
    }


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


    protected function getMLANumberAndDate($volNumSeparator = '.', $useYearBrackets = false, $volPrefix = ', vol.')
    {
        $vol = $this->driver->tryMethod('getVolume');
        $num = $this->driver->tryMethod('getIssue');
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
            return $volPrefix . $volNum . ($useYearBrackets ?  ' (' . $year . ')' : ', ' . $year);
        } else {
            // Right now, we'll assume if day == 1, this is a monthly publication;
            // that's probably going to result in some bad citations, but it's the
            // best we can do without writing extra record driver methods.
            return (($day > 1) ? $day . ' ' : '')
                . (empty($month) ? '' : $month . ' ')
                . $year;
        }
    }


    public function getCitationMLA($etAlThreshold = 4, $volNumSeparator = '.', $useYearBrackets = false,
                                   $yearPageSeparator = ', ', $volPrefix = ', vol. ', $usePagePrefix = true)
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
            $mla['journal'] =  $this->capitalizeTitle($this->details['journal']);
            $mla['numberAndDate'] = $this->getMLANumberAndDate($volNumSeparator, $useYearBrackets, $volPrefix);
            if ($doi = $this->driver->tryMethod('getCleanDOI'))
               $mla['doi'] = $doi;

            $urls =  $this->driver->tryMethod('getUrls');
            if (!empty($urls)) {
                // Choose first available URL
                $url = $urls[0]['url'];
                if (!empty($url))
                    $mla['url'] = $url;
            }

            $formatter = new \IntlDateFormatter($this->getTranslatorLocale(),
                             \IntlDateFormatter::SHORT, \IntlDateFormatter::NONE);
            if ($formatter === null)
                 throw new InvalidConfigException(intl_get_error_message());
            $mla['localizedDate'] = $formatter->format(new \DateTime());
            $mla['yearPageSeparator'] = $yearPageSeparator;
            $mla['usePagePrefix'] = $usePagePrefix;

            return $partial('Citation/mla-article.phtml', $mla);
        }
    }


    public function getCitationChicago()
    {
        return $this->getCitationMLA(9, ', no. ', true, ': ', ' ', false);
    }
}
