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
    'use '.$userClass.';',
    'use '.$formNs.'\\ForgotPasswordType;',
    'use MsgPhp\\User\\Command\\RequestUserPasswordCommand;',
    'use Doctrine\\ORM\\EntityManagerInterface;',
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
 * @Route("/forgot-password", name="forgot_password")
 */
final class ForgotPasswordController
{
    public function __invoke(
        Request \$request,
        FormFactoryInterface \$formFactory,
        FlashBagInterface \$flashBag,
        Environment \$twig,
        MessageBusInterface \$bus,
        EntityManagerInterface \$em
    ): Response {
        \$form = \$formFactory->createNamed('', ForgotPasswordType::class);
        \$form->handleRequest(\$request);

        if (\$form->isSubmitted() && \$form->isValid()) {
            \$user = \$em->getRepository(${userShortClass}::class)->findOneBy(['credential.${fieldName}' => \$form->getData()['${fieldName}']]);
            \$bus->dispatch(new RequestUserPasswordCommand(\$user->getId()));
            \$flashBag->add('success', 'You\\'re password is requested.');

            return new RedirectResponse('/login');
        }

        return new Response(\$twig->render('${template}', [
            'form' => \$form->createView(),
        ]));
    }
}

PHP;
