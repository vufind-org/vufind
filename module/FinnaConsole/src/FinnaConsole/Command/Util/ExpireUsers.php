<?php

/**
 * Console service for anonymizing expired user accounts.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2024.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */

namespace FinnaConsole\Command\Util;

use DateTime;
use Finna\Db\Service\FinnaUserServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use VuFind\Account\UserAccountService;
use VuFind\Db\Entity\UserEntityInterface;

use function floatval;
use function sprintf;

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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
#[AsCommand(
    name: 'util/expire_users'
)]
class ExpireUsers extends AbstractUtilCommand
{
    use \FinnaConsole\Command\Util\ConsoleLoggerTrait;

    /**
     * Whether comments are deleted
     */
    protected $removeComments;

    /**
     * Whether ratingd are deleted
     */
    protected $removeRatings;

    /**
     * Minimum (and default) legal age of rows to delete.
     *
     * @var int
     */
    protected $minAge = 180;

    /**
     * Constructor
     *
     * @param FinnaUserServiceInterface $userService        User database service
     * @param UserAccountService        $userAccountService User account database service
     * @param \VuFind\Config\Config     $config             Main configuration
     */
    public function __construct(
        protected FinnaUserServiceInterface $userService,
        protected UserAccountService $userAccountService,
        \VuFind\Config\Config $config
    ) {
        $this->removeComments = $config->Authentication->delete_comments_with_user ?? true;
        $this->removeRatings = $config->Authentication->delete_ratings_with_user ?? true;
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
            )
            ->addOption(
                'report',
                null,
                null,
                'do not write any changes to the database'
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
        $reportOnly = $input->getOption('report');

        // Abort if we have an invalid expiration age.
        if ($daysOld < $this->minAge) {
            $output->writeln(
                str_replace(
                    '%%age%%',
                    $this->minAge,
                    'Expiration age must be at least %%age%% days.'
                )
            );
            return 1;
        }

        if ($reportOnly) {
            $output->writeln('Dry run -- changes will not be made');
        }

        try {
            $count = 0;
            $users = $this->getExpiredUsers($daysOld);
            foreach ($users as $user) {
                $this->msg(
                    'Removing user: ' . $user->getUsername() . ' (' . $user->getId() . ')'
                );
                if (!$reportOnly) {
                    $this->userAccountService->purgeUserData($user, $this->removeComments, $this->removeRatings);
                }
                $count++;
            }

            if ($count === 0) {
                $this->msg('No expired users to remove.');
            } else {
                $this->msg("$count expired users removed.");
            }
        } catch (\Exception $e) {
            $this->err(
                'Exception: ' . $e->getMessage(),
                'Exception occurred'
            );
            while ($e = $e->getPrevious()) {
                $this->err('  Previous exception: ' . $e->getMessage());
            }
            return 1;
        }

        if ($reportOnly) {
            $output->writeln('Dry run -- changes were not made');
        }

        return 0;
    }

    /**
     * Returns all users that have not been active for given amount of days.
     *
     * @param int $days Preserve users active less than provided amount of days ago
     *
     * @return UserEntityInterface[]
     */
    protected function getExpiredUsers($days): array
    {
        $expireDate = new DateTime(sprintf('-%d days', (int)$days));
        return $this->userService->getExpiringUsers($expireDate);
    }
}
