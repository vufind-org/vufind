<?php

/**
 * Mink test class for the VuFind APIs.
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

use Behat\Mink\Element\Element;

/**
 * Mink test class for the VuFind APIs.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ApiTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * Make a record retrieval API call and return the resulting page object.
     *
     * @param string $id Record ID to retrieve.
     *
     * @return Element
     */
    protected function makeRecordApiCall($id = 'testbug2'): Element
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/api');
        $page = $session->getPage();
        $this->clickCss($page, '#operations-Record-get_record button');
        $this->clickCss($page, '#operations-Record-get_record .try-out button');
        $this->findCssAndSetValue($page, '#operations-Record-get_record input[type="text"]', $id);
        $this->clickCss($page, '#operations-Record-get_record .execute-wrapper button');
        return $page;
    }

    /**
     * Test that the API is disabled by default.
     *
     * @return void
     */
    #[\VuFindTest\Attribute\HtmlValidation(false)]
    public function testApiDisabledByDefault(): void
    {
        $page = $this->makeRecordApiCall();
        $this->assertEquals(
            '403',
            $this->findCssAndGetText($page, '.live-responses-table .response td.response-col_status')
        );
    }

    /**
     * Test that the API can be turned on and accessed via Swagger UI.
     *
     * @return void
     */
    #[\VuFindTest\Attribute\HtmlValidation(false)]
    public function testEnabledRecordApi(): void
    {
        $this->changeConfigs(
            [
                'permissions' => [
                    'enable-record-api' => [
                        'permission' => 'access.api.Record',
                        'require' => 'ANY',
                        'role' => 'guest',
                    ],
                ],
            ]
        );
        $page = $this->makeRecordApiCall();
        $this->assertEquals(
            '200',
            $this->findCssAndGetText($page, '.live-responses-table .response td.response-col_status')
        );
    }
}
