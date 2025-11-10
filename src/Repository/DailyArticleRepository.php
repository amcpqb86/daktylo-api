<?php

namespace App\Repository;

use App\Entity\DailyArticle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DailyArticle>
 */
class DailyArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DailyArticle::class);
    }

    public function findByDate(\DateTimeImmutable $date): ?DailyArticle
    {
        return $this->findOneBy(['date' => $date]);
    }
}
