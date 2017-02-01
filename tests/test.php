<?php

require_once '../vendor/autoload.php';

$parser = new \SearchDAV\DAV\QueryParser();

$body = file_get_contents('./basicquery.xml');

var_dump($parser->parse($body)['{DAV:}basicsearch']);
