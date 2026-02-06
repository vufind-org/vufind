<?php

/**
 * GVI Controller
 *
 * PHP version 8
 *
 * Copyright (C) Universitätsbibliothek Mannheim 2026.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Controller
 * @author   Stefan Weil <sw@weilnetz.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Controller;

use Laminas\ServiceManager\ServiceLocatorInterface;

/**
 * GVI Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Stefan Weil <sw@weilnetz.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class GVIController extends AbstractSolrSearch
{
    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm Service locator
     */
    public function __construct(ServiceLocatorInterface $sm)
    {
        $this->searchClassId = 'GVI';
        parent::__construct($sm);
    }

    /**
     * Results action.
     *
     * @return mixed
     */
    public function resultsAction()
    {
        // Special case -- redirect tag searches.
        if ($this->params()->fromQuery('type') == 'tag') {
            // Because we're coming in from a search, we want to do a fuzzy
            // tag search, not an exact search like we would when linking to a
            // specific tag name.
            $this->getRequest()->getQuery()->set('fuzzy', 'true');
            return $this->forwardTo('Tag', 'Home');
        }

        // Default case -- standard behavior.
        return parent::resultsAction();
    }
}
