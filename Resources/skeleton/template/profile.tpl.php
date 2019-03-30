{% extends '<?= $base_template ?>' %}

{% block <?= $base_template_block ?> %}
    <h1>Your Profile</h1>

    <p>Logged in as: <em>{{ <?= MsgPhp\UserBundle\Twig\GlobalVariable::NAME ?>.current.<?= $username_field ?> }}</em></p>
    <p><a href="{{ path('logout') }}">Logout</a></p>
{% endblock %}
