<?php

declare(strict_types=1);

$uses = [
    'use Symfony\\Component\\HttpFoundation\\Response;',
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
 * @Route("/profile", name="profile")
 */
final class ProfileController
{
    public function __invoke(Environment \$twig): Response
    {
        return new Response(\$twig->render('${template}'));
    }
}

PHP;
