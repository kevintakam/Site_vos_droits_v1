<?php
// scripts/create_admin.php
declare(strict_types=1);

use App\Entity\User;
use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasher;

require __DIR__ . '/../vendor/autoload.php';

// 1) Charger l'env si présent (utile hors CLI Symfony)
$envFile = __DIR__ . '/../.env';
if (is_file($envFile)) {
    (new Dotenv())->bootEnv($envFile);
}

// 2) Boot Kernel
$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'dev', (bool)($_SERVER['APP_DEBUG'] ?? true));
$kernel->boot();
$container = $kernel->getContainer();

try {
    // 3) Doctrine EntityManager via l'ID "doctrine"
    $doctrine = $container->get('doctrine');
    $em = $doctrine->getManager();

    // 4) Hasher SANS le conteneur (évite le service privé/inliné)
    //    On configure la factory comme dans security.yaml: algorithm "auto" pour App\Entity\User
    $factory = new PasswordHasherFactory([
        User::class => ['algorithm' => 'auto'],
        // Vous pouvez ajouter d'autres classes si besoin
    ]);
    $userPasswordHasher = new UserPasswordHasher($factory);

    // ---------- Paramètres (modifiable ou via argv) ----------
    $email     = $argv[1] ?? 'danbouss22@gmail.com';
    $plain     = $argv[2] ?? 'ChangeMe123!';
    $firstname = $argv[3] ?? 'Admin';
    $lastname  = $argv[4] ?? 'Root';
    // --------------------------------------------------------

    // 5) Vérifier existence
    $repo = $em->getRepository(User::class);
    $user = $repo->findOneBy(['email' => $email]);

    if ($user) {
        echo "[INFO] L’utilisateur existe déjà : {$email}\n";
        exit(0);
    }

    // 6) Créer utilisateur (adaptez les setters à votre entité)
    $user = new User();
    $user->setEmail($email);
    if (method_exists($user, 'setFirstName')) { $user->setFirstName($firstname); }
    if (method_exists($user, 'setLastName'))  { $user->setLastName($lastname); }
    $user->setRoles(['ROLE_ADMIN']);

    // 7) Hachage du mot de passe via notre hasher autonome
    $hashed = $userPasswordHasher->hashPassword($user, $plain);
    $user->setPassword($hashed);

    // 8) (Option) Activez/validez le compte si votre entité le prévoit
    // if (method_exists($user, 'setIsVerified')) { $user->setIsVerified(true); }

    // 9) Persist/flush
    $em->persist($user);
    $em->flush();

    echo "[OK] Administrateur créé : {$email}\n";

} catch (\Throwable $e) {
    fwrite(STDERR, "[ERROR] {$e->getMessage()}\n");
    exit(1);
} finally {
    if (method_exists($kernel, 'shutdown')) {
        $kernel->shutdown();
    }
}
