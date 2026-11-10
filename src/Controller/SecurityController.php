<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    #[Route('/connexion', name: 'login')]
    public function login(AuthenticationUtils $auth): Response
    {
        return $this->render('security/login.html.twig', [
            'error' => $auth->getLastAuthenticationError(),
            'last_username' => $auth->getLastUsername(),
        ]);
    }

    #[Route('/deconnexion', name: 'logout')]
    public function logout(): never
    {
        throw new \LogicException('Intercepted by the firewall.');
    }
}
