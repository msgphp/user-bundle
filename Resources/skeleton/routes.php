<?php

declare(strict_types=1);

$routesConfig = $sep = '';

foreach ($routes as $route) {
    $routesConfig .= $sep.'        '.str_replace("\n", "\n        ", $route);
    $sep = "\n";
}

return <<<PHP
<?php

use Symfony\\Component\\Routing\\Loader\\Configurator\\RoutingConfigurator;

return function (RoutingConfigurator \$routes) {
    \$routes
{$routesConfig}
    ;
};

PHP;
