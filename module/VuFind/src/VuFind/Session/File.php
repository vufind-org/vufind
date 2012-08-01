<?php
/**
 * File-based session handler
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Session_Handlers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/creating_a_session_handler Wiki
 */
namespace VuFind\Session;

/**
 * File-based session handler
 *
 * @category VuFind2
 * @package  Session_Handlers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/creating_a_session_handler Wiki
 */
class File extends AbstractBase
{
    protected $path;

    /**
     * Constructor.
     *
     * @param Zend_Config $config Session configuration ([Session] section of
     * config.ini)
     */
    public function __construct($config)
    {
        // Set defaults if nothing set in config file.
        if (isset($config->file_save_path)) {
            $this->path = $config->file_save_path;
        } else {
            $tempdir = function_exists('sys_get_temp_dir')
                ? sys_get_temp_dir() : DIRECTORY_SEPARATOR . 'tmp';
            $this->path = $tempdir . DIRECTORY_SEPARATOR . 'vufind_sessions';
        }

        // Die if the session directory does not exist and cannot be created.
        if (!file_exists($this->path) || !is_dir($this->path)) {
            if (!@mkdir($this->path)) {
                throw new \Exception(
                    "Cannot access session save path: " . $this->path
                );
            }
        }

        // Call standard session initialization from this point.
        parent::__construct($config);
    }

    /**
     * Read function must return string value always to make save handler work as
     * expected. Return empty string if there is no data to read.
     *
     * @param string $sess_id The session ID to read
     *
     * @return string
     */
    public function read($sess_id)
    {
        $sess_file = $this->path . '/sess_' . $sess_id;
        if (!file_exists($sess_file)) {
            return '';
        }

        // enforce lifetime of this session data
        if (filemtime($sess_file) + $this->lifetime <= time()) {
            $this->destroy($sess_id);
            return '';
        }

        return (string)@file_get_contents($sess_file);
    }

    /**
     * Write function that is called when session data is to be saved.
     *
     * @param string $sess_id The current session ID
     * @param string $data    The session data to write
     *
     * @return void
     */
    public function write($sess_id, $data)
    {
        $sess_file = $this->path . '/sess_' . $sess_id;
        if ($fp = @fopen($sess_file, "w")) {
            $return = fwrite($fp, $data);
            fclose($fp);
            if ($return !== false) {
                return;
            }
        }
        // If we got this far, something went wrong with the file output...
        // It is tempting to throw an exception here, but this code is called
        // outside of the context of exception handling, so all we can do is
        // echo a message.
        echo 'Cannot write session to ' . $sess_file . "\n";
    }

    /**
     * The destroy handler, this is executed when a session is destroyed with
     * session_destroy() and takes the session id as its only parameter.
     *
     * @param string $sess_id The session ID to destroy
     *
     * @return void
     */
    public function destroy($sess_id)
    {
        // Perform standard actions required by all session methods:
        parent::destroy($sess_id);

        // Perform file-specific cleanup:
        $sess_file = $this->path . '/sess_' . $sess_id;
        return(@unlink($sess_file));
    }

    /**
     * The garbage collector, this is executed when the session garbage collector
     * is executed and takes the max session lifetime as its only parameter.
     *
     * @param int $maxlifetime Maximum session lifetime.
     *
     * @return void
     */
    public function gc($maxlifetime)
    {
        foreach (glob($this->path . "/sess_*") as $filename) {
            if (filemtime($filename) + $maxlifetime < time()) {
                @unlink($filename);
            }
        }
        return true;
    }
}