<?php

/**
 * Simple XML-based factory for record collection.
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
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
namespace VuFindSearch\Backend\WorldCatDiscovery\Response;

use VuFind\Connection\WorldCatKnowledgeBaseUrlService as UrlService;
use VuFindSearch\Response\RecordCollectionFactoryInterface;
use VuFindSearch\Exception\InvalidArgumentException;

/**
 * Simple XML-based factory for record collection.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class RecordCollectionFactory implements RecordCollectionFactoryInterface
{
    /**
     * Factory to turn data into a record object.
     *
     * @var Callable
     */
    protected $recordFactory;

    /**
     * Class of collection.
     *
     * @var string
     */
    protected $collectionClass;

    /**
     * WorldCat Knowledge Base URL Service
     *
     * @var UrlService
     */
    protected $urlService;

    /**
     * Constructor.
     *
     * @param Callable   $recordFactory   Record factory function (null for default)
     * @param string     $collectionClass Class of collection
     * @param UrlService $urlService      Url Service
     *
     * @return void
     */
    public function __construct($recordFactory = null, $collectionClass = null,
        UrlService $urlService = null
    ) {
        if (!is_callable($recordFactory)) {
            throw new InvalidArgumentException('Record factory must be callable.');
        }
        $this->recordFactory = $recordFactory;
        $this->collectionClass = (null === $collectionClass)
            ? 'VuFindSearch\Backend\WorldCatDiscovery\Response\RecordCollection'
            : $collectionClass;
        $this->urlService = $urlService;
    }

    /**
     * Return record collection.
     *
     * @param array $response Collection of documents
     *
     * @return RecordCollection
     */
    public function factory($response)
    {
        // Add code to make sure its an OfferSet or BibSearchResults object

        $collection = new $this->collectionClass($response);

        // Determine if its an OfferSet or BibSearchResults object
        // and get the results accordingly

        if (is_a($response, 'WorldCat\Discovery\BibSearchResults')) {
            $results = $response->getSearchResults();
            $offers = null;
        } elseif (is_a($response, 'WorldCat\Discovery\OfferSet')) {
            $results = $response->getCreativeWorks();
            $offers = $response->getOffers();
        } else {
            echo 'Error';
        }

        foreach ($results as $doc) {
            $record = call_user_func(
                $this->recordFactory, ['doc' => $doc, 'offers' => $offers]
            );
            $collection->add($record);
            if ($this->urlService) {
                $this->urlService->addToQueue($record);
            }
        }
        return $collection;
    }

}