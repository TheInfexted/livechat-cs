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
$routes->post('/chat/rate-session', 'ChatController::rateSession');
$routes->post('/chat/canned-response', 'ChatController::sendCannedResponse');
$routes->get('/chat/customer-history/(:segment)', 'ChatController::getCustomerHistory/$1');
$routes->get('/chat/quick-actions', 'ChatController::getQuickActions');
$routes->get('/agent/workload', 'ChatController::getAgentWorkload');
$routes->post('/admin/close-inactive', 'ChatController::closeInactiveSessions');

// Admin routes
$routes->group('admin', ['filter' => 'authfilter'], function($routes) {
    $routes->get('/', 'AdminController::dashboard');
    $routes->get('dashboard', 'AdminController::dashboard');
    $routes->get('chat', 'AdminController::chat');
    $routes->get('agents', 'AdminController::agents');
    $routes->post('agents/edit', 'AdminController::editAgent');
    $routes->post('agents/add', 'AdminController::addAgent');
    $routes->post('agents/delete', 'AdminController::deleteAgent');
    $routes->get('canned-responses', 'AdminController::cannedResponses');
    $routes->get('canned-responses/get/(:segment)', 'AdminController::getCannedResponse/$1');
    $routes->get('canned-responses/get-all', 'AdminController::getAllCannedResponses');
    $routes->post('canned-responses/save', 'AdminController::saveCannedResponse');
    $routes->post('canned-responses/delete', 'AdminController::deleteCannedResponse');
    
    // Keyword responses routes
    $routes->get('keyword-responses', 'AdminController::keywordResponses');
    $routes->get('get-keyword-response/(:segment)', 'AdminController::getKeywordResponse/$1');
    $routes->post('save-keyword-response', 'AdminController::saveKeywordResponse');
    $routes->post('delete-keyword-response', 'AdminController::deleteKeywordResponse');
    
    $routes->get('sessions-data', 'AdminController::sessionsData');
    $routes->get('settings', 'AdminController::settings');
    $routes->post('settings/save', 'AdminController::saveSettings');
    $routes->get('customers', 'AdminController::customers');
    $routes->get('customers/(:segment)', 'AdminController::customerDetails/$1');
    $routes->get('export/chats', 'AdminController::exportChats');

});

// Real-time notifications (for WebSocket fallback)
$routes->group('api/notifications', function($routes) {
    $routes->get('poll', 'NotificationController::poll');
    $routes->post('mark-read', 'NotificationController::markRead');
});

// Webhook routes (for third-party integrations)
$routes->group('webhook', function($routes) {
    $routes->post('incoming/(:segment)', 'WebhookController::handleIncoming/$1');
    $routes->post('status-update', 'WebhookController::statusUpdate');
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