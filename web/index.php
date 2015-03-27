<?php

require('../vendor/autoload.php');

$app = new Silex\Application();
$app['debug'] = true;

// Register the monolog logging service
$app->register(new Silex\Provider\MonologServiceProvider(), array(
  'monolog.logfile' => 'php://stderr',
));

// Register the Twig templating engine
$app->register(new Silex\Provider\TwigServiceProvider(), array(
  'twig.path' => __DIR__.'/../views',
));

// Register the Postgres database add-on
$dbopts = parse_url(getenv('DATABASE_URL'));
$app->register(new Herrera\Pdo\PdoServiceProvider(),
  array(
    'pdo.dsn' => 'pgsql:dbname='.ltrim($dbopts["path"],'/').';host='.$dbopts["host"],
    'pdo.port' => $dbopts["port"],
    'pdo.username' => $dbopts["user"],
    'pdo.password' => $dbopts["pass"]
  )
);

// Our web handlers

$app->get('/', function() use($app) {
  $app['monolog']->addDebug('logging output.');
  $st = $app['pdo']->prepare('SELECT * FROM gcm_users');
  $st->execute();

  $names = array();
  while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    $app['monolog']->addDebug('Row ' . $row['name']);
    $names[] = $row;
  }

  return $app['twig']->render('index.html.twig', array(
    'users' => $names
  ));
});

$app->get('/db/', function() use($app) {
  $st = $app['pdo']->prepare('SELECT name FROM test_table');
  $st->execute();

  $names = array();
  while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    $app['monolog']->addDebug('Row ' . $row['name']);
    $names[] = $row;
  }

  return $app['twig']->render('database.twig', array(
    'names' => $names
  ));
});

$app->get('/twig/{name}', function($name) use($app) {
  return $app['twig']->render('index.twig', array(
    'name' => $name,
  ));
});


$app->get('/get_user/', function() use($app) {
  $st = $app['pdo']->prepare('SELECT name FROM gcm_users');
  $st->execute();

  $names = array();
  while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    $app['monolog']->addDebug('Row ' . $row['name']);
    $names[] = $row;
  }

  return $app['twig']->render('database.twig', array(
    'names' => $names
  ));
});

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/*
* /register?name=xxxx&email=yyy&gcm_regid=32525
*/

$app->post('/register', function (Request $request) use($app) {
  $name = $request->get('name');
  $email = $request->get('email');
  $gcm_regid = $request->get('gcm_regid');
  $time_stamp = new DateTime();
  $time_stamp = $time_stamp->format('Y-m-d H:i:s');

   $sql = "INSERT INTO gcm_users (name, email, gcm_regid, created_at) VALUES (?, ?, ?, ?)";
  $stmt = $app['pdo']->prepare($sql);
  if (!$stmt) {
    $app['monolog']->addDebug('PDOException ' . $app['pdo']->errorInfo());
	return new Response('PDOException ' . $app['pdo']->errorInfo(), 500);
  }
  
  include_once './GCM.php';
  $stmt->execute(array($name,$email,$gcm_regid,$time_stamp));
  $gcm = new GCM();
  $registatoin_ids = array($gcm_regid);
  $message = array("product" => "shirt");
  $result = $gcm->send_notification($registatoin_ids, $message);
  return new Response("name = {$name}; email = {$email}; gcm_regid = {$gcm_regid}; created_at = {$time_stamp}; message= {$message}", 201);
  
});

$app->get('/send_message', function (Request $request) use($app) {
  $regId = $request->get['regId'];
  $message = $request->get['message'];   
  include_once './GCM.php';
  
  $gcm = new GCM();
  $registatoin_ids = array($regId);
  $message = array("price" => $message);
  $result = $gcm->send_notification($registatoin_ids, $message);
 
  return new Response("regId = {$regId}; message= {$message}", 201);
  
});

$app->run();

?>
