<?php

declare(strict_types=1);

$fields = <<<TWIG
        {{ form_row(form.${fieldName}) }}
TWIG;

if ($hasPassword) {
    $fields .= <<<'TWIG'

        {{ form_row(form.password.plain) }}
        {{ form_row(form.password.confirmation) }}
TWIG;
}

return <<<TWIG
{% extends '${base}' %}

{% block ${block} %}
    <h1>Register</h1>

    {{ form_start(form) }}
        {{ form_errors(form) }}
${fields}

        <div>
            <input type="submit" value="Register" />
        </div>
    {{ form_end(form) }}
{% endblock %}

TWIG;
