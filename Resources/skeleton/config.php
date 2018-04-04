<?php

declare(strict_types=1);

$serviceConfig = '';
if ($services) {
    $services = implode("\n", array_map(function ($class, string $alias): string {
        $service = '        ->set('.(is_string($class) ? $class : $alias).'::class)';
        if (is_string($class)) {
            $service .= "\n        ->alias(${alias}::class, ${class}::class)";
        }

        return $service;
    }, array_keys($services), $services));
    $serviceConfig = <<<PHP


    \$container->services()
        ->defaults()
            ->private()
            ->autoconfigure()
            ->autowire()

${services}
    ;
PHP;
}

return <<<PHP
<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator \$container) {
    \$container->extension('msgphp_user', ${config});${serviceConfig}
};

PHP;
