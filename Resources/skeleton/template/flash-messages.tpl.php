{% if app.request.hasPreviousSession %}
    <ul>
    {% for type, messages in app.flashes %}
        {% for message in messages %}
            <li>[{{ type }}] {{ message|trans }}</li>
        {% endfor %}
    {% endfor %}
    </ul>
{% endif %}
