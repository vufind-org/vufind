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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\View\Helper\Root;
use DateTime,
    VuFind\I18n\Translator\TranslatorAwareInterface,
    Zend\Feed\Writer\Writer as FeedWriter,
    Zend\Feed\Writer\Feed,
    Zend\View\Helper\AbstractHelper;

/**
 * "Results as feed" view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ResultFeed extends AbstractHelper implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Override title
     *
     * @var string
     */
    protected $overrideTitle = null;

    /**
     * Set override title.
     *
     * @param string $title Title
     *
     * @return void
     */
    public function setOverrideTitle($title)
    {
        $this->overrideTitle = $title;
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
        $manager->setInvokableClass(
            'opensearchrendererfeed',
            'VuFind\Feed\Writer\Extension\OpenSearch\Renderer\Feed'
        );
        $manager->setInvokableClass(
            'opensearchfeed', 'VuFind\Feed\Writer\Extension\OpenSearch\Feed'
        );
        FeedWriter::registerExtension('OpenSearch');
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
        if (null !== $this->overrideTitle) {
            $feed->setTitle($this->translate($this->overrideTitle));
        } else {
            $feed->setTitle(
                $this->translate('Results for') . ' '
                . $results->getParams()->getDisplayQuery()
            );
        }
        $feed->setLink(
            $baseUrl . $results->getUrlQuery()->setViewParam(null)->getParams(false)
        );
        $feed->setFeedLink(
            $baseUrl . $results->getUrlQuery()->getParams(false),
            $results->getParams()->getView()
        );
        $feed->setDescription(
            $this->translate('Showing') . ' ' . $results->getStartRecord() . '-'
            . $results->getEndRecord() . ' ' . $this->translate('of') . ' '
            . $results->getResultTotal()
        );

        $params = $results->getParams();

        // add atom links for easier paging
        $feed->addOpensearchLink(
            $baseUrl . $results->getUrlQuery()->setPage(1)->getParams(false),
            'first',
            $params->getView()
        );
        if ($params->getPage() > 1) {
            $feed->addOpensearchLink(
                $baseUrl . $results->getUrlQuery()
                    ->setPage($params->getPage() - 1)->getParams(false),
                'previous',
                $params->getView()
            );
        }
        $lastPage = ceil($results->getResultTotal() / $params->getLimit());
        if ($params->getPage() < $lastPage) {
            $feed->addOpensearchLink(
                $baseUrl . $results->getUrlQuery()
                    ->setPage($params->getPage() + 1)->getParams(false),
                'next',
                $params->getView()
            );
        }
        $feed->addOpensearchLink(
            $baseUrl . $results->getUrlQuery()->setPage($lastPage)->getParams(false),
            'last',
            $params->getView()
        );

        // add opensearch fields
        $feed->setOpensearchTotalResults($results->getResultTotal());
        $feed->setOpensearchItemsPerPage($params->getLimit());
        $feed->setOpensearchStartIndex($results->getStartRecord() - 1);
        $feed->setOpensearchSearchTerms($params->getQuery()->getAllTerms());

        $records = $results->getResults();
        foreach ($records as $current) {
            $this->addEntry($feed, $current);
        }

        return $feed;
    }

    /**
     * Support method to extract a date from a record driver. Return empty string
     * if no valid match is found.
     *
     * @param \VuFind\RecordDriver\AbstractBase $record Record to read from
     *
     * @return string
     */
    protected function getDcDate($record)
    {
        // See if we can extract a date that's pre-formatted in a DC-friendly way:
        $dates = (array)$record->tryMethod('getPublicationDates');
        $regex = '/[0-9]{4}(\-[01][0-9])?(\-[0-3][0-9])?/';
        foreach ($dates as $date) {
            if (preg_match($regex, $date, $matches)) {
                // If the full string is longer than the match, see if we can use
                // DateTime to format it to something more useful:
                if (strlen($date) > strlen($matches[0])) {
                    try {
                        $formatter = new DateTime($date);
                        return $formatter->format('Y-m-d');
                    } catch (\Exception $ex) {
                        // DateTime failed; fall through to default behavior below.
                    }
                }
                return $matches[0];
            }
        }

        // Still no good? Give up.
        return '';
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
        $author = $record->tryMethod('getPrimaryAuthor');
        if (!empty($author)) {
            $entry->addAuthor(['name' => $author]);
        }
        $formats = $record->tryMethod('getFormats');
        if (is_array($formats)) {
            foreach ($formats as $format) {
                $entry->addDCFormat($format);
            }
        }
        $dcDate = $this->getDcDate($record);
        if (!empty($dcDate)) {
            $entry->setDCDate($dcDate);
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
