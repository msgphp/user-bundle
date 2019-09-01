{% extends '<?= $base_template ?>' %}

{% block <?= $base_template_block ?> %}
    <h1>{{ 'title.my_profile'|trans }}</h1>

    <p>{{ 'user.logged_in_as'|trans({ '%username%': <?= MsgPhp\UserBundle\Twig\GlobalVariable::NAME ?>.current.<?= $username_field ?> })|raw }}</p>
    <p><a href="{{ path('logout') }}">{{ 'action.logout'|trans }}</a></p>

    <h2>{{ 'title.change_username'|trans }}</h2>
    {{ form_start(username_form) }}
        {{ form_errors(username_form) }}
        {{ form_row(username_form.<?= $username_field ?>) }}

        <div>
            <input type="submit" value="{{ 'action.change'|trans }}" />
        </div>
    {{ form_end(username_form) }}

<?php if ($has_password): ?>
    <h2>{{ 'title.change_password'|trans }}</h2>
    {{ form_start(password_form) }}
        {{ form_errors(password_form) }}
        {{ form_row(password_form.current) }}
        {{ form_row(password_form.<?= $password_field ?>) }}

        <div>
            <input type="submit" value="{{ 'action.change'|trans }}" />
        </div>
    {{ form_end(password_form) }}
<?php endif; ?>
{% endblock %}
