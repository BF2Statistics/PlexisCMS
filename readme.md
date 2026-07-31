### Project Structure

This project is organized into three top-level directories that separate concerns by responsibility:

```
bf2/
├── application/    # All application-specific logic, templates, and data
├── system/         # Reusable framework/infrastructure code (domain-agnostic)
└── public/         # Web-accessible assets (CSS, JS, images, themes)
```

---

### Application vs System

**`application/`** — Everything specific to this application lives here: modules, game logic, server-side templates, SQL migrations, and configuration.

**`system/`** — Generic, reusable framework components (routing, database, caching, HTTP, security, presentation engine, diagnostics). These have no knowledge of the application domain and could theoretically be used in any project.

> **Rule of thumb:** If it's specific to this app, it goes in `application/`. If it's generic infrastructure, it goes in `system/`.

#### Error Handling & the Event System

The system includes a built-in error handler (`system/framework/Diagnostics/ErrorHandler.php`) that catches HTTP errors like 403 (Forbidden) and 404 (Not Found) and renders default error pages from `system/views/`.

These errors are dispatched as events through the `EventManager`. Application modules can subscribe to these events via `GetSubscribedEvents()` in their `Module.php` and handle errors themselves — the priority system ensures the application gets first chance to respond before the system fallback kicks in.

#### Available Events

Events are subscribed to by their **string name**, not by class name. The event classes are what get passed to your listener method as a parameter.

**Router events** (from `system/framework/Routing/Router.php`):

| Event Name | Event Class Passed | Description                                           |
|---|---|-------------------------------------------------------|
| `route.notFound` | `RouteNotFoundEvent` | Fired when the router cannot match a URL to any route |
| `route.matched` | `RouteMatchedEvent` | Fired after a route is successfully matched to a module |
| `router.reloadModuleRoutes.before` | `RouteEvent` | Fired before **each module's** routes are reloaded    |
| `router.reloadRoutes.after` | `RouterEvent` | Fired after all module routes have been reloaded      |

**Dispatcher events** (from `system/framework/Http/Dispatcher.php`):

| Event Name | Event Class Passed | Description |
|---|---|---|
| `dispatch.route.notFound` | `RouteNotFoundEvent` | Fired when the dispatcher cannot route a request (after router-level handling) |
| `dispatch.route.matched` | `RouteMatchedEvent` | Fired after the dispatcher successfully routes a request |
| `dispatch.forbidden` | `HttpForbiddenEvent` | Fired when a request is denied due to insufficient permissions (403) |

