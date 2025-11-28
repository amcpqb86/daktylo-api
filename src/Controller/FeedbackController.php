<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;

#[Route('/api/feedback')]
final class FeedbackController extends AbstractController
{
    #[Route('', name: 'feedback_send', methods: ['POST'])]
    public function send(Request $request, MailerInterface $mailer): \Symfony\Component\HttpFoundation\JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $message   = trim($data['message'] ?? '');
        $fromEmail = trim($data['email'] ?? '');
        $page      = $data['page'] ?? null;      // optionnel: page depuis laquelle le feedback est envoyé
        $meta      = $data['meta'] ?? null;      // optionnel: infos diverses (mode de jeu, navigateur, etc.)

        if ($message === '') {
            return $this->json([
                'success' => false,
                'error'   => 'Message de feedback manquant.',
            ], 400);
        }

        $bodyLines = [
            "Nouveau feedback Daktylo :",
            "",
            "Message :",
            $message,
            "",
            "Email de l’utilisateur : " . ($fromEmail ?: '(non renseigné)'),
        ];

        if ($page) {
            $bodyLines[] = '';
            $bodyLines[] = 'Page : ' . $page;
        }

        if ($meta) {
            $bodyLines[] = '';
            $bodyLines[] = 'Meta :';
            $bodyLines[] = is_string($meta) ? $meta : json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        $email = (new Email())
            ->from('Daktylo <no-reply@daktylo.fr>')
            ->to('contact@amelienbernard.fr')
            ->subject('[Daktylo] Nouveau feedback')
            ->text(implode("\n", $bodyLines));

        if ($fromEmail) {
            $email->replyTo($fromEmail);
        }

        try {
            $mailer->send($email);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'error'   => 'Erreur lors de l’envoi du mail : ' . $e->getMessage(),
            ], 500);
        }

        return $this->json(['success' => true]);
    }
}
