<?php
/**
 * Factory for BTJ Cover Images module.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2018.
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
 * @author   Kalle Pyykkönen <kalle.pyykkonen@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\Content\Covers;

use Zend\ServiceManager\ServiceManager;

/**
 * Factory for BTJ Cover Images module.
 *
 * @category VuFind
 * @package  Service
 * @author   Kalle Pyykkönen <kalle.pyykkonen@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class BTJFactory extends \VuFind\Service\Factory
{
    /**
     * Construct the BTJ Cover Image Service cover content loader.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \Finna\Content\Covers\BTJ
     */
    public static function getBTJ(ServiceManager $sm)
    {
        return new \Finna\Content\Covers\BTJ(
            $sm->get('VuFind\RecordLoader')
        );
    }
}
