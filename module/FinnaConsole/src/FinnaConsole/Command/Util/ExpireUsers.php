<?php
/**
 * Console service for anonymizing expired user accounts.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015-2020.
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
namespace FinnaConsole\Command\Util;

use Laminas\Db\Sql\Select;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console service for anonymizing expired user accounts.
 *
 * Does not use the AbstractExpireCommand since we need special processing for
 * comment removal.
 *
 * @category VuFind
 * @package  Service
 * @author   Riikka Kalliomäki <riikka.kalliomaki@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class ExpireUsers extends AbstractUtilCommand
{
    use \FinnaConsole\Command\Util\ConsoleLoggerTrait;

    /**
     * The name of the command (the part after "public/index.php")
     *
     * @var string
     */
    protected static $defaultName = 'util/expire_users';

    /**
     * Table on which to expire rows
     *
     * @var \VuFind\Db\Table\User
     */
    protected $table;

    /**
     * Whether comments are deleted
     */
    protected $removeComments;

    /**
     * Minimum (and default) legal age of rows to delete.
     *
     * @var int
     */
    protected $minAge = 180;

    /**
     * Constructor
     *
     * @param \Finna\Db\Table\User   $table  Table on which to expire rows
     * @param \Laminas\Config\Config $config Main configuration
     */
    public function __construct(
        \VuFind\Db\Table\User $table,
        \Laminas\Config\Config $config
    ) {
        $this->table = $table;
        $this->removeComments
            = $config->Authentication->delete_comments_with_user ?? true;
        parent::__construct();
    }

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setDescription('Expire old users in the database')
            ->addArgument(
                'age',
                InputArgument::OPTIONAL,
                'the age (in days) of users to expire',
                $this->minAge
            );
    }

    /**
     * Run the command.
     *
     * @param InputInterface  $input  Input object
     * @param OutputInterface $output Output object
     *
     * @return int 0 for success
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Collect arguments/options:
        $daysOld = floatval($input->getArgument('age'));

        // Abort if we have an invalid expiration age.
        if ($daysOld < $this->minAge) {
            $output->writeln(
                str_replace(
                    '%%age%%', $this->minAge,
                    'Expiration age must be at least %%age%% days.'
                )
            );
            return 1;
        }

        try {
            $count = 0;
            $users = $this->getExpiredUsers($daysOld);
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
        } catch (\Exception $e) {
            $this->err(
                "Exception: " . $e->getMessage(),
                'Exception occurred'
            );
            while ($e = $e->getPrevious()) {
                $this->err("  Previous exception: " . $e->getMessage());
            }
            exit(1);
        }

        return true;
    }

    /**
     * Returns all users that have not been active for given amount of days.
     *
     * @param int $days Preserve users active less than provided amount of days ago
     *
     * @return \Finna\Db\Row\User[]
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
