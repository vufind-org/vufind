<?php

/**
 * Trait adding functionality to retry failed clicks.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Feature;

use Behat\Mink\Element\Element;
use Behat\Mink\Session;

/**
 * Trait adding functionality to retry failed clicks.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
trait RetryClickTrait
{
    /**
     * After a failed button click has been detected, resize the window and try again.
     *
     * @param Session $session  Mink session
     * @param Element $page     Current page element
     * @param string  $selector Selector to click
     *
     * @return void
     */
    protected function retryClickWithResizedWindow(Session $session, Element $page, string $selector): void
    {
        // For some reason, the click action does not always succeed here; resizing
        // the window and retrying seems to prevent intermittent test failures.
        echo "\n\nMink click failed; retrying with resized window!\n";
        $session->resizeWindow(1280, 200, 'current');
        $this->clickCss($page, $selector);
        $session->resizeWindow(1280, 768, 'current');
    }
}
