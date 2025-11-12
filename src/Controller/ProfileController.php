<?php

namespace App\Controller;

use App\Entity\GameSession;
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

        // Vérifie si le nom d'utilisateur existe déjà
        $existing = $em->getRepository(User::class)->findOneBy(['username' => $newUsername]);
        if ($existing && $existing->getId() !== $this->getUser()->getId()) {
            return new JsonResponse(['error' => 'Ce nom d’utilisateur est déjà pris'], 409);
        }

        /** @var User $user */
        $user = $this->getUser();
        $user->setUsername($newUsername);

        $em->flush();

        return new JsonResponse(['success' => true, 'username' => $user->getUsername()]);
    }

    #[Route('/me', name: 'app_me', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $bestDailyTime = $em->getRepository(GameSession::class)->getBestScoreOfTodayDaily();

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'username' => $user->getUsername(),
            'lastLoginAt' => $user->getLastLoginAt()?->format(\DateTimeInterface::ATOM),
            'bestDailyTime' => $bestDailyTime !== null ? (int) $bestDailyTime : null,
        ]);
    }
}
