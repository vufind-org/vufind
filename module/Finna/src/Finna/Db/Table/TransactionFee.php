<?php
/**
 * Table Definition for online payment transaction fee
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
 * @author   Leszek Manicki <leszek.z.manicki@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Db\Table;

/**
 * Table Definition for online payment transaction fee
 *
 * @category VuFind2
 * @package  Db_Table
 * @author   Leszek Manicki <leszek.z.manicki@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class TransactionFee extends \VuFind\Db\Table\Gateway
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('finna_fee', 'Finna\Db\Row\TransactionFee');
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
        $fee->title = isset($fine['title']) ? $fine['title'] : '';
        $fee->type = $fine['fine'];
        $fee->amount = $fine['amount'];
        $fee->currency = $currency;
        if (!$fee->amount) {
            return false;
        }
        if (!$fee->save()) {
            return false;
        }

        $table = $this->getDbTable('TransactionFees');
        $row = $table->createRow();
        $row->transaction_id = $transactionId;
        $row->fee_id = $fee->id;
        if (!$row->save()) {
            return false;
        }
        return true;
    }
}
