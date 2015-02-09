<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license.
 */

namespace ZfcRbac\Factory;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Create a list of guards
 *
 * @author  Michaël Gallego <mic.gallego@gmail.com>
 * @licence MIT
 */
class GuardsFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     * @return \ZfcRbac\Guard\GuardInterface[]|array
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        /* @var \ZfcRbac\Options\ModuleOptions $options */
        $options       = $serviceLocator->get('ZfcRbac\Options\ModuleOptions');
        $guardsOptions = $options->getGuards();

        if (empty($guardsOptions)) {
            return [];
        }

        /* @var \ZfcRbac\Guard\GuardPluginManager $pluginManager */
        $pluginManager = $serviceLocator->get('ZfcRbac\Guard\GuardPluginManager');
        $guards        = [];

        foreach ($guardsOptions as $type => $options) {
            $guards[] = $pluginManager->get($type, $options);
        }

        return $guards;
    }
}
