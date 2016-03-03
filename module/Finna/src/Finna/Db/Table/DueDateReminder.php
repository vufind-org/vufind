<?php
/**
 * Table Definition for due date reminders.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2016.
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
 * @category VuFind
 * @package  Db_Table
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace Finna\Db\Table;
use VuFind\Crypt\HMAC;

/**
 * Table Definition for due date reminders.
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class DueDateReminder extends \VuFind\Db\Table\Gateway
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct(
            'finna_due_date_reminder', 'Finna\Db\Row\DueDateReminder'
        );
    }

    /**
     * Utility function for generating a token for unsubscribing a
     * due date alert.
     *
     * @param VuFind\Crypt\HMAC $hmac HMAC hash generator
     * @param object            $user User object
     * @param int               $id   ID
     *
     * @return string token
     */
    public function getUnsubscribeSecret(HMAC $hmac, $user, $id)
    {
        $data = [
            'id' => $id,
            'user_id' => $user->id,
            'created' => $user->created
        ];
        return $hmac->generate(array_keys($data), $data);
    }
}
