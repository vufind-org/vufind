# CHANGELOG

## 1.2.0

* `isGranted` no longer cast permissions to string. Instead, the permission is now given to your role entity as it. This
may be a potential BC if you only expected string in your `hasPermission` method.
* `PermissionInterface` is deprecated and will be removed in final implementation (likely for ZF3). RBAC should not
enforce any interface for a permission as its representation is dependant of your application. However, modules
like ZfcRbac may enforce an interface for permissions.
* Various PHPDoc fixes

## 1.1.0

* [BC] Remove factory. It was not intend to be here but rather on ZfcRbac. This way this component is completely
framework agnostic

## 1.0.0

* Initial release
