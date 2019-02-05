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

return <<<'YAML'
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
                default_middleware: allow_no_handlers
                middleware:
                    - msgphp.messenger.console_message_receiver

services:
    msgphp.messenger.command_bus: '@command_bus'
    msgphp.messenger.event_bus: '@event_bus'

YAML;
