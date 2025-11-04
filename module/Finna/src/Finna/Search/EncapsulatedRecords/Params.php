<?php

/**
 * Encapsulated Records aspect of the Search Multi-class (Params)
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022.
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
 * @package  Search_EncapsulatedRecords
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace Finna\Search\EncapsulatedRecords;

/**
 * Encapsulated Records Search Parameters
 *
 * @category VuFind
 * @package  Search_EncapsulatedRecords
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Params extends \VuFind\Search\Base\Params
{
    use \Finna\Search\FinnaParams {
        initLimit as finnaInitLimit;
    }

    /**
     * Add filters to the object based on values found in the request object.
     *
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    protected function initFilters($request)
    {
        // Special filter -- if the "id" parameter is set, limit to a specific
        // record:
        $id = $request->get('id');
        if (isset($id)) {
            $this->addFilter("ids:{$id}");
        }

        // Otherwise use standard parent behavior:
        parent::initFilters($request);
    }

    /**
     * Pull the page size parameter or set to default
     *
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    protected function initLimit($request)
    {
        if (
            $request->offsetExists('limit')
            && null === $request->offsetGet('limit')
        ) {
            // Null value is allowed (no limit)
            $this->limit = null;
        } else {
            $this->finnaInitLimit($request);
        }
    }
}
