<?php
/**
 * "Results as feed" view helper
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2015.
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
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;

/**
 * "Results as feed" view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class ResultFeed extends \VuFind\View\Helper\Root\ResultFeed
{
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
        $title = empty($title) ? $record->getBreadcrumb() : $title;
        $entry->setTitle(
            empty($title) ? $this->translate('Title not available') : $title
        );
        $serverUrl = $this->getView()->plugin('serverurl');
        $recordLink = $this->getView()->plugin('recordlink');
        try {
            $url = $serverUrl($recordLink->getUrl($record));
        } catch (\Zend\Mvc\Router\Exception\RuntimeException $e) {
            // No route defined? See if we can get a URL out of the driver.
            // Useful for web results, among other things.
            $url = $record->tryMethod('getUrl');
            if (empty($url) || !is_string($url)) {
                throw new \Exception('Cannot find URL for record.');
            }
        }
        $entry->setLink($url);
        $date = $this->getDateModified($record);
        if (!empty($date)) {
            $entry->setDateModified($date);
        }
        $formats = $record->tryMethod('getFormats');
        if (is_array($formats)) {
            // Take only the most specific format and get rid of level indicator
            // and trailing slash
            $format = end($formats);
            $format = implode('/', array_slice(explode('/', $format), 1, -1));
            $entry->addDCFormat($format);
        }
        $dcDate = $this->getDcDate($record);
        if (!empty($dcDate)) {
            $entry->setDCDate($dcDate);
        }
        $urlHelper = $this->getView()->plugin('url');
        $recordHelper = $this->getView()->plugin('record');
        $recordImage = $this->getView()->plugin('recordImage');
        $imageUrl = $recordImage($recordHelper($record))->getLargeImage();
        $entry->setEnclosure(
            [
                'uri' => $serverUrl($imageUrl),
                'type' => 'image/jpeg',
                // TODO: this should be zero, but the item renderer doesn't currently
                // allow that (see https://github.com/zendframework/zend-feed/pull/5)
                'length' => 1
            ]
        );
        $entry->setCommentCount(count($record->getComments()));
        $summaries = $record->tryMethod('getSummary');
        if (!empty($summaries)) {
            $entry->setDescription(implode(' -- ', $summaries));
        }

        $feed->addEntry($entry);
    }
}
