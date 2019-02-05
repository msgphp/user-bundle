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

use MsgPhp\UserBundle\Twig\GlobalVariable;

$userVariable = GlobalVariable::NAME;

return <<<TWIG
{% extends '${base}' %}

{% block ${block} %}
    <h1>Your Profile</h1>

    <p>Logged in as: <em>{{ ${userVariable}.current.${fieldName} }}</em></p>
    <p><a href="{{ path('logout') }}">Logout</a></p>
{% endblock %}

TWIG;
