<?php

/**
 * Abstract base class of SOLR error listeners.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2013.
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
 * @link     http://vufind.org   Main Site
 */

namespace VuFind\Search\Solr;

use VuFindSearch\Backend\BackendInterface;

use Zend\EventManager\EventInterface;

/**
 * Abstract base class of SOLR error listeners.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

abstract class AbstractErrorListener
{

    /**
     * Tag indicating a parser error.
     *
     * @var string
     */
    const TAG_PARSER_ERROR = 'VuFind\Search\ParserError';

    /**
     * Backends to listen for.
     *
     * @var array
     */
    protected $backends;

    /**
     * Constructor.
     *
     * @param string $backend Name of backend to listen for
     *
     * @return void
     */
    public function __construct($backend)
    {
        $this->backends = array();
        $this->addBackend($backend);
    }

    /**
     * Add backend to listen for.
     *
     * @param BackendInterface|string $backend Backend instance or name of backend
     *
     * @return void
     */
    public function addBackend($backend)
    {
        if ($backend instanceOf BackendInterface) {
            $this->backends[] = $backend->getName();
        } else {
            $this->backends[] = $backend;
        }
    }

    /**
     * Return true if listeners listens for backend errors.
     *
     * @param BackendInterface|string $backend Backend instance or name of backend
     *
     * @return boolean
     */
    public function listenForBackend($backend)
    {
        if ($backend instanceOf BackendInterface) {
            $backend = $backend->getName();
        }
        return in_array($backend, $this->backends);
    }

    /**
     * VuFindSearch.error event.
     *
     * @param EventInterface $event The event
     *
     * @return EventInterface
     */
    abstract public function onSearchError(EventInterface $event);
}