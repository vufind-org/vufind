<?php

/**
 * File-based session handler
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Session_Handlers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:session_handlers Wiki
 */

namespace VuFind\Session;

use Laminas\Config\Config;

use function function_exists;
use function strlen;

/**
 * File-based session handler
 *
 * @category VuFind
 * @package  Session_Handlers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:session_handlers Wiki
 */
class File extends AbstractBase
{
    /**
     * Path to session file
     *
     * @var string
     */
    protected $path;

    /**
     * Constructor
     *
     * @param Config $config Session configuration ([Session] section of
     * config.ini)
     */
    public function __construct(Config $config = null)
    {
        parent::__construct($config);

        // Set defaults if nothing set in config file.
        if (isset($config->file_save_path)) {
            $this->path = $config->file_save_path;
        } else {
            $tempdir = function_exists('sys_get_temp_dir')
                ? sys_get_temp_dir() : DIRECTORY_SEPARATOR . 'tmp';
            $this->path = $tempdir . DIRECTORY_SEPARATOR . 'vufind_sessions';
        }

        // Die if the session directory does not exist and cannot be created.
        if (
            (!file_exists($this->path) || !is_dir($this->path))
            && !mkdir($this->path)
        ) {
            throw new \Exception('Cannot access session save path: ' . $this->path);
        }
    }

    /**
     * Read function must return string value always to make save handler work as
     * expected. Return empty string if there is no data to read.
     *
     * @param string $sessId The session ID to read
     *
     * @return string
     */
    public function read($sessId): string
    {
        $sessFile = $this->path . '/sess_' . $sessId;
        if (!file_exists($sessFile)) {
            return '';
        }

        // enforce lifetime of this session data
        if (filemtime($sessFile) + $this->lifetime <= time()) {
            $this->destroy($sessId);
            return '';
        }

        return (string)file_get_contents($sessFile);
    }

    /**
     * The destroy handler, this is executed when a session is destroyed with
     * session_destroy() and takes the session id as its only parameter.
     *
     * @param string $sessId The session ID to destroy
     *
     * @return bool
     */
    public function destroy($sessId): bool
    {
        // Perform standard actions required by all session methods:
        parent::destroy($sessId);

        // Perform file-specific cleanup:
        $sessFile = $this->path . '/sess_' . $sessId;
        if (file_exists($sessFile)) {
            return unlink($sessFile);
        }
        return true;
    }

    /**
     * The garbage collector, this is executed when the session garbage collector
     * is executed and takes the max session lifetime as its only parameter.
     *
     * @param int $maxlifetime Maximum session lifetime.
     *
     * @return int|false
     */
    public function gc($maxlifetime): int|false
    {
        $count = 0;
        foreach (glob($this->path . '/sess_*') as $filename) {
            if (filemtime($filename) + $maxlifetime < time()) {
                unlink($filename);
                $count++;
            }
        }
        return $count;
    }

    /**
     * A function that is called internally when session data is to be saved.
     *
     * @param string $sessId The current session ID
     * @param string $data   The session data to write
     *
     * @return bool
     */
    protected function saveSession($sessId, $data): bool
    {
        $sessFile = $this->path . '/sess_' . $sessId;
        if ($handle = fopen($sessFile, 'w')) {
            $return = false;
            // Lock the file for exclusive access to avoid issues with multiple
            // processes writing session simultaneously:
            if (flock($handle, LOCK_EX)) {
                $return = fwrite($handle, $data);
                // Make sure that there's no trailing data by truncating the file to
                // the correct length:
                ftruncate($handle, strlen($data));
                fflush($handle);
                flock($handle, LOCK_UN);
            }
            fclose($handle);
            if ($return !== false) {
                return true;
            }
        }
        // If we got this far, something went wrong with the file output...
        // It is tempting to throw an exception here, but this code is called
        // outside of the context of exception handling, so all we can do is
        // echo a message.
        echo 'Cannot write session to ' . $sessFile . "\n";
        return false;
    }
}
