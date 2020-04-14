<?php
/**
 * Table Definition for online payment fee
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Db\Table;

use VuFind\Db\Row\RowGateway;
use VuFind\Db\Table\PluginManager;
use Zend\Db\Adapter\Adapter;

/**
 * Table Definition for online payment fee
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Fee extends \VuFind\Db\Table\Gateway
{
    /**
     * Constructor
     *
     * @param Adapter       $adapter Database adapter
     * @param PluginManager $tm      Table manager
     * @param array         $cfg     Zend Framework configuration
     * @param RowGateway    $rowObj  Row prototype object (null for default)
     * @param string        $table   Name of database table to interface with
     */
    public function __construct(Adapter $adapter, PluginManager $tm, $cfg,
        RowGateway $rowObj = null, $table = 'finna_fee'
    ) {
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
    }

    /**
     * Add fee to a transaction.
     *
     * @param string $transactionId Transaction ID
     * @param array  $fine          Fine data
     * @param int    $userId        User ID
     * @param string $currency      Currency
     *
     * @return boolean success
     */
    public function addFee($transactionId, $fine, $userId, $currency)
    {
        $fee = $this->createRow();
        $fee->user_id = $userId;
        $fee->transaction_id = $transactionId;
        $fee->title = $fine['title'] ?? '';
        $fee->type = mb_substr($fine['fine'], 0, 255, 'UTF-8');
        $fee->amount = $fine['balance'];
        $fee->currency = $currency;
        if (!$fee->amount) {
            return false;
        }
        if (!$fee->save()) {
            return false;
        }
        return true;
    }
}
