<?php

/**
 * Record cache aware marker interface.
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Record
 * @author   Markus Beh <markus.beh@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Record\Cache;

/**
 * RecordCacheAwareInterface
 *
 * @category VuFind
 * @package  Record
 * @author   Markus Beh <markus.beh@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
interface RecordCacheAwareInterface
{
    /**
     * Set record cache
     *
     * @param \VuFind\Record\Cache $recordCache record cache
     *
     * @return void
     */
    public function setRecordCache(\VuFind\Record\Cache $recordCache);

    /**
     * Get record cache
     *
     * @return \VuFind\Record\Cache
     */
    public function getRecordCache();
}
