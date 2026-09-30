<?php

/**
 * Allow to match regex from config file.
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Regex
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/vufind/ Main page
 */

namespace VuFind\Regex;

use Exception;

use function array_key_exists;

/**
 * Class to hold data for the Get This button.
 *
 * @category VuFind
 * @package  Regex
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/vufind/ Main page
 */
class Regex
{
    /**
     * Constructor.
     *
     * @param array $config Regular expression configuration
     */
    public function __construct(protected array $config)
    {
    }

    /**
     * Set the config to use.
     *
     * @param array $config Config (usually from Regex.yaml)
     *
     * @return void
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    /**
     * Matches against regex in config.
     *
     * @param string $regexName Regex name within the config file
     * @param string $string    The string to test against
     * @param ?bool  $default   Boolean to return if the regex doesn't exist, or null to throw an error (default)
     *
     * @return bool
     * @throws Exception        If the regex given as parameter does not exist and default is not set
     */
    public function matches(string $regexName, string $string, ?bool $default = null): bool
    {
        if (!isset($this->config[$regexName])) {
            // We don't throw an error if the regex key exists but is null (empty)
            if (!array_key_exists($regexName, $this->config) && null === $default) {
                throw new Exception('The regex named "' . $regexName . '" does not exist in the config.');
            }
            return $default;
        }

        foreach ($this->config[$regexName] as $pattern) {
            if (preg_match($pattern, $string) === 1) {
                return true;
            }
        }
        return false;
    }
}
