<?php

require_once __DIR__ . '/../vendor/autoload.php';

$server = new \Sabre\DAV\Server();
$server->addPlugin(new \SearchDAV\DAV\SearchPlugin(new \SearchDAV\Test\DummyBackend()));

$server->exec();
