<?php

/**
 * Mink tests for basic error handling.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

/**
 * Mink tests for basic error handling.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ErrorHandlingTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * Test error message when site email is displayed.
     *
     * @return void
     */
    public function testErrorWithEmail(): void
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl('/Record/does_not_exist'));
        $page = $session->getPage();
        $this->assertEquals(
            'An error has occurred An error occurred during execution; please try again later. Please contact the'
            . ' Library Reference Department for assistance support@myuniversity.edu',
            $this->findCssAndGetText($page, '.alert-danger')
        );
    }

    /**
     * Test error message when site email is hidden.
     *
     * @return void
     */
    public function testErrorWithoutEmail(): void
    {
        $this->changeConfigs(
            [
                'config' => [
                    'Site' => [
                        'email' => '',
                    ],
                ],
            ]
        );

        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl('/Record/does_not_exist'));
        $page = $session->getPage();
        $this->assertEquals(
            'An error has occurred An error occurred during execution; please try again later. Please contact the'
            . ' Library Reference Department for assistance',
            $this->findCssAndGetText($page, '.alert-danger')
        );
    }
}
