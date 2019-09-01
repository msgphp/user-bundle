{% extends '<?= $base_template ?>' %}

{% block <?= $base_template_block ?> %}
    <h1>{{ 'title.reset_password'|trans }}</h1>

    {{ form_start(form) }}
        {{ form_errors(form) }}
        {{ form_row(form.<?= $password_field ?>) }}

        <div>
            <input type="submit" value="{{ 'action.reset_password'|trans }}" />
        </div>
    {{ form_end(form) }}
{% endblock %}
