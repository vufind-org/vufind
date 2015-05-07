<?php

namespace ZfcRbacTest\Asset;

use Rbac\Role\RoleInterface;

class MockRoleWithPermissionMethod implements RoleInterface
{
    public function getPermissions()
    {
        return ['permission-method-a', 'permission-method-b'];
    }

    public function getName()
    {
        return 'role-with-permission-method';
    }
    public function hasPermission($permission)
    {
        return false;
    }
}