<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ProfileController extends AbstractController
{
    #[Route('/profile/username', name: 'app_profile_username', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function updateUsername(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $newUsername = $data['username'] ?? null;

        if (!$newUsername) {
            return new JsonResponse(['error' => 'Username manquant'], 400);
        }

        /** @var User $user */
        $user = $this->getUser();
        $user->setUsername($newUsername);

        $em->flush();

        return new JsonResponse(['success' => true, 'username' => $user->getUsername()]);
    }
}
