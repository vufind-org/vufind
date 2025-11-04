<?php

/**
 * "Results as feed" view helper
 *
 * PHP version 8
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\View\Helper\Root;

use Finna\View\Helper\Root\RecordImage as RecordImageHelper;
use VuFind\Db\Entity\UserListEntityInterface;
use VuFind\Db\Service\CommentsServiceInterface;
use VuFind\View\Helper\Root\Record as RecordHelper;

use function array_slice;
use function count;
use function is_array;
use function is_string;

/**
 * "Results as feed" view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ResultFeed extends \VuFind\View\Helper\Root\ResultFeed
{
    /**
     * User list object
     *
     * @var UserListEntityInterface
     */
    protected $list = null;

    /**
     * Constructor
     *
     * @param RecordHelper             $recordHelper      Record helper
     * @param RecordImageHelper        $recordImageHelper Record image helper
     * @param CommentsServiceInterface $commentsService   Comments database service
     */
    public function __construct(
        protected RecordHelper $recordHelper,
        protected RecordImageHelper $recordImageHelper,
        protected CommentsServiceInterface $commentsService
    ) {
    }

    /**
     * Set user list for this feed.
     *
     * @param UserListEntityInterface $list List
     *
     * @return void
     */
    public function setList(UserListEntityInterface $list): void
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
        $recordLinker = $this->getView()->plugin('recordLinker');
        try {
            $url = $serverUrl($recordLinker->getUrl($record));
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
                    $this->list->id,
                    $this->list->user_id
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
        $recordHelperInst = ($this->recordHelper)($record);
        $imageUrl = ($this->recordImageHelper)($recordHelperInst)->getLargeImage() . '&w=1024&h=1024&imgext=.jpeg';
        $entry->setEnclosure(
            [
                'uri' => $serverUrl($imageUrl),
                'type' => 'image/jpeg',
                'length' => 0,
            ]
        );
        $comments = $this->commentsService->getRecordComments($record->getUniqueID(), $record->getSourceIdentifier());
        $entry->setCommentCount(count($comments));
        $summaries = [];
        if (isset($this->list)) {
            $summaries = $recordHelperInst->getListNotes($this->list->getId());
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
