<?php

// src/Service/LevelCalculator.php
namespace App\Service;

final class LevelCalculator
{
    private function xpToNextLevel(int $level): int
    {
        return 100 + 25 * ($level - 1) * ($level - 1);
    }

    /**
     * @return array{
     *   level:int,
     *   currentXp:int,
     *   neededForNext:int
     * }
     */
    public function computeLevel(int $totalXp): array
    {
        $level = 1;
        $xpLeft = max(0, $totalXp);

        while ($xpLeft >= $this->xpToNextLevel($level)) {
            $xpLeft -= $this->xpToNextLevel($level);
            $level++;
        }

        return [
            'level'        => $level,
            'currentXp'    => $xpLeft,
            'neededForNext'=> $this->xpToNextLevel($level),
        ];
    }
}
