<?php

/**
 * Row definition for finna_page_view_stats
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022-2024.
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
 * @package  Db_Table
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace Finna\Db\Row;

use Finna\Db\Entity\FinnaPageViewStatsEntityInterface;

/**
 * Row definition for finna_page_view_stats
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 *
 * @property string $institution
 * @property string $view
 * @property int $crawler
 * @property string $controller
 * @property string $action
 * @property string $date
 * @property int $count
 */
class FinnaPageViewStats extends \VuFind\Db\Row\RowGateway implements FinnaPageViewStatsEntityInterface
{
    use FinnaBaseStatsLogTrait;

    /**
     * Constructor
     *
     * @param \Laminas\Db\Adapter\Adapter $adapter Database adapter
     */
    public function __construct($adapter)
    {
        parent::__construct(
            [
                'institution',
                'view',
                'crawler',
                'date',
            ],
            'finna_page_view_stats',
            $adapter
        );
    }

    /**
     * Controller setter
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
     * Controller getter
     *
     * @return string
     */
    public function getController(): string
    {
        return $this->controller;
    }

    /**
     * Action setter
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

    /**
     * Action getter
     *
     * @return string
     */
    public function getAction(): string
    {
        return $this->action;
    }
}
