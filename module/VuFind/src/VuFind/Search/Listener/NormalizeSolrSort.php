<?php

/**
 * Normalize SOLR sort listener.
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

use Zend\EventManager\SharedEventManagerInterface;
use Zend\EventManager\EventInterface;

/**
 * Normalize SOLR sort listener.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class NormalizeSolrSort implements ListenerInterface
{
    /**
     * Table for sort normalization.
     *
     * @var array
     */
    protected $normalizeSortTable = array(
        'year' => array('field' => 'publishDateSort', 'order' => 'desc'),
        'publishDateSort' => array('field' => 'publishDateSort', 'order' => 'desc'),
        'author' => array('field' => 'authorStr', 'order' => 'asc'),
        'title' => array('field' => 'title_sort', 'order' => 'asc'),
        'relevance' => array('field' => 'score', 'order' => 'desc'),
        'callnumber' => array('field' => 'callnumber', 'order' => 'asc'),
    );

    /**
     * Backend.
     *
     * @var BackendInterface
     */
    protected $backend;

    /**
     * Constructor.
     *
     * @param BackendInterface $backend Backend
     *
     * @return void
     */
    public function __construct (BackendInterface $backend)
    {
        $this->backend = $backend;
    }

    /**
     * Attach listener to shared event manager.
     *
     * @param SharedEventManagerInterface $manager Shared event manager
     *
     * @return void
     */
    public function attach (SharedEventManagerInterface $manager)
    {
        $manager->attach('VuFind\Search', 'search.pre', array($this, 'onSearchPre'));
    }

    /**
     * Normalize sort directive.
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
            if ($params) {
                $table  = $this->normalizeSortTable;

                $sort = $params->get('sort') ?: 'relevance';
                if (is_array($sort)) {
                    $sort = end($sort);
                }

                $normalized = array();
                foreach (explode(',', $sort) as $component) {
                    $parts = explode(' ', $component);
                    $field = reset($parts);
                    $order = next($parts);
                    if (isset($table[$field])) {
                        $normalized[] = sprintf(
                            '%s %s',
                            $table[$field]['field'],
                            $order ?: $table[$field]['order']
                        );
                    } else {
                        $normalized[] = $component;
                    }
                }
                $params->set('sort', implode(',', $normalized));
            }
        }
        return $event;
    }
}