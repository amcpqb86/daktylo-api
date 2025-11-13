<?php

namespace App\Dto;

final class UserStatsView
{
    public function __construct(
        private int $textsCompleted,
        private int $totalPlaytimeMs,
        private int $perfectStreak,
        private int $highAccuracyStreak,
        private int $winStreak,
        private int $duelsWon,
        private int $elo,
        private int $friendsCount,
        private int $messagesSent,
    ) {
    }

    public function getTextsCompleted(): int
    {
        return $this->textsCompleted;
    }

    public function getTotalPlaytimeMs(): int
    {
        return $this->totalPlaytimeMs;
    }

    public function getPerfectStreak(): int
    {
        return $this->perfectStreak;
    }

    public function getHighAccuracyStreak(): int
    {
        return $this->highAccuracyStreak;
    }

    public function getWinStreak(): int
    {
        return $this->winStreak;
    }

    public function getDuelsWon(): int
    {
        return $this->duelsWon;
    }

    public function getElo(): int
    {
        return $this->elo;
    }

    public function getFriendsCount(): int
    {
        return $this->friendsCount;
    }

    public function getMessagesSent(): int
    {
        return $this->messagesSent;
    }
}
