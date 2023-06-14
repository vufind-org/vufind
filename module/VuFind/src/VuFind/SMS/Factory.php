<?php

/**
 * Factory for instantiating SMS objects
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2009.
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
 * @package  SMS
 * @author   Ronan McHugh <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\SMS;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

/**
 * Factory for instantiating SMS objects
 *
 * @category VuFind
 * @package  SMS
 * @author   Ronan McHugh <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 *
 * @codeCoverageIgnore
 */
class Factory implements FactoryInterface
{
    /**
     * Create service
     *
     * @param ContainerInterface $container Service manager
     * @param string             $name      Requested service name (unused)
     * @param array              $options   Extra options (unused)
     *
     * @return SMSInterface
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(
        ContainerInterface $container,
        $name,
        array $options = null
    ) {
        // Load configurations:
        $configManager = $container->get(\VuFind\Config\PluginManager::class);
        $mainConfig = $configManager->get('config');
        $smsConfig = $configManager->get('sms');

        // Determine SMS type:
        $type = $smsConfig->General->smsType ?? 'Mailer';

        // Initialize object based on requested type:
        switch (strtolower($type)) {
            case 'clickatell':
                $client = $container->get(\VuFindHttp\HttpService::class)
                    ->createClient();
                return new Clickatell($smsConfig, ['client' => $client]);
            case 'mailer':
                $options = [
                    'mailer' => $container->get(\VuFind\Mailer\Mailer::class),
                ];
                if (isset($mainConfig->Site->email)) {
                    $options['defaultFrom'] = $mainConfig->Site->email;
                }
                return new Mailer($smsConfig, $options);
            default:
                throw new \Exception('Unrecognized SMS type: ' . $type);
        }
    }
}
