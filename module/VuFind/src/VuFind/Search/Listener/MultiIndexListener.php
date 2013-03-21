<?php

/**
 * MultiIndex listener class file.
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

namespace VuFind\Search\Listener;

use VuFindSearch\Backend\BackendInterface;
use Zend\EventManager\EventInterface;

/**
 * MultiIndex listener class file.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class MultiIndexListener
{
    /**
     * Backend.
     *
     * @var BackendInterface
     */
    protected $backend;

    /**
     * Available shards, indexed by name.
     *
     * @var array
     */
    protected $available;

    /**
     * Active shards.
     *
     * @var array
     */
    protected $active;

    /**
     * Fields to strip, indexed by shard name.
     *
     * @var array
     */
    protected $stripfields;

    /**
     * Base search specs.
     *
     * @var array
     */
    protected $specs;

    /**
     * Constructor.
     *
     * @param BackendInterface $backend     Backend
     * @param array            $shards      Available shards, indexed by name
     * @param array            $stripfields Fields to strip, indexed by shard name
     * @param array            $specs       Base search specs
     *
     * @return void
     */
    public function __construct (BackendInterface $backend, array $shards, array $stripfields, array $specs)
    {
        $this->specs       = $specs;
        $this->active      = array();
        $this->backend     = $backend;
        $this->available   = $shards;
        $this->stripfields = $stripfields;
    }

    /**
     * Set active shards.
     *
     * @param array $active Active shards
     *
     * @return void
     */
    public function setActiveShards (array $active)
    {
        $this->active = array_combine($active, $active);
    }

    /**
     * VuFindSearch.pre()
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchPre (EventInterface $event)
    {
        $backend = $event->getTarget();
        if ($backend === $this->backend) {
            $params = $event->getParam('params');
            $fields = $this->getFields();
            $specs  = $this->getSearchSpecs($fields);
            $backend->getQueryBuilder()->setSpecs($specs);
            $facets = $params->get('facet.field') ?: array();
            $params->set('facet.field', array_diff($facets, $fields));
            $shards = array_intersect_key($this->available, $this->active);
            $params->set('shards', implode(',', $shards));
        }
        return $event;
    }

    /// Internal API

    /**
     * Return array of fields to strip.
     *
     * @return array
     */
    protected function getFields ()
    {
        $fields = array();
        foreach ($this->stripfields as $shard => $strip) {
            if (isset($this->active[$shard])) {
                $fields = array_merge($fields, $strip);
            }
        }
        return array_unique($fields);
    }

    /**
     * Strip fields from base search specs.
     *
     * @param array $fields Fields to strip
     *
     * @return array
     */
    protected function getSearchSpecs (array $fields)
    {
        $specs  = array();
        $fields = array_merge(
            $fields,
            array_map(
                function ($field) {
                    return "-{$field}";
                },
                $fields
            )
        );
        foreach ($this->specs as $handler => $spec) {
            $specs[$handler] = array();
            foreach ($spec as $component => $settings) {
                switch ($component) {
                case 'QueryFields':
                    $specs[$handler][$component] = $this->stripSpecsQueryFields($settings, $fields);
                    break;
                default:
                    $specs[$handler][$component] = $settings;
                    break;
                }
            }
        }
        return $specs;
    }

    /**
     * Strip fields from a search specs QueryFields section.
     *
     * @param array $settings QueryField section
     * @param array $fields   Fields to strip
     *
     * @return array
     */
    protected function stripSpecsQueryFields (array $settings, array $fields)
    {
        $stripped = array();
        foreach ($settings as $field => $rule) {
            if (is_numeric($field)) {
                $group = array();
                $type  = reset($rule);
                while (next($rule) !== false) {
                    if (!in_array(key($rule), $fields)) {
                        $group[key($rule)] = current($rule);
                    }
                }
                if ($group) {
                    array_unshift($group, $type);
                    $stripped[$field] = $group;
                }
            } else {
                if (!in_array($field, $fields, true)) {
                    $stripped[$field] = $rule;
                }
            }
        }
        return $stripped;
    }
}