<?php

/**
 * Console command: switch database encryption algorithm.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2020.
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
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFindConsole\Command\Util;

use Laminas\Config\Config;
use Laminas\Crypt\BlockCipher;
use Laminas\Crypt\Symmetric\Openssl;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use VuFind\Config\Locator as ConfigLocator;
use VuFind\Config\PathResolver;
use VuFind\Config\Writer as ConfigWriter;
use VuFind\Db\Table\User as UserTable;

/**
 * Console command: switch database encryption algorithm.
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class SwitchDbHashCommand extends Command
{
    /**
     * The name of the command (the part after "public/index.php")
     *
     * @var string
     */
    protected static $defaultName = 'util/switch_db_hash';

    /**
     * VuFind configuration.
     *
     * @var Config
     */
    protected $config;

    /**
     * User table gateway
     *
     * @var UserTable
     */
    protected $userTable;

    /**
     * Config file path resolver
     *
     * @var PathResolver
     */
    protected $pathResolver;

    /**
     * Constructor
     *
     * @param Config       $config       VuFind configuration
     * @param UserTable    $userTable    User table gateway
     * @param string|null  $name         The name of the command; passing null means
     * it must be set in configure()
     * @param PathResolver $pathResolver Config file path resolver
     */
    public function __construct(
        Config $config,
        UserTable $userTable,
        $name = null,
        PathResolver $pathResolver = null
    ) {
        $this->config = $config;
        $this->userTable = $userTable;
        $this->pathResolver = $pathResolver;
        parent::__construct($name);
    }

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setDescription('Encryption algorithm switcher')
            ->setHelp(
                'Switches the encryption algorithm in the database '
                . 'and config. Expects new algorithm and (optional) new key as'
                . ' parameters.'
            )->addArgument('newmethod', InputArgument::REQUIRED, 'Encryption method')
            ->addArgument('newkey', InputArgument::OPTIONAL, 'Encryption key');
    }

    /**
     * Get a config writer
     *
     * @param string $path Path of file to write
     *
     * @return ConfigWriter
     */
    protected function getConfigWriter($path)
    {
        return new ConfigWriter($path);
    }

    /**
     * Get an OpenSsl object for the specified algorithm (or return null if the
     * algorithm is 'none').
     *
     * @param string $algorithm Encryption algorithm
     *
     * @return Openssl
     */
    protected function getOpenSsl($algorithm)
    {
        return ($algorithm == 'none') ? null : new Openssl(compact('algorithm'));
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
        // Validate command line arguments:
        $newhash = $input->getArgument('newmethod');

        // Pull existing encryption settings from the configuration:
        if (
            !isset($this->config->Authentication->ils_encryption_key)
            || !($this->config->Authentication->encrypt_ils_password ?? false)
        ) {
            $oldhash = 'none';
            $oldkey = null;
        } else {
            $oldhash = $this->config->Authentication->ils_encryption_algo
                ?? 'blowfish';
            $oldkey = $this->config->Authentication->ils_encryption_key;
        }

        // Pull new encryption settings from argument or config:
        $newkey = $input->getArgument('newkey') ?? $oldkey;

        // No key specified AND no key on file = fatal error:
        if ($newkey === null) {
            $output->writeln('Please specify a key as the second parameter.');
            return 1;
        }

        // If no changes were requested, abort early:
        if ($oldkey == $newkey && $oldhash == $newhash) {
            $output->writeln('No changes requested -- no action needed.');
            return 0;
        }

        // Initialize Openssl first, so we can catch any illegal algorithms before
        // making any changes:
        try {
            $oldCrypt = $this->getOpenSsl($oldhash);
            $newCrypt = $this->getOpenSsl($newhash);
        } catch (\Exception $e) {
            $output->writeln($e->getMessage());
            return 1;
        }

        // Next update the config file, so if we are unable to write the file,
        // we don't go ahead and make unwanted changes to the database:
        $configPath = $this->pathResolver
            ? $this->pathResolver->getLocalConfigPath('config.ini', null, true)
            : ConfigLocator::getLocalConfigPath('config.ini', null, true);
        $output->writeln("\tUpdating $configPath...");
        $writer = $this->getConfigWriter($configPath);
        $writer->set('Authentication', 'encrypt_ils_password', true);
        $writer->set('Authentication', 'ils_encryption_algo', $newhash);
        $writer->set('Authentication', 'ils_encryption_key', $newkey);
        if (!$writer->save()) {
            $output->writeln("\tWrite failed!");
            return 1;
        }

        // Now do the database rewrite:
        $users = $this->userTable->select(
            function ($select) {
                $select->where->isNotNull('cat_username');
            }
        );
        $output->writeln("\tConverting hashes for " . count($users) . ' user(s).');
        foreach ($users as $row) {
            $pass = null;
            if ($oldhash != 'none' && isset($row['cat_pass_enc'])) {
                $oldcipher = new BlockCipher($oldCrypt);
                $oldcipher->setKey($oldkey);
                $pass = $oldcipher->decrypt($row['cat_pass_enc']);
            } else {
                $pass = $row['cat_password'];
            }
            $newcipher = new BlockCipher($newCrypt);
            $newcipher->setKey($newkey);
            $row['cat_password'] = null;
            $row['cat_pass_enc'] = $newcipher->encrypt($pass);
            $row->save();
        }

        // If we got this far, all went well!
        $output->writeln("\tFinished.");
        return 0;
    }
}
