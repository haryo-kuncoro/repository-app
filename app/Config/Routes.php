<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// Auto-routing dimatikan (lihat Config\Routing). Semua route didefinisikan eksplisit.
$routes->get('/', 'Auth::index');

// --- Tamu ----------------------------------------------------------------
$routes->group('', ['filter' => 'guest'], static function (RouteCollection $routes): void {
    $routes->get('login', 'Auth::login');
    $routes->post('login', 'Auth::attempt');
    $routes->post('captcha/verify', 'Captcha::verify');
});

// --- Wajib login ---------------------------------------------------------
$routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->post('logout', 'Auth::logout');

    // Profil & password (semua pengguna login)
    $routes->get('profile', 'Profile::index');
    $routes->post('profile', 'Profile::update');
    $routes->get('profile/password', 'Profile::password');
    $routes->post('profile/password', 'Profile::updatePassword');

    // Dashboard
    $routes->get('dashboard', 'Dashboard::index', ['filter' => 'perm:dashboard.view']);

    // Berkas
    $routes->get('files', 'Files::index', ['filter' => 'perm:files.view']);
    $routes->get('files/data', 'Files::data', ['filter' => 'perm:files.view']);
    $routes->get('files/create', 'Files::create', ['filter' => 'perm:files.create']);
    $routes->post('files/store', 'Files::store', ['filter' => 'perm:files.create']);
    $routes->get('files/trash', 'Files::trash', ['filter' => 'perm:files.manage_all']);
    $routes->get('files/(:num)', 'Files::show/$1', ['filter' => 'perm:files.view']);
    $routes->get('files/(:num)/download', 'Files::download/$1', ['filter' => 'perm:files.download']);
    $routes->get('files/(:num)/preview', 'Files::preview/$1', ['filter' => 'perm:files.download']);
    $routes->get('files/(:num)/edit', 'Files::edit/$1', ['filter' => 'perm:files.edit,files.manage_all']);
    $routes->post('files/(:num)/update', 'Files::update/$1', ['filter' => 'perm:files.edit,files.manage_all']);
    $routes->post('files/(:num)/delete', 'Files::delete/$1', ['filter' => 'perm:files.delete,files.manage_all']);
    $routes->post('files/(:num)/restore', 'Files::restore/$1', ['filter' => 'perm:files.manage_all']);
    $routes->post('files/(:num)/purge', 'Files::purge/$1', ['filter' => 'perm:files.manage_all']);
    $routes->post('files/(:num)/rescan', 'Files::rescan/$1', ['filter' => 'perm:files.manage_all']);

    // Kategori
    $routes->get('categories', 'Categories::index', ['filter' => 'perm:categories.manage']);
    $routes->post('categories/save', 'Categories::save', ['filter' => 'perm:categories.manage']);
    $routes->post('categories/(:num)/delete', 'Categories::delete/$1', ['filter' => 'perm:categories.manage']);

    // Pengguna
    $routes->get('users', 'Users::index', ['filter' => 'perm:users.manage']);
    $routes->get('users/create', 'Users::create', ['filter' => 'perm:users.manage']);
    $routes->post('users/store', 'Users::store', ['filter' => 'perm:users.manage']);
    $routes->get('users/(:num)/edit', 'Users::edit/$1', ['filter' => 'perm:users.manage']);
    $routes->post('users/(:num)/update', 'Users::update/$1', ['filter' => 'perm:users.manage']);
    $routes->post('users/(:num)/delete', 'Users::delete/$1', ['filter' => 'perm:users.manage']);

    // Peran & hak akses
    $routes->get('roles', 'Roles::index', ['filter' => 'perm:roles.manage']);
    $routes->get('roles/create', 'Roles::create', ['filter' => 'perm:roles.manage']);
    $routes->post('roles/store', 'Roles::store', ['filter' => 'perm:roles.manage']);
    $routes->get('roles/(:num)/edit', 'Roles::edit/$1', ['filter' => 'perm:roles.manage']);
    $routes->post('roles/(:num)/update', 'Roles::update/$1', ['filter' => 'perm:roles.manage']);
    $routes->post('roles/(:num)/delete', 'Roles::delete/$1', ['filter' => 'perm:roles.manage']);

    // Log aktivitas
    $routes->get('logs', 'Logs::index', ['filter' => 'perm:logs.view']);
    $routes->get('logs/data', 'Logs::data', ['filter' => 'perm:logs.view']);
});
