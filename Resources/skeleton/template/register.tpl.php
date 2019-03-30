{% extends '<?= $base_template ?>' %}

{% block <?= $base_template_block ?> %}
    <h1>Register</h1>

    {{ form_start(form) }}
        {{ form_errors(form) }}s
        {{ form_row(form.<?= $username_field ?>) }}
<?php if ($has_password): ?>
        {{ form_row(form.<?= $password_field ?>.plain) }}
        {{ form_row(form.<?= $password_field ?>.confirmation) }}
<?php endif; ?>

        <div>
            <input type="submit" value="Register" />
        </div>
    {{ form_end(form) }}
{% endblock %}
