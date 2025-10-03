<?php
declare(strict_types=1);

use App\Kernel;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__).'/vendor/autoload.php';

// Lire l'env le plus tôt possible (évite toute lecture de .env)
$env   = $_SERVER['APP_ENV']   ?? $_ENV['APP_ENV']   ?? getenv('APP_ENV')   ?: 'prod';
$debug = $_SERVER['APP_DEBUG'] ?? $_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG');
$debug = filter_var($debug, FILTER_VALIDATE_BOOL) ?: false;

// Rendre visibles pour le Kernel
$_SERVER['APP_ENV']   = $_ENV['APP_ENV']   = $env;
$_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] = $debug ? '1' : '0';

// Debug en local seulement
if ($debug) {
    umask(0000);
    Debug::enable();
}

// Démarrage "classique" (pas de autoload_runtime / pas de Dotenv)
$kernel  = new Kernel($env, $debug);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
