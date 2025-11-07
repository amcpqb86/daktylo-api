<?php
// src/Controller/WikiController.php
namespace App\Controller;

use App\Entity\WikiArticle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class WikiController extends AbstractController
{
    #[Route('/api/wiki/random', name: 'api_wiki_random', methods: ['GET'])]
    public function random(EntityManagerInterface $em): JsonResponse
    {
        $repo = $em->getRepository(WikiArticle::class);

        $count = (int) $repo->createQueryBuilder('w')
            ->select('COUNT(w.id)')
            ->getQuery()
            ->getSingleScalarResult();

        if ($count === 0) {
            return $this->json([
                'error' => 'no_article',
                'message' => 'Aucun article en base.',
            ], 404);
        }

        $offset = random_int(0, $count - 1);

        $article = $repo->createQueryBuilder('w')
            ->setFirstResult($offset)
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleResult();

        return $this->json([
            'id' => $article->getId(),
            'wiki_id' => $article->getWikiId(),
            'title' => $article->getTitle(),
            'text' => $article->getText(),
        ]);
    }

    #[Route('/api/wiki/{id}', name: 'api_wiki_by_id', methods: ['GET'])]
    public function getById(int $id, EntityManagerInterface $em): JsonResponse
    {
        $article = $em->getRepository(WikiArticle::class)->find($id);

        if (!$article) {
            return $this->json([
                'error' => 'not_found',
                'message' => 'Article introuvable.',
            ], 404);
        }

        return $this->json([
            'id' => $article->getId(),
            'wiki_id' => $article->getWikiId(),
            'title' => $article->getTitle(),
            'text' => $article->getText(),
        ]);
    }
}
