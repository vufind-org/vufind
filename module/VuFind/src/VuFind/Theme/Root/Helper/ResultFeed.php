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
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
namespace VuFind\Theme\Root\Helper;
use Zend\View\Helper\AbstractHelper;

/**
 * "Results as feed" view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class ResultFeed extends AbstractHelper
{
    /**
     * Represent the current search results as a feed.
     *
     * @param VF_Search_Base_Results $results Search results to convert to feed.
     *
     * @return Zend_Feed_Writer_Feed
     */
    public function __invoke($results)
    {
        /* TODO
        // Set up plugin loader so we can use custom feed extensions:
        $loader = Zend_Feed_Writer::getPluginLoader();
        $loader->addPrefixPath(
            'VF_Feed_Writer_Extension_', 'VF/Feed/Writer/Extension/'
        );

        // Create the parent feed
        $feed = new Zend_Feed_Writer_Feed();
        $feed->setTitle(
            $this->view->translate('Results for') . ' '
            . $results->getDisplayQuery()
        );
        $feed->setLink(
            $this->view->serverUrl($this->view->currentPath())
            . $results->getUrl()->setViewParam(null, false)
        );
        $feed->setFeedLink(
            $this->view->serverUrl($this->view->currentPath())
            . $results->getUrl()->getParams(false),
            $results->getView()
        );

        $records = $results->getResults();
        $feed->setDescription(
            $this->view->translate('Displaying the top') . ' ' . count($records)
            . ' ' . $this->view->translate('search results of') . ' '
            . $results->getResultTotal() . ' ' . $this->view->translate('found')
        );

        foreach ($records as $current) {
            $this->addEntry($feed, $current);
        }

        return $feed;
         */
    }

    /**
     * Support method to turn a record driver object into an RSS entry.
     *
     * @param Zend_Feed_Writer_Feed $feed   Feed to update
     * @param VF_RecordDriver_Base  $record Record to add to feed
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
     * @param VF_RecordDriver_Base $record Record to pull date from.
     *
     * @return int|Zend_Date|null
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
            return new Zend_Date(
                array('year' => $matches[1], 'month' => 1, 'day' => 1)
            );
        }

        // If we got this far, no date is available:
        return null;
    }
}