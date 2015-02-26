<?php
/**
 * Third-party content loader
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
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\Content;
use VuFind\ServiceManager\AbstractPluginManager;

/**
 * Third-party content loader
 *
 * @category VuFind2
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Loader
{
    /**
     * Plug-in loader
     *
     * @var AbstractPluginManager
     */
    protected $loader;

    /**
     * Provider information
     *
     * @var string
     */
    protected $providers;

    /**
     * Constructor
     *
     * @param AbstractPluginManager $loader    Plugin loader for content
     * @param string                $providers Provider information
     */
    public function __construct(AbstractPluginManager $loader, $providers = '')
    {
        $this->loader = $loader;
        $this->providers = $providers;
    }

    /**
     * Build an ISBN object; return false if value is invalid.
     *
     * @param string $isbn ISBN
     *
     * @return \VuFindCode\ISBN|bool
     */
    protected function getIsbnObject($isbn)
    {
        // We can't proceed without an ISBN:
        return (empty($isbn))
            ? false : new \VuFindCode\ISBN($isbn);
    }

    /**
     * Retrieve results for the providers specified.
     *
     * @param string $isbn ISBN to use for lookup
     *
     * @return array
     */
    public function loadByIsbn($isbn)
    {
        $results = [];
        if (!($isbnObj = $this->getIsbnObject($isbn))) {
            return $results;
        }

        // Fetch from provider
        $providers = explode(',', $this->providers);
        foreach ($providers as $provider) {
            $parts = explode(':', trim($provider));
            $provider = $parts[0];
            if (!empty($provider)) {
                $key = isset($parts[1]) ? $parts[1] : '';
                try {
                    $plugin = $this->loader->get($provider);
                    $results[$provider] = $plugin->loadByIsbn($key, $isbnObj);

                    // If the current provider had no valid data, store nothing:
                    if (empty($results[$provider])) {
                        unset($results[$provider]);
                    }
                } catch (\Exception $e) {
                    // Ignore exceptions:
                    error_log($e->getMessage());
                    unset($results[$provider]);
                }
            }
        }

        return $results;
    }
}
