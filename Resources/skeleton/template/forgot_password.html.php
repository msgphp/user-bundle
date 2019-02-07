<?php

declare(strict_types=1);

return <<<TWIG
{% extends '${base}' %}

{% block ${block} %}
    <h1>Forgot Password</h1>

    {{ form_start(form) }}
        {{ form_errors(form) }}
        {{ form_row(form.${fieldName}) }}

        <div>
            <input type="submit" value="Request a password" />
        </div>
    {{ form_end(form) }}
{% endblock %}

TWIG;
