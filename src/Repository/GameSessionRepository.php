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

    /**
     * @param array{
     *   mode?: ?string,
     *   metric: 'durationMs'|'wpm'|'score',
     *   agg: 'min'|'max',
     *   direction: 'ASC'|'DESC',
     *   start?: ?\DateTimeImmutable,
     *   end?: ?\DateTimeImmutable,
     *   articleId?: ?int,
     *   success?: bool,
     *   limit?: int
     * } $opts
     * @return array<int,array{userId:int,username:string,bestValue:string,attempts:string}>
     */
    public function getLeaderboardByWindow(array $opts): array
    {
        $qb = $this->createQueryBuilder('gs')
            ->join('gs.user', 'u');

        // métrique/agrégat dynamiques
        $metricField = match ($opts['metric']) {
            'wpm'   => 'gs.wpm',
            'score' => 'gs.score',
            default => 'gs.durationMs',
        };
        $aggFn = ($opts['agg'] === 'max') ? 'MAX' : 'MIN';

        $qb->select(sprintf(
            'u.id AS userId, COALESCE(u.username, u.email) AS username, %s(%s) AS bestValue, COUNT(gs.id) AS attempts',
            $aggFn,
            $metricField
        ));

        // filtres
        if (!empty($opts['mode'])) {
            $qb->andWhere('gs.mode = :mode')->setParameter('mode', $opts['mode']);
        }
        if (!empty($opts['success'])) {
            $qb->andWhere('gs.success = :success')->setParameter('success', $opts['success']);
        }
        if (!empty($opts['articleId'])) {
            // relation ManyToOne: on filtre via l'id de l'entité reliée
            $qb->join('gs.wikiArticle', 'wa')
                ->andWhere('wa.id = :aid')
                ->setParameter('aid', $opts['articleId']);
        }
        if (!empty($opts['start'])) {
            $qb->andWhere('gs.playedAt >= :start')->setParameter('start', $opts['start']);
        }
        if (!empty($opts['end'])) {
            $qb->andWhere('gs.playedAt < :end')->setParameter('end', $opts['end']);
        }

        $qb->groupBy('u.id, u.username, u.email')
            ->orderBy('bestValue', $opts['direction'] ?? 'ASC')
            ->setMaxResults($opts['limit'] ?? 100);

        return $qb->getQuery()->getArrayResult();
    }

}
