<?php

namespace PushDemo\Controller;

use PushDemo\Entity\User;
use PushDemo\Form\Type\UserType;
use PushDemo\Helper\PHP_GCM\Sender;
use PushDemo\Helper\PHP_GCM\Message;
use Silex\Application;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;

class AdminUserController
{
    public function indexAction(Request $request, Application $app)
    {
        // Perform pagination logic.
        $limit = 10;
        $total = $app['repository.user']->getCount();
        $numPages = ceil($total / $limit);
        $currentPage = $request->query->get('page', 1);
        $offset = ($currentPage - 1) * $limit;
        $users = $app['repository.user']->findAll($limit, $offset);

        $data = array(
            'users' => $users,
            'currentPage' => $currentPage,
            'numPages' => $numPages,
            'here' => $app['url_generator']->generate('admin_users'),
        );
        return $app['twig']->render('admin_users.html.twig', $data);
    }

    public function addAction(Request $request, Application $app)
    {
        $user = new User();
        $form = $app['form.factory']->create(new UserType(), $user);

        if ($request->isMethod('POST')) {
            $form->bind($request);
            if ($form->isValid()) {
                $app['repository.user']->save($user);
                $message = 'The user ' . $user->getUsername() . ' has been saved.';
                $app['session']->getFlashBag()->add('success', $message);
                // Redirect to the edit page.
                $redirect = $app['url_generator']->generate('admin_user_edit', array('user' => $user->getId()));
                
                return $app->redirect($redirect);
            }
        }

        $data = array(
            'form' => $form->createView(),
            'title' => 'Add new user',
        );
        return $app['twig']->render('form.html.twig', $data);
    }

    public function editAction(Request $request, Application $app)
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

    public function deleteAction(Request $request, Application $app)
    {
        $user = $request->attributes->get('user');
        if (!$user) {
            $app->abort(404, 'The requested user was not found.');
        }

        $app['repository.user']->delete($user->getId());
        return $app->redirect($app['url_generator']->generate('admin_users'));
    }

    public function sendAction(Request $request, Application $app)
    {
        $gcmId = $request->get('gcm');
        $message = $request->get('message');
        if (!$gcmId) {
            $app->abort(404, 'The requested gcm id was not found. request='.$request);
        }
        
        $registatoin_ids = array($gcmId);
        $pushData = array("message" => $message);
        // $payloadData = array(
        //     'registration_ids' => $registatoin_ids,
        //     'data' => $pushData,
        // );
        
        //TODO GCM integration 1
        /* 
        $gcm = new GCM();
        $result = $gcm->send_notification($registatoin_ids, $pushData, $app);*/  
        $app['monolog']->addDebug('registatoin_ids ' . $registatoin_ids . ' pushData = '.$pushData);  

        //TODO GCM integration 2
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

        return $app->redirect($app['url_generator']->generate('homepage'));
    }
}
