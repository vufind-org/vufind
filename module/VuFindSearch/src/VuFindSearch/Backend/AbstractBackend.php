<?php

/**
 * Abstract backend.
 *
 * PHP version 8
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
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Backend;

use Laminas\Log\LoggerAwareInterface;
use Laminas\Math\Rand;
use VuFindSearch\Response\RecordCollectionFactoryInterface;
use VuFindSearch\Response\RecordCollectionInterface;

use function sprintf;

/**
 * Abstract backend.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
abstract class AbstractBackend implements BackendInterface, LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Record collection factory.
     *
     * @var RecordCollectionFactoryInterface
     */
    protected $collectionFactory = null;

    /**
     * Backend identifier.
     *
     * @var string
     */
    protected $identifier = null;

    /**
     * Set the backend identifier.
     *
     * @param string $identifier Backend identifier
     *
     * @return void
     */
    public function setIdentifier($identifier)
    {
        $this->identifier = $identifier;
    }

    /**
     * Return backend identifier.
     *
     * @return string
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * Set the record collection factory.
     *
     * @param RecordCollectionFactoryInterface $factory Factory
     *
     * @return void
     */
    public function setRecordCollectionFactory(
        RecordCollectionFactoryInterface $factory
    ) {
        $this->collectionFactory = $factory;
    }

    /**
     * Return the record collection factory.
     *
     * Lazy loads a generic collection factory.
     *
     * @return RecordCollectionFactoryInterface
     */
    abstract public function getRecordCollectionFactory();

    /// Internal API

    /**
     * Inject source identifier in record collection and all contained records.
     *
     * @param RecordCollectionInterface $response Response
     *
     * @return RecordCollectionInterface
     */
    protected function injectSourceIdentifier(RecordCollectionInterface $response)
    {
        $response->setSourceIdentifiers($this->identifier);

        if (count($response->getRecords()) > 0) {
            // TODO: Replace custom UUID generation with Doctrine
            // UUID generator once available (after the merge of #2233)
            $response->setResultSetIdentifier($this->generateUuid());
        }

        return $response;
    }

    /**
     * Generates a shorter UUID-like identifier.
     *
     * This method uses Laminas\Math\Rand to generate cryptographically secure random bytes
     * and formats them into a shorter identifier.
     *
     * @return string A randomly generated shorter UUID-like identifier.
     */
    public function generateUuid(): string
    {
        $data = bin2hex(Rand::getBytes(8));
        return sprintf(
            '%08s-%04s-%04s',
            substr($data, 0, 8),
            substr($data, 8, 4),
            substr($data, 12, 4)
        );
    }
}
