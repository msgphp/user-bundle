<?php

declare(strict_types=1);

return <<<PHP
<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator \$container) {
    \$container->extension('msgphp_user', ${config});
};

PHP;
