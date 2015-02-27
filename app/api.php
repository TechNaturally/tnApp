<?php
require 'vendor/autoload.php';

require_once 'tnApp/TN/App.php';

$app = new \Slim\Slim(array(
	'cookies.encrypt' => true,
	'cookies.secret_key' => 'dd1391cefbcb8e30a258bcfa8b3c9e60a0264892fe333aaef9f7a7b79543fe2f'
	));
$app->view(new \JsonApiView());

$tna = new \TN\App($app, 'demo.json');

$app->add(new \JsonApiMiddleware());

$app->get('/', function () use ($app) {
	$app->render(200, array('msg' => $app->getName().' API Online!'));
});

$app->run();
?>