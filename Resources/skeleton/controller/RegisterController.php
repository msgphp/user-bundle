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

$uses = [
    'use '.$formNs.'\\RegisterType;',
    'use MsgPhp\\User\\Command\\CreateUserCommand;',
    'use Symfony\\Component\\Form\\FormFactoryInterface;',
    'use Symfony\\Component\\HttpFoundation\\Request;',
    'use Symfony\\Component\\HttpFoundation\\RedirectResponse;',
    'use Symfony\\Component\\HttpFoundation\\Response;',
    'use Symfony\\Component\\HttpFoundation\\Session\\Flash\\FlashBagInterface;',
    'use Symfony\\Component\\Messenger\\MessageBusInterface;',
    'use Symfony\\Component\\Routing\\Annotation\\Route;',
    'use Twig\\Environment;',
];

sort($uses);
$uses = implode("\n", $uses);

return <<<PHP
<?php

declare(strict_types=1);

namespace ${ns};

${uses}

/**
 * @Route("/register", name="register")
 */
final class RegisterController
{
    public function __invoke(
        Request \$request,
        FormFactoryInterface \$formFactory,
        FlashBagInterface \$flashBag,
        Environment \$twig,
        MessageBusInterface \$bus
    ): Response {
        \$form = \$formFactory->createNamed('', RegisterType::class);
        \$form->handleRequest(\$request);

        if (\$form->isSubmitted() && \$form->isValid()) {
            \$bus->dispatch(new CreateUserCommand(\$form->getData()));
            \$flashBag->add('success', 'You\\'re successfully registered.');

            return new RedirectResponse('${redirect}');
        }

        return new Response(\$twig->render('${template}', [
            'form' => \$form->createView(),
        ]));
    }
}

PHP;
