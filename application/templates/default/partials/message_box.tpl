{#
# Renders global system messages/notifications.
#
# @var array[] messages {
#     @var string  type        The HTML class for styling (e.g., 'error', 'info', 'warning').
#     @var string  message     The actual text content to display.
#     @var bool    is_closable Whether to show a close button for the message.
# }
#}
<ul>
    {% foreach item in messages %}
    <p class="message {{ item.type }}">
        {% if item.is_closable %}<span class="close-bt"></span>{% endif %}{{ item.message }}
    </p>
    {% endfor %}
</ul>