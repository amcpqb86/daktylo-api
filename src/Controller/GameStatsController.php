<?php
// src/Controller/GameStatsController.php
namespace App\Controller;

use App\Entity\GameSession;
use App\Entity\WikiArticle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/stats')]
class GameStatsController extends AbstractController
{
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
            'avg_wpm' => round($stats['avgWpm'], 1),
            'avg_accuracy' => round($stats['avgAccuracy'], 1),
            'total_time' => $formattedTime,
            'best_wpm' => (int) $stats['bestWpm'],
            'account_created' => $user->getCreatedAt()?->format(\DateTimeInterface::ATOM),
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
}
