<?php

/**
 * Command to write a document object to Solr.
 *
 * PHP version 8
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

use VuFindSearch\Backend\Solr\Backend;
use VuFindSearch\Backend\Solr\Document\DocumentInterface;
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
class WriteDocumentCommand extends \VuFindSearch\Command\CallMethodCommand
{
    /**
     * Document to write.
     *
     * @var DocumentInterface
     */
    protected $doc;

    /**
     * Timeout value.
     *
     * @var ?int
     */
    protected $timeout;

    /**
     * Handler to use.
     *
     * @var string
     */
    protected $handler;

    /**
     * Constructor.
     *
     * @param string            $backendId Search backend identifier
     * @param DocumentInterface $doc       Document to write
     * @param ?int              $timeout   Timeout value (null for default)
     * @param string            $handler   Handler to use
     * @param ?ParamBag         $params    Search backend parameters
     */
    public function __construct(
        string $backendId,
        DocumentInterface $doc,
        int $timeout = null,
        string $handler = 'update',
        ?ParamBag $params = null
    ) {
        $this->doc = $doc;
        $this->timeout = $timeout;
        $this->handler = $handler;
        parent::__construct(
            $backendId,
            Backend::class,
            'writeDocument',
            $params
        );
    }

    /**
     * Return search backend interface method arguments.
     *
     * @return array
     */
    public function getArguments(): array
    {
        return [
            $this->getDocument(),
            $this->getTimeout(),
            $this->getHandler(),
            $this->getSearchParameters(),
        ];
    }

    /**
     * Return document to write.
     *
     * @return DocumentInterface
     */
    public function getDocument(): DocumentInterface
    {
        return $this->doc;
    }

    /**
     * Return timeout value.
     *
     * @return int|null
     */
    public function getTimeout(): ?int
    {
        return $this->timeout;
    }

    /**
     * Return handler to use.
     *
     * @return string
     */
    public function getHandler(): string
    {
        return $this->handler;
    }
}
