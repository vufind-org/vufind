<?php

/**
 * Unit tests for multiindex listener.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2013.
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
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace VuFindTest\Search\Solr;

use VuFindSearch\ParamBag;
use VuFindSearch\Backend\Solr\Backend;
use VuFindSearch\Backend\Solr\Connector;

use VuFind\Search\Solr\MultiIndexListener;
use VuFindTest\Unit\TestCase;
use Zend\EventManager\Event;

/**
 * Unit tests for multiindex listener.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class MultiIndexListenerTest extends TestCase
{
    /**
     * Specs used for stripping tests.
     *
     * @var array
     */
    protected static $specs = array(
        'test' => array(
            'QueryFields' => array(
                'A' => array(
                    array('onephrase', 500),
                    array('and', 200)
                ),
                'B' => array(
                    array('and', 100),
                    array('or', 50),
                ),
                0 => array(
                    0 => array('AND', 50),
                    'C' => array(
                        array('onephrase', 200),
                    ),
                    'D' => array(
                        array('onephrase', 300),
                    ),
                    '-E' => array(
                        array('or', '~')
                    )
                )
            )
        )
    );

    /**
     * Available shards used for stripping tests.
     *
     * @var array
     */
    protected static $shards = array(
        'a' => 'example.org/a',
        'b' => 'example.org/b',
        'c' => 'example.org/c',
    );

    /**
     * Shard fields used for stripping tests.
     *
     * @var array
     */
    protected static $fields = array(
        'a' => array('field_1', 'field_3'),
        'b' => array('field_3'),
    );

    /**
     * Backend.
     *
     * @var BackendInterface
     */
    protected $backend;

    /**
     * Prepare listener.
     *
     * @var MultiIndexListener
     */
    protected $listener;

    /**
     * Setup.
     *
     * @return void
     */
    protected function setup ()
    {
        $connector      = new Connector('http://example.org/');
        $this->backend  = new Backend($connector);
        $this->listener = new MultiIndexListener($this->backend, self::$shards, self::$fields, self::$specs);
    }

    /**
     * Strip fields from a field facet.
     *
     * @return void
     */
    public function testStripFacetFields ()
    {
        $params   = new ParamBag(
            array(
                'facet.field' => array('field_1', 'field_2', 'field_3'), 
                'shards' => array(self::$shards['b'], self::$shards['c']),
            )
        );
        $event    = new Event('pre', $this->backend, array('params' => $params));
        $this->listener->onSearchPre($event);

        $facets   = $params->get('facet.field');
        sort($facets);
        $this->assertEquals(array('field_1', 'field_2'), $facets);
    }

    /**
     * Apply strip to empty specs.
     *
     * @return void
     */
    public function testStripSpecsEmptySpecs ()
    {
        $this->setProperty($this->listener, 'specs', array());
        $specs = $this->callMethod($this->listener, 'getSearchSpecs', array(array('A', 'B', 'E')));
        $this->assertEmpty($specs);
    }

    /**
     * Don't strip anything.
     *
     * @return void
     */
    public function testStripSpecsNoFieldsToStrip ()
    {
        $specs = $this->callMethod($this->listener, 'getSearchSpecs', array(array('F', 'G', 'H')));
        $this->assertEquals($specs, self::$specs);
    }

    /**
     * Strip specs.
     *
     * @return void
     */
    public function testStripSpecsStrip ()
    {
        $specs = $this->callMethod($this->listener, 'getSearchSpecs', array(array('A', 'B', 'E')));
        $this->assertEquals(
            array('test' => array(
                      'QueryFields' => array(
                          0 => array(
                              0 => array('AND', 50),
                              'C' => array(
                                  array('onephrase', 200)
                              ),
                              'D' => array(
                                  array('onephrase', 300)
                              )
                          )
                      )
                  )
            ),
            $specs
        );
    }

    /**
     * Strip an entire QueryFields section.
     *
     * @return void
     */
    public function testStripSpecsAllQueryFields ()
    {
        $specs = $this->callMethod($this->listener, 'getSearchSpecs', array(array('A', 'B', 'C', 'D', 'E')));
        $this->assertEquals(
            array('test' => array('QueryFields' => array())),
            $specs
        );
    }
}
