<?php

/**
 * Embedded Preview tab.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */

namespace VuFind\RecordTab;

use VuFind\ServiceManager\Factory\Autowire;

use function in_array;
use function is_bool;
use function strlen;

/**
 * Embedded Preview tab.
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
class Preview extends AbstractBase
{
    /**
     * Is this tab active?
     *
     * @var bool
     */
    protected $active = false;

    /**
     * Constructor.
     *
     * @param array|bool $configOrActive Boolean to set the active status explicitly, or config array to detect it
     * from configuration.
     */
    public function __construct(
        #[Autowire(config: 'config')]
        array|bool $configOrActive = false
    ) {
        if (is_bool($configOrActive)) {
            $this->active = $configOrActive;
        } else {
            $cfg = $configOrActive;

            // currently only active if config [content] [previews] contains google
            // and googleoptions[tab] is not empty.
            $this->active = false;
            if (isset($cfg['Content']['previews'])) {
                $previews = array_map(
                    'trim',
                    explode(',', strtolower($cfg['Content']['previews']))
                );
                if (
                    in_array('google', $previews)
                    && strlen(trim($cfg['Content']['GoogleOptions']['tab'] ?? '')) > 0
                ) {
                    $this->active = true;
                }
            }
        }
    }

    /**
     * Get the on-screen description for this tab.
     *
     * @return string
     */
    public function getDescription()
    {
        return 'Preview';
    }

    /**
     * Is this tab active?
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->active;
    }

    /**
     * Is this tab initially visible?
     *
     * @return bool
     */
    public function isVisible()
    {
        // in this case there is no downside to keeping it hidden
        // until there is content
        return false;
    }

    /**
     * Can this tab be loaded via AJAX?
     *
     * @return bool
     */
    public function supportsAjax()
    {
        // No, Google script magic required
        return false;
    }
}
