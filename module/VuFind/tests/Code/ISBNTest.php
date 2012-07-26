<?php
/**
 * ISBN Test Class
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @link     http://vufind.org/wiki/unit_tests Wiki
 */
namespace VuFind\Tests\Code;
use VuFind\Code\ISBN;

/**
 * ISBN Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/unit_tests Wiki
 */
class ISBNTest extends \VuFind\Tests\TestCase
{
    /**
     * Test Valid ISBN-10.
     *
     * @return void
     */
    public function testValidISBN10()
    {
        $isbn = new ISBN('0123456789');
        $this->assertEquals($isbn->get10(), '0123456789');
        $this->assertEquals($isbn->get13(), '9780123456786');
        $this->assertTrue($isbn->isValid());
    }

    /**
     * Test Valid ISBN-13.
     *
     * @return void
     */
    public function testValidISBN13()
    {
        $isbn = new ISBN('9780123456786');
        $this->assertEquals($isbn->get10(), '0123456789');
        $this->assertEquals($isbn->get13(), '9780123456786');
        $this->assertTrue($isbn->isValid());
    }

    /**
     * Test Valid ISBN-10 with dashes.
     *
     * @return void
     */
    public function testValidISBN10WithDashes()
    {
        $isbn = new ISBN('0-12-345678-9');
        $this->assertEquals($isbn->get10(), '0123456789');
        $this->assertEquals($isbn->get13(), '9780123456786');
        $this->assertTrue($isbn->isValid());
    }

    /**
     * Test Valid ISBN-13 with dashes.
     *
     * @return void
     */
    public function testValidISBN13WithDashes()
    {
        // Valid ISBN-13 with dashes:
        $isbn = new ISBN('978-0-12-345678-6');
        $this->assertEquals($isbn->get10(), '0123456789');
        $this->assertEquals($isbn->get13(), '9780123456786');
        $this->assertTrue($isbn->isValid());
    }

    /**
     * Test Valid ISBN-13 that is not part of the Bookland EAN.
     *
     * @return void
     */
    public function testValidISBN13OutsideOfBooklandEAN()
    {
        // Valid ISBN-13 outside of Bookland EAN:
        $isbn = new ISBN('9790123456785');
        $this->assertEquals($isbn->get10(), false);
        $this->assertEquals($isbn->get13(), '9790123456785');
        $this->assertTrue($isbn->isValid());
    }

    /**
     * Test Invalid ISBN-10.
     *
     * @return void
     */
    public function testInvalidISBN10()
    {
        // Invalid ISBN-10:
        $isbn = new ISBN('2314346323');
        $this->assertEquals($isbn->get10(), false);
        $this->assertEquals($isbn->get13(), false);
        $this->assertFalse($isbn->isValid());
    }
}