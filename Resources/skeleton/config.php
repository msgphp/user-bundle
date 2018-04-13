<?php

declare(strict_types=1);

$extensionConfig = $servicesConfig = $sep = '';

if ($config) {
    $extensionConfig = <<<PHP
    \$container->extension('msgphp_user', ${config});
PHP;
}

foreach ($services as $service) {
    $servicesConfig .= $sep.'        '.str_replace("\n", "\n        ", $service);
    $sep = "\n\n";
}

if ($servicesConfig) {
    if ($extensionConfig) {
        $extensionConfig .= "\n\n";
    }
    $servicesConfig = <<<PHP
    \$container->services()
        ->defaults()
            ->private()
            ->autoconfigure()
            ->autowire()

${servicesConfig}
    ;
PHP;
}

return <<<PHP
<?php

use Symfony\\Component\\DependencyInjection\\Loader\\Configurator\\ContainerConfigurator;
use function Symfony\\Component\\DependencyInjection\\Loader\\Configurator\\ref;

return function (ContainerConfigurator \$container) {
${extensionConfig}${servicesConfig}
};

PHP;
