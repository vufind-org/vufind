<?php

/**
 * ILS driver test
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\ILS\Driver;

use PDOStatement;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\ILS\Driver\Voyager;

/**
 * ILS driver test
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class VoyagerTest extends \VuFindTest\Unit\ILSDriverTestCase
{
    use \VuFindTest\Feature\FixtureTrait;
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->driver = new Voyager(new \VuFind\Date\Converter());
    }

    /**
     * Test MARC holdings parsing.
     *
     * @return void
     */
    public function testMarcParsing(): void
    {
        $marc = $this->getFixture('marc/voyagerholdings.mrc');
        $results = $this->callMethod($this->driver, 'processRecordSegment', [$marc]);

        $this->assertEquals(
            [
                'summary' => [
                    'v. 1-5 (nos. 1-57) Jan.1931 thru Dec. 1936',
                    'v. 5-10 (nos. 58-111) Jan.1937 thru Nov.-Dec. 1941',
                    'v. 10-15 (nos. 112-183) Jan.1942 thru Dec. 1947',
                    'v. 16-19 (nos. 184-231) Jan.1948 thru Dec. 1951',
                    'v. 20-24 (nos. 232-291) Jan. 1952 thru Dec.15, 1956',
                    'v. 25-28 (nos. 292-339) Jan. 15, 1957 thru Dec., 15,1960',
                    'v. 29-33 (nos. 340-387) Jan. 15, 1961 thru Dec. 15, 1964',
                    'v. 34-37 (nos. 388-435) Jan. 15, 1965 thru Dec. 15, 1968',
                    'v. 38-41 (nos. 436-483) Jan. 15, 1969 thru Dec. 15, 1972',
                    'v. 42-44 (nos. 484-516) Jan. 15, 1973 thru Dec. 1975',
                    'v. 45-47 (nos. 517-534) Feb. 1976 thru Dec. 1978',
                    'v. 48-51 (nos. 535-558) Feb. 1979 thru Dec. 1982',
                    'v. 52-55 (nos. 559-582) Feb. 1983 thru Dec. 1986',
                    'v. 56-58 (nos. 583-600) Feb. 1987 thru Dec. 1989',
                    'v. 59-61 (nos. 601-618) Feb. 1990 thru Dec. 1992',
                    'v.62-63 (nos. 619-630) Feb. 1993 thru Dec. 1994',
                    'v. 64-65 (nos. 631-642) Feb. 1995 thru Dec. 1996',
                    'v. 66 (nos. 643-648) Feb. 1997 thru Dec. 1997 + Supplement on Year\'s Work',
                    'v. 67 (nos. 649-654) Feb. 1998 thru Dec. 1998 + Supplement on Year\'s Work',
                    'v. 68 (nos. 649-654) Feb. 1999 thru Dec. 1999 + Supplement on Year\'s Work',
                    'v. 69 (nos. 661-666) Feb. 2000 thru Dec. 2000',
                    'v. 70 (nos. 667-672) Feb. 2001 thru Dec. 2001',
                    'v. 71-72 (nos. 673-684) Feb. 2002 thru Dec. 2003',
                    'v. 73-74 (nos. 685-696) Feb. 2004 thru Dec. 2005',
                    'v. 75-76 (nos. 697-708) Feb. 2006 thru Dec. 2007',
                    'v. 77-78 (nos. 709-720) Feb. 2008 thru Dec. 2009',
                    'v. 79-80 (nos. 721-732) Feb./Apr. 2010 thru Dec. 2011',
                    'v. 81-82 (nos. 733-742) Feb. 2012 thru Winter 2013',
                    'v. 83-90 (nos. 743-773) Spring 2014 thru Fall 2021',
                    'Membership List of the Happy Hour Brotherhood, 1991',
                    'Dime Novel Sketches, Numbers 1-249, Arranged Alphabetically',
                    'Special birthday no., 1938 (photocopy)',
                    'Supplements (Golden Days (undated), Index-Digest (issues 1-159; undated), Oct. 1958, Apr. 1959,'
                    . ' Sep. 1960, Sep. 1962, Nov. 1962, Dec. 1962, Feb. 1963, Nov. 1965,  May 15, 1970, Jul. 15, 1972'
                    . ', Jul. 15, 1974, Dec. 1975, Oct. 1976, Aug. 1977, Feb. 1978, Aug. 1978, Feb. 1979, Apr. 1980, '
                    . 'Oct. 1981, Dec. 1985, Aug. 1998, Fall 2014)',
                    'Box of duplicate issues',
                ],
            ],
            $results
        );
    }

    /**
     * Test that patron usernames are correctly encoded during login.
     *
     * @return void
     */
    public function testUsernameEncodingDuringLogin(): void
    {
        // Create a mock SQL response
        $mockResult = $this->createMock(PDOStatement::class);
        $mockResult->method('fetch')->willReturn(null);

        $driver = $this->getDriverWithMockSqlResponse($mockResult);
        $this->assertNull($driver->patronLogin('Tést', 'foo'));
        $this->assertEquals([':username' => mb_convert_encoding('tést', 'ISO-8859-1', 'UTF-8')], $driver->lastBind);
        $this->assertEquals(
            'SELECT PATRON.PATRON_ID, PATRON.FIRST_NAME, PATRON.LAST_NAME, PATRON.LAST_NAME as LOGIN '
            . 'FROM .PATRON, .PATRON_BARCODE '
            . 'WHERE PATRON.PATRON_ID = PATRON_BARCODE.PATRON_ID AND '
            . 'lower(PATRON_BARCODE.PATRON_BARCODE) = :username AND PATRON_BARCODE.BARCODE_STATUS IN (1,4)',
            $driver->lastSql
        );
    }

    /**
     * Get a Voyager driver customized to return a mock SQL response.
     *
     * @param MockObject&PDOStatement $mockResult Mock result to return from executeSQL
     *
     * @return Voyager
     */
    protected function getDriverWithMockSqlResponse(MockObject&PDOStatement $mockResult): Voyager
    {
        return new class ($mockResult) extends Voyager {
            /**
             * Last SQL statement passed to executeSQL
             *
             * @var string
             */
            public string $lastSql = '';

            /**
             * Last bind array passed to executeSQL
             *
             * @var array
             */
            public array $lastBind = [];

            /**
             * Constructor
             *
             * @param MockObject&PDOStatement $mockResult Mock result to return from executeSQL
             */
            public function __construct(protected MockObject&PDOStatement $mockResult)
            {
                parent::__construct(new \VuFind\Date\Converter());
            }

            /**
             * Execute an SQL query
             *
             * @param string|array $sql  SQL statement (string or array that includes
             * bind params)
             * @param array        $bind Bind parameters (if $sql is string)
             *
             * @return PDOStatement
             */
            protected function executeSQL($sql, $bind = [])
            {
                $this->lastSql = $sql;
                $this->lastBind = $bind;
                return $this->mockResult;
            }
        };
    }
}
