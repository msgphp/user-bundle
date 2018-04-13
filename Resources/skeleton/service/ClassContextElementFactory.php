<?php

declare(strict_types=1);

$uses = [
    'use '.$userClass.';',
    'use '.$credentialClass.';',
    'use MsgPhp\\Domain\\Infra\\Console\\Context\\ClassContextElementFactoryInterface;',
    'use MsgPhp\\Domain\\Infra\\Console\\Context\\ContextElement;',
];
$cases = [];

if (false !== strpos($credentialClass, 'Email')) {
    $cases[] = <<<PHP
            case 'email':
                \$element->label = 'E-mail';
                break;
PHP;
}

if (false !== strpos($credentialClass, 'Password')) {
    $uses[] = 'use MsgPhp\User\Password\PasswordHashingInterface;';
    $constructor = <<<PHP
    private \$factory;
    private \$passwordHashing;

    public function __construct(ClassContextElementFactoryInterface \$factory, PasswordHashingInterface \$passwordHashing)
    {
        \$this->factory = \$factory;
        \$this->passwordHashing = \$passwordHashing;
    }
PHP;
    $cases[] = <<<PHP
            case 'password':
                if (${userShortClass}::class === \$class || ${credentialShortClass}::class === \$class) {
                    \$element
                        ->hide()
                        ->generator(function (): string {
                            return bin2hex(random_bytes(8));
                        })
                        ->normalizer(function (string \$value): string {
                            return \$this->passwordHashing->hash(\$value);
                        });
                }
                break;
PHP;
} else {
    $constructor = <<<PHP
    private \$factory;

    public function __construct(ClassContextElementFactoryInterface \$factory)
    {
        \$this->factory = \$factory;
    }
PHP;
}

$switch = '';
if ($cases) {
    $switch = "\n\n        switch (\$argument) {\n".implode("\n", $cases)."\n        }";
}

sort($uses);
$uses = implode("\n", $uses);

return <<<PHP
<?php

declare(strict_types=1);

namespace ${ns};

${uses}

final class ${class} implements ClassContextElementFactoryInterface
{
${constructor}

    public function getElement(string \$class, string \$method, string \$argument): ContextElement
    {
        \$element = \$this->factory->getElement(\$class, \$method, \$argument);${switch}

        return \$element;
    }
}

PHP;
