<?php /** @link https://github.com/symfony/recipes/blob/master/symfony/security-bundle/3.3/config/packages/security.yaml */ ?>
security:
    encoders:
        MsgPhp\User\Infrastructure\Security\UserIdentity: <?= $hashing."\n" ?>
    # https://symfony.com/doc/current/security.html#where-do-users-come-from-user-providers
    providers:
        msgphp_user: { id: MsgPhp\User\Infrastructure\Security\UserIdentityProvider }
    firewalls:
        dev:
            pattern: ^/(_(profiler|wdt)|css|images|js)/
            security: false
        main:
            anonymous: true
            provider: msgphp_user

            # activate different ways to authenticate

            # http_basic: true
            # https://symfony.com/doc/current/security.html#a-configuring-how-your-users-will-authenticate

            # https://symfony.com/doc/current/security/form_login_setup.html
            form_login:
                login_path: /login
                check_path: /login
                default_target_path: /profile
                username_parameter: <?= $username_field."\n" ?>
                password_parameter: <?= $password_field."\n" ?>

            logout:
                path: logout

    # Easy way to control access for large sections of your site
    # Note: Only the *first* access control that matches will be used
    access_control:
        # - { path: ^/admin, roles: ROLE_ADMIN }
        - { path: ^/profile, roles: <?= $default_role ?> }
