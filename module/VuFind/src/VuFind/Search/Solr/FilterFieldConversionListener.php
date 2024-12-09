<?php

/**
 * Listener to convert one field to another in filters (for legacy purposes).
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
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Search\Solr;

use Laminas\EventManager\EventInterface;
use Laminas\EventManager\SharedEventManagerInterface;
use VuFindSearch\Service;

use function is_array;

/**
 * Listener to convert one field to another in filters (for legacy purposes).
 *
 * @category VuFind
 * @package  Search
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class FilterFieldConversionListener
{
    /**
     * Map of old field => new field.
     *
     * @var array
     */
    protected $map;

    /**
     * Constructor.
     *
     * @param array $map Map of old field => new field.
     *
     * @return void
     */
    public function __construct(array $map)
    {
        $this->map = $map;
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
     * Set up conditional hidden filters.
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchPre(EventInterface $event)
    {
        $params = $event->getParam('command')->getSearchParameters();
        $fq = $params->get('fq');
        if (is_array($fq) && !empty($fq)) {
            // regex lookahead to ignore strings inside quotes:
            $lookahead = '(?=(?:[^\"]*+\"[^\"]*+\")*+[^\"]*+$)';
            $new_fq = [];
            foreach ($fq as $currentFilter) {
                foreach ($this->map as $oldField => $newField) {
                    $currentFilter = preg_replace(
                        "/\b$oldField:$lookahead/",
                        "$newField:",
                        $currentFilter
                    );
                }
                $new_fq[] = $currentFilter;
            }
            $params->set('fq', $new_fq);
        }

        return $event;
    }
}
