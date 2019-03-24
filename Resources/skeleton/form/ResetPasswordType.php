<?php

declare(strict_types=1);

$uses = [
    'use MsgPhp\\User\\Infrastructure\\Form\\Type\\HashedPasswordType;',
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
