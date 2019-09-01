{% extends '<?= $base_template ?>' %}

{% block <?= $base_template_block ?> %}
    <h1>{{ 'title.login'|trans }}</h1>

    {% if error %}
        <p>{{ error.messageKey|trans(error.messageData, 'security') }}</p>
    {% endif %}

    {{ form_start(form) }}
        {{ form_errors(form) }}
        {{ form_row(form.<?= $username_field ?>) }}
        {{ form_row(form.<?= $password_field ?>) }}

        <div>
            <input type="submit" value="{{ 'action.login'|trans }}" />
<?php if ($controllers['forgot_password']): ?>
            <p><a href="{{ url('forgot_password') }}">{{ 'action.forgot_password'|trans }}</a></p>
<?php endif; ?>
        </div>
    {{ form_end(form) }}
{% endblock %}
