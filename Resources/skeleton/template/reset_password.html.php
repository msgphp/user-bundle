<?php

declare(strict_types=1);

return <<<TWIG
{% extends '${base}' %}

{% block ${block} %}
    <h1>Reset Password</h1>

    {{ form_start(form) }}
        {{ form_errors(form) }}
        {{ form_row(form.password.password) }}
        {{ form_row(form.password.confirmation) }}

        <div>
            <input type="submit" value="Reset your password" />
        </div>
    {{ form_end(form) }}
{% endblock %}

TWIG;
