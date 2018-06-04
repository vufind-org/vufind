<?php
/**
 * Console service for anonymizing expired user accounts.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015-2016.
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
 * @package  Service
 * @author   Riikka Kalliomäki <riikka.kalliomaki@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace FinnaConsole\Service;

use Zend\Db\Sql\Select;

/**
 * Console service for anonymizing expired user accounts.
 *
 * @category VuFind
 * @package  Service
 * @author   Riikka Kalliomäki <riikka.kalliomaki@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class ExpireUsers extends AbstractService
{
    /**
     * Table for user accounts
     *
     * @var \VuFind\Db\Table\User
     */
    protected $table;

    /**
     * Whether comments are deleted
     */
    protected $removeComments;

    /**
     * Constructor
     *
     * @param \VuFind\Db\Table\User $table          User table
     * @param bool                  $removeComments Whether to delete comments
     */
    public function __construct(\VuFind\Db\Table\User $table, $removeComments)
    {
        $this->table = $table;
        $this->removeComments = $removeComments;
    }

    /**
     * Run service.
     *
     * @param array $arguments Command line arguments.
     *
     * @return boolean success
     */
    public function run($arguments)
    {
        if (!isset($arguments[0]) || (int)$arguments[0] < 180) {
            echo "Usage:\n  php index.php util expire_users <days>\n\n"
                . "  Removes all user accounts that have not been logged into\n"
                . "  for past <days> days. Values below 180 are not accepted.\n";
            return false;
        }

        $users = $this->getExpiredUsers($arguments[0]);
        $count = 0;

        foreach ($users as $user) {
            $this->msg("Removing user: " . $user->username);
            $user->delete($this->removeComments);
            $count++;
        }

        if ($count === 0) {
            $this->msg('No expired users to remove.');
        } else {
            $this->msg("$count expired users removed.");
        }

        return true;
    }

    /**
     * Returns all users that have not been active for given amount of days.
     *
     * @param int $days Preserve users active less than provided amount of days ago
     *
     * @return \Zend\Db\ResultSet\ResultSet
     */
    protected function getExpiredUsers($days)
    {
        $expireDate = date('Y-m-d', strtotime(sprintf('-%d days', (int)$days)));

        return $this->table->select(
            function (Select $select) use ($expireDate) {
                $select->where->lessThan('last_login', $expireDate);
                $select->where->notEqualTo(
                    'last_login',
                    '2000-01-01 00:00:00'
                );
            }
        );
    }
}
