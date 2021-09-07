<?php

/**
 * Command to write a document object to Solr.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2021.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
namespace VuFindSearch\Backend\Solr\Command;

use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Backend\Solr\Connector;
use VuFindSearch\Backend\Solr\Document\DocumentInterface;
use VuFindSearch\Command\CommandInterface;
use VuFindSearch\ParamBag;

/**
 * Command to write a document object to Solr.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class WriteDocumentCommand extends \VuFindSearch\Command\AbstractBase
{
    /**
     * Constructor.
     *
     * @param string            $backend Search backend identifier
     * @param DocumentInterface $doc     Document to write
     * @param ?int              $timeout Timeout value (null for default)
     * @param string            $handler Handler to use
     * @param ?ParamBag         $params  Search backend parameters
     */
    public function __construct(
        string $backend,
        DocumentInterface $doc,
        int $timeout = null,
        string $handler = 'update',
        ?ParamBag $params = null
    ) {
        parent::__construct($backend, compact('doc', 'timeout', 'handler'), $params);
    }

    /**
     * Execute command on backend.
     *
     * @param BackendInterface $backend Backend
     *
     * @return CommandInterface Command instance for method chaining
     */
    public function execute(BackendInterface $backend): CommandInterface
    {
        $this->validateBackend($backend);
        $connector = is_callable([$backend, 'getConnector'])
            ? $backend->getConnector() : null;
        if (!($connector instanceof Connector)) {
            throw new \Exception(
                $connector === null
                    ? 'Connector not found'
                    : 'Unexpected connector: ' . get_class($connector)
            );
        }
        // If we have a custom timeout, remember the old timeout value and then
        // override it with a different one:
        $oldTimeout = null;
        if (is_int($this->context['timeout'] ?? null)) {
            $oldTimeout = $connector->getTimeout();
            $connector->setTimeout($this->context['timeout']);
        }

        // Write!
        $connector->write(
            $this->context['doc'],
            $this->context['handler'] ?? 'update',
            $this->params
        );

        // Restore previous timeout value, if necessary:
        if (null !== $oldTimeout) {
            $connector->setTimeout($oldTimeout);
        }

        // Save the core name in the results in case the caller needs it.
        return $this->finalizeExecution(['core' => $this->getCore($connector)]);
    }

    /**
     * Extract the Solr core from a connector's URL.
     *
     * @param Connector $connector Solr connector
     *
     * @return string
     */
    protected function getCore(Connector $connector)
    {
        $url = rtrim($connector->getUrl(), '/');
        $parts = explode('/', $url);
        return array_pop($parts);
    }
}
