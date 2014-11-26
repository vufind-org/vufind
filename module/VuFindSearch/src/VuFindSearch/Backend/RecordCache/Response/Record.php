<?php

/**
 * Record cache entry model.
 *
 * PHP version 5
 *
 * Copyright (C) 2014 University of Freiburg.
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
 * @author   Markus Beh <markus.beh@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */

namespace VuFindSearch\Backend\RecordCache\Response;

use VuFindSearch\Response\RecordInterface;

class Record implements RecordInterface
{

    protected $source;

    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function setSourceIdentifier($identifier)
    {
        $this->source = $identifier;
    }

    public function getSourceIdentifier()
    {
        return $this->source;
    }

    public function getData()
    {
        return $this->data;
    }
}
