<?php

namespace PushDemo\Controller;

use PushDemo\Entity\User;
use PushDemo\Form\Type\UserType;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ParameterBag;
use PushDemo\Helper\PHP_GCM\Sender;
use PushDemo\Helper\PHP_GCM\Message;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;

class UserController
{
    public function meAction(Request $request, Application $app)
    {
        $token = $app['security']->getToken();
        $user = $token->getUser();
        $now = new \DateTime();
        $interval = $now->diff($user->getCreatedAt());
        $memberSince = $interval->format('%d days %H hours %I minutes ago');

        $data = array(
            'user' => $user,
            'memberSince' => $memberSince,
        );
        return $app['twig']->render('profile.html.twig', $data);
    }

    public function loginAction(Request $request, Application $app)
    {
        $form = $app['form.factory']->createBuilder('form')
            ->add('username', 'text', array('label' => 'Username', 'data' => $app['session']->get('_security.last_username')))
            ->add('password', 'password', array('label' => 'Password'))
            ->add('login', 'submit')
            ->getForm();

        $data = array(
            'form'  => $form->createView(),
            'error' => $app['security.last_error']($request),
        );
        return $app['twig']->render('login.html.twig', $data);
    }

    public function logoutAction(Request $request, Application $app)
    {
        $app['session']->clear();
        return $app->redirect($app['url_generator']->generate('homepage'));
    }

    /*
    * /register API
    * method : POST
    * 
    * params:
    *   username
    *   password
    *   mail
    *   role : ROLE_ADMIN, ROLE_USER, ROLE_COURIER
    *   gcm_regid
    * 
    * return:
    *   json {
    *       'error':0/1,
    *       'error_message':if_any
    *       'uid':'uid'
    *       'user': {
    *             'name':'name'
    *             'email':'email'
    *             'create_at':'create_at'
    *             'gcmId':'gcmId'
    *       }
    *    }
    */

    public function registerAction(Request $request, Application $app)
    {
        $username = $request->get('username');
        $mail = $request->get('mail');
        $password = $request->get('password');
        $role = $request->get('role');
        $gcmId = $request->get('gcm_regid');

        $responseData = array('error' => FALSE);
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json'); 
        //TODO create method isUserExist() in repository
        try {
            $existingUser = $app['repository.user']->loadUserByUsername($username);
            $responseData['error'] = TRUE;
            $responseData['error_message'] = 'User already existed';
            $response->setContent(json_encode($responseData)); 
            $response->setStatusCode(500);  
        } catch (UsernameNotFoundException $e) {
            $user = new User();
            $user->setUsername($username);
            $user->setPassword($password);
            $user->setMail($mail);
            $user->setRole($role);
            $user->setGcm($gcmId);

            $app['repository.user']->save($user);
            $user = $app['repository.user']->loadUserByUsername($username);
            $responseData['error'] = FALSE;
            $responseData['user']['uid'] = $user->getId();
            $responseData['user']['name'] = $user->getUsername();
            $responseData['user']['email'] = $user->getMail();
            $responseData['user']['created_at'] = $user->getCreatedAt();
            $responseData['user']['gcmId'] = $user->getGcm();
            $response->setContent(json_encode($responseData)); 
            $response->setStatusCode(200);  
            $app['session']->getFlashBag()->add('success', $message); 

            //TODO handle error in storing to DB
            // if ($app['repository.user']->save($user)) {
            //     $responseData['error'] = FALSE;
            //     $responseData['uid'] = $user->getId();
            //     $responseData['user']['name'] = $user->getUsername();
            //     $responseData['user']['email'] = $user->getMail();
            //     $responseData['user']['created_at'] = $user->getCreatedAt();
            //     $responseData['user']['gcmId'] = $user->getGcm();
            //     $response->setContent(json_encode($responseData)); 
            //     $response->setStatusCode(200);  
            //     $app['session']->getFlashBag()->add('success', $message);    
            // } else {
            //     $responseData['error'] = TRUE;
            //     $responseData['error_message'] = 'Error occured in registration';
            //     $response->setContent(json_encode($responseData)); 
            //     $response->setStatusCode(500);  
            // }
        }

        return $response;
    }

    /*
    * /client_login API
    * method : POST
    * 
    * params:
    *   usernameOrEmail
    *   password
    * 
    * return:
    *   json {
    *       'error':0/1,
    *       'error_message':if_any
    *       'uid':'uid'
    *       'user': {
    *             'name':'name'
    *             'email':'email'
    *             'create_at':'create_at'
    *             'gcmId':'gcmId'
    *       }
    *    }
    * 
    */

    public function clientLoginAction(Request $request, Application $app)
    {
        $usernameOrEmail = $request->get('usernameOrEmail');
        $password = $request->get('password');
        $responseData = array('error' => FALSE);
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');

        try {
            $existingUser = $app['repository.user']->loadUserByUsernameAndPassword($usernameOrEmail, $password);
            $responseData['error'] = FALSE;
            $responseData['user']['uid'] = $existingUser->getId();
            $responseData['user']['name'] = $existingUser->getUsername();
            $responseData['user']['email'] = $existingUser->getMail();
            $responseData['user']['created_at'] = $existingUser->getCreatedAt();
            $responseData['user']['gcmId'] = $existingUser->getGcm();
            $app['session']->getFlashBag()->add('success', $message);
            $response->setContent(json_encode($responseData));  
            $response->setStatusCode(200);  
        } catch (UsernameNotFoundException $e) {
            $responseData['error'] = TRUE;
            $responseData['error_message'] = 'Incorrect Email/Username or password!';
            $response->setContent(json_encode($responseData));
            $response->setStatusCode(500);  
        }

        return $response;
        
    }

