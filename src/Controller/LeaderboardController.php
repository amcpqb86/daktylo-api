<?php
// src/Controller/LeaderboardController.php

namespace App\Controller;

use App\Repository\GameSessionRepository;
use App\Repository\UserRepository;
use App\Service\LevelCalculator;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/leaderboard', name: 'api_leaderboard', methods: ['GET'])]
class LeaderboardController extends AbstractController
{
    public function __invoke(Request $req, GameSessionRepository $repo): JsonResponse
    {
        $mode    = $req->query->get('mode'); // ex: normal|blitz|endless|wiki|daily|letters|invite
        $metric  = $req->query->get('metric', 'durationMs'); // durationMs|wpm|score
        $agg     = $req->query->get('agg'); // min|max (optionnel: auto selon metric)
        $period  = $req->query->get('period', 'today'); // today|this_week|this_month|range
        $tz      = new DateTimeZone('Europe/Paris');

        [$start, $end] = $this->resolveWindow(
            $period,
            $req->query->get('start'),
            $req->query->get('end'),
            $tz
        );

        // filtres optionnels
        $articleId = $req->query->getInt('articleId', 0) ?: null; // pour daily/wiki
        $limit     = max(1, min(500, (int) $req->query->get('limit', 100)));

        // défauts intelligents: duration = MIN/ASC ; wpm/score = MAX/DESC
        if (!$agg) {
            $agg = in_array($metric, ['wpm', 'score', 'wordsTyped'], true) ? 'max' : 'min';
        }
        $direction = ($agg === 'min') ? 'ASC' : 'DESC';

        $rows = $repo->getLeaderboardByWindow([
            'mode'       => $mode,       // null = tous modes
            'metric'     => $metric,
            'agg'        => $agg,        // 'min' | 'max'
            'direction'  => $direction,  // 'ASC' | 'DESC'
            'start'      => $start,
            'end'        => $end,
            'articleId'  => $articleId,
            'limit'      => $limit,
            'success'    => true,
        ]);

        return $this->json([
            'filters' => [
                'mode'      => $mode,
                'metric'    => $metric,
                'agg'       => $agg,
                'period'    => $period,
                'start'     => $start?->format(DateTimeImmutable::ATOM),
                'end'       => $end?->format(DateTimeImmutable::ATOM),
                'articleId' => $articleId,
                'limit'     => $limit,
            ],
            'leaderboard' => array_map(static fn(array $r) => [
                'userId'        => (int) $r['userId'],
                'username'      => $r['username'],
                'bestValue'     => (int) $r['bestValue'],   // ms si durationMs, sinon valeur brute
                'attempts'      => (int) $r['attempts'],
                'bestWpm'       => isset($r['bestWpm']) ? (int) $r['bestWpm'] : null,
                'bestAccuracy'  => isset($r['bestAccuracy']) ? (float) $r['bestAccuracy'] : null,
                'bestSessionId' => isset($r['bestSessionId']) ? (int) $r['bestSessionId'] : null,
            ], $rows),
        ]);
    }

    /** @return array{0:?DateTimeImmutable,1:?DateTimeImmutable} */
    private function resolveWindow(string $period, ?string $start, ?string $end, DateTimeZone $tz): array
    {
        $now = new DateTimeImmutable('now', $tz);

        switch ($period) {
            case 'today':
                $s = $now->setTime(0,0,0);
                $e = $s->add(new DateInterval('P1D'));
                return [$s, $e];

            case 'this_week':
                // Lundi 00:00 → lundi prochain
                $dow = (int) $now->format('N'); // 1..7
                $s = $now->modify('-'.($dow-1).' days')->setTime(0,0,0);
                $e = $s->add(new DateInterval('P7D'));
                return [$s, $e];

            case 'this_month':
                $s = $now->modify('first day of this month')->setTime(0,0,0);
                $e = $s->modify('first day of next month');
                return [$s, $e];

            case 'range':
                $s = $start ? new DateTimeImmutable($start, $tz) : null;
                $e = $end   ? new DateTimeImmutable($end,   $tz) : null;
                return [$s, $e];

            default:
                // fallback = tout (pas de borne)
                return [null, null];
        }
    }

    #[Route('/levels', name: 'api_leaderboard_levels', methods: ['GET'])]
    public function levels(UserRepository $userRepository, LevelCalculator $levelCalc): JsonResponse
    {
        // On récupère tous les users triés par totalXp (SQL simple)
        $users = $userRepository->createQueryBuilder('u')
            ->orderBy('u.totalXp', 'DESC')
            ->setMaxResults(200)
            ->getQuery()
            ->getResult();

        // On reconstruit les données avec level info
        $rows = [];

        foreach ($users as $user) {
            $info = $levelCalc->computeLevel($user->getTotalXp());

            $rows[] = [
                'id'       => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'xp'       => $info, // level + currentXp + neededForNext
                'totalXp'  => $user->getTotalXp(),
            ];
        }

        // Tri final : level DESC puis currentXp DESC
        usort($rows, function ($a, $b) {
            if ($a['xp']['level'] === $b['xp']['level']) {
                return $b['xp']['currentXp'] <=> $a['xp']['currentXp'];
            }
            return $b['xp']['level'] <=> $a['xp']['level'];
        });

        return $this->json($rows);
    }
}
