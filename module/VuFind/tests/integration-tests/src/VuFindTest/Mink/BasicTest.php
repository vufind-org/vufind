<?php
/**
 * Very simple Mink test class.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2011.
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFindTest\Mink;

/**
 * Very simple Mink test class.
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class BasicTest extends \VuFindTest\Unit\MinkTestCase
{
    /**
     * Test that the home page is available.
     *
     * @return void
     */
    public function testHomePage()
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/Home');
        $this->assertHttpStatus(200);
        $page = $session->getPage();
        $this->assertTrue(false !== strstr($page->getContent(), 'VuFind'));
    }

    /**
     * Test that AJAX availability status is working.
     *
     * @return void
     */
    public function testAjaxStatus()
    {
        // Search for a known record:
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/Home');
        $page = $session->getPage();
        $this->findCss($page, '.searchForm [name="lookfor"]')
            ->setValue('id:testsample1');
        $this->findCss($page, '.btn.btn-primary')->click();
        $this->snooze();

        // Check for sample driver location/call number in output (this will
        // only appear after AJAX returns):
        $this->assertEquals(
            'A1234.567',
            $this->findCss($page, '.callnumber')->getText()
        );
        $this->assertEquals(
            '3rd Floor Main Library',
            $this->findCss($page, '.location')->getText()
        );
    }
}
