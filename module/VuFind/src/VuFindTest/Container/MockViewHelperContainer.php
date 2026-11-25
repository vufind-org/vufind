<?php

/**
 * View helper container that produces mock objects.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Container;

use Laminas\View\HelperPluginManagerInterface;
use VuFind\ServiceManager\AbstractPluginManager;

/**
 * View helper container that produces mock objects.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class MockViewHelperContainer extends AbstractPluginManager implements HelperPluginManagerInterface
{
    use MockContainerTrait;

    /**
     * Return the name of the base class or interface that plug-ins must conform to.
     *
     * @return string
     */
    protected function getExpectedInterface()
    {
        return '';
    }

    /**
     * Reset stateful helpers.
     *
     * @return void
     */
    public function resetState(): void
    {
    }
}
