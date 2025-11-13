<?php

namespace App\Controller;

use App\Entity\Achievement;
use App\Entity\User;
use App\Repository\AchievementRepository;
use App\Repository\UserAchievementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AchievementController extends AbstractController
{
    #[Route('/api/achievements', name: 'api_achievements', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function list(
        AchievementRepository $achievementRepository,
        UserAchievementRepository $uaRepo
    ): JsonResponse {

        /** @var User $user */
        $user = $this->getUser();

        $achievements = $achievementRepository->createQueryBuilder('a')
            ->andWhere('a.hidden = false')
            ->orderBy('a.category', 'ASC')
            ->addOrderBy('a.id', 'ASC')
            ->getQuery()
            ->getResult();

        // Liste des succès déjà débloqués par user
        $unlocked = $uaRepo->createQueryBuilder('ua')
            ->select('IDENTITY(ua.achievement) AS id, ua.unlockedAt AS unlockedAt')
            ->andWhere('ua.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getArrayResult();

        $unlockedMap = [];
        foreach ($unlocked as $u) {
            $unlockedMap[$u['id']] = $u['unlockedAt'];
        }

        $result = [];

        foreach ($achievements as $a) {
            /** @var Achievement $a */
            $id = $a->getId();

            $result[] = [
                'id'          => $id,
                'code'        => $a->getCode(),
                'name'        => $a->getName(),
                'description' => $a->getDescription(),
                'category'    => $a->getCategory(),
                'unlocked'    => isset($unlockedMap[$id]),
                'unlockedAt'  => $unlockedMap[$id] ?? null,
            ];
        }

        return $this->json($result);
    }
}
