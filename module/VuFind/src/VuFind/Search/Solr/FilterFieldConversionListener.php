<?php

/**
 * Listener to convert one field to another in filters (for legacy purposes).
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
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Search\Solr;

use Zend\EventManager\SharedEventManagerInterface;
use Zend\EventManager\EventInterface;

/**
 * Listener to convert one field to another in filters (for legacy purposes).
 *
 * @category VuFind2
 * @package  Search
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
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
        $manager->attach('VuFind\Search', 'pre', [$this, 'onSearchPre']);
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
        $params = $event->getParam('params');
        $fq = $params->get('fq');
        if (is_array($fq) && !empty($fq)) {
            // regex lookahead to ignore strings inside quotes:
            $lookahead = '(?=(?:[^\"]*+\"[^\"]*+\")*+[^\"]*+$)';
            $new_fq = [];
            foreach ($fq as $currentFilter) {
                foreach ($this->map as $oldField => $newField) {
                    $currentFilter = preg_replace(
                        "/\b$oldField:$lookahead/", "$newField:", $currentFilter
                    );
                }
                $new_fq[] = $currentFilter;
            }
            $params->set('fq', $new_fq);
        }

        return $event;
    }
}