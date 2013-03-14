<?php

/**
 * Manager for search backends.
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

namespace VuFind\Search;

use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\EventManager\EventInterface;

use VuFindSearch\Backend\BackendInterface;

use UnexpectedValueException;

/**
 * Manager for search backends.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class BackendManager
{
    /**
     * Backend registry.
     *
     * @var ServiceLocatorInterface
     */
    protected $registry;

    /**
     * Constructor.
     *
     * @param ServiceManager $registry Backend registry
     *
     * @return void
     */
    public function __construct (ServiceLocatorInterface $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Return backend registry.
     *
     * @return ServiceLocatorInterface
     */
    public function getBackendRegistry ()
    {
        return $this->registry;
    }

    /**
     * Return named backend.
     *
     * @param string $name Backend name
     *
     * @return BackendInterface
     *
     * @throws UnexpectedValueException Retrieved backend is not an object
     * @throws UnexpectedValueException Retrieved backend does not implement BackendInterface
     */
    public function get ($name)
    {
        $backend = $this->registry->get($name, false);
        if (!is_object($backend)) {
            throw new UnexpectedValueException(sprintf('Expected backend registry to return object, got %s', gettype($backend)));
        }
        if (!$backend instanceOf BackendInterface) {
            throw new UnexpectedValueException(sprintf('Object of class %s does not implement the expected interface', get_class($backend)));
        }
        $backend->setIdentifier($name);
        return $backend;
    }

    /**
     * Return true if named backend is available.
     *
     * @param string $name Backend name
     *
     * @return boolean
     */
    public function has ($name)
    {
        return $this->registry->has($name);
    }

    /**
     * Listener for search system event `resolve`.
     *
     * @param EventInterface $e
     *
     * @return BackendInterface|null
     */
    public function onResolve (EventInterface $e)
    {
        $name = $e->getParam('backend');
        if ($name && $this->has($name)) {
            return $this->get($name);
        }
        return null;
    }
}