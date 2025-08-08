<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Class BaseController
 *
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 * Extend this class in any new controllers:
 *     class Home extends BaseController
 *
 * For security be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Instance of the main Request object.
     *
     * @var CLIRequest|IncomingRequest
     */
    protected $request;

    /**
     * An array of helpers to be loaded automatically upon
     * class instantiation. These helpers will be available
     * to all other controllers that extend BaseController.
     *
     * @var list<string>
     */
    protected $helpers = ['url', 'form'];

    // Models (Frontend - Customer chat only)
    protected $chatModel;
    protected $userModel;
    protected $messageModel;
    protected $chatFileModel;
    protected $userRoleModel;
    protected $keywordResponseModel;

    
    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */
    protected $session;
    

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        // Initialize session
        $this->session = \Config\Services::session();
        
        // Initialize models (Frontend only)
        $this->chatModel = new \App\Models\ChatModel();
        $this->userModel = new \App\Models\UserModel();
        $this->messageModel = new \App\Models\MessageModel();
        $this->chatFileModel = new \App\Models\ChatFileModel();
        $this->userRoleModel = new \App\Models\UserRoleModel();
        $this->keywordResponseModel = new \App\Models\KeywordResponseModel();
    }
    
    protected function jsonResponse($data, $statusCode = 200)
    {
        return $this->response->setJSON($data)->setStatusCode($statusCode);
    }
    
    protected function generateSessionId()
    {
        return bin2hex(random_bytes(32));
    }
}
