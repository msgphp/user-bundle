{% extends '<?= $base_template ?>' %}

{% block <?= $base_template_block ?> %}
    <h1>{{ 'title.register'|trans }} </h1>

    {{ form_start(form) }}
        {{ form_errors(form) }}
        {{ form_row(form.<?= $username_field ?>) }}
<?php if ($has_password): ?>
        {{ form_row(form.<?= $password_field ?>) }}
<?php endif; ?>

        <div>
            <input type="submit" value="{{ 'action.register'|trans }}" />
        </div>
    {{ form_end(form) }}
{% endblock %}
