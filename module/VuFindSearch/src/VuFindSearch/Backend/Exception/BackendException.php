<?php

/**
 * BackendException.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @link     http://vufind.org
 */
namespace VuFindSearch\Backend\Exception;

use VuFindSearch\Exception\RuntimeException;

/**
 * BackendException.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class BackendException extends RuntimeException
{
    /**
     * Tags.
     *
     * @var array
     */
    protected $tags = [];

    /**
     * Add a tag.
     *
     * @param string $tag Tag name
     *
     * @return void
     */
    public function addTag($tag)
    {
        $this->tags[] = (string)$tag;
    }

    /**
     * Return all tags.
     *
     * @return array
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * Return true if the exception has the requested tag.
     *
     * @param string $tag Tag
     *
     * @return boolean
     */
    public function hasTag($tag)
    {
        return in_array($tag, $this->tags);
    }
}
