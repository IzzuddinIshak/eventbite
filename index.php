<?php
   ini_set("date.timezone", "Asia/Kuala_Lumpur");

   header('Access-Control-Allow-Origin: *');

   //*
   // Allow from any origin
   if (isset($_SERVER['HTTP_ORIGIN'])) {
      // Decide if the origin in $_SERVER['HTTP_ORIGIN'] is one
      // you want to allow, and if so:
      header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
      header('Access-Control-Allow-Credentials: true');
      header('Access-Control-Max-Age: 86400');    // cache for 1 day
   }

   // Access-Control headers are received during OPTIONS requests
   if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {

      if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
         header("Access-Control-Allow-Methods: GET, POST, DELETE, PUT, OPTIONS");

      if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
         header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

      exit(0);
   }
   //*/

   require_once 'vendor/autoload.php';

   use \Psr\Http\Message\ServerRequestInterface as Request;
   use \Psr\Http\Message\ResponseInterface as Response;

   use Ramsey\Uuid\Uuid;
   use Ramsey\Uuid\Exception\UnsatisfiedDependencyException;

   include_once("salt_pepper.php");
   include_once("database_class.php");

   //load environment variable - jwt secret key
   $dotenv = new Dotenv\Dotenv(__DIR__);
   $dotenv->load();

   //jwt secret key in case dotenv not working in apache
   //$jwtSecretKey = "jwt_secret_key";

   function getDatabase() {
      $dbhost="localhost";
      $dbuser="root";
      $dbpass="";
      $dbname="eventbite";

      $db = new Database($dbhost, $dbuser, $dbpass, $dbname);
      return $db;
   }

   use Slim\App;
   use Slim\Middleware\TokenAuthentication;
   use Firebase\JWT\JWT;

   function getLoginTokenPayload($request, $response) {
      $token_array = $request->getHeader('HTTP_AUTHORIZATION');
      $token = substr($token_array[0], 7);

      //decode the token
      try
      {
         $tokenDecoded = JWT::decode(
            $token,
            getenv('JWT_SECRET'),
            array('HS256')
         );

         //in case dotenv not working
         /*
         $tokenDecoded = JWT::decode(
            $token,
            $GLOBALS['jwtSecretKey'],
            array('HS256')
         );
         */
      }
      catch(Exception $e)
      {
         $data = Array(
            "jwt_status" => "token_invalid"
         );

         return $response->withJson($data, 401)
                         ->withHeader('Content-tye', 'application/json');
      }

      return $tokenDecoded->user_id;
   }

   $config = [
      'settings' => [
         'displayErrorDetails' => true
      ]
   ];

   $app = new App($config);

   $authenticator = function($request, TokenAuthentication $tokenAuth){

      /**
         * Try find authorization token via header, parameters, cookie or attribute
         * If token not found, return response with status 401 (unauthorized)
      */
      $token = $tokenAuth->findToken($request); //from header

      try {
         $tokenDecoded = JWT::decode($token, getenv('JWT_SECRET'), array('HS256'));

         //in case dotenv not working
         //$tokenDecoded = JWT::decode($token, $GLOBALS['jwtSecretKey'], array('HS256'));
      }
      catch(Exception $e) {
         throw new \app\UnauthorizedException('Invalid Token');
      }
   };

   //Add token authentication middleware
   $app->add(new TokenAuthentication([
        'path' => ['/admin', '/create', '/manage', '/edit', '/buy'], //secure route - need token
        'passthrough' => [ //public route, no token needed
          '/',
          '/home',
          '/profile',
          '/login',
          '/signup',
         ],
        'authenticator' => $authenticator
   ]));

   //SIGNUP
   $app->post('/signup', function($request, $response){
      $json = json_decode($request->getBody());
      $username = $json->username;
      $email = $json->email;
      $contact = $json->contact;
      $clearpassword = $json->password;

      //insert user
      $db = getDatabase();
      $dbs = $db->insertUser($username, $clearpassword, $contact, $email);
      $db->close();

      $data = array(
         "insertstatus" => $dbs->status,
         "error" => $dbs->error
      );

      return $response->withJson($data, 200)
                      ->withHeader('Content-type', 'application/json');
   });

   //LOGIN
   $app->post('/login', function($request, $response){
      //extract form data - email and password
      $json = json_decode($request->getBody());
      $username = $json->username;
      $clearpassword = $json->password;

      //do db authentication
      $db = getDatabase();
      $data = $db->authenticateUser($username);
      $db->close();

      //status -1 -> user not found
      //status 0 -> wrong password
      //status 1 -> login success

      $returndata = array(
      );

      //user not found
      if ($data === NULL) {
         $returndata = array(
            'status' => -1
         );
      }
      //user found
      else {
         //now verify password hash - one way mdf hash using salt-peper
         if (pepper($clearpassword, $data->passwordhash)) {
            //correct password

            //create JWT token
            $date = date_create();
            $jwtIAT = date_timestamp_get($date);
            $jwtExp = $jwtIAT + (60 * 60 * 12); //expire after 12 hours

            $jwtToken = array(
               "iss" => "mycontacts.net", //token issuer
               "iat" => $jwtIAT, //issued at time
               "exp" => $jwtExp, //expire
               // "role" => "member",
               "user_id" => $data->user_id,
               "username" => $data->username
            );
            $token = JWT::encode($jwtToken, getenv('JWT_SECRET'));

            $returndata = array(
               'status' => 1,
               'token' => $token,
               "user_id" => $data->user_id,
               'username' => $data->username
            );
         } else {
            //wrong password
            $returndata = array(
               'status' => 0
            );
         }
      }

      return $response->withJson($returndata, 200)
                      ->withHeader('Content-type', 'application/json');
   });

   //GET - HOME
   $app->get('/home', function($request, $response){

      // $ownerlogin = getLoginTokenPayload($request, $response);

      $db = getDatabase();
      $data = $db->getEvents();
      $db->close();

      return $response->withJson($data, 200)
                      ->withHeader('Content-type', 'application/json');
   });

   //GET - VIEW TICKET
   $app->get('/view/{event_id}', function($request, $response, $args){

      $event_id = $args['event_id'];

      $db = getDatabase();
      $data = $db->viewEvent($event_id);
      $db->close();

      return $response->withJson($data, 200)
                      ->withHeader('Content-type', 'application/json');
   });

   //POST - BUY TICKET
   $app->post('/buy/{event_id}', function($request, $response, $args){

     $loginfo = getLoginTokenPayload($request, $response);

     $event_id = $args['event_id'];

      $db = getDatabase();
      $dbs = $db->buyEvent($event_id, $loginfo);
      $db->close();

      $data = array(
         "insertstatus" => $dbs->status,
         "error" => $dbs->error
      );

      return $response->withJson($data, 200)
                      ->withHeader('Content-type', 'application/json');
   });

   //POST - INSERT EVENT
   $app->post('/create', function($request, $response){

      // $ownerlogin = getLoginTokenPayload($request, $response);

      //form data
      $json = json_decode($request->getBody());
      $name = $json->name;
      $time = $json->time;
      $price = $json->price;
      $description = $json->description;

      $db = getDatabase();
      $dbs = $db->insertEvent($name, $time, $price, $description);
      $db->close();

      $data = array(
         "insertstatus" => $dbs->status,
         "error" => $dbs->error
      );

      return $response->withJson($data, 200)
                      ->withHeader('Content-type', 'application/json');
   });

   //GET - MANAGE EVENTS
   $app->get('/manage', function($request, $response){

      // $ownerlogin = getLoginTokenPayload($request, $response);

      $db = getDatabase();
      $data = $db->getManageEvents();
      $db->close();

      return $response->withJson($data, 200)
                      ->withHeader('Content-type', 'application/json');
   });

   //GET - SINGLE EVENT VIA ID
   $app->get('/manage/edit/{event_id}', function($request, $response, $args){

      $event_id = $args['event_id'];

      $db = getDatabase();
      $data = $db->getEventViaId($event_id);
      $db->close();

      return $response->withJson($data, 200)
                      ->withHeader('Content-type', 'application/json');
   });

   //PUT - UPDATE SINGLE EVENT VIA ID
   $app->put('/manage/edit/{event_id}', function($request, $response, $args){

      $event_id = $args['event_id'];

      //form data
      $json = json_decode($request->getBody());
      $name = $json->name;
      $time = $json->time;
      $price = $json->price;
      $description = $json->description;

      $db = getDatabase();
      $dbs = $db->updateEventViaId($event_id, $name, $time, $price, $description);
      $db->close();

      $data = Array(
         "updatestatus" => $dbs->status,
         "error" => $dbs->error
      );

      return $response->withJson($data, 200)
                      ->withHeader('Content-type', 'application/json');
   });

   //DELETE - SINGLE EVENT VIA ID
   $app->delete('/manage/{event_id}', function($request, $response, $args){

      $event_id = $args['event_id'];

      $db = getDatabase();
      $dbs = $db->deleteEventViaId($event_id);
      $db->close();

      $data = Array(
         "deletestatus" => $dbs->status,
         "error" => $dbs->error
      );

      return $response->withJson($data, 200)
                      ->withHeader('Content-type', 'application/json');
   });

   //GET - SELLING RECORD
   $app->get('/record', function($request, $response){

      // $ownerlogin = getLoginTokenPayload($request, $response);

      $db = getDatabase();
      $data = $db->getRecord();
      $db->close();

      return $response->withJson($data, 200)
                      ->withHeader('Content-type', 'application/json');
   });


   //GET - LOAD PROFILE
   $app->get('/profile', function($request, $response, $args){

      $user_id = $args['user_id'];

      $db = getDatabase();
      $data = $db->getProfile($user_id);
      $db->close();

      return $response->withJson($data, 200)
                      ->withHeader('Content-type', 'application/json');
   });

   //PUT - UPDATE PROFILE
   $app->put('/profile', function($request, $response, $args){

      $user_id = $args['user_id'];

      //form data
      $json = json_decode($request->getBody());
      $username = $json->username;
      $email = $json->email;
      $contact = $json->contact;

      $db = getDatabase();
      $dbs = $db->updateProfile($user_id, $username, $email, $contact);
      $db->close();

      $data = Array(
         "updatestatus" => $dbs->status,
         "error" => $dbs->error
      );

      return $response->withJson($data, 200)
                      ->withHeader('Content-type', 'application/json');
   });

   $app->run();
