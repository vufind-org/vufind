<?php
/**
 * "Results as feed" view helper
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\View\Helper\Root;
use DateTime, Zend\Feed\Writer\Writer as FeedWriter, Zend\Feed\Writer\Feed,
    Zend\View\Helper\AbstractHelper;

/**
 * "Results as feed" view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class ResultFeed extends AbstractHelper
{
    protected $translator = false;

    /**
     * Get access to the translator helper.
     *
     * @return object
     */
    public function getTranslatorHelper()
    {
        if (!$this->translator) {
            $this->translator = $this->getView()->plugin('translate');
        }
        return $this->translator;
    }

    /**
     * Override the translator helper (useful for testing purposes).
     *
     * @param object $translator New translator object.
     *
     * @return void
     */
    public function setTranslatorHelper($translator)
    {
        $this->translator = $translator;
    }

    /**
     * Set up Dublin Core extension.
     *
     * @return void
     */
    protected function registerExtension()
    {
        $manager = FeedWriter::getExtensionManager();
        $manager->setInvokableClass(
            'dublincorerendererentry',
            'VuFind\Feed\Writer\Extension\DublinCore\Renderer\Entry'
        );
        $manager->setInvokableClass(
            'dublincoreentry', 'VuFind\Feed\Writer\Extension\DublinCore\Entry'
        );
    }

    /**
     * Represent the current search results as a feed.
     *
     * @param \VuFind\Search\Base\Results $results     Search results to convert to
     * feed
     * @param string                      $currentPath Base path to display in feed
     * (leave null to load dynamically using currentpath view helper)
     *
     * @return Feed
     */
    public function __invoke($results, $currentPath = null)
    {
        $this->registerExtension();

        // Determine base URL if not already provided:
        if (is_null($currentPath)) {
            $currentPath = $this->getView()->plugin('currentpath')->__invoke();
        }
        $serverUrl = $this->getView()->plugin('serverurl');
        $baseUrl = $serverUrl($currentPath);

        // Create the parent feed
        $feed = new Feed();
        $translator = $this->getTranslatorHelper();
        $feed->setTitle(
            $translator('Results for') . ' '
            . $results->getParams()->getDisplayQuery()
        );
        $feed->setLink(
            $baseUrl . $results->getUrlQuery()->setViewParam(null, false)
        );
        $feed->setFeedLink(
            $baseUrl . $results->getUrlQuery()->getParams(false),
            $results->getParams()->getView()
        );

        $records = $results->getResults();
        $feed->setDescription(
            $translator('Displaying the top') . ' ' . count($records)
            . ' ' . $translator('search results of') . ' '
            . $results->getResultTotal() . ' ' . $translator('found')
        );

        foreach ($records as $current) {
            $this->addEntry($feed, $current);
        }

        return $feed;
    }

    /**
     * Support method to turn a record driver object into an RSS entry.
     *
     * @param Feed                              $feed   Feed to update
     * @param \VuFind\RecordDriver\AbstractBase $record Record to add to feed
     *
     * @return void
     */
    protected function addEntry($feed, $record)
    {
        $entry = $feed->createEntry();
        $title = $record->tryMethod('getTitle');
        $entry->setTitle(empty($title) ? $record->getBreadcrumb() : $title);
        $serverUrl = $this->getView()->plugin('serverurl');
        $recordLink = $this->getView()->plugin('recordlink');
        $entry->setLink($serverUrl($recordLink->getUrl($record)));
        $date = $this->getDateModified($record);
        if (!empty($date)) {
            $entry->setDateModified($date);
        }
        $author = $record->tryMethod('getPrimaryAuthor');
        if (!empty($author)) {
            $entry->addAuthor(array('name' => $author));
        }
        $formats = $record->tryMethod('getFormats');
        if (is_array($formats)) {
            foreach ($formats as $format) {
                $entry->addDCFormat($format);
            }
        }
        $date = $record->tryMethod('getPublicationDates');
        if (isset($date[0]) && !empty($date[0])) {
            $entry->setDCDate($date[0]);
        }

        $feed->addEntry($entry);
    }

    /**
     * Support method to extract modified date from a record driver object.
     *
     * @param \VuFind\RecordDriver\AbstractBase $record Record to pull date from.
     *
     * @return int|DateTime|null
     */
    protected function getDateModified($record)
    {
        // Best case -- "last indexed" date is available:
        $date = $record->tryMethod('getLastIndexed');
        if (!empty($date)) {
            return strtotime($date);
        }

        // Next, try publication date:
        $date = $record->tryMethod('getPublicationDates');
        if (isset($date[0])) {
            // Extract first string of numbers -- this should be a year:
            preg_match('/[^0-9]*([0-9]+).*/', $date[0], $matches);
            $date = new DateTime();
            $date->setDate($matches[1], 1, 1);
            return $date;
        }

        // If we got this far, no date is available:
        return null;
    }
}