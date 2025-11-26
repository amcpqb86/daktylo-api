<?php
// src/Controller/GameStatsController.php
namespace App\Controller;

use App\Entity\GameSession;
use App\Entity\User;
use App\Entity\WikiArticle;
use App\Repository\GameSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/stats')]
class GameStatsController extends AbstractController
{

    public function __construct(
        private GameSessionRepository $gameSessionRepository,
        private Security $security,
    ) {}

    #[Route('/global', name: 'api_stats_global', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function global(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $repo = $em->getRepository(GameSession::class);

        $qb = $repo->createQueryBuilder('g')
            ->select(
                'COUNT(g.id) AS totalSessions',
                'COALESCE(SUM(g.charsTyped), 0) AS totalChars',
                'COALESCE(SUM(g.wordsTyped), 0) AS totalWords',
                'COALESCE(AVG(g.wpm), 0) AS avgWpm',
                'COALESCE(AVG(g.accuracy), 0) AS avgAccuracy',
                'COALESCE(SUM(g.durationMs), 0) AS totalTimeMs',
                'COALESCE(MAX(g.wpm), 0) AS bestWpm'
            )
            ->where('g.user = :user')
            ->setParameter('user', $user);

        $stats = $qb->getQuery()->getSingleResult();

        // Conversion ms → h:m:s pour le front
        $totalSeconds = floor($stats['totalTimeMs'] / 1000);
        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        $seconds = $totalSeconds % 60;
        $formattedTime = sprintf('%02dh %02dm %02ds', $hours, $minutes, $seconds);

