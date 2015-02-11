<?php

/**
 * SOLR 3.x error listener.
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
namespace VuFind\Search\Solr\V3;

use VuFind\Search\Solr\AbstractErrorListener;

use VuFindSearch\Backend\Exception\HttpErrorException;

use Zend\EventManager\EventInterface;

/**
 * SOLR 3.x error listener.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class ErrorListener extends AbstractErrorListener
{
    /**
     * VuFindSearch.error
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchError(EventInterface $event)
    {
        $backend = $event->getParam('backend_instance');
        if ($this->listenForBackend($backend)) {
            $error  = $event->getTarget();
            if ($error instanceof HttpErrorException) {
                $reason = $error->getResponse()->getReasonPhrase();
                if (stristr($reason, 'org.apache.lucene.queryParser.ParseException')
                    || stristr($reason, 'undefined field')
                ) {
                    $error->addTag('VuFind\Search\ParserError');
                }
            }
        }
        return $event;
    }
}