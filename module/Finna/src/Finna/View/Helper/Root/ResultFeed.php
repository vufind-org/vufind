<?php
/**
 * "Results as feed" view helper
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2015-2019.
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
     * User list object
     *
     * @var Db\Row\UserList
     */
    protected $list = null;

    /**
     * Set user list for this feed.
     *
     * @param Db\Row\UserList $list List
     *
     * @return void
     */
    public function setList($list)
    {
        $this->list = $list;
    }

    /**
     * Support method to turn a record driver object into an RSS entry.
     *
     * @param Laminas\Feed\Writer\Feed          $feed   Feed to update
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
        $serverUrl = $this->getView()->plugin('serverUrl');
        $recordLink = $this->getView()->plugin('recordLink');
        try {
            $url = $serverUrl($recordLink->getUrl($record));
        } catch (\Laminas\Router\Exception\RuntimeException $e) {
            // No route defined? See if we can get a URL out of the driver.
            // Useful for web results, among other things.
            $url = $record->tryMethod('getUrl');
            if (empty($url) || !is_string($url)) {
                throw new \Exception('Cannot find URL for record.');
            }
        }
        $entry->setLink($url);

        if ($this->list) {
            if (method_exists($record, 'getListSavedDate')) {
                $saved = $record->getListSavedDate(
                    $this->list->id, $this->list->user_id
                );
                if ($saved) {
                    $entry->setDateModified(new \DateTime($saved));
                }
            }
        } else {
            $date = $this->getDateModified($record);
            if (!empty($date)) {
                $entry->setDateModified($date);
            }
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
        $recordHelper = $this->getView()->plugin('record');
        $recordImage = $this->getView()->plugin('recordImage');
        $imageUrl = $recordImage($recordHelper($record))->getLargeImage()
            . '&w=1024&h=1024&imgext=.jpeg';
        $entry->setEnclosure(
            [
                'uri' => $serverUrl($imageUrl),
                'type' => 'image/jpeg',
                'length' => 0
            ]
        );
        $entry->setCommentCount(count($record->getComments()));
        $summaries = [];
        if (isset($this->list)) {
            $summaries = $record->getListNotes($this->list->id);
        }
        if (empty($summaries)) {
            $summaries = array_filter($record->tryMethod('getSummary'));
        }
        if (!empty($summaries)) {
            $entry->setDescription(implode(' -- ', $summaries));
        }

        $feed->addEntry($entry);
    }
}
