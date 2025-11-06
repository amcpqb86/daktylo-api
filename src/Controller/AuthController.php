<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\LoginCode;
use App\Repository\UserRepository;
use App\Repository\LoginCodeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;

final class AuthController extends AbstractController
{

    public function __construct(
        private JWTTokenManagerInterface $jwtManager,
    ) {}

    #[Route('/auth/request-code', name: 'auth_request_code', methods: ['POST'])]
    public function requestCode(
        Request $request,
        UserRepository $users,
        LoginCodeRepository $loginCodeRepo,
        EntityManagerInterface $em,
        MailerInterface $mailer
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;
        if (!$email) {
            return $this->json(['success' => false, 'error' => 'Email manquant'], 400);
        }

        $user = $users->findOneBy(['email' => strtolower($email)]);
        if (!$user) {
            $user = (new User())->setEmail($email);
            $em->persist($user);
        }

        $lastCode = $loginCodeRepo->findOneBy(
            ['user' => $user, 'used' => false],
            ['id' => 'DESC']
        );

        if ($lastCode && $lastCode->getCreatedAt() > (new \DateTimeImmutable())->modify('-60 seconds')) {
            return $this->json([
                'success' => false,
                'error' => 'Vous avez déjà demandé un code récemment.'
            ], 429);
        }
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $loginCode = (new LoginCode())
            ->setUser($user)
            ->setCode($code)
            ->setExpiresAt((new \DateTimeImmutable())->modify('+10 minutes'));

        $em->persist($loginCode);
        $em->flush();

        $mailer->send(
            (new Email())
                ->from('Layro <no-reply@layro.app>')
                ->to($email)
                ->subject('Votre code de connexion')
                ->text('Votre code est : ' . $code)
        );

        return $this->json(['success' => true]);
    }

    #[Route('/auth/verify-code', name: 'auth_verify_code', methods: ['POST'])]
    public function verifyCode(
        Request $request,
        LoginCodeRepository $codes,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;
        $code = $data['code'] ?? null;

        if (!$email || !$code) {
            return $this->json(['success' => false, 'error' => 'Email ou code manquant'], 400);
        }

        $loginCode = $codes->findOneBy(['code' => $code], ['id' => 'DESC']);
        if (!$loginCode) {
            return $this->json(['success' => false, 'error' => 'Code invalide'], 400);
        }

        if ($loginCode->getUser()->getEmail() !== strtolower($email)) {
            return $this->json(['success' => false, 'error' => 'Code non associé à cet email'], 400);
        }

        if ($loginCode->isUsed() || $loginCode->isExpired()) {
            return $this->json(['success' => false, 'error' => 'Code expiré ou déjà utilisé'], 400);
        }

        $loginCode->setUsed(true);
        $loginCode->getUser()->setLastLoginAt(new \DateTimeImmutable());
        $em->flush();

        $token = $this->jwtManager->createFromPayload(
            $loginCode->getUser(),
            ['email' => $loginCode->getUser()->getEmail()]
        );

        return $this->json([
            'success' => true,
            'token' => $token,
        ]);
    }
}
