<?php

/**
 * Regex Test Class.
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Catalog
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 **/

namespace VuFindTest;

use Exception;
use PHPUnit\Framework\TestCase;
use VuFind\Config\YamlReader;
use VuFind\Regex\Regex;
use VuFind\Regex\RegexFactory;
use VuFindTest\Container\MockContainer;

/**
 * Regex Test Class.
 *
 * @category VuFind
 * @package  Tests
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class RegexTest extends TestCase
{
    /**
     * Getter for a sample config.
     *
     * @return array[]
     */
    protected static function getConfig(): array
    {
        return [
            'regex_name1' => [
                '/Case sensitive pattern/',
                '/CaSe InSeNsiTiVe PaTtErn/i',
                '/wildcard.* pattern/i',
            ],
            'regex_name2' => [
                '/Case sensitive pattern/',
                '/another pattern/i',
                '/^whole pattern$/',
            ],
        ];
    }

    /**
     * Test for Regex class.
     *
     * @return void
     * @throws Exception
     */
    public function testRegex(): void
    {
        $regex = new Regex(self::getConfig());
        $this->assertFalse($regex->matches('regex_name1', 'Not matching pattern'));
        $this->assertTrue($regex->matches('regex_name1', 'String containing Case sensitive pattern'));
        $this->assertTrue($regex->matches('regex_name1', 'String containing Case insensitive pattern'));
        $this->assertTrue($regex->matches('regex_name1', 'String containing wildcard pattern'));
        $this->assertTrue($regex->matches('regex_name1', 'String containing wildcard123 pattern'));

        $this->assertFalse($regex->matches('regex_name2', 'Not matching pattern'));
        $this->assertTrue($regex->matches('regex_name2', 'String containing Case sensitive pattern'));
        $this->assertFalse($regex->matches('regex_name2', 'String containing Case Sensitive Pattern'));
        $this->assertTrue($regex->matches('regex_name2', 'String containing another pattern and more'));
        $this->assertFalse($regex->matches('regex_name2', 'String containing whole pattern and more'));
        $this->assertTrue($regex->matches('regex_name2', 'whole pattern'));

        $this->assertTrue($regex->matches('regex_name3', 'pattern', true));
        $this->assertFalse($regex->matches('regex_name3', 'pattern', false));

        $regex->setConfig([]);
        $this->assertFalse($regex->matches('regex_name2', 'whole pattern', false));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The regex named "regex_name2" does not exist in the config.');
        $this->assertFalse($regex->matches('regex_name2', 'whole pattern'));
    }

    /**
     * Test the Regex factory.
     *
     * @return void
     * @throws \PHPUnit\Framework\MockObject\Exception
     * @throws \Psr\Container\ContainerExceptionInterface&\Throwable
     */
    public function testFactory(): void
    {
        $yaml = $this->createMock(YamlReader::class);
        $yaml->expects($this->once())->method('get')->willReturn(['regex' => ['/pattern/i']]);

        $container = $this->createMock(MockContainer::class);
        $container->expects($this->once())->method('get')->willReturn($yaml);

        $factory = new RegexFactory();
        $regex = $factory($container, Regex::class);
        $this->assertInstanceOf(Regex::class, $regex);

        $this->assertTrue($regex->matches('regex', 'string with pattern'));
        $this->assertFalse($regex->matches('regex', 'string without p a t t e r n'));
    }
}
