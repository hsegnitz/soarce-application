<?php

use Slim\App;

$container = require '../application/Bootstrap.php';

$app = new App($container);

$app->get('/',            '\Soarce\Application\Controllers\IndexController:index');
$app->any('/receive',     '\Soarce\Application\Controllers\ReceiveController:index');
$app->any('/maintenance', '\Soarce\Application\Controllers\MaintenanceController:index');
$app->group('/control',   function() {
    $this->get('',                      '\Soarce\Application\Controllers\ControlController:index');
    $this->any('/usecases[/{usecase}]', '\Soarce\Application\Controllers\ControlController:usecase');
    $this->any('/services[/{service}]', '\Soarce\Application\Controllers\ControlController:service');
});
$app->group('/coverage',   function() {
    $this->get('',                                       '\Soarce\Application\Controllers\CoverageController:index');
    $this->get('/file/{file:[0-9]+}',                    '\Soarce\Application\Controllers\CoverageController:file');
    $this->get('/file/{file:[0-9]+}/line/{line:[0-9]+}', '\Soarce\Application\Controllers\CoverageController:line');
});
$app->group('/trace',   function() {
    $this->get('',                   '\Soarce\Application\Controllers\TraceController:index');
    $this->get('/calls',             '\Soarce\Application\Controllers\TraceController:calls');
    $this->get('/usecaseByFile',     '\Soarce\Application\Controllers\TraceController:usecaseByFile');
});

$app->run();
