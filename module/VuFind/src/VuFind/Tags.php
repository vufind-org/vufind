<?php
/**
 * VuFind tag processing logic
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
 * @package  Tags
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/ Wiki
 */
namespace VuFind;

/**
 * VuFind tag processing logic
 *
 * @category VuFind2
 * @package  Tags
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/ Wiki
 */
class Tags
{
    /**
     * Maximum tag length.
     *
     * @var int
     */
    protected $maxLength;

    /**
     * Constructor
     *
     * @param int $maxLength Maximum tag length
     */
    public function __construct($maxLength = 64)
    {
        $this->maxLength = $maxLength;
    }

    /**
     * Parse a user-submitted tag string into an array of separate tags.
     *
     * @param string $tags User-provided tags
     *
     * @return array
     */
    public function parse($tags)
    {
        preg_match_all('/"[^"]*"|[^ ]+/', trim($tags), $words);
        $result = [];
        foreach ($words[0] as $tag) {
            // Wipe out double-quotes and trim over-long tags:
            $result[] = substr(str_replace('"', '', $tag), 0, $this->maxLength);
        }
        return array_unique($result);
    }
}
