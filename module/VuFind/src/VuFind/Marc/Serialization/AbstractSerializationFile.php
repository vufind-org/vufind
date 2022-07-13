<?php
/**
 * Abstract base class for serialization format support classes.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2022.
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
 * @package  MARC
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
namespace VuFind\Marc\Serialization;

/**
 * Abstract base class for serialization format support classes.
 *
 * @category VuFind
 * @package  MARC
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
abstract class AbstractSerializationFile implements SerializationFileInterface,
    MessageCallbackInterface
{
    /**
     * Message callback
     *
     * @var callable
     */
    protected $messageCallback = null;

    /**
     * Set message callback
     *
     * @param callable $callback Message callback
     *
     * @return void
     */
    public function setMessageCallback(?callable $callback): void
    {
        $this->messageCallback = $callback;
    }

    /**
     * Output a message
     *
     * @param string $msg   Message
     * @param int    $level Error level (see
     * https://www.php.net/manual/en/function.error-reporting.php)
     *
     * @return void
     */
    protected function message(string $msg, int $level): void
    {
        if (null !== $this->messageCallback) {
            call_user_func($this->messageCallback, $msg, $level);
        }
    }
}
