<?php

namespace App\Service;

use App\Dto\UserStatsView;
use App\Entity\User;
use App\Repository\GameSessionRepository;

class UserStatsService
{
    public function __construct(
        private GameSessionRepository $gameSessionRepository,
    ) {
    }

    public function buildStatsFor(User $user): UserStatsView
    {
        // Agrégats simples sur toutes les parties du joueur
        $result = $this->gameSessionRepository->createQueryBuilder('s')
            ->select('COUNT(s.id) AS textsCompleted')
            ->addSelect('COALESCE(SUM(s.durationMs), 0) AS totalPlaytimeMs')
            ->addSelect('COALESCE(SUM(CASE WHEN s.errors = 0 THEN 1 ELSE 0 END), 0) AS perfectSessions')
            ->addSelect('COALESCE(SUM(CASE WHEN s.success = true THEN 1 ELSE 0 END), 0) AS duelsWon')
            ->andWhere('s.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleResult();

        $textsCompleted   = (int) ($result['textsCompleted'] ?? 0);
        $totalPlaytimeMs  = (int) ($result['totalPlaytimeMs'] ?? 0);
        $duelsWon         = (int) ($result['duelsWon'] ?? 0);

        // Streaks pas encore calculés → 0 pour l’instant
        $perfectStreak       = 0;
        $highAccuracyStreak  = 0;
        $winStreak           = 0;

        // Si tu as un champ elo sur User
        $elo = method_exists($user, 'getElo') ? (int) $user->getElo() : 0;

        // Social pas encore en place → 0
        $friendsCount  = 0;
        $messagesSent  = 0;

        return new UserStatsView(
            $textsCompleted,
            $totalPlaytimeMs,
            $perfectStreak,
            $highAccuracyStreak,
            $winStreak,
            $duelsWon,
            $elo,
            $friendsCount,
            $messagesSent,
        );
    }
}
