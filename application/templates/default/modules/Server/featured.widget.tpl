<section>
    <div class="block-border">
        <div class="block-content">
            <h1>Featured Servers</h1>
            <div class="block-controls">
                <ul class="controls-buttons">
                    <li class="sep"></li>
                    <li id="home">
                        <a href="{{ app.base_url }}/servers" title="Home">
                            <img src="public/images/icons/fugue/arrow-circle.png" width="16" height="16">
                        </a>
                    </li>
                </ul>
            </div>
        {% foreach server in servers %}
            <div class="task with-legend">
                <div class="legend">
                {% if server.is_full %}
                    <img src="public/images/icons/fugue/status-busy.png" width="16" height="16">
                {% else if server.is_online %}
                    <img src="public/images/icons/fugue/status.png" width="16" height="16">
                {% else %}
                    <img src="public/images/icons/fugue/status-offline.png" width="16" height="16">
                {% endif %}
                {{ server.name }}
                </div>
                <div class="task-description">
                    <ul class="floating-tags">
                        <li class="tag-address">{{ server.address }}</li>
                        <li class="tag-port">{{ server.port }}</li>
                        <li class="tag-mode">{{ server.map_mode }}</li>
                    </ul>
                    <h3>{{ server.map_name }}</h3>
                    {{ server.player_count }}/{{ server.map_size }} players
                </div>
                <ul class="task-dialog">
                    <li class="auto-hide">
                        <a href="bf2://{{ server.address }}/{{ server.port }}" class="button blue">Join Server</a>
                        <a href="{{ app.base_url }}/servers/view/{{ server.id }}" class="button blue">View Server Details</a>
                    </li>
                </ul>
            </div>
        {% else %}
            <p>No featured servers found.</p>
        {% endforeach %}
            <div class="block-footer clearfix">
                <div class="float-right">
                    <img src="public/images/icons/fugue/arrow-curve-000-left.png" width="16" height="16" class="picto">&nbsp;
                    <a class="button" href="{{ app.base_url }}/servers">View More Servers</a>
                </div>
            </div>
        </div>
    </div>
</section>