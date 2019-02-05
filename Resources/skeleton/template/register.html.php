<?php

declare(strict_types=1);

/*
 * This file is part of the MsgPHP package.
 *
 * (c) Roland Franssen <franssen.roland@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$fields = <<<TWIG
        {{ form_row(form.${fieldName}) }}
TWIG;

if ($hasPassword) {
    $fields .= <<<'TWIG'

        {{ form_row(form.password.password) }}
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
