### Plexis CMS Template Engine Documentation

The Plexis CMS Template Engine is a powerful and flexible templating system designed to separate presentation logic from application logic. It supports variable substitution, control structures, template inheritance, expressions, filters, and custom extensions.

The engine is located in the `System\Presentation\Engine` namespace. Templates are compiled into PHP code and cached for optimal performance.

---

### Table of Contents
1. [Basic Usage](#basic-usage)
2. [Template Syntax Overview](#template-syntax-overview)
3. [Variables](#variables)
4. [Expressions](#expressions)
5. [Filters](#filters)
6. [Control Structures](#control-structures)
7. [Variable Assignment (Set)](#variable-assignment-set)
8. [Template Inheritance (Extends)](#template-inheritance-extends)
9. [Includes](#includes)
10. [Assets](#assets)
11. [Inserts](#inserts)
12. [Run (HMVC Requests)](#run-hmvc-requests)
13. [Comments](#comments)
14. [Verbatim Blocks](#verbatim-blocks)
15. [Complete Example](#complete-example)

---

### Basic Usage

#### Loading and Rendering a View

```php
use System\Presentation\View;

// Load from file
$view = View::FromFile('home/index.tpl');

// Assign variables
$view->assign('title', 'Home Page');
$view->assign('user', ['name' => 'John Doe', 'admin' => true]);

// Render the view
echo $view->render();
```

#### Loading from String

```php
$view = View::FromString('Hello, {{ name }}!');
$view->assign('name', 'World');
echo $view->render(); // Outputs: Hello, World!
```

#### Binding Insert Callbacks

```php
$view->bind('contents', function() use ($childViewContent) {
    return $childViewContent;
});

$view->bind('copyright_year', function() {
    return date('Y');
});
```

---

### Template Syntax Overview

The engine uses three types of delimiters:

| Delimiter | Purpose | Example |
|-----------|---------|---------|
| `{{ }}` | Variable output / expressions | `{{ user.name }}` |
| `{% %}` | Directives (control structures, assignments) | `{% if condition %}` |
| `{# #}` | Comments (not rendered) | `{# This is a comment #}` |

---

### Variables

Variables are output using double curly braces `{{ }}`. Multiple access styles are supported:

```html
{# Simple variable #}
{{ title }}

{# Array access via dot notation #}
{{ user.name }}

{# Array access via bracket notation #}
{{ user['name'] }}

{# Object property access #}
{{ user->email }}

{# Object method calls #}
{{ user->getName() }}

{# Method calls with arguments #}
{{ itemObject->someProperty->method('with', params, true) }}

{# Nested array/object access #}
{{ address.street }}
{{ app.config.bf2_version }}

{# String concatenation with ~ operator #}
{{ app.theme_url ~ '/css/style.css' }}
```

---

### Expressions

The template engine supports rich expressions within `{{ }}` output tags.

#### Null Coalesce Operator (`??`)

Provide fallback values when a variable is null or undefined:

```html
{{ name ?? 'Guest' }}
{{ user.nickname ?? user.name ?? 'Anonymous' }}
```

#### Ternary Operator (`? :`)

Conditional expressions:

```html
{{ isTrue ? 'yes!' : 'No!' }}
{{ isActive ? 'Active' : 'Inactive' }}
{{ item.isCurrent ? ' current' : '' }}
```

#### Mathematical Expressions

Perform calculations directly in templates:

```html
{{ 100 * 1.5 }}
{{ price + tax }}
```

#### Comparison and Logical Operators

- **Comparison:** `==`, `!=`, `>`, `<`, `>=`, `<=`, `===`, `!==`, `<>`, `<=>`
- **Logical:** `and`, `or`, `xor`, `&&`, `||`
- **Unary:** `not`, `!`
- **Special:** `is`, `is not` (maps to `==` and `!=`)

---

### Filters

Filters modify variables before rendering. They are applied using the pipe `|` character.

#### Built-in Filters

| Filter | PHP Function | Description |
|--------|-------------|-------------|
| `upper` | `strtoupper` | Convert to uppercase |
| `lower` | `strtolower` | Convert to lowercase |
| `capitalize` | `ucfirst` | Capitalize first letter |
| `length` | `mb_strlen` | Get string length |
| `escape` / `e` | `htmlspecialchars` | HTML-escape output |
| `reverse` | `strrev` | Reverse a string |
| `count` | `count` | Count array elements |

#### Usage

```html
{{ name | upper }}
{{ username | lower }}
{{ title | capitalize }}
{{ description | escape }}
```

#### Custom Filters

Custom filters can be registered via the `FilterRegistry`:

```php
$filterRegistry->register('shorten', 'my_shorten_function');
```

```html
{{ description | shorten }}
```

---

### Control Structures

Control structures are enclosed in `{% %}` tags.

#### If / ElseIf / Else

```html
{% if user.admin %}
    <p>Welcome, Administrator!</p>
{% elseif user.logged_in %}
    <p>Welcome back, {{ user.name }}.</p>
{% else %}
    <p>Please log in.</p>
{% endif %}
```

#### Negation with `not`

```html
{% if not app.user->isGuest() %}
    <p>You are logged in.</p>
{% endif %}
```

#### Test Operators (`is` / `is not`)

Special test keywords can be used with `is` and `is not`:

- `is empty` / `is not empty` — checks if a variable is empty
- `is defined` / `is not defined` — checks if a variable is set (isset)
- `is odd` / `is not odd` — checks if a number is odd
- `is even` / `is not even` — checks if a number is even

```html
{% if items is empty %}
    No items found.
{% endif %}

{% if app.messages is not empty %}
    {# display messages #}
{% endif %}

{% if cart is not empty %}
    <p>You have items in your cart.</p>
{% endif %}

{% if item.children is empty %}
    <li>{{ item.label }}</li>
{% endif %}
```

#### Combining with method calls

```html
{% if app.user->isGranted('admin_access') %}
    <button>Admin Panel</button>
{% endif %}
```

---

#### Foreach Loop

Iterate over arrays or collections. Supports two syntax styles.

**Syntax 1: Standard (`as`)**

```html
{% foreach users as user %}
    <li>{{ user.name }}</li>
{% endforeach %}
```

**With key => value pairs:**

```html
{% foreach cart as index => item %}
    <li>{{ item.name }} - ${{ item.price }}</li>
{% endforeach %}
```

**Syntax 2: Python-style (`in`)**

```html
{% foreach user in users %}
    {{ user.name }}
{% endforeach %}
```

**With key, value (`in` style):**

```html
{% foreach key, value in items %}
    {{ key }}: {{ value }}
{% endforeach %}
```

**Foreach with Else Clause:**

Display alternative content when the iterable is empty:

```html
{% foreach friend in friends %}
    <li>{{ friend.name }} ({{ friend.age }} years old)</li>
{% else %}
    <li>No friends</li>
{% endforeach %}
```

**Loop Variables:**

Inside a foreach loop, a special `loop` variable is available:

| Variable | Description |
|----------|-------------|
| `loop.index` | 1-based iteration counter |
| `loop.index0` | 0-based iteration counter |
| `loop.first` | `true` if this is the first iteration |
| `loop.last` | `true` if this is the last iteration |
| `loop.count` | Total number of items |
| `loop.key` | Current key |
| `loop.parent` | Context of the parent scope (for nested loops) |

---

#### For Loop

Numeric iteration loops.

**Range Loop:**

```html
{% for i 1..10 %}
    {{ i }}
{% endfor %}
```

Supports both ascending (`1..10`) and descending (`10..1`) ranges.

**C-Style Loop:**

```html
{% for i = 0, i < 10, i++ %}
    {{ i }}
{% endfor %}
```

---

### Variable Assignment (Set)

Use `{% set %}` to create or assign variables within templates.

**Simple Assignment:**

```html
{% set userName = user.name %}
Welcome, {{ userName }}!
```

**With Expressions:**

```html
{% set total = 100 * 1.5 %}
Total: {{ total }}
```

**With Null Coalesce:**

```html
{% set displayName = user.nickname ?? user.name ?? 'Guest' %}
{{ displayName }}
```

**With Ternary Operator:**

```html
{% set status = isActive ? 'Active' : 'Inactive' %}
Status: {{ status }}
```

**With Object/Dictionary Literals:**

```html
{% set colors = { red: '#FF0000', green: '#00FF00', blue: '#0000FF' } %}
Red: {{ colors.red }}
```

**With `is` Tests:**

```html
{% set class = (icon_map[item.icon] is defined) ? icon_map[item.icon] : '' %}
```

**Multi-line dictionary:**

```html
{% set icon_map = {
    'profile': 'icon_user',
    'leaderboard': 'icon_star',
    'search': 'icon_search'
} %}
```

---

### Template Inheritance (Extends)

Template inheritance allows child views to specify a layout. Use the `{% extends %}` directive at the top of a child template:

```html
{% extends "layouts/default" %}

<h1>Welcome to my site</h1>
<p>This content will be injected into the layout.</p>
```

The layout file is resolved from the `application/templates/{templateName}/layouts/` directory. The `.tpl` extension is appended automatically if omitted.

---

### Includes

Include partial templates into the current view.

**Basic Include:**

```html
{% include "partials/sidebar" %}
{% include "partials/message_box.tpl" %}
```

**With Context Variables (`with`):**

Pass additional variables to the included partial:

```html
{% include 'partials/message_box' with { messages: app.messages } %}
{% include 'modules/Navigation/subnav.widget' with { items: item.children, icon_map: icon_map } %}
```

**Isolated Context (`only`):**

When `only` is specified, the included partial receives **only** the variables passed via `with`, without access to the parent template's context:

```html
{% include 'partials/widget' with { items: data } only %}
```

---

### Assets

Include CSS and JavaScript files. The engine automatically detects the file type based on the file extension (`.css` or `.js`) and attaches it to the layout.

**Basic Usage:**

```html
{% asset "css/style.css" %}
{% asset "js/app.js" %}
```

**With Variables and Concatenation:**

```html
{% asset app.theme_url ~ '/css/custom.css' %}
{% asset app.theme_url ~ '/js/lists.js' %}
```

**With Priority (optional second parameter):**

Lower priority values load earlier. Default priority is `50`.

```html
{% asset app.theme_url ~ '/js/lists.js', 21 %}
```

---

### Inserts

Inserts execute registered callback functions to output dynamic content. Callbacks are registered in PHP using `View::bind()`.

**Basic Usage:**

```html
{% insert 'contents' %}
{% insert 'head' %}
{% insert 'stylesheets' %}
{% insert 'scripts' %}
{% insert 'jsvars' %}
{% insert 'breadcrumb' %}
```

**With Arguments:**

```html
{% insert "breadcrumbs" with { key1: value1, key2: value2 } %}
```

**Registering Callbacks in PHP:**

```php
$view->bind('copyright_year', function() {
    return date('Y');
});

$view->bind('contents', function() use ($childViewContent) {
    return $childViewContent;
});
```

---

### Run (HMVC Requests)

The `{% run %}` directive executes internal HMVC (Hierarchical Model-View-Controller) requests, embedding the output of other controllers or widgets within the template.

**Basic Syntax:**

```html
{% run 'site-nav.widget' %}
```

**With Named Parameters:**

```html
{% run 'featured-servers' with { limit: 3 } %}
{% run 'top-players-score' with { limit: 10 } %}
{% run "blog.widget" with { theme: 'dark', numPosts: 3 * 2, userId: app.user.id } %}
```

Parameters can include:
- String literals
- Numbers and expressions
- Variables from the current context
- Nested object/array access

This is useful for embedding reusable widgets (navigation, sidebars, leaderboards, etc.) and loading dynamic content from different modules.

---

### Comments

Comments are enclosed in `{# #}` and are **not** rendered in the output. They are stripped during compilation.

```html
{# This is a comment and won't appear in the HTML #}

{# Remove include to remove the popular pages side bar #}
{% include "partials/popular_pages_sidebar.tpl" %}
```

**Multi-line comments** are also supported:

```html
{#
# @var array[] items {
#     @var string  label
#     @var string  href
#     @var bool    isCurrent
# }
#}
```

Comments are useful for:
- Documenting template logic and variable contracts
- Temporarily disabling template code
- Adding notes for other developers

---

### Verbatim Blocks

Use `{% verbatim %}` to prevent the engine from processing template syntax within a block. This is useful when you need to output literal `{{ }}` or `{% %}` characters (e.g., for JavaScript frameworks like Vue.js or Angular).

```html
{% verbatim %}
    {{ this_will_not_be_processed }}
    {% this_either %}
{% endverbatim %}
```

Content inside verbatim blocks is output as-is without any template processing.

---

### Complete Example

Here is a comprehensive example demonstrating multiple features, based on real templates in the project:

```html
{# Dashboard Template #}
{% extends "layouts/default" %}
{% asset app.theme_url ~ '/js/lists.js', 21 %}

{# Set up a lookup dictionary #}
{% set icon_map = {
    'profile': 'icon_user',
    'leaderboard': 'icon_star',
    'search': 'icon_search'
} %}

Hello, {{ name | upper }}!

{# Null coalesce and ternary #}
{{ name ?? 'Guest' }}
{{ isActive ? 'Active' : 'Inactive' }}

{# Conditional rendering #}
{% if not app.user->isGuest() %}
    <p>Logged in as: <strong>{{ app.user.username }}</strong></p>
{% else %}
    <p>Welcome, Guest</p>
{% endif %}

{# Check permissions via method call #}
{% if app.user->isGranted('admin_access') %}
    <button>Admin Panel</button>
{% endif %}

{# Display messages if any exist #}
{% if app.messages is not empty %}
    {% include 'partials/message_box' with { messages: app.messages } %}
{% endif %}

{# Foreach with else clause #}
<ul>
{% foreach friend in friends %}
    <li>{{ friend.name }} ({{ friend.age }} years old)</li>
{% else %}
    <li>No friends found.</li>
{% endforeach %}
</ul>

{# Foreach with key => value #}
{% foreach cart as index => item %}
    <li>{{ item.name }} - ${{ item.price }}</li>
{% endforeach %}

{# For loop with range #}
{% for i 1..10 %}
    <span>{{ i }}</span>
{% endfor %}

{# Set with expressions #}
{% set total = 100 * 1.5 %}
Total: {{ total }}

{% set displayName = user.nickname ?? user.name ?? 'Guest' %}
{{ displayName }}

{# Include with context and 'only' isolation #}
{% include 'modules/Navigation/subnav.widget' with { items: item.children, icon_map: icon_map } %}

{# Insert registered callbacks #}
{% insert 'contents' %}

{# HMVC widget with parameters #}
{% run 'featured-servers' with { limit: 3 } %}
{% run 'top-players-score' with { limit: 10 } %}

{# Verbatim block for literal output #}
{% verbatim %}
    Vue.js: {{ message }}
{% endverbatim %}
```

---

### Architecture Overview

The engine consists of the following core components:

| Component | Description |
|-----------|-------------|
| `Lexer` | Tokenizes template strings into a `TokenStream` |
| `Parser` | Parses tokens into a node tree (AST) |
| `Compiler` | Compiles the node tree into executable PHP code |
| `ViewRenderer` | Executes compiled templates within a variable context |
| `View` | Public API for loading, assigning variables, and rendering |
| `Layout` | Wraps views in a common layout structure |
| `Template` | Centralized compilation and path resolution pipeline |

#### Directive Handlers (`Engine\Directives\`)

Each directive has a dedicated handler that parses its tokens into nodes:

- `AssetDirectiveHandler` — `{% asset %}`
- `ExtendsDirectiveHandler` — `{% extends %}`
- `ForDirectiveHandler` — `{% for %}` / `{% endfor %}`
- `ForeachDirectiveHandler` — `{% foreach %}` / `{% endforeach %}`
- `IfDirectiveHandler` — `{% if %}` / `{% elseif %}` / `{% else %}` / `{% endif %}`
- `IncludeDirectiveHandler` — `{% include %}`
- `InsertDirectiveHandler` — `{% insert %}`
- `RunDirectiveHandler` — `{% run %}`
- `SetDirectiveHandler` — `{% set %}`

#### Compiler Strategies (`Engine\Strategies\`)

Each directive has a corresponding compiler strategy that generates PHP code:

- `AssetCompilerStrategy` — Compiles to `$this->includeAsset()`
- `ExtendsCompilerStrategy` — Compiles to `$this->setLayout()`
- `ForCompilerStrategy` — Compiles to PHP `for` loops
- `ForeachCompilerStrategy` — Compiles to `$this->renderForeachLoop()` with precompiled loop files
- `IfCompilerStrategy` — Compiles to PHP `if`/`elseif`/`else`/`endif`
- `IncludeCompilerStrategy` — Compiles to `$this->renderInclude()`
- `InsertCompilerStrategy` — Compiles to `$this->renderInsert()`
- `RunCompilerStrategy` — Compiles to `$this->renderWidget()`
- `SetCompilerStrategy` — Compiles to PHP variable assignment

#### Filter Registry (`Engine\Filters\`)

Manages the mapping of template filter names to PHP functions. Custom filters can be registered at runtime.

---

This documentation covers all features available in the `System\Presentation\Engine` template engine, derived from the actual source code in the Directives, Strategies, and Filters namespaces, as well as real-world template examples.