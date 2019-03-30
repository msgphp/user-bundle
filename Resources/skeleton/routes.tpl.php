<?= "<?php\n" ?>

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes
<?php foreach ($routes as $route): ?>
        <?= str_replace("\n", "\n        ", $route)."\n" ?>
<?php endforeach; ?>
    ;
};
