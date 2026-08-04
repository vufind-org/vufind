<?php

/**
 * Abstract Test Class for element making helpers.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2019.
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
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Unit;

/**
 * Abstract Test Class for element making helpers.
 *
 * @category VuFind
 * @package  Tests
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
abstract class AbstractMakeTagTestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * Get makeTag helper with mock view.
     *
     * @return \VuFind\View\Helper\Root\MakeTag
     */
    protected function getMakeTagHelper(): \VuFind\View\Helper\Root\MakeTag
    {
        return new \VuFind\View\Helper\Root\MakeTag(
            new \Laminas\View\Helper\HtmlAttributes(),
            new \Laminas\View\Helper\EscapeHtml(),
        );
    }
}
