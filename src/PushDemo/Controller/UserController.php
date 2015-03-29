<?php

namespace PushDemo\Controller;

use PushDemo\Entity\User;
use PushDemo\Form\Type\UserType;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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
    */

    public function registerAction(Request $request, Application $app)
    {
        $user = new User();
        if ($request->isMethod('POST')) {
            $user->setUsername($request->get('username'));
            $user->setPassword($request->get('password'));
            $user->setMail($request->get('mail'));
            $user->setRole($request->get('role'));
            $user->setGcm($request->get('gcm_regid'));

            $app['repository.user']->save($user);
            $message = 'The user ' . $user->getUsername() . ' has been saved.';
            $app['session']->getFlashBag()->add('success', $message);                
            return new Response($message, 201);
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
