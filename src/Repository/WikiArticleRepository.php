<?php

namespace App\Repository;

use App\Entity\WikiArticle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WikiArticle>
 */
class WikiArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WikiArticle::class);
    }


    public function findOneRandomNotInDaily(array $alreadyPickedIds): ?\App\Entity\WikiArticle
    {
        // 1. compter combien d'articles sont éligibles
        $qbCount = $this->createQueryBuilder('w')
            ->select('COUNT(w.id)');

        if (!empty($alreadyPickedIds)) {
            $qbCount->andWhere('w.id NOT IN (:ids)')
                ->setParameter('ids', $alreadyPickedIds);
        }

        $total = (int) $qbCount->getQuery()->getSingleScalarResult();
        if ($total === 0) {
            return null;
        }

        $offset = random_int(0, $total - 1);

        $qb = $this->createQueryBuilder('w');

        if (!empty($alreadyPickedIds)) {
            $qb->andWhere('w.id NOT IN (:ids)')
                ->setParameter('ids', $alreadyPickedIds);
        }

        return $qb
            ->setFirstResult($offset)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
