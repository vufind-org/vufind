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

namespace ZfcRbac\Guard;

use Zend\ServiceManager\AbstractPluginManager;
use ZfcRbac\Exception;

/**
 * Plugin manager to create guards
 *
 * @method GuardInterface get($name)
 *
 * @author  Michaël Gallego <mic.gallego@gmail.com>
 * @author  JM Leroux <jmleroux.pro@gmail.com>
 * @licence MIT
 */
class GuardPluginManager extends AbstractPluginManager
{
    /**
     * @var array
     */
    protected $factories = [
        'ZfcRbac\Guard\ControllerGuard'            => 'ZfcRbac\Factory\ControllerGuardFactory',
        'ZfcRbac\Guard\ControllerPermissionsGuard' => 'ZfcRbac\Factory\ControllerPermissionsGuardFactory',
        'ZfcRbac\Guard\RouteGuard'                 => 'ZfcRbac\Factory\RouteGuardFactory',
        'ZfcRbac\Guard\RoutePermissionsGuard'      => 'ZfcRbac\Factory\RoutePermissionsGuardFactory',
    ];

    /**
     * {@inheritDoc}
     */
    public function validatePlugin($plugin)
    {
        if ($plugin instanceof GuardInterface) {
            return; // we're okay
        }

        throw new Exception\RuntimeException(sprintf(
            'Guards must implement "ZfcRbac\Guard\GuardInterface", but "%s" was given',
            is_object($plugin) ? get_class($plugin) : gettype($plugin)
        ));
    }

    /**
     * {@inheritDoc}
     */
    protected function canonicalizeName($name)
    {
        return $name;
    }
}
