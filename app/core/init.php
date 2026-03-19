<?php

spl_autoload_register(function ($className)
{
    $parts = explode('\\', ltrim($className, '\\'));
    $shortName = end($parts);

    if (!is_string($shortName) || $shortName === '')
    {
        return;
    }

    $modelFile = __DIR__ . '/../models/' . ucfirst($shortName) . '.php';
    if (is_file($modelFile))
    {
        require_once $modelFile;
    }
});

require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';
require __DIR__ . '/Database.php';
require __DIR__ . '/Model.php';
require __DIR__ . '/ApiController.php';
require __DIR__ . '/App.php';
require __DIR__ . '/Session.php';
require __DIR__ . '/Request.php';
