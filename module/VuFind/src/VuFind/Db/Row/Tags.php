<?php
/**
 * Row Definition for tags
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
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Db\Row;
use VuFind\Db\Table\Resource as ResourceTable, Zend\Db\Sql\Expression;

/**
 * Row Definition for tags
 *
 * @category VuFind2
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Tags extends RowGateway implements \VuFind\Db\Table\DbTableAwareInterface
{
    use \VuFind\Db\Table\DbTableAwareTrait;

    /**
     * Constructor
     *
     * @param \Zend\Db\Adapter\Adapter $adapter Database adapter
     */
    public function __construct($adapter)
    {
        parent::__construct('id', 'tags', $adapter);
    }

    /**
     * Get all resources associated with the current tag.
     *
     * @param string $source Record source (optional limiter)
     * @param string $sort   Resource field to sort on (optional)
     * @param int    $offset Offset for results
     * @param int    $limit  Limit for results (null for none)
     *
     * @return array
     */
    public function getResources($source = null, $sort = null, $offset = 0,
        $limit = null
    ) {
        // Set up base query:
        $tag = $this;
        $callback = function ($select) use ($tag, $source, $sort, $offset, $limit) {
            $select->columns(
                array(
                    new Expression(
                        'DISTINCT(?)', array('resource.id'),
                        array(Expression::TYPE_IDENTIFIER)
                    ), '*'
                )
            );
            $select->join(
                array('rt' => 'resource_tags'),
                'resource.id = rt.resource_id',
                array()
            );
            $select->where->equalTo('rt.tag_id', $tag->id);

            if (!empty($source)) {
                $select->where->equalTo('source', $source);
            }

            if (!empty($sort)) {
                ResourceTable::applySort($select, $sort);
            }

            if ($offset > 0) {
                $select->offset($offset);
            }
            if (!is_null($limit)) {
                $select->limit($limit);
            }
        };

        $table = $this->getDbTable('Resource');
        return $table->select($callback);
    }
}
