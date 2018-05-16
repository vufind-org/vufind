<?php
/**
 * Table Definition for tags
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) Universitätsbibliothek Tübingen 2018
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
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace IxTheo\Db\Table;
use VuFind\Db\Row\RowGateway;
use VuFind\Db\Table\Tags as VuFindTags;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Predicate\Predicate;
use Zend\Db\Sql\Select;

class Tags extends VuFindTags
{
    /**
     * Constructor
     *
     * @param Adapter       $adapter       Database adapter
     * @param PluginManager $tm            Table manager
     * @param array         $cfg           Zend Framework configuration
     * @param RowGateway    $rowObj        Row prototype object (null for default)
     * @param bool          $caseSensitive Are tags case sensitive?
     * @param string        $table         Name of database table to interface with
     */
    public function __construct(Adapter $adapter, \VuFind\Db\Table\PluginManager $tm, $cfg,
        RowGateway $rowObj = null, $caseSensitive = false, $table = 'tags'
    ) {
error_log("IN IXTHEO TAGS TABLE");
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
    }

    public function dummy() {

        error_log("DUMMY DUMMY");
    }

}