> **Note:** `route.notFound` and `dispatch.route.notFound` are different events. The router fires `route.notFound` during route resolution; the dispatcher fires `dispatch.route.notFound` when the entire routing process fails (including after the router's event). Similarly, `route.matched` and `dispatch.route.matched` fire at different stages of the pipeline.

---

### Templates & Themes

Templates and themes are intentionally split across two directories for security:

| What | Location | Why |
|---|---|---|
| **Templates** (`.tpl` files) | `application/templates/{theme-name}/` | Server-side rendering logic — must NOT be publicly accessible |
| **Theme assets** (CSS, JS, images) | `public/themes/{theme-name}/` | Client-side assets — must be publicly accessible via the web server |

Both directories use **matching folder names** to link a template set with its assets. For example, the `default` theme consists of:

```
application/templates/default/    ← server-side templates
public/themes/default/            ← client-side assets (CSS, JS, images)
```

#### Template Directory Structure

```
application/templates/{theme-name}/
├── template.xml   # REQUIRED — theme metadata (template will not work without this)
├── layouts/       # Page layout templates (e.g., main.tpl, installer.tpl)
├── modules/       # Module-specific view templates
├── partials/      # Reusable template fragments for the {% include %} directive
└── compiled/      # Auto-generated compiled templates (do not edit)
```

> **Important:** There is NO `views/` folder inside templates. Module-specific views go in `modules/`, and reusable fragments go in `partials/`.

#### `template.xml` — Required

Every theme **must** include a `template.xml` file in its template directory (`application/templates/{theme-name}/template.xml`). Without this file, the template will not function. See `application/templates/default/template.xml` for a working example.

#### Dynamic Asset Loading

Templates can dynamically load additional CSS and JS files using the `{% asset %}` directive provided by the presentation engine (`system/framework/Presentation/`). This allows themes to include custom scripts and stylesheets directly from their templates without modifying application code.

---

### JavaScript: Module Scripts & Theme Overrides

Module-specific client-side scripts are located in:

```
public/js/modules/
├── Error/
├── Install/
└── Player/
```

**Theme JS overrides:** If a theme includes JS scripts for a module in its own theme assets folder (`public/themes/{theme-name}/js/modules/`), those scripts will be used **instead of** the ones in `public/js/modules/`. This gives the theme the final say over module-specific client-side behavior.

Additionally, themes can add entirely new JS files and load them dynamically in templates using the `{% asset %}` directive.

Shared JS libraries that are not module-specific live in `public/js/libs/`.

---

### Modules

Application modules live in `application/modules/` and represent distinct features:

```
application/modules/
├── Admin/
├── Dashboard/
├── Devtest/
├── Error/
├── Install/
├── Navigation/
├── Player/
└── Server/
```

Each module contains its own controllers, models, and configuration (e.g., `Module.php`, `module.xml`, `Controllers/`).

#### `Module.php` — Required

Every module **must** have a `Module.php` file in its root directory (e.g., `application/modules/YourModule/Module.php`). This file is required for the module to function. It must:

1. Live in the namespace `Modules\YourModule`
2. Extend `System\AbstractModule`
3. Implement all required abstract methods

Here is a minimal `Module.php` example:

```php
<?php
namespace Modules\YourModule;

use System\AbstractModule;
use System\Version;

class Module extends AbstractModule
{
    public function install(): void
    {
        // Called when the module is first installed.
    }

    public function uninstall(): void
    {
        // Called when the module is removed.
    }

    public function upgrade(string $previousVersion): void
    {
        // Called when the version on disk is higher than the DB version.
    }

    public static function GetSubscribedEvents(): array
    {
        // Return an array of event names => handler methods.
        // Example: subscribe to 404 errors from the dispatcher
        // return [
        //     'dispatch.route.notFound' => [Controllers\FrontController::class, 'onRouteNotFound', 10],
        // ];
        return [];
    }

    public static function GetAdminController(): ?string
    {
        return null;
    }

    public static function GetRouteControllers(): array
    {
        return [
            Controllers\FrontController::class
        ];
    }

    public static function GetVersion(): Version
    {
        return Version::Parse('1.0.0');
    }

    public static function GetDisplayName(): string
    {
        return 'Your Module Name';
    }

    public static function GetDescription(): string
    {
        return 'A brief description of what this module does.';
    }

    public static function GetAuthor(): string
    {
        return 'Your Name';
    }

    public static function GetAuthorEmail(): string
    {
        return 'you@example.com';
    }

    public static function GetAuthorUrl(): string
    {
        return 'https://example.com';
    }

    public static function GetCopyright(): string
    {
        return 'Copyright 2026, Your Name';
    }
}
```

#### Subscribing to Events

Modules can listen to system events by returning event subscriptions from `GetSubscribedEvents()`. Use the **string event names** listed in the [Available Events](#available-events) table above. For example, a module could handle 404 errors by subscribing to `dispatch.route.notFound` — the priority system ensures application-level handlers run before the system's default error handler.

---

### Creating a New Theme

To create a new theme called `mytheme`:

1. **Create the template directory** at `application/templates/mytheme/`:
   ```
   application/templates/mytheme/
   ├── template.xml   # REQUIRED
   ├── layouts/       # Page layouts
   ├── modules/       # Module view templates
   ├── partials/      # Reusable fragments ({% include %})
   └── compiled/      # Auto-generated (do not edit)
   ```

2. **Create the assets directory** at `public/themes/mytheme/`:
   ```
   public/themes/mytheme/
   ├── css/           # Stylesheets
   ├── js/            # Theme-specific JavaScript (can override public/js/modules/)
   └── images/        # Theme images
   ```

3. **Add a `template.xml`** — copy from `application/templates/default/template.xml` and customize.

4. **Optionally override module JS** by placing scripts in `public/themes/mytheme/js/modules/{ModuleName}/` — these take priority over `public/js/modules/{ModuleName}/`.

5. **Use `{% asset %}` in templates** to dynamically load any additional theme-specific CSS or JS files.

> **Key point:** The folder name must match in both locations. `template.xml` is mandatory. The theme has the final say over module JS.

---

### Quick Reference

| You want to... | Put files in... |
|---|---|
| Add application logic or a new module | `application/modules/YourModule/` (with `Module.php`) |
| Add or edit module view templates | `application/templates/{theme-name}/modules/` |
| Add reusable template fragments | `application/templates/{theme-name}/partials/` |
| Add or edit page layouts | `application/templates/{theme-name}/layouts/` |
| Add or edit theme CSS/JS/images | `public/themes/{theme-name}/` |
| Override module JS from a theme | `public/themes/{theme-name}/js/modules/YourModule/` |
| Add module-specific client-side JS | `public/js/modules/YourModule/` |
| Add shared JS libraries | `public/js/libs/` |
| Add framework-level infrastructure | `system/framework/` |
| Add application-level SQL | `application/sql/` |
| Add system-level SQL | `system/sql/` |
