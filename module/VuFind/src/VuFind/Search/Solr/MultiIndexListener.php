<?php

/**
 * MultiIndex listener class file.
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Search\Solr;

use Laminas\EventManager\EventInterface;
use Laminas\EventManager\SharedEventManagerInterface;
use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Service;

use function in_array;
use function is_array;

/**
 * MultiIndex listener class file.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
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
    protected $shards;

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
    public function __construct(
        BackendInterface $backend,
        array $shards,
        array $stripfields,
        array $specs
    ) {
        $this->specs       = $specs;
        $this->backend     = $backend;
        $this->shards      = $shards;
        $this->stripfields = $stripfields;
    }

    /**
     * Attach listener to shared event manager.
     *
     * @param SharedEventManagerInterface $manager Shared event manager
     *
     * @return void
     */
    public function attach(SharedEventManagerInterface $manager)
    {
        $manager->attach(
            Service::class,
            Service::EVENT_PRE,
            [$this, 'onSearchPre']
        );
    }

    /**
     * VuFindSearch.pre()
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchPre(EventInterface $event)
    {
        $command = $event->getParam('command');
        if ($command->getTargetIdentifier() === $this->backend->getIdentifier()) {
            $params = $command->getSearchParameters();
            $allShardsContexts = ['retrieve', 'retrieveBatch'];
            if (in_array($command->getContext(), $allShardsContexts)) {
                // If we're retrieving by id(s), we should pull all shards to be
                // sure we find the right record(s).
                $params->set('shards', implode(',', $this->shards));
            } else {
                // In any other context, we should make sure our field values are
                // all legal.

                // Normalize array of strings containing comma-separated values to
                // simple array of values; check if $params->get('shards') returns
                // an array to prevent invalid argument warnings.
                $shards = $params->get('shards');
                $shards = explode(
                    ',',
                    implode(',', (is_array($shards) ? $shards : []))
                );
                $fields = $this->getFields($shards);
                $specs  = $this->getSearchSpecs($fields);
                $this->backend->getQueryBuilder()->setSpecs($specs);
                $facets = $params->get('facet.field') ?: [];
                $params->set('facet.field', array_diff($facets, $fields));
            }
        }
        return $event;
    }

    /// Internal API

    /**
     * Return array of fields to strip.
     *
     * @param array $shards Active shards
     *
     * @return array
     */
    protected function getFields(array $shards)
    {
        $fields = [];
        foreach ($this->stripfields as $name => $strip) {
            if (isset($this->shards[$name])) {
                $uri = $this->shards[$name];
                if (in_array($uri, $shards)) {
                    $fields = array_merge($fields, $strip);
                }
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
    protected function getSearchSpecs(array $fields)
    {
        $specs  = [];
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
            $specs[$handler] = [];
            foreach ($spec as $component => $settings) {
                switch ($component) {
                    case 'QueryFields':
                        $specs[$handler][$component]
                            = $this->stripSpecsQueryFields($settings, $fields);
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
    protected function stripSpecsQueryFields(array $settings, array $fields)
    {
        $stripped = [];
        foreach ($settings as $field => $rule) {
            if (is_numeric($field)) {
                $group = [];
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
