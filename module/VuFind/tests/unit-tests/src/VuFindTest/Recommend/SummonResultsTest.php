<?php

/**
 * SummonResults Test Class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2022.
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
 * @package  Tests
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @author   Emmanuel Afuadajo <afuadajoe@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Recommend;

use VuFind\Recommend\SummonResults;

/**
 * SummonResults Test Class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @author   Emmanuel Afuadajo <afuadajoe@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SummonResultsTest extends AbstractSearchObjectTestCase
{
    /**
     * Get the class name of the module.
     *
     * @return string
     */
    protected function getTestClass(): string
    {
        return SummonResults::class;
    }

    /**
     * Get search class id for the module.
     *
     * @return string
     */
    protected function getExpectedSearchClassId(): string
    {
        return 'Summon';
    }

    /**
     * Get the default heading for the module.
     *
     * @return string
     */
    protected function getExpectedDefaultHeading(): string
    {
        return 'Summon Results';
    }
}
