<?php

/**
 * Admin menu.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @package  Navigation
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Navigation;

use Symfony\Component\Yaml\Yaml;

/**
 * Admin menu.
 *
 * @category VuFind
 * @package  Navigation
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class AdminMenu extends AbstractMenu
{
    /**
     * Constructor.
     *
     * @param array $sectionConfig          Menu configuration
     * @param bool  $showOverdriveAdminMenu Show Overdrive admin menu item?
     */
    public function __construct(
        array $sectionConfig,
        protected bool $showOverdriveAdminMenu
    ) {
        $this->addRequiredSettings(
            [
                'label',
                'route',
            ],
            self::ITEM_CONTEXT
        );
        parent::__construct($sectionConfig);
    }

    /**
     * Return context variables that can be used to render the section.
     *
     * @return array
     */
    public function getSectionContext(): array
    {
        $context = parent::getSectionContext();
        $context['items'] = $this->getMenu()['Admin']['MenuItems'] ?? [];
        return $context;
    }

    /**
     * Get default menu configuration.
     *
     * @return array
     */
    public static function getDefaultMenuConfig(): array
    {
        $yaml = <<<YAML
            Admin:
              MenuItems:
                - name: home
                  label: Home
                  route: admin
            
                - name: socialstats
                  label: Social Statistics
                  route: admin/social
            
                - name: config
                  label: Configuration
                  route: admin/config
            
                - name: maintenance
                  label: System Maintenance
                  route: admin/maintenance
            
                - name: tags
                  label: Tag Maintenance
                  route: admin/tags
            
                - name: feedback
                  label: Feedback Management
                  route: admin/feedback
            
                - name: overdrive
                  label: od_admin_menu
                  route: admin/overdrive
                  checkMethod: checkShowOverdrive
            
                - name: payment
                  label: Online Payment
                  route: admin/payment
            YAML;
        return Yaml::parse($yaml);
    }

    /**
     * Check whether to show Overdrive admin menu item.
     *
     * @return bool
     */
    public function checkShowOverdrive(): bool
    {
        return $this->showOverdriveAdminMenu;
    }
}
