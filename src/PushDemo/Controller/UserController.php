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

        $existingUser = $app['repository.user']->loadUserByUsername($username);
        $responseData = array('error' => FALSE);
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json'); 

        if($existingUser) {
            $responseData['error'] = TRUE;
            $responseData['error_message'] = 'User already existed';
            $response->setContent(json_encode($responseData));
        } else {
            $user = new User();
            $user->setUsername($request->get('username'));
            $user->setPassword($request->get('password'));
            $user->setMail($request->get('mail'));
            $user->setRole($request->get('role'));
            $user->setGcm($request->get('gcm_regid'));

            if ($app['repository.user']->save($user)) {
                $responseData['error'] = FALSE;
                $responseData['uid'] = $user->getId();
                $responseData['user']['name'] = $user->getUsername();
                $responseData['user']['email'] = $user->getMail;
                $responseData['user']['created_at'] = $user->getCreatedAt();
                $responseData['user']['gcmId'] = $user->getGcm;
                $response->setContent(json_encode($responseData));
                $app['session']->getFlashBag()->add('success', $message);    
            } else {
                $responseData['error'] = TRUE;
                $responseData['error_message'] = 'Error occured in registration';
                $response->setContent(json_encode($responseData));
            }
            
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

        $existingUser = $app['repository.user']->loadUserByUsername($usernameOrEmail);
        $responseData = array('error' => FALSE);
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json'); 

        if($existingUser) {
            $responseData['error'] = FALSE;
            $responseData['uid'] = $existingUser->getId();
            $responseData['user']['name'] = $existingUser->getUsername();
            $responseData['user']['email'] = $existingUser->getMail;
            $responseData['user']['created_at'] = $existingUser->getCreatedAt();
            $responseData['user']['gcmId'] = $existingUser->getGcm;
            $app['session']->getFlashBag()->add('success', $message);
            $response->setContent(json_encode($responseData));               
            
        } else {
            $responseData['error'] = TRUE;
            $responseData['error_message'] = 'Incorrect Email/Username or password!';
            $response->setContent(json_encode($responseData));
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
}