     /*
    * /push_to_user API
    * method : POST
    * 
    * params:
    *   current_user
    *   password
    *   username
    *   message
    * 
    * return:
    *   200 : message sent
    */

    public function pushToUserAction(Request $request, Application $app)
    {
        if ($request->isMethod('POST')) {
            $current_user = $app['repository.user']->loadUserByUsername($request->get('current_user'));
            $current_pass = $request->get('current_user');
            $password = $current_user->getPassword();
            //TODO user auth 

            $user = $app['repository.user']->loadUserByUsername($request->get('username'));
            $gcmId = $user->getGcm();
            $message = $request->get('message');

            $app['monolog']->addDebug('password '.$password.' current_pass = '.$current_pass.' gcmId = '.$gcmId);
            if (!$gcmId) {
                $app->abort(404, 'The requested gcm id was not found. request='.$request);
                return new Response('The requested gcm id was not found', 404);
            }
        
            $registatoin_ids = array($gcmId);
            $pushData = array("message" => $message);
            $app['monolog']->addDebug('registatoin_ids ' . $registatoin_ids . ' pushData = '.$pushData);  

            $collapseKey = 'message with payload';
            $sender = new Sender(GOOGLE_API_KEY);
            $messageSend = new Message($collapseKey, $pushData);
            $numberOfRetryAttempts = 2;
            try {
                $result = $sender->send($messageSend, $gcmId, $numberOfRetryAttempts);
            } catch (\InvalidArgumentException $e) {
            // $deviceRegistrationId was null
                return 'deviceRegistrationId was null';
            } catch (PHP_GCM\InvalidRequestException $e) {
            // server returned HTTP code other than 200 or 503
                return 'server returned HTTP code other than 200 or 503';
            } catch (\Exception $e) {
            // message could not be sent
                return 'message could not be sent';
            }

            return new Response('message sent to '.$user->getUsername(), 200);
        }
    }

    public function editUserAction(Request $request, Application $app)
    {
        $user = $request->attributes->get('user');
        if (!$user) {
            $app->abort(404, 'The requested user was not found.');
        }
        if ($request->isMethod('POST')) {
            $previousPassword = $user->getPassword();
            // If an empty password was entered, restore the previous one.
            $password = $user->getPassword();
            if (!$password) {
                $user->setPassword($previousPassword);
            }

            $app['repository.user']->save($user);
            $message = 'The user ' . $user->getUsername() . ' has been saved.';
            $app['session']->getFlashBag()->add('success', $message);
            return new Response($message, 201);
        }

    }

   /* Temporary Please REMOVE 
    * /user_token API
    * Web hook from ionic

    { 
        "received": "2015-03-18T17:21:42.571286",
        "user_id": 1337,
        "name": "Test_User",
        "app_id": "YOUR_APP_ID",
        "_push": {
            "android_tokens": [
            "asg3tgqg", "3tgfgt23yg", "g3ggqg3g4g", "h45g4wtgwh4"
            ]
        },
        "message": "I come from planet Ion" 
    }

    */

    public function userTokenAction(Request $request, Application $app)
    {
        if ($request->isMethod('POST')) {
            $responseData = array('error' => FALSE);
            $response = new Response();
            $response->headers->set('Content-Type', 'application/json');

            if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
                $app['monolog']->addDebug('request getContent ' . $request->getContent()); 
                $data = json_decode($request->getContent(), true);
                $app['monolog']->addDebug('user_token data ' . $data); 
                if (empty($data)) {
                    return new Response('Json body is empty', 200);
                }
                
                $token_invalid =  $data['token_invalid'];
                if ($token_invalid) {
                    return new Response('Token Invalid', 200);
                }

                $username = $data['name'];
                $app['monolog']->addDebug('user_token username ' . $username); 
                $mail = $data['message'];
                $password = $data['app_id'];
                $role = 'ROLE_USER';
                $gcmId = $data['_push']['android_tokens'][0];
                $app['monolog']->addDebug('user_token gcmId ' . $gcmId);

                try {
                    $existingUser = $app['repository.user']->loadUserByUsername($username);
                    $responseData['error'] = TRUE;
                    $responseData['error_message'] = 'User already existed';
                    $response->setContent(json_encode($responseData)); 
                    $response->setStatusCode(500);  
                } catch (UsernameNotFoundException $e) {
                    $user = new User();
                    $user->setUsername($username);
                    $user->setPassword($password);
                    $user->setMail($mail);
                    $user->setRole($role);
                    $user->setGcm($gcmId);

                    $app['repository.user']->save($user);
                    $user = $app['repository.user']->loadUserByUsername($username);
                    $responseData['error'] = FALSE;
                    $responseData['user']['uid'] = $user->getId();
                    $responseData['user']['name'] = $user->getUsername();
                    $responseData['user']['email'] = $user->getMail();
                    $responseData['user']['created_at'] = $user->getCreatedAt();
                    $responseData['user']['gcmId'] = $user->getGcm();
                    $response->setContent(json_encode($responseData)); 
                    $response->setStatusCode(200);  
                    $app['session']->getFlashBag()->add('success', $message); 
                }
            }

            return $response;
        }
    }

}
