<?php
// src/Controller/DailyArticleController.php

namespace App\Controller;

use App\Repository\DailyArticleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class DailyArticleController extends AbstractController
{
    #[Route('/api/daily-article', name: 'api_daily_article', methods: ['GET'])]
    public function __invoke(DailyArticleRepository $dailyArticleRepository): JsonResponse
    {
        $today = (new \DateTimeImmutable('today'))->setTime(0, 0, 0);
        $daily = $dailyArticleRepository->findByDate($today);

        if (!$daily) {
            return $this->json(['message' => 'Pas d’article pour aujourd’hui'], 404);
        }

        $article = $daily->getArticle();

        return $this->json([
            'date' => $daily->getDate()->format('Y-m-d'),
            'article' => [
                'id' => $article->getId(),
                'title' => $article->getTitle(),
                'wikiId' => $article->getWikiId() ?? null,
                'text' => $article->getText() ?? null,
            ],
        ]);
    }
}
