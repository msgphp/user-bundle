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
