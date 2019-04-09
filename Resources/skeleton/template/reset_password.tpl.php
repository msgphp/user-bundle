{% extends '<?= $base_template ?>' %}

{% block <?= $base_template_block ?> %}
    <h1>Reset Password</h1>

    {{ form_start(form) }}
        {{ form_errors(form) }}
        {{ form_row(form.<?= $password_field ?>) }}

        <div>
            <input type="submit" value="Reset your password" />
        </div>
    {{ form_end(form) }}
{% endblock %}
