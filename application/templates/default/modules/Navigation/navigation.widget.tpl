{#
# @var array[] items {
#     @var string  label        The HTML class for styling (e.g., 'error', 'info', 'warning').
#     @var string  href         The actual text content to display.
#     @var string  title        The title attribute
#     @var string  target       The link target (_self, _blank)
#     @var string  icon         The name of the icon, if any
#     @var bool    isCurrent    Whether this item is current for the page loaded
#     @var bool    isActive     Whether this menu has a child that isCurrent (keep menu open for example)
#     @var array   children
# }
# @var app array Global application context containing base_url, theme_url, session, etc.
#}
<ul>
{% set icon_map = {
    'profile': 'icon_user',
    'leaderboard': 'icon_star',
    'search': 'icon_search'
} %}
{% foreach item in items %}
    {% if item.children is empty %}
        {% set class = (icon_map[item.icon] is defined) ? icon_map[item.icon] : '' %}
        <li class="{{ class }}{{ item.isCurrent ? ' current' : '' }}">
            <a href="{{ item.href }}" title="{{ item.title }}" target="{{ item.target }}">{{ item.label }}</a>
        </li>
    {% else %}
        <li class="with-menu">
            <a href="{{ item.href }}" title="{{ item.title }}" target="{{ item.target }}">{{ item.label }}</a>
            <div class="menu">
                <img src="{{ app.theme_url }}/images/menu-open-arrow.png" width="16" height="16">
                {% include 'modules/Navigation/subnav.widget' with { items: item.children, icon_map: icon_map } %}
            </div>
        </li>
    {% endif %}
{% endforeach %}
</ul>