<?php

declare(strict_types=1);

return <<<TWIG
{% extends '${base}' %}

{% block ${block} %}
    <h1>Your Profile</h1>

    <p>Logged in as: <em>{{ msgphp_user.user.${fieldName} }}</em></p>
{% endblock %}

TWIG;
