{% extends '<?= $base_template ?>' %}

{% block <?= $base_template_block ?> %}
    <h1>Forgot Password</h1>

    {{ form_start(form) }}
        {{ form_errors(form) }}
        {{ form_row(form.<?= $username_field ?>) }}

        <div>
            <input type="submit" value="Request a password" />
        </div>
    {{ form_end(form) }}
{% endblock %}
