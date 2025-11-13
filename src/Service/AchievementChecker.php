<?php

// src/Service/AchievementChecker.php
namespace App\Service;

use App\Dto\UserStatsView;
use App\Entity\Achievement;
use App\Entity\GameSession;
use App\Entity\User;
use App\Entity\UserAchievement;
use App\Enum\AchievementType;
use App\Repository\AchievementRepository;
use App\Repository\UserAchievementRepository;
use Doctrine\ORM\EntityManagerInterface;

class AchievementChecker
{
    public function __construct(
        private AchievementRepository $achievementRepository,
        private UserAchievementRepository $userAchievementRepository,
        private EntityManagerInterface $em,
    ) {}

    /**
     * Check achievements liés à une partie jouée
     * Retourne la liste des Achievement débloqués (pour le JSON de réponse).
     */
    public function checkForSession(User $user, GameSession $session, UserStatsView $stats): array
    {
        // UserStatsView = DTO ou service qui te donne les stats globales de l’utilisateur
        $unlocked = [];

        $achievements = $this->achievementRepository->findAll(); // ou filtrés par type si tu veux optimiser

        foreach ($achievements as $achievement) {
            if ($this->userAchievementRepository->isUnlocked($user, $achievement)) {
                continue;
            }

            if ($this->meetsConditionForSession($achievement, $user, $session, $stats)) {
                $ua = new UserAchievement($user, $achievement, [
                    'wpm'       => $session->getWpm(),
                    'accuracy'  => $session->getAccuracy(),
                    'mode'      => $session->getMode(),
                ]);

                $this->em->persist($ua);
                $unlocked[] = $achievement;
            }
        }

        if (!empty($unlocked)) {
            $this->em->flush();
        }

        return $unlocked;
    }

    /**
     * Check achievements liés à un event (ajout ami, message, classement, etc.)
     */
    public function checkForEvent(User $user, string $eventCode, array $context, UserStatsView $stats): array
    {
        $unlocked = [];
        $achievements = $this->achievementRepository->findBy(['type' => AchievementType::EVENT]);

        foreach ($achievements as $achievement) {
            if ($this->userAchievementRepository->isUnlocked($user, $achievement)) {
                continue;
            }

            if ($this->meetsConditionForEvent($achievement, $user, $eventCode, $context, $stats)) {
                $ua = new UserAchievement($user, $achievement, $context);
                $this->em->persist($ua);
                $unlocked[] = $achievement;
            }
        }

        if (!empty($unlocked)) {
            $this->em->flush();
        }

        return $unlocked;
    }

    private function meetsConditionForSession(
        Achievement $a,
        User $user,
        GameSession $s,
        UserStatsView $stats,
    ): bool {
        $type = $a->getType();
        $target = $a->getTargetValue();
        $cfg = $a->getConfig();

        // Ex : filtrer par mode si tu veux (blitz, daily, letters…)
        if (isset($cfg['mode']) && $cfg['mode'] !== $s->getMode()) {
            return false;
        }

        return match ($type) {
            AchievementType::SESSION_CHARS =>
                $s->getCharsTyped() >= $target, // "Premiers pas" (100 chars)

            AchievementType::SESSION_WPM =>
                $s->getWpm() >= $target,        // Doigts de feu, Éclair, Tornado, etc.

            AchievementType::SESSION_ACCURACY =>
                $s->getAccuracy() >= $target,   // Sniper du clavier (100%), Régulier (si tu passes par streak stats)

            AchievementType::SESSION_DURATION =>
                $s->getDurationMs() <= $target, // Chronométré : target = 60000 ms

            AchievementType::TOTAL_TEXTS =>
                $stats->getTextsCompleted() >= $target, // Premier mot, Apprenti, Polygraphe, etc.

            AchievementType::TOTAL_PLAYTIME =>
                $stats->getTotalPlaytimeMs() >= $target, // Marathon, No life du clavier

            AchievementType::PERFECT_STREAK =>
                $stats->getPerfectStreak() >= $target,   // Machine sans faille

            AchievementType::HIGH_ACCURACY_STREAK =>
                $stats->getHighAccuracyStreak() >= $target, // Régulier 10 textes > 95%

            AchievementType::WIN_STREAK =>
                $stats->getWinStreak() >= $target,        // Toujours plus vite !, Inarrêtable

            AchievementType::DUELS_WON =>
                $stats->getDuelsWon() >= $target,         // Première victoire, Champion local

            AchievementType::RANK_ELO =>
                $stats->getElo() >= $target,              // Grand maître

            AchievementType::SESSION_FLAGS =>
            $this->checkSessionFlags($a, $s, $stats), // Zen, Robot ?, Maître des mots, etc.

            default => false,
        };
    }

    private function meetsConditionForEvent(
        Achievement $a,
        User $user,
        string $eventCode,
        array $context,
        UserStatsView $stats,
    ): bool {
        $cfg = $a->getConfig();

        return match ($a->getType()) {
            AchievementType::SOCIAL_FRIENDS =>
                $eventCode === 'friend_added'
                && $stats->getFriendsCount() >= $a->getTargetValue(), // Premier ami

            AchievementType::SOCIAL_MESSAGES =>
                $eventCode === 'chat_message'
                && $stats->getMessagesSent() >= $a->getTargetValue(), // Papoteur

            AchievementType::EVENT =>
                ($cfg['eventCode'] ?? null) === $eventCode,           // Daktylo secret, Inspiré, etc.

            default => false,
        };
    }

    private function checkSessionFlags(Achievement $a, GameSession $s, UserStatsView $stats): bool
    {
        $code = $a->getCode();

        return match ($code) {
            // 🐢 Zen — WPM < 20 + 0 faute
            'zen' =>
                $s->getWpm() < 20 && $s->getErrors() === 0,

            // 🦾 Robot ? — 200 WPM et 100% précision
            'robot' =>
                $s->getWpm() >= 200 && $s->getAccuracy() === 100,

            // 🧙‍♂️ Maître des mots — on se base sur le nombre de caractères tapés
            'master_of_words' =>
                $s->getCharsTyped() >= 1000,


            'natural_rhythm' => false,
            'letter_wall' => false,

            default => false,
        };
    }
}
