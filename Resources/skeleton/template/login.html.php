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

if ($hasForgotPassword) {
    $forgotPasswordLink = <<<'TWIG'

            <p><a href="{{ url('forgot_password') }}">Forgot password?</a></p>
TWIG;
} else {
    $forgotPasswordLink = '';
}

return <<<TWIG
{% extends '${base}' %}

{% block ${block} %}
    <h1>Login</h1>

    {{ form_start(form) }}
        {{ form_errors(form) }}
        {{ form_row(form.${fieldName}) }}
        {{ form_row(form.password) }}

        <div>
            <input type="submit" value="Login" />${forgotPasswordLink}
        </div>
    {{ form_end(form) }}
{% endblock %}

TWIG;
