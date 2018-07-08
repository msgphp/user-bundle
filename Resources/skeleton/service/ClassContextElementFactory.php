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

$constructor = '{';
if (false !== strpos($credentialClass, 'Password')) {
    $uses[] = 'use MsgPhp\User\Password\PasswordHashingInterface;';
    $constructor = <<<PHP
{
    private \$passwordHashing;

    public function __construct(PasswordHashingInterface \$passwordHashing)
    {
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
${constructor}
    public function getElement(string \$class, string \$method, string \$argument): ContextElement
    {
        \$element = new ContextElement(ucfirst(preg_replace(['/([A-Z]+)([A-Z][a-z])/', '/([a-z\\d])([A-Z])/'], ['\\\\1 \\\\2', '\\\\1 \\\\2'], \$argument)));${switch}

        return \$element;
    }
}

PHP;
