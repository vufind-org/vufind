<?php

/**
 * Entity model for finna_page_view_stats table
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace Finna\Db\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Entity model for finna_page_view_stats table
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
#[ORM\Table(name: 'finna_page_view_stats')]
#[ORM\Entity]
class FinnaPageViewStats extends FinnaBaseStats implements FinnaPageViewStatsEntityInterface
{
    /**
     * Controller.
     *
     * @var string
     */
    #[ORM\Column(name: 'controller', type: 'string', length: 255, nullable: false)]
    #[ORM\Id]
    protected string $controller;

    /**
     * Action.
     *
     * @var string
     */
    #[ORM\Column(name: 'action', type: 'string', length: 255, nullable: false)]
    #[ORM\Id]
    protected string $action;

    /**
     * Get controller.
     *
     * @return string
     */
    public function getController(): string
    {
        return $this->controller;
    }

    /**
     * Set controller.
     *
     * @param string $controller Controller
     *
     * @return static
     */
    public function setController(string $controller): static
    {
        $this->controller = mb_substr($controller, 0, 128, 'UTF-8');
        return $this;
    }

    /**
     * Get action.
     *
     * @return string
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * Set action.
     *
     * @param string $action Action
     *
     * @return static
     */
    public function setAction(string $action): static
    {
        $this->action = mb_substr($action, 0, 128, 'UTF-8');
        return $this;
    }
}
