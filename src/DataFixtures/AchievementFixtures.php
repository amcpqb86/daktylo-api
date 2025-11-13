<?php

namespace App\DataFixtures;

use App\Entity\Achievement;
use App\Enum\AchievementType;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Bundle\FixturesBundle\Fixture;

class AchievementFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $defs = [
            [
                'code' => 'first_word',
                'name' => 'Premier mot',
                'description' => 'Termine ton tout premier texte.',
                'category' => 'Débutant',
                'type' => AchievementType::TOTAL_TEXTS,
                'target' => 1,
            ],
            [
                'code' => 'first_100_chars',
                'name' => 'Premiers pas',
                'description' => 'Tape 100 caractères.',
                'category' => 'Débutant',
                'type' => AchievementType::SESSION_CHARS,
                'target' => 100,
            ],
            [
                'code' => 'apprenti',
                'name' => 'Apprenti',
                'description' => 'Termine 5 textes sans erreur.',
                'category' => 'Débutant',
                'type' => AchievementType::PERFECT_STREAK,
                'target' => 5,
            ],
            [
                'code' => 'concentration',
                'name' => 'Concentration',
                'description' => 'Fini un texte sans aucune faute.',
                'category' => 'Débutant',
                'type' => AchievementType::SESSION_ACCURACY,
                'target' => 100,
            ],
            [
                'code' => 'chrono_1_min',
                'name' => 'Chronométré',
                'description' => 'Termine un texte en moins de 1 minute.',
                'category' => 'Débutant',
                'type' => AchievementType::SESSION_DURATION,
                'target' => 60000,
            ],

            // 🔵 Vitesse
            [
                'code' => 'wpm_60',
                'name' => 'Doigts de feu',
                'description' => 'Atteins 60 WPM.',
                'category' => 'Vitesse',
                'type' => AchievementType::SESSION_WPM,
                'target' => 60,
            ],
            [
                'code' => 'wpm_100',
                'name' => 'Éclair',
                'description' => 'Atteins 100 WPM.',
                'category' => 'Vitesse',
                'type' => AchievementType::SESSION_WPM,
                'target' => 100,
            ],
            [
                'code' => 'wpm_150',
                'name' => 'Tornado',
                'description' => 'Atteins 150 WPM.',
                'category' => 'Vitesse',
                'type' => AchievementType::SESSION_WPM,
                'target' => 150,
            ],
            [
                'code' => 'wpm_200',
                'name' => 'Clavier en fusion',
                'description' => 'Atteins 200 WPM.',
                'category' => 'Vitesse',
                'type' => AchievementType::SESSION_WPM,
                'target' => 200,
            ],
            [
                'code' => 'win_streak_5',
                'name' => 'Toujours plus vite !',
                'description' => 'Gagne 5 courses d’affilée.',
                'category' => 'Vitesse',
                'type' => AchievementType::WIN_STREAK,
                'target' => 5,
            ],

            // 🟣 Précision
            [
                'code' => 'sniper_100',
                'name' => 'Sniper du clavier',
                'description' => 'Obtiens 100 % de précision.',
                'category' => 'Précision',
                'type' => AchievementType::SESSION_ACCURACY,
                'target' => 100,
            ],
            [
                'code' => 'precision_95_streak_10',
                'name' => 'Régulier',
                'description' => 'Reste au-dessus de 95 % de précision pendant 10 textes.',
                'category' => 'Précision',
                'type' => AchievementType::HIGH_ACCURACY_STREAK,
                'target' => 10,
            ],
            [
                'code' => 'perfect_5_streak',
                'name' => 'Machine sans faille',
                'description' => '100 % de précision sur 5 parties consécutives.',
                'category' => 'Précision',
                'type' => AchievementType::PERFECT_STREAK,
                'target' => 5,
            ],

            // 🟠 Endurance
            [
                'code' => 'marathon',
                'name' => 'Marathon',
                'description' => 'Joue pendant 1 heure cumulée.',
                'category' => 'Endurance',
                'type' => AchievementType::TOTAL_PLAYTIME,
                'target' => 3600000,
            ],
            [
                'code' => 'nolife_10h',
                'name' => 'No life du clavier',
                'description' => 'Joue pendant 10 heures cumulées.',
                'category' => 'Endurance',
                'type' => AchievementType::TOTAL_PLAYTIME,
                'target' => 36000000,
            ],
            [
                'code' => 'polygraphe_100',
                'name' => 'Polygraphe',
                'description' => 'Termine 100 textes.',
                'category' => 'Endurance',
                'type' => AchievementType::TOTAL_TEXTS,
                'target' => 100,
            ],
            [
                'code' => 'win_streak_20',
                'name' => 'Inarrêtable',
                'description' => '20 victoires d’affilée.',
                'category' => 'Endurance',
                'type' => AchievementType::WIN_STREAK,
                'target' => 20,
            ],

            // 🟡 Social
            [
                'code' => 'first_friend',
                'name' => 'Premier ami',
                'description' => 'Ajoute un ami.',
                'category' => 'Social',
                'type' => AchievementType::SOCIAL_FRIENDS,
                'target' => 1,
            ],
            [
                'code' => 'chat_10',
                'name' => 'Papoteur',
                'description' => 'Envoie 10 messages dans le chat.',
                'category' => 'Social',
                'type' => AchievementType::SOCIAL_MESSAGES,
                'target' => 10,
            ],
            [
                'code' => 'first_challenge',
                'name' => 'Défieur',
                'description' => 'Lance un défi à un ami.',
                'category' => 'Social',
                'type' => AchievementType::EVENT,
                'target' => null,
                'config' => ['eventCode' => 'challenge_sent'],
            ],
            [
                'code' => 'guest_win',
                'name' => 'Invité d’honneur',
                'description' => 'Gagne une partie hébergée par un autre joueur.',
                'category' => 'Social',
                'type' => AchievementType::EVENT,
                'target' => null,
                'config' => ['eventCode' => 'guest_win'],
            ],

            // 🧩 Cachés / spéciaux
            [
                'code' => 'zen',
                'name' => 'Zen',
                'description' => 'Termine un texte lentement (moins de 20 WPM) sans erreur.',
                'category' => 'Divers',
                'type' => AchievementType::SESSION_FLAGS,
            ],
            [
                'code' => 'robot',
                'name' => 'Robot ?',
                'description' => 'Atteins 200 WPM avec 100 % de précision.',
                'category' => 'Divers',
                'type' => AchievementType::SESSION_FLAGS,
            ],
            [
                'code' => 'master_of_words',
                'name' => 'Maître des mots',
                'description' => 'Termine un texte de plus de 1000 caractères.',
                'category' => 'Divers',
                'type' => AchievementType::SESSION_FLAGS,
            ],
            [
                'code' => 'secret_daktylo',
                'name' => 'Daktylo secret',
                'description' => 'Découvre un texte caché.',
                'category' => 'Divers',
                'type' => AchievementType::EVENT,
                'config' => ['eventCode' => 'secret_text_found'],
            ],
            [
                'code' => 'natural_rhythm',
                'name' => 'Rythme naturel',
                'description' => 'Tape un texte entier sans jamais t’arrêter plus de 1 seconde.',
                'category' => 'Divers',
                'type' => AchievementType::SESSION_FLAGS,
            ],
            [
                'code' => 'inspire',
                'name' => 'Inspiré',
                'description' => 'Crée ton propre texte personnalisé.',
                'category' => 'Divers',
                'type' => AchievementType::EVENT,
                'config' => ['eventCode' => 'custom_text_created'],
            ],
            [
                'code' => 'letter_wall',
                'name' => 'Mur de lettres',
                'description' => 'Rate 10 fois la même lettre.',
                'category' => 'Divers',
                'type' => AchievementType::SESSION_FLAGS,
            ],
            [
                'code' => 'lucky_finish',
                'name' => 'Chanceux',
                'description' => 'Gagne une partie avec 1 % de précision d’avance.',
                'category' => 'Divers',
                'type' => AchievementType::EVENT,
                'config' => ['eventCode' => 'lucky_finish'],
            ],
        ];

        foreach ($defs as $d) {
            $a = new Achievement();
            $a->setCode($d['code']);
            $a->setName($d['name']);
            $a->setDescription($d['description']);
            $a->setCategory($d['category']);
            $a->setType($d['type']);
            $a->setTargetValue($d['target'] ?? null);
            $a->setConfig($d['config'] ?? []);
            $manager->persist($a);
        }

        $manager->flush();
    }
}
