<ul>
{% foreach item in items %}
    {% if item.children is empty %}
        {% set class = (icon_map[item.icon] is defined) ? icon_map[item.icon] : '' %}
        <li class="{{ class }}{{ item.isCurrent ? ' current' : '' }}">
            <a href="{{ item.href }}" title="{{ item.title }}"  target="{{ item.target }}">{{ item.label }}</a>
        </li>
    {% else %}
        <li>
            <a href="{{ item.href }}" title="{{ item.name }}" target="{{ item.target }}">{{ item.label }}</a>
            {% include 'modules/Navigation/subnav.widget' with { items: item.children, icon_map: icon_map } %}
        </li>
    {% endif %}
    {% if item.hasSeparator %}
        <li class="sep"></li>
    {% endif %}
{% endforeach %}
</ul>