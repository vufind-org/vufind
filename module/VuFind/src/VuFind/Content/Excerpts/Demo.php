<?php
/**
 * Demo (fake data) excerpts content loader.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\Content\Excerpts;

/**
 * Demo (fake data) excerpts content loader.
 *
 * @category VuFind2
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Demo extends \VuFind\Content\AbstractBase
{
    /**
     * This method is responsible for generating fake author note data for testing
     * purposes.
     *
     * @param string           $key     API key
     * @param \VuFindCode\ISBN $isbnObj ISBN object
     *
     * @throws \Exception
     * @return array     Returns array with table of contents data.
     */
    public function loadByIsbn($key, \VuFindCode\ISBN $isbnObj)
    {
        // Initialize return value:
        return [
            ['Content' => 'Demo excerpt key: ' . $key],
            ['Content' => 'Demo excerpt ISBN: ' . $isbnObj->get13()],
        ];
    }
}
