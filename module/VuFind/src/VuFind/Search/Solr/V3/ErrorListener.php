<?php

/**
 * SOLR 3.x error listener.
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

namespace VuFind\Search\Solr\V3;

use Laminas\EventManager\EventInterface;
use VuFind\Search\Solr\AbstractErrorListener;
use VuFindSearch\Backend\Exception\HttpErrorException;

/**
 * SOLR 3.x error listener.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
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
        $command = $event->getParam('command');
        if ($this->listenForBackend($command->getTargetIdentifier())) {
            $error = $event->getParam('error');
            if ($error instanceof HttpErrorException) {
                $reason = $error->getResponse()->getReasonPhrase();
                if (
                    stristr($reason, 'org.apache.lucene.queryParser.ParseException')
                    || stristr($reason, 'undefined field')
                ) {
                    $error->addTag(self::TAG_PARSER_ERROR);
                }
            }
        }
        return $event;
    }
}
