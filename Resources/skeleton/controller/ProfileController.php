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
