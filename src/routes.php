<?php

// Register route converters.
// Each converter needs to check if the $id it received is actually a value,
// as a workaround for https://github.com/silexphp/Silex/pull/768.
$app['controllers']->convert('user', function ($id) use ($app) {
    if ($id) {
        return $app['repository.user']->find($id);
    }
});

// Register routes.
$app->get('/', 'PushDemo\Controller\IndexController::indexAction')
    ->bind('homepage');

$app->get('/me', 'PushDemo\Controller\UserController::meAction')
    ->bind('me');
$app->match('/login', 'PushDemo\Controller\UserController::loginAction')
    ->bind('login');
$app->get('/logout', 'PushDemo\Controller\UserController::logoutAction')
    ->bind('logout');

$app->get('/admin', 'PushDemo\Controller\AdminController::indexAction')
    ->bind('admin');

$app->get('/admin/users', 'PushDemo\Controller\AdminUserController::indexAction')
    ->bind('admin_users');
$app->match('/admin/users/add', 'PushDemo\Controller\AdminUserController::addAction')
    ->bind('admin_user_add');
$app->match('/admin/users/{user}/edit', 'PushDemo\Controller\AdminUserController::editAction')
    ->bind('admin_user_edit');
$app->match('/admin/users/{user}/delete', 'PushDemo\Controller\AdminUserController::deleteAction')
    ->bind('admin_user_delete');
