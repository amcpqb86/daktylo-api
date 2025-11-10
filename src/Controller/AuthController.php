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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

final class AuthController extends AbstractController
{

    public function __construct(
        private JWTTokenManagerInterface $jwtManager,
        private UserPasswordHasherInterface $passwordHasher,
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
        $email = strtolower(trim($data['email'] ?? ''));
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

        try {
            $mailer->send(
                (new Email())
                    ->from('Daktylo <no-reply@daktylo.fr>')
                    ->to($email)
                    ->subject('Votre code de connexion')
                    ->text('Votre code est : ' . $code)
            );
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }

        return $this->json(['success' => true]);
    }

    #[Route('/auth/verify-code', name: 'auth_verify_code', methods: ['POST'])]
    public function verifyCode(
        Request $request,
        LoginCodeRepository $codes,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $email = strtolower(trim($data['email'] ?? ''));
        $code = $data['code'] ?? null;

        if (!$email || !$code) {
            return $this->json(['success' => false, 'error' => 'Email ou code manquant'], 400);
        }

        $loginCode = $codes->findOneBy(['code' => $code], ['id' => 'DESC']);
        if (!$loginCode) {
            return $this->json(['success' => false, 'error' => 'Code invalide'], 400);
        }

        $user = $loginCode->getUser();

        if ($user->getEmail() !== $email) {
            return $this->json(['success' => false, 'error' => 'Code non associé à cet email'], 400);
        }

        if ($loginCode->isUsed() || $loginCode->isExpired()) {
            return $this->json(['success' => false, 'error' => 'Code expiré ou déjà utilisé'], 400);
        }

        $loginCode->setUsed(true);
        $user->setLastLoginAt(new \DateTimeImmutable());
        $em->flush();

        $token = $this->jwtManager->create($user);

        $needsProfile = !$user->getPassword();

        return $this->json([
            'success' => true,
            'token' => $token,
            'needsProfile' => $needsProfile,
            'email' => $user->getEmail(),
            'username' => $user->getUsername(),
        ]);
    }

    #[Route('/auth/login-password', name: 'auth_login_password', methods: ['POST'])]
    public function loginPassword(Request $request, UserRepository $users): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = strtolower(trim($data['email'] ?? ''));
        $password = $data['password'] ?? null;

        if (!$email || !$password) {
            return $this->json(['success' => false, 'error' => 'Email ou mot de passe manquant'], 400);
        }

        /** @var User|null $user */
        $user = $users->findOneBy(['email' => $email]);
        if (!$user) {
            return $this->json(['success' => false, 'error' => 'Utilisateur introuvable'], 400);
        }

        if (!$user->getPassword()) {
            return $this->json(['success' => false, 'error' => 'Aucun mot de passe défini pour cet utilisateur'], 400);
        }

        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            return $this->json(['success' => false, 'error' => 'Mot de passe incorrect'], 400);
        }

        $user->setLastLoginAt(new \DateTimeImmutable());
        $token = $this->jwtManager->create($user);

        return $this->json([
            'success' => true,
            'token' => $token,
        ]);
    }
    #[Route('/auth/set-password', name: 'auth_set_password', methods: ['POST'])]
    public function setPassword(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['success' => false, 'error' => 'Non authentifié'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $password = $data['password'] ?? null;

        if (!$password) {
            return $this->json(['success' => false, 'error' => 'Mot de passe manquant'], 400);
        }

        $hash = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hash);
        $em->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/auth/refresh', name: 'auth_refresh', methods: ['POST'])]
    public function refresh(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['success' => false, 'error' => 'Non authentifié'], 401);
        }

        $token = $this->jwtManager->create($user);

        return $this->json([
            'success' => true,
            'token' => $token,
        ]);
    }
}
