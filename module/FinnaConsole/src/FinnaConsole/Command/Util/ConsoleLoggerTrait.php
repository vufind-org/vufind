<?php
/**
 * Trait for logs in console services.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2016-2020.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace FinnaConsole\Command\Util;

use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Trait for logs in console services.
 *
 * @category VuFind
 * @package  Service
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 * @todo     Use Symfony output
 */
trait ConsoleLoggerTrait
{
    /**
     * Output interface
     *
     * @var OutputInterface
     */
    protected $output = null;

    /**
     * Log an exception triggered by ZF2 for administrative purposes.
     *
     * @param \Exception $error Exception to log
     *
     * @return void
     */
    public function logException($error)
    {
        // We need to build a variety of pieces so we can supply
        // information at five different verbosity levels:
        $baseError = get_class($error) . ' : ' . $error->getMessage();
        $prev = $error->getPrevious();
        while ($prev) {
            $baseError .= ' ; ' . get_class($prev) . ' : ' . $prev->getMessage();
            $prev = $prev->getPrevious();
        }
        $backtrace = "\nBacktrace:\n";
        if (is_array($error->getTrace())) {
            foreach ($error->getTrace() as $line) {
                if (!isset($line['file'])) {
                    $line['file'] = 'unlisted file';
                }
                if (!isset($line['line'])) {
                    $line['line'] = 'unlisted';
                }
                $backtraceLine = $line['file'] .
                    ' line ' . $line['line'] . ' - ' .
                    (isset($line['class']) ? 'class = ' . $line['class'] . ', ' : '')
                    . 'function = ' . $line['function'];
                $backtrace .= "{$backtraceLine}\n";
                if (!empty($line['args'])) {
                    $args = [];
                    foreach ($line['args'] as $i => $arg) {
                        $args[] = $i . ' = ' . $this->argumentToString($arg);
                    }
                    $backtraceLine .= ', args: ' . implode(', ', $args);
                } else {
                    $backtraceLine .= ', args: none.';
                }
                $backtrace .= "{$backtraceLine}\n";
            }
        }

        $this->err($baseError . $backtrace);
    }

    /**
     * Convert function argument to a loggable string
     *
     * @param mixed $arg Argument
     *
     * @return string
     */
    protected function argumentToString($arg)
    {
        if (is_object($arg)) {
            return get_class($arg) . ' Object';
        }
        if (is_array($arg)) {
            $args = [];
            foreach ($arg as $key => $item) {
                $args[] = "$key => " . $this->argumentToString($item);
            }
            return 'array(' . implode(', ', $args) . ')';
        }
        if (is_bool($arg)) {
            return $arg ? 'true' : 'false';
        }
        if (is_int($arg) || is_float($arg)) {
            return (string)$arg;
        }
        if (null === $arg) {
            return 'null';
        }
        return "'$arg'";
    }

    /**
     * Output a message with a timestamp
     *
     * @param string $msg     Message
     * @param int    $verbose Verbosity level (one of OutputInterface::VERBOSITY_*
     * constants)
     *
     * @return void
     */
    protected function msg($msg, $verbose = 0)
    {
        $msg = '[' . getmypid() . "] $msg";
        if (null === $this->output) {
            file_put_contents('php://stdout', "$msg\n", FILE_APPEND);
        } else {
            $this->output->writeln($msg, $verbose);
        }
    }

    /**
     * Output an error message with a timestamp
     *
     * @param string $msg          Message
     * @param string $publishedMsg Published version of the error message. Must
     * exclude any privacy-related information. Use '=' for same as $msg.
     *
     * @return void
     */
    protected function err($msg, $publishedMsg = '')
    {
        if ($publishedMsg) {
            if ('=' === $publishedMsg) {
                $publishedMsg = $msg;
            }
            $publishedMsg = '[' . getmypid() . "] $publishedMsg";
            if ($this->output instanceof ConsoleOutputInterface) {
                $stderr = $this->output->getErrorOutput();
                $stderr->writeln($publishedMsg);
            } else {
                file_put_contents('php://stderr', "$publishedMsg\n", FILE_APPEND);
            }
        }
        $msg = "ERROR: $msg";
        $this->msg($msg);
    }

    /**
     * Output a warning message with a timestamp
     *
     * @param string $msg Message
     *
     * @return void
     */
    protected function warn($msg)
    {
        $msg = "WARNING: $msg";
        $this->msg($msg);
    }
}
