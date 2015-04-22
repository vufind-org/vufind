<?php
/**
 * Table Definition for comments
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @package  Db_Table
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Db\Table;

/**
 * Table Definition for comments
 *
 * @category VuFind2
 * @package  Db_Table
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Comments extends \VuFind\Db\Table\Comments
{
    /**
     * Get tags associated with the specified resource.
     * Added email to result.
     *
     * @param string $id     Record ID to look up
     * @param string $source Source of record to look up
     *
     * @return array|\Zend\Db\ResultSet\AbstractResultSet
     */
    public function getForResource($id, $source = 'VuFind')
    {
        $resourceTable = $this->getDbTable('Resource');
        $resource = $resourceTable->findResource($id, $source, false);
        if (empty($resource)) {
            return [];
        }

        $callback = function ($select) use ($resource) {
            $select->columns(['*']);
            $select->join(
                ['u' => 'user'], 'u.id = comments.user_id',
                ['firstname', 'lastname', 'email']
            );
            $select->where->equalTo('comments.resource_id',  $resource->id);
            $select->order('comments.created');
        };

        return $this->select($callback);
    }

}
