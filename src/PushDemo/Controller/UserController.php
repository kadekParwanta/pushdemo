<?php

namespace PushDemo\Controller;

use PushDemo\Entity\User;
use PushDemo\Form\Type\UserType;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use PushDemo\Helper\PHP_GCM\Sender;
use PushDemo\Helper\PHP_GCM\Message;

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
    *   201 : Created
    *   500 : Error (Already exist; Error in db)
    */

    public function registerAction(Request $request, Application $app)
    {
        $username = $request->get('username');
        $mail = $request->get('mail');
        $password = $request->get('password');

        $existingUser = $app['repository.user']->loadUserByUsername($username);
        $response = array('error' => FALSE);

        if($existingUser) {
            $response['error'] = TRUE;
            $response['error_message'] = 'User already existed';
            return new Response($response, 500);  
        } else {
            $user = new User();
            $user->setUsername($request->get('username'));
            $user->setPassword($request->get('password'));
            $user->setMail($request->get('mail'));
            $user->setRole($request->get('role'));
            $user->setGcm($request->get('gcm_regid'));

            if ($app['repository.user']->save($user)) {
                $response['error'] = FALSE;
                $response['uid'] = $user->getId();
                $response['user']['name'] = $user->getUsername();
                $response['user']['email'] = $user->getMail;
                $response['user']['created_at'] = $user->getCreatedAt();
                $response['user']['gcmId'] = $user->getGcm;
                $app['session']->getFlashBag()->add('success', $message);                
                return new Response($response, 201);              
            } else {
                $response['error'] = TRUE;
                $response['error_message'] = 'Error occured in registration';
                return new Response($response, 500);  
            }
            
        }
        
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
    *   200 : OK
    *   500 : Error (Incorrect params)
    */

    public function clientLoginAction(Request $request, Application $app)
    {
        $usernameOrEmail = $request->get('usernameOrEmail');
        $password = $request->get('password');

        $existingUser = $app['repository.user']->loadUserByUsername($usernameOrEmail);
        $response = array('error' => FALSE);

        if($existingUser) {
            $response['error'] = FALSE;
            $response['uid'] = $existingUser->getId();
            $response['user']['name'] = $existingUser->getUsername();
            $response['user']['email'] = $existingUser->getMail;
            $response['user']['created_at'] = $existingUser->getCreatedAt();
            $response['user']['gcmId'] = $existingUser->getGcm;
            $app['session']->getFlashBag()->add('success', $message);                
            return new Response($response, 200);   
        } else {
            $response['error'] = TRUE;
            $response['error_message'] = 'Incorrect Email/Username or password!';
            return new Response($response, 500); 
        }
        
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
}
