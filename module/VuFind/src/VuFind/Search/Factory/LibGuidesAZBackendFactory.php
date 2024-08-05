<?php

/**
 * Factory for LibGuides A-Z Databases backends.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Search\Factory;

use VuFindSearch\Backend\LibGuides\QueryBuilder;

/**
 * Factory for LibGuides A-Z Databases backends.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class LibGuidesAZBackendFactory extends LibGuidesBackendFactory
{
    /**
     * Return the service name.
     *
     * @return string
     */
    protected function getServiceName()
    {
        return 'LibGuidesAZ';
    }

    /**
     * Create the LibGuides query builder.
     *
     * @return QueryBuilder
     */
    protected function createQueryBuilder()
    {
        $builder = new QueryBuilder();

        // Widget type 2 = Databases A-Z
        $builder->setDefaultWidgetType('2');

        return $builder;
    }
}
