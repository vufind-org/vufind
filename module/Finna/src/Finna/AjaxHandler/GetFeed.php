<?php
/**
 * GetFeed AJAX handler
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015-2018.
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
 * @package  AJAX
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace Finna\AjaxHandler;

use Finna\Feed\Feed as FeedService;
use Laminas\Config\Config;
use Laminas\Escaper\Escaper;
use Laminas\Feed\Writer\Feed;
use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\Mvc\Controller\Plugin\Url;
use Laminas\View\Renderer\RendererInterface;
use Vufind\ILS\Connection;
use VuFind\Record\Loader;
use VuFind\Session\Settings as SessionSettings;

/**
 * GetFeed AJAX handler
 *
 * @category VuFind
 * @package  AJAX
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetFeed extends \VuFind\AjaxHandler\AbstractBase
{
    use FeedTrait;

    /**
     * RSS configuration
     *
     * @var Config
     */
    protected $config;

    /**
     * Feed service
     *
     * @var FeedService
     */
    protected $feedService;

    /**
     * ILS connection
     *
     * @var Connection
     */
    protected $ils;

    /**
     * Record loader
     *
     * @var Loader
     */
    protected $recordLoader;

    /**
     * View renderer
     *
     * @var RendererInterface
     */
    protected $renderer;

    /**
     * URL helper
     *
     * @var Url
     */
    protected $url;

    /**
     * Constructor
     *
     * @param SessionSettings   $ss           Session settings
     * @param Config            $config       RSS configuration
     * @param FeedService       $fs           Feed service
     * @param Loader            $recordLoader Record Loader
     * @param Connection        $ils          ILS connection
     * @param RendererInterface $renderer     View renderer
     * @param Url               $url          URL helper
     */
    public function __construct(SessionSettings $ss, Config $config,
        FeedService $fs, Loader $recordLoader, Connection $ils,
        RendererInterface $renderer, Url $url
    ) {
        $this->sessionSettings = $ss;
        $this->config = $config;
        $this->feedService = $fs;
        $this->recordLoader = $recordLoader;
        $this->ils = $ils;
        $this->renderer = $renderer;
        $this->url = $url;
    }

    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        $this->disableSessionWrites();  // avoid session write timing bug

        $id = $params->fromPost('id', $params->fromQuery('id'));
        if (!$id) {
            return $this->formatResponse('', self::STATUS_HTTP_BAD_REQUEST);
        }

        $touchDevice = $params->fromQuery('touch-device') === '1';

        try {
            $serverHelper = $this->renderer->plugin('serverurl');
            $homeUrl = $serverHelper($this->url->fromRoute('home'));

            if ($config = $this->feedService->getFeedConfig($id)) {
                $config = $config['result'];
            }

            if (!isset($config['ilsList'])) {
                // Normal feed
                $feed = $this->feedService->readFeed($id, $homeUrl);
            } else {
                // ILS list to be converted to a feed
                $query = $config['ilsList'];
                $amount = $config['amount'] ?? 20;
                $type = $config['type'] ?? 'carousel';
                $source = $config['source'] ?? 'Solr';
                $ilsId = $config['ilsId'];

                $patronId = !empty($ilsId) ? $ilsId . '.123' : '';
                $amount = $amount > 20 ? 20 : $amount;

                $result = $this->ils->checkFunction(
                    'getTitleList', ['id' => $patronId]
                );
                if (!$result) {
                    return $this->formatResponse('Missing configurations', 501);
                }

                $records = [];
                $data = $this->ils->getTitleList(
                    ['query' => $query, 'pageSize' => $amount, 'id' => $ilsId]
                );

                foreach ($data['records'] ?? [] as $key => $obj) {
                    $loadedRecord = $this->recordLoader->load(
                        $ilsId . '.' . $obj['id'], $source, true
                    );
                    $loadedRecord->setExtraDetail('ils_details', $obj);
                    $records[] = $loadedRecord;
                }

                $serverUrl = $this->renderer->plugin('serverUrl');
                $recordHelper = $this->renderer->plugin('record');
                $recordImage = $this->renderer->plugin('recordImage');
                $recordUrl = $this->renderer->plugin('recordLink');
                $escaper = new Escaper('utf-8');

                $feed = new Feed();
                $feed->setTitle($query);
                $feed->setLink($serverUrl());
                $feed->setDateModified(time());
                $feed->setId(' ');
                $feed->setDescription(' ');
                foreach ($records as $rec) {
                    $isRecord = !$rec instanceof \VuFind\RecordDriver\Missing;
                    $entry = $feed->createEntry();
                    $entry->setTitle($rec->getTitle());
                    $entry->setDateModified(time());
                    $entry->setDateCreated(time());
                    $entry->setId($rec->getUniqueID());
                    if ($isRecord) {
                        $entry->setLink($recordUrl->getUrl($rec));
                    }
                    $ilsDetails = $rec->getExtraDetail('ils_details');
                    $author = $isRecord
                        ? $rec->getPrimaryAuthorForSearch()
                        : $ilsDetails['author'];
                    $year = $isRecord ?
                        ($rec->getPublicationDates()[0] ?? '')
                        : $ilsDetails['year'];

                    $content = [];
                    if ($isRecord) {
                        $content[] = trim(
                            $recordHelper($rec)->getFormatList() . ' ' .
                            $recordHelper($rec)->getSourceIdElement()
                        );
                    }
                    if (!empty($author)) {
                        $content[] = trim($escaper->escapeHtml($author));
                    }
                    if (!empty($year)) {
                        $content[] = trim($escaper->escapeHtml($year));
                    }

                    if (!empty($content)) {
                        $contentString = implode('; ', $content);
                        $entry->setContent($contentString);
                    }

                    $imageUrl = $recordImage($recordHelper($rec))->getLargeImage()
                        . '&w=1024&h=1024&imgext=.jpeg';
                    $entry->setEnclosure(
                        [
                            'uri' => $serverUrl($imageUrl),
                            'type' => 'image/jpeg',
                            'length' => 0
                        ]
                    );

                    $feed->addEntry($entry);
                }

                $feed = $feed->export('rss', false);
                $feed = \Laminas\Feed\Reader\Reader::importString($feed);

                $feed = $this->feedService->parseFeed($feed, $config);
            }
        } catch (\Exception $e) {
            return $this->formatResponse($e->getMessage(), self::STATUS_HTTP_ERROR);
        }

        if (!$feed) {
            return $this->formatResponse(
                'Error reading feed', self::STATUS_HTTP_ERROR
            );
        }

        return $this->formatResponse(
            $this->formatFeed(
                $feed,
                $this->config,
                $this->renderer,
                false,
                $touchDevice
            )
        );
    }
}
