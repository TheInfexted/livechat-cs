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
$routes->post('/chat/end-customer-session', 'ChatController::endCustomerSession');
$routes->post('/chat/rate-session', 'ChatController::rateSession');
$routes->post('/chat/canned-response', 'ChatController::sendCannedResponse');
$routes->get('/chat/customer-history/(:segment)', 'ChatController::getCustomerHistory/$1');
$routes->get('/chat/quick-actions', 'ChatController::getQuickActions');
$routes->get('/agent/workload', 'ChatController::getAgentWorkload');

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
    
    // Frontend integration route for getting chatroom links
    $routes->match(['get', 'post'], 'getChatroomLink', 'ChatController::getChatroomLink');
    
    // CORS preflight OPTIONS routes for widget API
    $routes->options('widget/validate', function() {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        http_response_code(200);
        exit();
    });
    $routes->options('widget/validate-session', function() {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        http_response_code(200);
        exit();
    });
    $routes->options('widget/log-message', function() {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        http_response_code(200);
        exit();
    });
    
    // Widget API validation routes (no auth filter - public API)
    $routes->post('widget/validate', 'WidgetAuthController::validateWidget');
    $routes->post('widget/validate-session', 'WidgetAuthController::validateChatStart');
    $routes->post('widget/log-message', 'WidgetAuthController::logMessageSent');
});