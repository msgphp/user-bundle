<?php

declare(strict_types=1);

$uses = [
    'use '.$formNs.'\\LoginType;',
    'use Symfony\\Component\\Form\\FormError;',
    'use Symfony\\Component\\Form\\FormFactoryInterface;',
    'use Symfony\\Component\\HttpFoundation\\Response;',
    'use Symfony\\Component\\Routing\\Annotation\\Route;',
    'use Symfony\\Component\\Security\\Http\\Authentication\\AuthenticationUtils;',
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
 * @Route("/login", name="login")
 */
final class LoginController
{
    public function __invoke(
        Environment \$twig,
        FormFactoryInterface \$formFactory,
        AuthenticationUtils \$authenticationUtils
    ): Response {
        \$form = \$formFactory->createNamed('', LoginType::class, [
            '${fieldName}' => \$authenticationUtils->getLastUsername(),
        ]);

        if (null !== \$error = \$authenticationUtils->getLastAuthenticationError(true)) {
            \$form->addError(new FormError(\$error->getMessage(), \$error->getMessageKey(), \$error->getMessageData()));
        }

        return new Response(\$twig->render('${template}', [
            'form' => \$form->createView(),
        ]));
    }
}

PHP;
