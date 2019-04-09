{% extends '<?= $base_template ?>' %}

{% block <?= $base_template_block ?> %}
    <h1>Your Profile</h1>

    <p>Logged in as: <em>{{ <?= MsgPhp\UserBundle\Twig\GlobalVariable::NAME ?>.current.<?= $username_field ?> }}</em></p>
    <p><a href="{{ path('logout') }}">Logout</a></p>

    <h2>Change username</h2>
    {{ form_start(username_form) }}
        {{ form_errors(username_form) }}
        {{ form_row(username_form.<?= $username_field ?>) }}

        <div>
            <input type="submit" value="Change" />
        </div>
    {{ form_end(username_form) }}

<?php if ($has_password): ?>
    <h2>Change password</h2>
    {{ form_start(password_form) }}
        {{ form_errors(password_form) }}
        <h3>Current password</h3>
        {{ form_row(password_form.current) }}

        <h3>New password</h3>
        {{ form_row(password_form.<?= $password_field ?>) }}

        <div>
            <input type="submit" value="Change" />
        </div>
    {{ form_end(password_form) }}
<?php endif; ?>
{% endblock %}
