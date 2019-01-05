<?php

declare(strict_types=1);

$fieldType = 'email' === $fieldName ? 'EmailType' : 'TextType';
$uniqueValidator = 'Unique'.ucfirst($fieldName);
$uses = [
    'use MsgPhp\\User\\Infra\\Validator\\UniqueUsername as '.$uniqueValidator.';',
    'use Symfony\\Component\\Form\\AbstractType;',
    'use Symfony\\Component\\Form\\Extension\\Core\\Type\\'.$fieldType.';',
    'use Symfony\\Component\\Form\\FormBuilderInterface;',
    'use Symfony\\Component\\Validator\\Constraints\\NotBlank;',
];

$validators = ['new NotBlank()'];
if ('EmailType' === $fieldType) {
    $validators[] = 'new Email()';
    $uses[] = 'use Symfony\\Component\\Validator\\Constraints\\Email;';
}
$validators[] = 'new '.$uniqueValidator.'()';

$constraints = implode(', ', $validators);
$fields = <<<PHP
        \$builder->add('${fieldName}', ${fieldType}::class, [
            'constraints' => [${constraints}],
        ]);
PHP;

if ($hasPassword) {
    $uses[] = 'use MsgPhp\\User\\Infra\\Form\\Type\\HashedPasswordType;';
    $fields .= <<<'PHP'

        $builder->add('password', HashedPasswordType::class, [
            'password_confirm' => true,
            'password_options' => ['constraints' => new NotBlank()],
        ]);
PHP;
}

sort($uses);
$uses = implode("\n", $uses);

return <<<PHP
<?php

declare(strict_types=1);

namespace ${ns};

${uses}

final class RegisterType extends AbstractType
{
    public function buildForm(FormBuilderInterface \$builder, array \$options)
    {
${fields}
    }
}

PHP;
