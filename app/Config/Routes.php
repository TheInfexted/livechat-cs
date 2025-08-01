<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// Home route - redirect to chat
$routes->get('/', 'ChatController::index');

// Chat routes (Customer side)
$routes->get('/chat', 'ChatController::index');
$routes->post('/chat/start-session', 'ChatController::startSession');
$routes->post('/chat/assign-agent', 'ChatController::assignAgent');
$routes->get('/chat/messages/(:segment)', 'ChatController::getMessages/$1');
$routes->post('/chat/close-session', 'ChatController::closeSession');
$routes->get('/chat/check-session-status/(:segment)', 'ChatController::checkSessionStatus/$1');

// Admin routes
$routes->group('admin', ['filter' => 'authfilter'], function($routes) {
    $routes->get('/', 'AdminController::dashboard');
    $routes->get('dashboard', 'AdminController::dashboard');
    $routes->get('chat', 'AdminController::chat');
    $routes->get('agents', 'AdminController::agents');
    $routes->get('reports', 'AdminController::reports');
});

// Authentication routes
$routes->get('/login', 'Auth::login');
$routes->post('/login', 'Auth::attemptLogin');
$routes->get('/logout', 'Auth::logout');

// API routes for WebSocket fallback (optional)
$routes->group('api', function($routes) {
    $routes->post('chat/send-message', 'ChatController::sendMessage');
    $routes->get('chat/check-status/(:segment)', 'ChatController::checkStatus/$1');
});