        return $this->json([
            'total_sessions' => (int) $stats['totalSessions'],
            'total_chars' => (int) $stats['totalChars'],
            'total_words' => (int) $stats['totalWords'],
            'avg_wpm' => round($stats['avgWpm'], 1),
            'avg_accuracy' => round($stats['avgAccuracy'], 1),
            'total_time' => $formattedTime,
            'best_wpm' => (int) $stats['bestWpm'],
            'account_created' => $user->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'errors_by_char' => $user->getTypingErrorChars(),
        ]);
    }

    #[Route('/product', name: 'api_stats_product', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function productInfos(EntityManagerInterface $em): JsonResponse
    {
        $wikiRepo = $em->getRepository(WikiArticle::class);

        $wikiArticleCount = $wikiRepo->count([]);
        $lastArticle = $wikiRepo->findOneBy([], ['createdAt' => 'DESC']);

        return $this->json([
            'wiki_article_count' => $wikiArticleCount,
            'last_article' => $lastArticle ? [
                'id' => $lastArticle->getId(),
                'title' => $lastArticle->getTitle(),
                'createdAt' => $lastArticle->getCreatedAt()->format('Y-m-d H:i:s'),
                'wikiId' => $lastArticle->getWikiId(),
            ] : null,
        ]);
    }

    #[Route('/me/overview', name: 'api_me_stats_overview', methods: ['GET'])]
    public function overview(): JsonResponse
    {
        $user = $this->security->getUser();

        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $sessions = $this->gameSessionRepository->findBy(['user' => $user]);

        if (!$sessions) {
            return $this->json([
                'total_duration_ms' => 0,
                'by_mode' => [],
                'wpm_distribution' => [],
                'radar' => [
                    'wpm' => 0,
                    'accuracy' => 0,
                    'max_streak' => 0,
                    'chars_typed' => 0,
                    'total_duration_ms' => 0,
                ],
                'xp' => [
                    'current' => 0,
                    'next_level' => 0,
                    'level' => 1,
                ],
            ]);
        }

        $totalDuration = 0;
        $totalChars    = 0;
        $totalWpm      = 0;
        $totalAccuracy = 0;
        $count         = 0;

        $byMode = [];
        $buckets = [
            '0-20'   => [0, 20],
            '20-40'  => [20, 40],
            '40-60'  => [40, 60],
            '60-80'  => [60, 80],
            '80-100' => [80, 100],
            '100+'   => [100, PHP_INT_MAX],
        ];
        $wpmDistribution = array_fill_keys(array_keys($buckets), 0);

        /** @var \App\Entity\GameSession $session */
        foreach ($sessions as $session) {
            $duration = $session->getDurationMs() ?? 0;
            $wpm      = $session->getWpm() ?? 0;
            $accuracy = $session->getAccuracy() ?? 0; // déjà entre 0 et 1 chez toi
            $chars    = $session->getCharsTyped() ?? 0;
            $mode     = $session->getMode() ?? 'unknown';

            $totalDuration += $duration;
            $totalChars    += $chars;
            $totalWpm      += $wpm;
            $totalAccuracy += $accuracy;
            $count++;

            if (!isset($byMode[$mode])) {
                $byMode[$mode] = [
                    'mode' => $mode,
                    'duration_ms' => 0,
                ];
            }
            $byMode[$mode]['duration_ms'] += $duration;

            foreach ($buckets as $label => [$min, $max]) {
                if ($wpm >= $min && $wpm < $max) {
                    $wpmDistribution[$label]++;
                    break;
                }
            }
        }

        $avgWpm      = $count > 0 ? $totalWpm / $count : 0;
        $avgAccuracy = $count > 0 ? $totalAccuracy / $count : 0;

        // système d’XP simplifié basé sur les caractères — adapte à ton vrai calcul
        $xpCurrent  = $totalChars;
        $xpPerLevel = 5000; // ex: 5000 chars par niveau
        $level      = max(1, (int) floor($xpCurrent / $xpPerLevel) + 1);
        $nextLevelXp = $level * $xpPerLevel;

        return $this->json([
            'total_duration_ms' => $totalDuration,
            'by_mode' => array_values($byMode),
            'wpm_distribution' => array_map(
                fn (string $label) => [
                    'bucket' => $label,
                    'count'  => $wpmDistribution[$label],
                ],
                array_keys($wpmDistribution)
            ),
            'radar' => [
                'wpm' => round($avgWpm, 2),
                'accuracy' => round($avgAccuracy, 4),
                'max_streak' => 0, // pas encore en base → 0
                'chars_typed' => $totalChars,
                'total_duration_ms' => $totalDuration,
            ],
            'xp' => [
                'current' => $xpCurrent,
                'next_level' => $nextLevelXp,
                'level' => $level,
            ],
        ]);
    }

    #[Route('/me/heatmap', name: 'api_me_stats_heatmap', methods: ['GET'])]
    public function heatmap(): JsonResponse
    {
        $user = $this->security->getUser();

        if (!$user instanceof \App\Entity\User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        /** @var GameSession[] $sessions */
        $sessions = $this->gameSessionRepository->findBy(['user' => $user]);

        // [day][hour] => ['sessions' => int, 'sumWpm' => float, 'sumAcc' => float]
        $grid = [];

        foreach ($sessions as $session) {
            $playedAt = $session->getPlayedAt();
            if (!$playedAt) {
                continue;
            }

            $playedAtLocal = $playedAt->setTimezone(new \DateTimeZone('Europe/Paris'));

            // 1 = lundi ... 7 = dimanche  → on veut 0–6
            $dayIndex = (int) $playedAtLocal->format('N') - 1; // 0–6
            $hour     = (int) $playedAtLocal->format('G');     // 0–23

            if (!isset($grid[$dayIndex])) {
                $grid[$dayIndex] = [];
            }
            if (!isset($grid[$dayIndex][$hour])) {
                $grid[$dayIndex][$hour] = [
                    'sessions' => 0,
                    'sumWpm'   => 0.0,
                    'sumAcc'   => 0.0,
                ];
            }

            $grid[$dayIndex][$hour]['sessions']++;
            $grid[$dayIndex][$hour]['sumWpm'] += (float) ($session->getWpm() ?? 0);
            $grid[$dayIndex][$hour]['sumAcc'] += (float) ($session->getAccuracy() ?? 0);
        }

        $points = [];
        foreach ($grid as $day => $hours) {
            foreach ($hours as $hour => $agg) {
                $sessionsCount = max(1, $agg['sessions']);

                $points[] = [
                    'day'          => (int) $day,                 // 0–6 (lun–dim)
                    'hour'         => (int) $hour,                // 0–23
                    'sessions'     => (int) $agg['sessions'],
                    'avg_wpm'      => round($agg['sumWpm'] / $sessionsCount, 1),
                    'avg_accuracy' => round($agg['sumAcc'] / $sessionsCount, 1),
                ];
            }
        }

        return $this->json([
            'points' => $points,
        ]);
    }

    #[Route('/me/heatmap-year', name: 'api_me_stats_heatmap_year', methods: ['GET'])]
    public function heatmapYear(Request $request): JsonResponse
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        // année demandée ?year=2025, sinon année courante
        $year = (int) ($request->query->get('year') ?? (new \DateTimeImmutable())->format('Y'));

        $start = new \DateTimeImmutable(sprintf('%d-01-01 00:00:00', $year));
        $end   = $start->modify('+1 year');

        /** @var GameSession[] $sessions */
        $sessions = $this->gameSessionRepository->createQueryBuilder('g')
            ->where('g.user = :user')
            ->andWhere('g.playedAt >= :start')
            ->andWhere('g.playedAt < :end')
            ->setParameter('user', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();

        // agrégat par jour: Y-m-d => { sessions, chars, words, wpm, accuracy }
        $days = [];

        foreach ($sessions as $session) {
            $playedAt = $session->getPlayedAt();
            if (!$playedAt) {
                continue;
            }

            $dateKey = $playedAt->format('Y-m-d');

            if (!isset($days[$dateKey])) {
                $days[$dateKey] = [
                    'sessions'     => 0,
                    'chars_typed'  => 0,
                    'words_typed'  => 0,
                    'sum_wpm'      => 0.0,
                    'sum_accuracy' => 0.0,
                ];
            }

            $days[$dateKey]['sessions']++;
            $days[$dateKey]['chars_typed']  += (int) ($session->getCharsTyped() ?? 0);
            $days[$dateKey]['words_typed']  += (int) ($session->getWordsTyped() ?? 0);
            $days[$dateKey]['sum_wpm']      += (float) ($session->getWpm() ?? 0);
            $days[$dateKey]['sum_accuracy'] += (float) ($session->getAccuracy() ?? 0);
        }

        $points = [];

        foreach ($days as $date => $agg) {
            $sessionsCount = max(1, $agg['sessions']);

            $points[] = [
                'date'         => $date, // ex: "2025-11-20"
                'sessions'     => $agg['sessions'],
                'chars_typed'  => $agg['chars_typed'],
                'words_typed'  => $agg['words_typed'],
                'avg_wpm'      => round($agg['sum_wpm'] / $sessionsCount, 1),
                'avg_accuracy' => round($agg['sum_accuracy'] / $sessionsCount, 3),
            ];
        }

        return $this->json([
            'year'   => $year,
            'points' => $points,
        ]);
    }
}
