<?php

/**
 * Unit tests for NestingParamBag.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Search
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest;

use PHPUnit\Framework\TestCase;
use VuFindSearch\NestingParamBag;
use VuFindSearch\ParamBag;

use function is_string;

/**
 * Unit tests for NestingParamBag.
 *
 * @category VuFind
 * @package  Search
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class NestingParamBagTest extends TestCase
{
    /**
     * Data provider for testFrom
     *
     * @return \Iterator
     */
    public static function fromProvider(): \Iterator
    {
        yield 'simple' => [
            new ParamBag([ 'filter' => 'format:Book', 'debugQuery' => true ]),
            [],
            [ 'filter' => ['format:Book'], 'debugQuery' => [ true ], ],
        ];
        yield 'null, default create' => [
            null,
            [],
            [],
        ];
        yield 'null, create true' => [
            null,
            [ true ],
            [],
        ];
        yield 'null, create false' => [
            null,
            [ false ],
            null,
        ];
    }

    /**
     * Test static from() function.
     *
     * @param ?ParamBag $original          Original ParamBag
     * @param array     $createIfNullParam Array containing nothing or the $createIfNull bool
     * @param ?array    $expectedContent   Expected content of the NestingParamBag
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('fromProvider')]
    public function testFrom(?ParamBag $original, array $createIfNullParam, ?array $expectedContent): void
    {
        $params = NestingParamBag::from($original, ...$createIfNullParam);
        $this->assertSame($expectedContent, $params?->getArrayCopy());
    }

    /**
     * Data provider for testFromArray
     *
     * @return \Iterator
     */
    public static function fromArrayProvider(): \Iterator
    {
        yield 'simple' => [
            [ 'filter' => 'format:Book', 'rows' => 10 ],
            new NestingParamBag([ 'filter' => [ 'format:Book' ], 'rows' => [ 10 ] ]),
        ];
        yield 'two dimensions' => [
            [ 'filter' => 'format:Book', 'params' => [ 'sow' => false, 'timeAllowed' => -1 ] ],
            new NestingParamBag([
                'filter' => [ 'format:Book' ],
                 'params' => new NestingParamBag(['sow' => false, 'timeAllowed' => -1]),
            ]),
        ];
    }

    /**
     * Test static fromArray() function.
     *
     * @param array           $values   Input values
     * @param NestingParamBag $expected Expected result
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('fromArrayProvider')]
    public function testFromArray(array $values, NestingParamBag $expected): void
    {
        $params = NestingParamBag::fromArray($values);
        $this->assertEquals($expected, $params);
    }

    /**
     * Data provider for getNested method
     *
     * @return \Iterator
     */
    public static function getNestedProvider(): \Iterator
    {
        yield 'present' => [ self::buildInputParams(), 'params', 'sow', [ false ] ];
        yield 'absent' => [ self::buildInputParams(), 'params', 'debugQuery', null ];
    }

    /**
     * Test getNested method.
     *
     * @param NestingParamBag $params     Bag of nested parameters
     * @param string          $name       Parameter name
     * @param string          $nestedName Nested parameter name
     * @param ?array          $expected   Expected array of parameter values or NULL if not set
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getNestedProvider')]
    public function testGetNested(NestingParamBag $params, string $name, string $nestedName, ?array $expected): void
    {
        $this->assertSame($expected, $params->getNested($name, $nestedName));
    }

    /**
     * Test hasNestedParam method.
     *
     * @param NestingParamBag $params        Bag of nested parameters
     * @param string          $name          Parameter name
     * @param string          $nestedName    Nested parameter name
     * @param ?array          $expectedArray Expected array of parameter values or NULL if not set
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getNestedProvider')]
    public function testHasNested(
        NestingParamBag $params,
        string $name,
        string $nestedName,
        ?array $expectedArray
    ): void {
        $expected = !empty($expectedArray);
        $this->assertSame($expected, $params->hasNestedParam($name, $nestedName));
    }

    /**
     * Data provider for setNested method
     *
     * @return \Iterator
     */
    public static function setNestedProvider(): \Iterator
    {
        yield 'add value' => [
            self::buildInputParams(),
            'params', 'spellcheck', true,
             new NestingParamBag([
                'filter' => [ 'format:Book' ],
                'params' => new NestingParamBag(['sow' => false, 'timeAllowed' => -1, 'spellcheck' => true]),
             ]),
        ];
        yield 'overwrite value' => [
            self::buildInputParams(),
            'params', 'sow', true,
             new NestingParamBag([
                'filter' => [ 'format:Book' ],
                'params' => new NestingParamBag(['sow' => true, 'timeAllowed' => -1]),
             ]),
        ];
        yield 'new top-level name' => [
            self::buildInputParams(),
            'facet', 'topic_facet', 'foo',
             new NestingParamBag([
                'filter' => [ 'format:Book' ],
                'params' => new NestingParamBag(['sow' => false, 'timeAllowed' => -1]),
                'facet' => new ParamBag(['topic_facet' => 'foo']),
             ]),
        ];
    }

    /**
     * Test setNestedParam method.
     *
     * @param NestingParamBag $params     Bag of nested parameters
     * @param string          $name       Parameter name
     * @param string          $nestedName Nested parameter name
     * @param mixed           $value      Nested parameter value
     * @param NestingParamBag $expected   Expected resulting bag of nested parameters
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('setNestedProvider')]
    public function testSetNested(
        NestingParamBag $params,
        string $name,
        string $nestedName,
        mixed $value,
        NestingParamBag $expected
    ): void {
        $params->setNested($name, $nestedName, $value);
        $this->assertEquals($expected, $params);
    }

    /**
     * Data provider for addNested method
     *
     * @return \Iterator
     */
    public static function addNestedProvider(): \Iterator
    {
        yield 'existing name' => [
            self::buildInputParams(),
            'params', 'sow', true,
             new NestingParamBag([
                'filter' => [ 'format:Book' ],
                'params' => new NestingParamBag(['sow' => [false, true], 'timeAllowed' => -1]),
             ]),
        ];
        yield 'new nested name' => [
            self::buildInputParams(),
            'params', 'spellcheck', true,
             new NestingParamBag([
                'filter' => [ 'format:Book' ],
                'params' => new NestingParamBag(['sow' => [false], 'timeAllowed' => -1, 'spellcheck' => true]),
             ]),
        ];
        yield 'new top-level name' => [
            self::buildInputParams(),
            'facet', 'topic_facet', 'foo',
             new NestingParamBag([
                'filter' => [ 'format:Book' ],
                'params' => new NestingParamBag(['sow' => false, 'timeAllowed' => -1]),
                'facet' => new ParamBag(['topic_facet' => 'foo']),
             ]),
        ];
    }

    /**
     * Test addNestedParam method.
     *
     * @param NestingParamBag $params     Bag of nested parameters
     * @param string          $name       Parameter name
     * @param string          $nestedName Nested parameter name
     * @param mixed           $value      Nested parameter value
     * @param NestingParamBag $expected   Expected resulting bag of nested parameters
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('addNestedProvider')]
    public function testAddNested(
        NestingParamBag $params,
        string $name,
        string $nestedName,
        mixed $value,
        NestingParamBag $expected
    ): void {
        $params->addNested($name, $nestedName, $value);
        $this->assertEquals($expected, $params);
    }

    /**
     * Data provider for addMultiNested method
     *
     * @return \Iterator
     */
    public static function addMultiNestedProvider(): \Iterator
    {
        yield 'second level' => [
            self::buildInputParams(),
            'params',
            ['spellcheck' => true],
             new NestingParamBag([
                'filter' => [ 'format:Book' ],
                'params' => new NestingParamBag(['sow' => false, 'timeAllowed' => -1, 'spellcheck' => true]),
             ]),
        ];
        yield 'deeper' => [
            self::buildInputParams(),
            'facet',
            ['topic_facet' => ['type' => 'terms', 'field' => 'topic_facet', 'limit' => 30,
                'domain' => ['excludeTags' => 'topic_facet_filter']]],
             new NestingParamBag([
                'filter' => [ 'format:Book' ],
                'params' => new NestingParamBag(['sow' => false, 'timeAllowed' => -1]),
                'facet' => new NestingParamBag(['topic_facet' => new NestingParamBag(
                    ['type' => 'terms', 'field' => 'topic_facet', 'limit' => 30, 'domain' => new NestingParamBag(
                        ['excludeTags' => 'topic_facet_filter']
                    )]
                )]),
             ]),
        ];
    }

    /**
     * Test addMultiNested method.
     *
     * @param NestingParamBag $params   Bag of nested parameters
     * @param string          $name     Parameter name
     * @param array           $value    Nested array of parameter values
     * @param NestingParamBag $expected Expected resulting bag of nested parameters
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('addMultiNestedProvider')]
    public function testAddMultiNested(
        NestingParamBag $params,
        string $name,
        mixed $value,
        NestingParamBag $expected
    ): void {
        $params->addMultiNested($name, $value);
        $this->assertEquals($expected, $params);
    }

    /**
     * Data provider for add method
     *
     * @return \Iterator
     */
    public static function addProvider(): \Iterator
    {
        yield 'new name' => [
            self::buildInputParams(),
            'sort', 'title', true,
             new NestingParamBag([
                'filter' => [ 'format:Book' ],
                'params' => new NestingParamBag(['sow' => false, 'timeAllowed' => -1]),
                'sort' => 'title',
             ]),
        ];
        yield 'existing name' => [
            self::buildInputParams(),
            'filter', 'location:Main Library', true,
             new NestingParamBag([
                'filter' => [ 'format:Book', 'location:Main Library' ],
                'params' => new NestingParamBag(['sow' => false, 'timeAllowed' => -1]),
             ]),
        ];
        yield 'adding scalar where existing is a ParamBag' => [
            self::buildInputParams(),
            'params', true, true,
            'New values for name params are not compatible with existing values; both or neither must be a ParamBag.',
        ];
        yield 'adding ParamBag where existing is a scalar' => [
            self::buildInputParams(),
            'filter', new ParamBag(['foo' => 'bar']), true,
            'New values for name filter are not compatible with existing values; both or neither must be a ParamBag.',
        ];
    }

    /**
     * Test add method.
     *
     * @param NestingParamBag        $params      Bag of nested parameters
     * @param string                 $name        Parameter name
     * @param mixed                  $value       Parameter values
     * @param bool                   $deduplicate Whether to de-duplicate
     * @param NestingParamBag|string $expected    Expected resulting bag of nested parameters or exception message
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('addProvider')]
    public function testAdd(
        NestingParamBag $params,
        string $name,
        mixed $value,
        bool $deduplicate,
        NestingParamBag|string $expected
    ): void {
        if (is_string($expected)) {
            $this->expectExceptionMessage($expected);
        }
        $params->add($name, $value, $deduplicate);
        if ($expected instanceof NestingParamBag) {
            $this->assertEquals($expected, $params);
        }
    }

    /**
     * Data provider for testJsonSerialize method
     *
     * @return \Iterator
     */
    public static function jsonSerializeProvider(): \Iterator
    {
        yield 'basic' => [
            self::buildInputParams(),
            [
                'filter' => 'format:Book',
                'params' => ['sow' => false, 'timeAllowed' => -1],
            ],
        ];
        yield 'multiple values for same name' => [
            new NestingParamBag([
                'filter' => [ 'format:Book', 'location:Main Library'],
                'params' => new NestingParamBag(['sow' => false, 'timeAllowed' => -1]),
            ]),
            [
                'filter' => ['format:Book', 'location:Main Library'],
                'params' => ['sow' => false, 'timeAllowed' => -1],
            ],

        ];
    }

    /**
     * Test add method.
     *
     * @param NestingParamBag $params   Bag of nested parameters
     * @param array           $expected Expected array of JSON-serialized values
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('jsonSerializeProvider')]
    public function testJsonSerialize(NestingParamBag $params, array $expected): void
    {
        $serialized = $params->jsonSerialize();
        $this->assertEquals($expected, $serialized);
    }

    /**
     * Build a NestingParamBag for testing.
     *
     * @return NestingParamBag
     */
    protected static function buildInputParams(): NestingParamBag
    {
        return new NestingParamBag([
            'filter' => [ 'format:Book' ],
            'params' => new NestingParamBag(['sow' => false, 'timeAllowed' => -1]),
        ]);
    }
}
