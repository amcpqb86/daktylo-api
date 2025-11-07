<?php
// src/Controller/GameSessionController.php
namespace App\Controller;

use App\Entity\GameSession;
use App\Entity\User;
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
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $mode          = $data['mode']          ?? 'normal';
        $durationMs    = (int) ($data['durationMs']    ?? 0);
        $charsTyped    = (int) ($data['charsTyped']    ?? 0);
        $errors        = (int) ($data['errors']        ?? 0);
        $accuracy      = $data['accuracy']     ?? null;
        $wpm           = (int) ($data['wpm']           ?? 0);
        $score         = (int) ($data['score']         ?? 0);
        $success       = (bool)($data['success']       ?? false);

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
        $session->setErrors($errors);
        $session->setAccuracy((float) $accuracy);
        $session->setWpm($wpm);
        $session->setScore($score);
        $session->setSuccess($success);

        $em->persist($session);
        $em->flush();

        return $this->json([
            'id' => $session->getId(),
            'mode' => $session->getMode(),
            'wpm' => $session->getWpm(),
            'accuracy' => $session->getAccuracy(),
            'createdAt' => $session->getPlayedAt()->format(\DateTimeInterface::ATOM),
        ], 201);
    }
}
