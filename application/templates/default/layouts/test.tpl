Hello, {{ name | upper }}!

{% if messages|count > 4 %}
    <p>You have more than 4 messages</p>
{% endif %}

{% set icon_map = {
    'profile': 'icon_user',
    'leaderboard': 'icon_star',
    'search': 'icon_search'
} %}
{% set class = (icon_map[item.icon_key] is defined) ? icon_map[item.icon_key] : '' %}

<h3>Template Syntax Examples (verbatim):</h3>
<pre>
{% verbatim %}
    Variables: {{ user.name }}
    Conditions: {% if user.isAdmin %}...{% endif %}
    Loops: {% foreach items as item %}...{% endforeach %}
{% endverbatim %}
</pre>

Testing null coalesce: {{  name ?? 'name is null' }}
Testing Ternary: {{ isTrue ? 'yes!' : 'No!' }}

{# Runs a callback used from View->bind(name, callable) method #}
{% insert 'contents' %}

{# Runs an internal HMVC request, with optional named parameters! #}
{% run "blog.widget" with { theme: 'dark', numPosts: 3 * 2, userId: app.user.id } %}

{% if is_logged_in %}
    <p>Welcome, {{ username }}! You are logged in.</p>
{% elseif something is not false %}
    <p>Please log in to access your account, {{ name }}.</p>
{% else %}
    <p>Please log in to access your account {{ name }}.</p>
{% endif %}

Your favorite colors are:
<ul>
    {% foreach favorite_colors as color %}
        <li>{{ color }}</li>
    {% endforeach %}
</ul>

{% if is_logged_in %}
    <p>Welcome, {{ username|upper }}! You are logged in.</p>
{% else %}
    <p>Please log in to access your account.</p>
{% endif %}

Address Details:
<ul>
    <li>Street: {{ address.street }}</li>
    <li>City: {{ address.city }}</li>
    <li>Zip: {{ address.zip }}</li>
</ul>

List of Friends:
<ul>
    {% foreach friend in friends %}
        <li>{{ friend.name }} ({{ friend.age }} years old)</li>
    {% else %}
        <li>No friends</li>
    {% endforeach %}
</ul>

{# Nested Conditions and Loops Test #}
{% if cart is not empty %}
    Your Shopping Cart:
    <ul>
        {% foreach cart as index => item %}
            <li>{{ item.name }} - ${{ item.price }}</li>
        {% endforeach %}
    </ul>
    <p>Total Price: {{ total_price }}$</p>
{% else %}
    <p>Your cart is empty.</p>
{% endif %}

Object access:
{{ itemObject->someProperty->method('with', params, true) }}

Set examples:
{% set total = 100 * 1.5 %}
Total: {{ total }}

{% set userName = user.name %}
Welcome, {{ userName }}!

{% set displayName = user.nickname ?? user.name ?? 'Guest' %}
{{ displayName }}

{% set status = isActive ? 'Active' : 'Inactive' %}
Status: {{ status }}

{% set colors = { red: '#FF0000', green: '#00FF00', blue: '#0000FF' } %}
Red: {{ colors.red }}