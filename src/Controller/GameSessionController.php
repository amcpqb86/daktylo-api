<?php
// src/Controller/GameSessionController.php
namespace App\Controller;

use App\Entity\GameSession;
use App\Entity\User;
use App\Repository\WikiArticleRepository;
use App\Service\AchievementChecker;
use App\Service\LevelCalculator;
use App\Service\UserStatsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/game-sessions')]
class GameSessionController extends AbstractController
{
    #[Route('', name: 'api_game_session_create', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function create(Request $request, EntityManagerInterface $em, WikiArticleRepository $wikiArticleRepository, LevelCalculator $levelCalculator, UserStatsService $statsService, AchievementChecker $achievementChecker): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $mode          = $data['mode']          ?? 'normal';
        $durationMs    = (int) ($data['durationMs']    ?? 0);
        $charsTyped    = (int) ($data['charsTyped']    ?? 0);
        $wordsTyped    = (int) ($data['wordsTyped']    ?? 0);
        $errors        = (int) ($data['errors']        ?? 0);
        $accuracy      = $data['accuracy']     ?? null;
        $wpm           = (int) ($data['wpm']           ?? 0);
        $score         = (int) ($data['score']         ?? 0);
        $success       = (bool)($data['success']       ?? false);
        $wikiId     = $data['wikiArticleId'] ?? null;

        $xpEarned = (int) $charsTyped;
        $user->addXp($xpEarned);

        // si le front n'a pas envoyé la précision, on la recalcule
        if ($accuracy === null) {
            if ($charsTyped > 0) {
                $correct = max($charsTyped - $errors, 0);
                $accuracy = round(($correct / $charsTyped) * 100, 2);
            } else {
                $accuracy = 100;
            }
        }

        $session = new GameSession();
        $session->setUser($user);
        $session->setMode($mode);
        $session->setPlayedAt(new \DateTimeImmutable());
        $session->setDurationMs($durationMs);
        $session->setCharsTyped($charsTyped);
        $session->setWordsTyped($wordsTyped);
        $session->setErrors($errors);
        $session->setAccuracy((float) $accuracy);
        $session->setWpm($wpm);
        $session->setScore($score);
        $session->setSuccess($success);
        if (in_array($mode, ['wiki', 'daily'], true) && $wikiId) {
            $wikiArticle = $wikiArticleRepository->find($wikiId);
            if ($wikiArticle) {
                $session->setWikiArticle($wikiArticle);
            }
        }

        $em->persist($session);
        $em->persist($user);
        $em->flush();

        $levelInfo = $levelCalculator->computeLevel($user->getTotalXp());

        $stats = $statsService->buildStatsFor($user);

        $newAchievements = $achievementChecker->checkForSession(
            $user,
            $session,
            $stats
        );


        return $this->json([
            'id' => $session->getId(),
            'mode' => $session->getMode(),
            'wpm' => $session->getWpm(),
            'accuracy' => $session->getAccuracy(),
            'createdAt' => $session->getPlayedAt()->format(\DateTimeInterface::ATOM),
            'xp' => [
                'earned'      => $xpEarned,
                'total'       => $user->getTotalXp(),
                'level'       => $levelInfo['level'],
                'currentXp'   => $levelInfo['currentXp'],
                'neededForNext' => $levelInfo['neededForNext'],
            ],
            'achievements' => array_map(fn($a) => [
                'code' => $a->getCode(),
                'name' => $a->getName(),
                'description' => $a->getDescription(),
            ], $newAchievements),
        ], 201);
    }
}
