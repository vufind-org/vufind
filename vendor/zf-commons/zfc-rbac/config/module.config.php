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

return [
    'service_manager' => [
        'invokables' => [
            'ZfcRbac\Collector\RbacCollector' => 'ZfcRbac\Collector\RbacCollector',
        ],

        'factories' => [
            /* Factories that do not map to a class */
            'ZfcRbac\Guards' => 'ZfcRbac\Factory\GuardsFactory',

            /* Factories that map to a class */
            'Rbac\Rbac'                                       => 'ZfcRbac\Factory\RbacFactory',
            'ZfcRbac\Assertion\AssertionPluginManager'        => 'ZfcRbac\Factory\AssertionPluginManagerFactory',
            'ZfcRbac\Guard\GuardPluginManager'                => 'ZfcRbac\Factory\GuardPluginManagerFactory',
            'ZfcRbac\Identity\AuthenticationIdentityProvider' => 'ZfcRbac\Factory\AuthenticationIdentityProviderFactory',
            'ZfcRbac\Options\ModuleOptions'                   => 'ZfcRbac\Factory\ModuleOptionsFactory',
            'ZfcRbac\Role\RoleProviderPluginManager'          => 'ZfcRbac\Factory\RoleProviderPluginManagerFactory',
            'ZfcRbac\Service\AuthorizationService'            => 'ZfcRbac\Factory\AuthorizationServiceFactory',
            'ZfcRbac\Service\RoleService'                     => 'ZfcRbac\Factory\RoleServiceFactory',
            'ZfcRbac\View\Strategy\RedirectStrategy'          => 'ZfcRbac\Factory\RedirectStrategyFactory',
            'ZfcRbac\View\Strategy\UnauthorizedStrategy'      => 'ZfcRbac\Factory\UnauthorizedStrategyFactory',
        ],
    ],

    'view_helpers' => [
        'factories' => [
            'ZfcRbac\View\Helper\IsGranted' => 'ZfcRbac\Factory\IsGrantedViewHelperFactory',
            'ZfcRbac\View\Helper\HasRole'   => 'ZfcRbac\Factory\HasRoleViewHelperFactory'
        ],
        'aliases' => [
            'isGranted' => 'ZfcRbac\View\Helper\IsGranted',
            'hasRole'   => 'ZfcRbac\View\Helper\HasRole'
        ]
    ],

    'controller_plugins' => [
        'factories' => [
            'ZfcRbac\Mvc\Controller\Plugin\IsGranted' => 'ZfcRbac\Factory\IsGrantedPluginFactory'
        ],
        'aliases' => [
            'isGranted' => 'ZfcRbac\Mvc\Controller\Plugin\IsGranted'
        ]
    ],

    'view_manager' => [
        'template_map' => [
            'error/403'                             => __DIR__ . '/../view/error/403.phtml',
            'zend-developer-tools/toolbar/zfc-rbac' => __DIR__ . '/../view/zend-developer-tools/toolbar/zfc-rbac.phtml'
        ]
    ],

    'zenddevelopertools' => [
        'profiler' => [
            'collectors' => [
                'zfc_rbac' => 'ZfcRbac\Collector\RbacCollector',
            ],
        ],
        'toolbar' => [
            'entries' => [
                'zfc_rbac' => 'zend-developer-tools/toolbar/zfc-rbac',
            ],
        ],
    ],

    'zfc_rbac' => [
        // Guard plugin manager
        'guard_manager' => [],

        // Role provider plugin manager
        'role_provider_manager' => [],

        // Assertion plugin manager
        'assertion_manager' => []
    ]
];
