<?php
/**
 * VuDL Connection Manager Test Class
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
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
namespace VuDLTest;
use VuDL\Connection\Manager;

/**
 * VuDL Connection Manager Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class ManagerTest extends \VuFindTest\Unit\TestCase
{
    protected function getTestSubject()
    {
        return new \VuDL\Connection\Manager(['First', 'Second'], new FakeServiceLocator());
    }

    public function testManager()
    {
        $subject = $this->getTestSubject();
        $this->assertEquals('First',  $subject->infoInFirst());
        $this->assertEquals('First 41',  $subject->paramPassing(41));
        $this->assertEquals('Second', $subject->nullInFirst());
        $this->assertEquals('Second', $subject->missingMethodInFirst());
    }

    public function testAllNull()
    {
        $subject = $this->getTestSubject();
        try {
            $subject->allNull();
        } catch(\Exception $e) {
            $this->assertEquals(
                'VuDL Connection Failed to resolved method "allNull"',
                $e->getMessage()
            );
            return;
        }

        $this->fail('Exception not thrown for all null response');
    }

    public function testMissingMethod()
    {
        $subject = $this->getTestSubject();
        try {
            $subject->missingMethod();
        } catch(\Exception $e) {
            $this->assertEquals(
                'VuDL Connection Failed to resolved method "missingMethod"',
                $e->getMessage()
            );
            return;
        }

        $this->fail('Exception not thrown for missing method');
    }
}

class FakeServiceLocator
{
    public function get($class)
    {
        if($class == "VuDL\\Connection\\First") {
            return new First();
        } else {
            return new Second();
        }
    }
}

class First
{
    public function infoInFirst()
    {
        return 'First';
    }
    public function paramPassing($n)
    {
        return 'First ' . $n;
    }
    public function nullInFirst()
    {
        return null;
    }
    public function allNull()
    {
        return null;
    }
}

class Second
{
    public function nullInFirst()
    {
        return 'Second';
    }
    public function missingMethodInFirst()
    {
        return 'Second';
    }
    public function allNull()
    {
        return null;
    }
}