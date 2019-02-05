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
    'use MsgPhp\\User\\Infra\\Form\\Type\\HashedPasswordType;',
    'use Symfony\\Component\\Form\\AbstractType;',
    'use Symfony\\Component\\Form\\FormBuilderInterface;',
    'use Symfony\\Component\\Validator\\Constraints\\NotBlank;',
];

sort($uses);
$uses = implode("\n", $uses);

return <<<PHP
<?php

declare(strict_types=1);

namespace ${ns};

${uses}

final class ResetPasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface \$builder, array \$options)
    {
        \$builder->add('password', HashedPasswordType::class, [
            'password_confirm' => true,
            'password_options' => ['constraints' => new NotBlank()],
        ]);
    }
}

PHP;
