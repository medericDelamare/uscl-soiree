<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:user:create', description: 'Crée ou met à jour un compte (contrôle à l\'entrée, ou administrateur avec --admin)')]
class UserCreateCommand
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Adresse e-mail')] string $email,
        #[Argument('Nom affiché')] string $name = '',
        #[Argument('Mot de passe (demandé si absent)')] ?string $password = null,
        #[Option('Donne accès au back-office')] bool $admin = false,
    ): int {
        $password ??= $io->askHidden('Mot de passe');
        if (strlen((string) $password) < 8) {
            $io->error('Le mot de passe doit faire au moins 8 caractères.');

            return 1;
        }
        $user = $this->users->findOneBy(['email' => mb_strtolower($email)]) ?? new User();
        $user->setEmail($email)->setName($name ?: $email)->setAdmin($admin);
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();
        $io->success("Compte $email prêt".($admin ? ' (administrateur).' : ' (contrôle à l\'entrée).'));

        return 0;
    }
}
