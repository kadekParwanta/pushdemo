<?php

namespace PushDemo\Controller;

use PushDemo\Entity\User;
use PushDemo\Form\Type\UserType;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

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

    public function registerAction(Request $request, Application $app)
    {
        $user = new User();
        $form = $app['form.factory']->create(new UserType(), $user);

        if ($request->isMethod('POST')) {
            $form->bind($request);
            if ($form->isValid()) {
                $app['repository.user']->save($user);
                $message = 'The user ' . $user->getUsername() . ' has been saved.';
                $app['session']->getFlashBag()->add('success', $message);                
                 return new Response($message, 201);
            }
        }

        $data = array(
            'form' => $form->createView(),
            'title' => 'Add new user',
        );
        return $app['twig']->render('form.html.twig', $data);
    }

    public function editUserAction(Request $request, Application $app)
    {
        $user = $request->attributes->get('user');
        if (!$user) {
            $app->abort(404, 'The requested user was not found.');
        }
        $form = $app['form.factory']->create(new UserType(), $user);

        if ($request->isMethod('POST')) {
            $previousPassword = $user->getPassword();
            $form->bind($request);
            if ($form->isValid()) {
                // If an empty password was entered, restore the previous one.
                $password = $user->getPassword();
                if (!$password) {
                    $user->setPassword($previousPassword);
                }

                $app['repository.user']->save($user);
                $message = 'The user ' . $user->getUsername() . ' has been saved.';
                $app['session']->getFlashBag()->add('success', $message);
            }
        }

        $data = array(
            'form' => $form->createView(),
            'title' => 'Edit user ' . $user->getUsername(),
        );
        return $app['twig']->render('form.html.twig', $data);
    }
}
