<?php

declare(strict_types=1);

return [
    'config:dump' => App\Commands\Config\DumpCommand::class,
    'config:php' => App\Commands\Config\PHPCommand::class,
    'state:import' => App\Commands\State\ImportCommand::class,
    'state:export' => App\Commands\State\ExportCommand::class,
    'state:push' => App\Commands\State\PushCommand::class,
    'storage:maintenance' => App\Commands\Storage\MaintenanceCommand::class,
    'storage:migrations' => App\Commands\Storage\MigrationsCommand::class,
    'storage:make' => App\Commands\Storage\MakeCommand::class,
    'scheduler:list' => App\Commands\Scheduler\Lists::class,
    'scheduler:run' => App\Commands\Scheduler\Run::class,
    'scheduler:closure' => App\Commands\Scheduler\RunClosure::class,
    'servers:list' => App\Commands\Servers\ListCommand::class,
    'servers:edit' => App\Commands\Servers\EditCommand::class,
    'servers:unify' => App\Commands\Servers\UnifyCommand::class,
];
