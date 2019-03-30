{% extends '<?= $base_template ?>' %}

{% block <?= $base_template_block ?> %}
    <h1>Login</h1>

    {{ form_start(form) }}
        {{ form_errors(form) }}
        {{ form_row(form.<?= $username_field ?>) }}
        {{ form_row(form.<?= $password_field ?>) }}

        <div>
            <input type="submit" value="Login" />
<?php if ($has_forgot_password): ?>
            <p><a href="{{ url('forgot_password') }}">Forgot password?</a></p>
<?php endif; ?>
        </div>
    {{ form_end(form) }}
{% endblock %}
