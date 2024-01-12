<?php

/**
 * Configuration loader interface
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Authentication
 * @author   Vaclav Rosecky <vaclav.rosecky@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Auth\Shibboleth;

/**
 * Configuration loader interface
 *
 * @category VuFind
 * @package  Authentication
 * @author   Vaclav Rosecky <vaclav.rosecky@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
interface ConfigurationLoaderInterface
{
    /**
     * Return shibboleth configuration.
     *
     * @param string $entityId entity ID of IdP
     *
     * @throws \VuFind\Exception\Auth
     * @return array shibboleth configuration
     */
    public function getConfiguration($entityId);
}
