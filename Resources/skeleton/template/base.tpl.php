<?php /** @link https://github.com/symfony/recipes/blob/master/symfony/twig-bundle/3.3/templates/base.html.twig */ ?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>{% block title %}Welcome!{% endblock %}</title>
        {% block stylesheets %}{% endblock %}
    </head>
    <body>
        {% block header %}
<?php if ($controllers['login'] || $controllers['register']): ?>
            <ul>
                {% if app.user %}
                    <li>{{ 'user.logged_in_as'|trans({ '%username%': <?= MsgPhp\UserBundle\Twig\GlobalVariable::NAME ?>.current.<?= $username_field ?> })|raw }}</li>
<?php if ($controllers['login']): ?>
                    <li><a href="{{ path('profile') }}">{{ 'action.view_profile'|trans }}</a></li>
                    <li><a href="{{ path('logout') }}">{{ 'action.logout'|trans }}</a></li>
<?php endif; ?>
                {% else %}
<?php if ($controllers['login']): ?>
                    <li><a href="{{ path('login') }}">{{ 'action.login'|trans }}</a></li>
<?php endif; ?>
<?php if ($controllers['register']): ?>
                    <li><a href="{{ path('register') }}">{{ 'action.register'|trans }}</a></li>
<?php endif; ?>
                {% endif %}
            </ul>
<?php endif; ?>
        {% endblock %}
        {% block body %}
            {{ include('partials/flash-messages.html.twig') }}
            {% block <?= $base_template_block ?> %}{% endblock %}
        {% endblock %}
        {% block javascripts %}{% endblock %}
    </body>
</html>
