<?php
/**
 * Factory for instantiating Mailer objects
 *
 * PHP version 5
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
 * @package  Mailer
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Mailer;
use Zend\Mail\Transport\InMemory;
use Zend\Mail\Transport\Smtp, Zend\Mail\Transport\SmtpOptions;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Factory for instantiating Mailer objects
 *
 * @category VuFind
 * @package  Mailer
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 *
 * @codeCoverageIgnore
 */
class Factory implements \Zend\ServiceManager\FactoryInterface
{
    /**
     * Build the mail transport object.
     *
     * @param \Zend\Config\Config $config Configuration
     *
     * @return InMemory|Smtp
     */
    protected function getTransport($config)
    {
        // In test mode? Return fake object:
        if (isset($config->Mail->testOnly) && $config->Mail->testOnly) {
            return new InMemory();
        }

        // Create mail transport:
        $settings = [
            'host' => $config->Mail->host, 'port' => $config->Mail->port
        ];
        if (isset($config->Mail->username) && isset($config->Mail->password)) {
            $settings['connection_class'] = 'login';
            $settings['connection_config'] = [
                'username' => $config->Mail->username,
                'password' => $config->Mail->password
            ];
            if (isset($config->Mail->secure)) {
                // always set user defined secure connection
                $settings['connection_config']['ssl'] = $config->Mail->secure;
            } else {
                // set default secure connection based on configured port
                if ($settings['port'] == '587') {
                    $settings['connection_config']['ssl'] = 'tls';
                } elseif ($settings['port'] == '487') {
                    $settings['connection_config']['ssl'] = 'ssl';
                }
            }
        }
        return new Smtp(new SmtpOptions($settings));
    }

    /**
     * Create service
     *
     * @param ServiceLocatorInterface $sm Service manager
     *
     * @return mixed
     */
    public function createService(ServiceLocatorInterface $sm)
    {
        // Load configurations:
        $config = $sm->get('VuFind\Config')->get('config');

        // Create service:
        return new \VuFind\Mailer\Mailer($this->getTransport($config));
    }
}
