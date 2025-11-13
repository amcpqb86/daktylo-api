<?php

namespace App\Repository;

use App\Entity\GameSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GameSession>
 */
class GameSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameSession::class);
    }

    public function getBestScoreOfTodayDaily(): ?int
    {
        $start = (new \DateTimeImmutable('today'))->setTime(0, 0, 0);
        $end = $start->modify('+1 day');

        return (int) $this->createQueryBuilder('g')
            ->select('MIN(g.durationMs)')
            ->andWhere('g.mode = :mode')
            ->andWhere('g.playedAt >= :start')
            ->andWhere('g.playedAt < :end')
            ->setParameter('mode', 'daily')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getBestScoreOfTodayDailyForUser(int $userId): ?int
    {
        $start = (new \DateTimeImmutable('today'))->setTime(0, 0, 0);
        $end = $start->modify('+1 day');

        return $this->createQueryBuilder('g')
            ->select('MIN(g.durationMs)')
            ->andWhere('g.mode = :mode')
            ->andWhere('g.playedAt >= :start')
            ->andWhere('g.playedAt < :end')
            ->andWhere('g.user = :user')
            ->setParameter('mode', 'daily')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('user', $userId)
            ->getQuery()
            ->getSingleScalarResult() ?: null;
    }


    public function getLeaderboardByWindow(array $opts): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $metric = $opts['metric'] ?? 'durationMs';           // durationMs|wpm|score
        $direction = ($opts['direction'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        $limit = (int)($opts['limit'] ?? 100);

        // Champ SQL réel en base
        $metricExpr = match ($metric) {
            'wpm'   => 'gs.wpm',
            'score' => 'gs.score',
            default => 'gs.duration_ms',
        };

        // Filtres dynamiques
        $where = ['1=1'];
        $params = [];

        if (!empty($opts['mode'])) {
            $where[] = 'gs.mode = :mode';
            $params['mode'] = $opts['mode'];
        }
        if (array_key_exists('success', $opts)) {
            $where[] = 'gs.success = :success';
            $params['success'] = (int) $opts['success'];
        }
        if (!empty($opts['articleId'])) {
            $where[] = 'gs.wiki_article_id = :aid';
            $params['aid'] = (int) $opts['articleId'];
        }
        if (!empty($opts['start'])) {
            $where[] = 'gs.played_at >= :start';
            $params['start'] = $opts['start']->format('Y-m-d H:i:s');
        }
        if (!empty($opts['end'])) {
            $where[] = 'gs.played_at < :end';
            $params['end'] = $opts['end']->format('Y-m-d H:i:s');
        }

        // Single query avec window functions (MySQL 8+/PostgreSQL)
        // 2) éviter le paramètre nommé dans LIMIT (MySQL le quote => '100')
        $sql = "
            WITH filtered AS (
                SELECT
                    u.id                                  AS user_id,
                    COALESCE(u.username, u.email)         AS username,
                    gs.id                                 AS session_id,
                    gs.wpm,
                    gs.accuracy,
                    gs.duration_ms,
                    gs.score,
                    $metricExpr                           AS metric_value,
                    COUNT(*) OVER (PARTITION BY u.id)     AS attempts
                FROM game_session gs
                JOIN `user` u ON u.id = gs.user_id
                WHERE " . implode(' AND ', $where) . "
            ),
            ranked AS (
                SELECT
                    f.*,
                    ROW_NUMBER() OVER (
                        PARTITION BY f.user_id
                        ORDER BY f.metric_value $direction, f.session_id ASC
                    ) AS rn
                FROM filtered f
            )
            SELECT
                r.user_id      AS userId,
                r.username     AS username,
                r.metric_value AS bestValue,
                r.attempts     AS attempts,
                r.wpm          AS bestWpm,
                r.accuracy     AS bestAccuracy,
                r.session_id   AS bestSessionId
            FROM ranked r
            WHERE r.rn = 1
            ORDER BY bestValue $direction
            LIMIT $limit
        ";

        $params['lim'] = $limit;

        return $conn->executeQuery($sql, $params)->fetchAllAssociative();
    }

}
