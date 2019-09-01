<?php /** @link https://github.com/symfony/recipes/blob/master/symfony/twig-bundle/3.3/templates/base.html.twig */ ?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>{% block title %}Welcome!{% endblock %}</title>
        {% block stylesheets %}{% endblock %}
    </head>
    <body>
        {% block header %}{% endblock %}
        {% block body %}
            {{ include('partials/flash-messages.html.twig') }}
            {% block <?= $base_template_block ?> %}{% endblock %}
        {% endblock %}
        {% block javascripts %}{% endblock %}
    </body>
</html>
