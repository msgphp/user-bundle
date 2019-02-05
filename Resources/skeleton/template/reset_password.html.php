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
