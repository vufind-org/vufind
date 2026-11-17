# AGENTS.md

This file provides guidance to AI coding agents when working with code in this repository.

VuFind® is a PHP (>= 8.2) library discovery layer built on Laminas MVC, Doctrine ORM and Apache Solr, with a
Bootstrap 5 / SCSS / plain JavaScript front end. The default branch is `dev`. The project has an
[AI policy](https://vufind.org/wiki/ai_policy) and
[coding standards](https://vufind.org/wiki/development:coding_standards) that contributions must follow.
A substantial AI contribution must be declared in the commit message, using a trailer of the form
`Assisted-by: <tool or model with version> (<vendor>)`.

## Commands

All tooling is driven through Phing (`build.xml`), which is installed by Composer.

```bash
composer install                 # PHP deps; post-install also downloads Solr + Swagger UI via Phing
composer run install-build-deps  # Node deps (root + themes/bootstrap5 workspace); needs Node >= 22.13
```

`composer install` downloads Solr into `solr/vendor` unless `solr/.disableAutomaticInstall` exists (CI creates
that file; do the same when Solr is not needed).

### Quality checks

```bash
composer qa                      # everything CI runs: JS/SCSS checks + PHP checks, with default configs
vendor/bin/phing qa-php          # phpunitfast, phpcs-console, php-cs-fixer-dryrun, phpstan-console, rector-console
vendor/bin/phing qa-js-and-scss  # eslint, checkSassBuild, jsDependencyCheck, jsTranslationsCheck
composer fix                     # auto-fix PHP: rector, then php-cs-fixer, then phpcbf
vendor/bin/phing eslint-fix      # auto-fix JS
```

Each check is also an individual Phing target (`phpcs-console`, `phpstan-console`, `php-cs-fixer-dryrun`,
`rector-console`, `eslint`, ...). Their configuration lives in `tests/` (`phpcs.xml`, `phpstan.neon`,
`rector.php`, `*.php-cs-fixer.php`) and `eslint.config.mjs`.

### Tests

PHPUnit is configured in `module/VuFind/tests/phpunit.xml`, which covers the unit and integration tests of
*all* modules. The bootstrap requires `VUFIND_LOCAL_DIR` to be set; Phing sets it automatically.

```bash
vendor/bin/phing phpunitfast                                           # all tests
vendor/bin/phing phpunitfaster                                         # stop at first failure
vendor/bin/phing phpunitfast -Dphpunit_extra_params="--filter CartTest"   # single class / method

# Direct PHPUnit invocation of a single file:
VUFIND_LOCAL_DIR=$PWD/local vendor/bin/phpunit -c module/VuFind/tests/phpunit.xml \
  module/VuFind/tests/unit-tests/src/VuFindTest/CartTest.php
```

Integration tests (`module/*/tests/integration-tests`, including the Mink browser tests) need a live
environment with Solr, a database, a web server and a browser driver. They detect it through
`local/solr-<port>.pid` (`VuFindTest\Feature\LiveDetectionTrait`) and are skipped otherwise, so a plain
`phpunitfast` run effectively executes the unit tests only. `vendor/bin/phing startup` / `shutdown` build
and tear down that environment (see the properties at the top of `build.xml`, overridable with `-Dname=value`).

Test classes mirror the source tree under the `VuFindTest\` namespace. Shared test support code (base
classes, `Feature\*Trait` helpers such as `FixtureTrait`, `ReflectionTrait`, `ConfigRelatedServicesTrait`,
mock containers) lives in `module/VuFind/src/VuFindTest`; fixtures in `module/*/tests/fixtures`.

### Front-end build

```bash
npm run build:css    # compile themes/*/scss/compiled.scss -> css/compiled.css (requires VUFIND_HOME to be set)
npm run watch:css
```

Generated assets are **committed**: `themes/*/css/compiled.css` and `themes/bootstrap5/{js,css}/vendor`.
After changing SCSS, rebuild and commit the CSS. After changing `themes/bootstrap5/package.json`, run
`composer update-npm-deps` (or `npm run copyUpdatedDeps`); CI fails if the vendored copies are out of sync.

### Console

`php public/index.php <group>/<command>` (or `<group> <command>`) runs the Symfony Console commands of the
`VuFindConsole` module (registered in `VuFindConsole\Command\PluginManager`), e.g. `generate/plugin`,
`generate/extendclass`, `generate/theme`, `language/normalize`, `util/...`, `upgrade/database`. The
`generate` commands are the intended way to scaffold plugins, routes and local-module overrides.

## Architecture

### Modules (`module/`)

Laminas modules loaded by `config/application.config.php`, each with `config/module.config.php`, `src/`
and `tests/`:

- `VuFindTheme` – theme inheritance, template resolution, asset pipeline.
- `VuFindSearch` – backend-agnostic search abstraction (no dependency on the `VuFind` module).
- `VuFind` – the application itself; almost all code lives here.
- `VuFindAdmin`, `VuFindApi` – admin UI and REST API (Swagger).
- `VuFindConsole` – CLI commands; only loaded under CLI outside the `testing` environment.
- `VuFindDevTools` – only loaded when `VUFIND_ENV` is `development` or `testing`.
- `VuFindLocalTemplate` – skeleton copied by the installer to create a site-specific local module.

Additional local modules are activated through the `VUFIND_LOCAL_MODULES` environment variable.

### Environment and configuration layering

`config/constants.config.php` derives everything from environment variables: `VUFIND_ENV`
(`production` default; `development`; `testing`), `VUFIND_LOCAL_DIR`, `VUFIND_CACHE_DIR`,
`VUFIND_LOCAL_MODULES`.

Two independent configuration systems exist:

1. **Laminas module config** (`module/*/config/module.config.php`) – routes, service manager, plugin
   managers. Cached under `<local>/cache/configs` in production, never in development/testing/CLI.
2. **VuFind runtime config** (`config/vufind/*.ini|*.yaml|*.json`) – the defaults shipped with the code.
   A file of the same name in `$VUFIND_LOCAL_DIR/config/vufind/` overrides it (with optional parent
   directories via `DirLocations.ini`). Never tell users to edit `config/vufind/` in place; never read
   these files directly – go through `VuFind\Config\ConfigManager` (`getConfigArray('config')`, ...) or
   `VuFind\Config\PathResolver`. `local/config/vufind/*` is git-ignored.

The `.ini` files in `config/vufind/` are also the user-facing documentation of every setting: a new
setting needs a commented entry there, and renamed/removed settings need handling in
`VuFind\Config\Upgrade`.

### Services, plugin managers and autowiring

Nearly every extensible feature is a **plugin manager** extending `VuFind\ServiceManager\AbstractPluginManager`,
found as `PluginManager.php` in the feature's namespace (`ILS\Driver`, `RecordDriver`, `RecordTab`,
`Recommend`, `Auth`, `AjaxHandler`, `Search\Options|Params|Results`, `Db\Entity`, `Db\Service`, `Action`,
...). Each one holds its default `$aliases`/`$factories` in the class and can be extended from any module
under `vufind.plugin_managers.<key>` in `module.config.php` (keys are listed at the bottom of the `VuFind`
module config). Adding a plugin therefore means: class + (optional) factory + registration in the plugin
manager.

Top-level services usually need no registration at all. `AbstractTopLevelServiceFactory` /
`FactoryDetector` resolve any `VuFind*` class name by, in order: a `#[DefaultFactory]` attribute; autowiring
(`AutowiringFactory`, when the constructor has no parameters or uses the `#[Autowire]` attribute, e.g.
`#[Autowire(config: 'config')]`, `#[Autowire(service: ..., container: ...)]`); a sibling `<Class>Factory`;
a parent class's factory. Prefer constructor property promotion with autowiring for new code; explicit
factories are the older style. `ServiceInitializer` injects dependencies for `*AwareInterface`s
(logger, translator, DB services, ...).

### Request handling: Actions, not controllers

`dev` has replaced Laminas controllers with PSR-7 **action classes**. `VuFind\Action\ActionDispatchListener`
intercepts the MVC dispatch event, maps the matched route's `controller`/`action` parameters to a
lowercase id `category/action`, and fetches the handler from `VuFind\Action\PluginManager`:

- `module/VuFind/src/VuFind/Action/<Category>/<Name>Action.php` is auto-discovered and autowired.
- Explicit aliases in the plugin manager reuse one class for several ids
  (e.g. `eds/home` → `Search\HomeAction`).
- Actions implement `action(ServerRequestInterface, ResponseInterface): ResponseInterface`, typically by
  extending `AbstractTemplateRenderingAction`, and use services from `VuFind\ActionHelper` (redirect,
  forward, login, flash messages, ...) instead of controller plugins.
- Per-action settings (search backend id, access permission, default tab, ...) are not hard-coded but
  applied from `vufind.action_config` in `module.config.php`; legacy URLs are mapped in
  `vufind.action_redirects`.

Routes are generated at the bottom of `module/VuFind/config/module.config.php` from the `$recordRoutes`,
`$dynamicRoutes`, `$staticRoutes` and `$nonTabRecordActions` arrays via `VuFind\Route\RouteGenerator`;
a new page needs an entry there (`Category/Action`, route name `category-action`). The remaining
`VuFind\Controller` namespace only holds legacy base classes.

### Search

`VuFindSearch\Service` executes command objects (`VuFindSearch\Command\SearchCommand`, `RetrieveCommand`,
...) against a backend from `VuFindSearch\Backend\*` (Solr, EDS, Primo, Summon, Blender, ...), firing
pre/post events that listeners (e.g. `VuFind\Search\Solr\*Listener`) use for filters, highlighting,
deduplication, etc.
Backends are built by factories in `VuFind\Search\Factory` and looked up in `VuFind\Search\BackendRegistry`.

On the application side, every search "class id" (`Solr`, `SolrAuth`, `Search2`, `EDS`, ...) is a triple of
`Options` / `Params` / `Results` classes under `VuFind\Search\<Id>\`, configured by its own `.ini`
(`searches.ini` + `facets.ini` for Solr) and `searchspecs.yaml`. Results are wrapped in **record drivers**
(`VuFind\RecordDriver\*`), which expose metadata to templates; templates never touch raw backend data.

`solr/vufind/` holds the Solr cores and schema; `import/` holds the SolrMarc (Java) and XSLT indexing rules
that populate them. A new Solr field touches the schema, the import properties and a record driver.

### ILS and database

`VuFind\ILS\Connection` proxies to a driver in `VuFind\ILS\Driver\*`, each configured by an `.ini` of the
same name. Drivers have widely differing capabilities, so callers check them with
`checkFunction()`/`checkCapability()` rather than assuming a method exists. `Demo` is the test driver.

The database layer is Doctrine ORM: entities in `VuFind\Db\Entity` and services in `VuFind\Db\Service`,
both always consumed through their `*Interface` and obtained from their plugin managers (never `new`).
Schema changes must be made in `module/VuFind/sql/mysql.sql` **and** `pgsql.sql`, plus a numbered migration
file under `module/VuFind/sql/migrations/{mysql,pgsql}/<version>/`.

### Themes (`themes/`)

A theme is a directory with `theme.config.php` (`extends`, `css`, `js`, `helpers`, `icons`, `mixins`).
Inheritance chain: `root` (no markup; registers all view helpers and shared templates) → `bootstrap5`
(the real UI) → `sandal5` / local themes. A template or asset in a child theme shadows the parent's file
at the same relative path, so changes belong in the lowest theme that owns the file. Templates are
`.phtml` under `templates/<category>/<action>.phtml`; view helpers are PHP classes in
`VuFind\View\Helper\Root|Bootstrap5` registered in the theme config, not in `module.config.php`.

JavaScript in `themes/bootstrap5/js` is plain ES2020 script files (no bundler, no modules) hanging off the
global `VuFind` object and loaded per `theme.config.php` priorities. ESLint flags deprecated jQuery APIs;
JSDoc is required.

### Translations

`languages/*.ini` (plus text-domain subdirectories such as `languages/CreatorRoles/`) hold UI strings;
`en.ini` is the reference. Keep files normalized (`php public/index.php language/normalize`). Strings used
from JavaScript via `VuFind.translate('key')` must be registered on the PHP side;
`npm run check:jsTranslations` verifies this.

## Conventions enforced by CI

- PSR-12, 120-character lines, and complete PEAR-style docblocks on every file, class, method and
  property – including tests. Copy the file header (GPL-2.0 notice, `@category`, `@package`, `@author`,
  `@license`, `@link`) from a neighbouring file.
- Native functions used in namespaced code must be imported (`use function count;`), imports ordered
  class / function / const.
- PHPStan (level 1) and Rector (dead-code, code-quality and PHPUnit sets, dry-run) must pass.
- The lowest supported PHP version is 8.2 (`composer.json` `config.platform.php`); CI runs 8.2–8.5.
- Core code must stay overridable: resolve classes through the service/plugin managers and declare
  members `protected`, never `private` (the code base has no private members), since sites customise
  VuFind by extending classes in local modules.
