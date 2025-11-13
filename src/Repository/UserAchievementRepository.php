<?php

namespace App\Repository;

use App\Entity\Achievement;
use App\Entity\User;
use App\Entity\UserAchievement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserAchievement>
 */
class UserAchievementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserAchievement::class);
    }

    public function isUnlocked(User $user, Achievement $achievement): bool
    {
        return (bool) $this->createQueryBuilder('ua')
            ->select('1')
            ->andWhere('ua.user = :user')
            ->andWhere('ua.achievement = :achievement')
            ->setParameter('user', $user)
            ->setParameter('achievement', $achievement)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return UserAchievement[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('ua')
            ->addSelect('a')
            ->join('ua.achievement', 'a')
            ->andWhere('ua.user = :user')
            ->setParameter('user', $user)
            ->orderBy('ua.unlockedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

}
