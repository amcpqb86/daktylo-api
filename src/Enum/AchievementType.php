<?php

// src/Enum/AchievementType.php
namespace App\Enum;

enum AchievementType: string
{
    case SESSION_CHARS = 'session_chars';
    case SESSION_WPM = 'session_wpm';
    case SESSION_ACCURACY = 'session_accuracy';
    case SESSION_DURATION = 'session_duration';
    case SESSION_FLAGS = 'session_flags';              // ex : "secret text", "hosted", etc.

    case TOTAL_TEXTS = 'total_texts';
    case TOTAL_PLAYTIME = 'total_playtime';
    case PERFECT_STREAK = 'perfect_streak';
    case HIGH_ACCURACY_STREAK = 'high_accuracy_streak';
    case WIN_STREAK = 'win_streak';
    case DUELS_WON = 'duels_won';
    case RANK_ELO = 'rank_elo';

    case SOCIAL_FRIENDS = 'social_friends';
    case SOCIAL_MESSAGES = 'social_messages';

    case EVENT = 'event'; // pour les succès très spécifiques / cachés
}
