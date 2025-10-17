<?php
// --- Shim: propage les variables d'environnement vers $_SERVER/$_ENV et putenv ---
// -> Empêche SymfonyRuntime de tenter Dotenv::bootEnv() (et donc .env) en prod.
$keys = ['APP_ENV' => 'prod', 'APP_DEBUG' => '0'];
foreach ($keys as $key => $default) {
    $val = getenv($key);
    if ($val === false || $val === null || $val === '') {
        $val = $default;
    }
    // 3 cibles pour être inratable
    $_SERVER[$key] = $val;
    $_ENV[$key]    = $val;
    putenv($key.'='.$val);
}

// (Optionnel mais très utile) si vous voulez forcer le runtime générique
// qui ne fait AUCUN chargement .env même si variables absentes.
// Décommentez la ligne suivante si besoin supplémentaire.
putenv('APP_RUNTIME=Symfony\Component\Runtime\GenericRuntim');

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    // Ici, APP_ENV et APP_DEBUG sont déjà présents dans $_SERVER
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
