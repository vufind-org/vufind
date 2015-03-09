<?php
/**
 * Factory for instantiating SMS objects
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  SMS
 * @author   Ronan McHugh <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\SMS;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Factory for instantiating SMS objects
 *
 * @category VuFind2
 * @package  SMS
 * @author   Ronan McHugh <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 *
 * @codeCoverageIgnore
 */
class Factory implements \Zend\ServiceManager\FactoryInterface
{
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
        $mainConfig = $sm->get('VuFind\Config')->get('config');
        $smsConfig = $sm->get('VuFind\Config')->get('sms');

        // Determine SMS type:
        $type = isset($smsConfig->General->smsType)
            ? $smsConfig->General->smsType : 'Mailer';

        // Initialize object based on requested type:
        switch (strtolower($type)) {
        case 'clickatell':
            $client = $sm->get('VuFind\Http')->createClient();
            return new Clickatell($smsConfig, ['client' => $client]);
        case 'mailer':
            $options = ['mailer' => $sm->get('VuFind\Mailer')];
            if (isset($mainConfig->Site->email)) {
                $options['defaultFrom'] = $mainConfig->Site->email;
            }
            return new Mailer($smsConfig, $options);
        default:
            throw new \Exception('Unrecognized SMS type: ' . $type);
        }
    }
}
