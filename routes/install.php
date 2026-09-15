<?php

declare(strict_types=1);

/**
 * Installer routes. These are the ONLY routes available until installation
 * completes, and they become inaccessible (403) once storage/installed.lock
 * exists — see Kernel::handle().
 */

use App\Controllers\InstallController;
use App\Core\Kernel;

$router = Kernel::router();

$router->get('/install', [InstallController::class, 'welcome']);
$router->get('/install/requirements', [InstallController::class, 'requirements']);
$router->get('/install/database', [InstallController::class, 'database']);
$router->post('/install/database/test', [InstallController::class, 'testDatabase'])->middleware('no_csrf');
$router->post('/install/database', [InstallController::class, 'saveDatabase'])->middleware('no_csrf');
$router->get('/install/application', [InstallController::class, 'application']);
$router->post('/install/application', [InstallController::class, 'saveApplication'])->middleware('no_csrf');
$router->get('/install/run', [InstallController::class, 'runInstall']);
$router->post('/install/run', [InstallController::class, 'runInstall'])->middleware('no_csrf');
$router->get('/install/complete', [InstallController::class, 'complete']);
