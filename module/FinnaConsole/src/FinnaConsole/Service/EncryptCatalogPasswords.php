<?php
/**
 * Console service for encrypting ILS passwords.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  Service
 * @author   Riikka Kalliomäki <riikka.kalliomaki@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class EncryptCatalogPasswords extends AbstractService
{
    /**
     * Table for user accounts
     *
     * @var \VuFind\Db\Table\User
     */
    protected $table;

    /**
     * Main configuration
     *
     * @var \Zend\Config\Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param \VuFind\Db\Table\User $table  User table.
     * @param \Zend\Config\Config   $config Main configuration.
     */
    public function __construct(
        \VuFind\Db\Table\User $table, \Zend\Config\Config $config
    ) {
        $this->table = $table;
        $this->config = $config;
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
        if (!isset($arguments[0]) || $arguments[0] != 'Y') {
            echo "Usage:\n  php index.php util encrypt_catalog_passwords Y\n\n"
                . "  Encrypt ILS passwords of all users and their library cards.\n";
            return false;
        }

        if (!isset($this->config->Authentication->encrypt_ils_password)
            || !$this->config->Authentication->encrypt_ils_password
        ) {
            echo "Encryption not enabled in config (check Authentication section)\n";
            return false;
        }

        $users = $this->table->select();
        $count = 0;
        $usersChanged = 0;
        $cardsChanged = 0;

        foreach ($users as $user) {
            if (strncmp($user->username, 'deleted:', 8) === 0) {
                continue;
            }
            echo "Checking user: " . $user->username . "\n";
            if (null !== $user->cat_password) {
                $user->saveCredentials($user->cat_username, $user->cat_password);
                ++$usersChanged;
                echo "  Password encrypted\n";
            }
            if ($user->libraryCardsEnabled()) {
                foreach ($user->getLibraryCards() as $card) {
                    if (null !== $card->cat_password) {
                        try {
                            $user->saveLibraryCard(
                                $card->id, $card->card_name, $card->cat_username,
                                $card->cat_password, $card->home_library
                            );
                            echo "  Library card {$card->id} password encrypted\n";
                        } catch (\VuFind\Exception\LibraryCard $e) {
                            // @codingStandardsIgnoreLine
                            if ($e->getMessage() == 'Username is already in use in another library card') {
                                // Duplicate library card, remove it
                                $user->deleteLibraryCard($card->id);
                                echo "  ***** Library card {$card->id}: "
                                    . "removed duplicate *****\n";
                            }
                        }
                        ++$cardsChanged;
                    }
                }
            }
            ++$count;
        }

        if ($count === 0) {
            echo "No users found\n";
        } else {
            echo "$count users processed with $usersChanged users"
                . " and $cardsChanged library cards encypted\n";
        }

        return true;
    }
}
