<?php
// src/Controller/LeaderboardController.php

namespace App\Controller;

use App\Repository\GameSessionRepository;
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
            $agg = in_array($metric, ['wpm', 'score'], true) ? 'max' : 'min';
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
                'userId'     => (int) $r['userId'],
                'username'   => $r['username'],
                'bestValue'  => (int) $r['bestValue'], // ms si durationMs ; sinon wpm/score
                'attempts'   => (int) $r['attempts'],
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
}
