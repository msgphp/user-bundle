<?php

declare(strict_types=1);

return <<<YAML
# see https://github.com/symfony/recipes/blob/master/symfony/messenger/4.1/config/packages/messenger.yaml
framework:
    messenger:
        transports:
            # Uncomment the following line to enable a transport named "amqp"
            # amqp: '%env(MESSENGER_TRANSPORT_DSN)%'

        routing:
            # Route your messages to the transports
            # 'App\Message\YourMessage': amqp

        default_bus: command_bus
        buses:
            command_bus:
                middleware:
                    - msgphp.messenger.console_message_receiver
            event_bus:
                middleware:
                    - msgphp.messenger.console_message_receiver
                    - allow_no_handler

YAML;
