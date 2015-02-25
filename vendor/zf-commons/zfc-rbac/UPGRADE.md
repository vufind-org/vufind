# Upgrade guide

## From v2.2 to v2.3

- No BC

## From v2.1 to v2.2

- [Potential BC] To simplify unit tests, we have introduced a new `AuthorizationServiceInterface` that the
`AuthorizationService` now implements. We didn't touch any interface (such as `AssertionInterface`) that rely explicitly
on typehinting the `AuthorizationService` to avoid big BC. However, we have updated the view helper and controller
plugins to use the interface instead. This can lead to a BC if you created subclasses of those plugins (which is
not a typical use case). If this is the case, just change `AuthorizationService` to `AuthorizationServiceInterface`.

## From v2.0 to v2.1

- [Potential BC] A potential BC have been introduced in v2.1 to respect interfaces of RBAC component more strictly.
However there is great chance that you have nothing to do. Now, ZfcRbac no longer cast permissions to string before
passing it to your "hasPermission" method in the Role entity. If you used to call `isGranted` using a string permission,
like this: `isGranted('myPermission')`, then you have nothing to do. However, if you are passing a `PermissionInterface`
object, you will now receive this object instead of a string. It's up to you to getting the name from your permission.

## From v1 to v2

Here are the major breaking changes from ZfcRbac 1 to ZfcRbac 2:

- [BC] Dependency to the ZF2 RBAC component has been replaced in favour of a ZF3 prototype which fixes a lot
of design issues.
- [BC] ZfcRbac no longer accepts multiple role providers. Therefore, the option `role_providers` has been renamed
to `role_provider`
- [BC] Permission providers are gone (hence, the options `permission_providers` as well as `permission_manager` should
be removed). Instead, roles now embed all the necessary information
- [BC] The `redirect_to_route` option for the `RedirectStrategy` is gone. Instead, we now have two options:
`redirect_to_route_connected` and `redirect_to_route_disconnected`. This solves an issue when people used to have
a guard on `login` for non-authenticated users only, which leaded to circular redirections.
- [BC] The default protection policy is now `POLICY_ALLOW`. `POLICY_DENY` was way too restrictive and annoying to
work with by default.
- [BC] `isGranted` method of the AuthorizationService no longer accepts an assertion as a second parameter. Instead,
the AuthorizationService now has an assertion map, that allows to map an assertion to a permission. This allows to
inject dependencies into assertions, as well as making the use of assertions much more transparent.
- [BC] Each assertions now receive the whole `AuthorizationService` instead of the current identity. This allows to
support use cases where an assertion needs to check another permission.
- [BC] Entity schema for hierarchical role have changed and no longer require to implement `RecursiveIterator`. Please have a look at the new schema in the `data` folder.
