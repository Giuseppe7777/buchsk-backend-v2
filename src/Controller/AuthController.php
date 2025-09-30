<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\TelnyxOtpService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

#[Route('/auth', name: 'auth_')]
final class AuthController extends AbstractController
{
    public function __construct(
      private TelnyxOtpService $telnyxOtp,
    ) {}

  #[Route('/register', name: 'register', methods: ['POST'])]
  public function register(
      Request $request,
      EntityManagerInterface $em,
      UserPasswordHasherInterface $hasher,
      UserRepository $users,
      \Psr\Log\LoggerInterface $logger,
  ): JsonResponse 
  {
      $data = json_decode($request->getContent(), true);

      // validation
      $validationError = $this->validateRegisterInput($data);
      if ($validationError !== null) {
          return $validationError;
      }

      $phone = $data['phone'];
      $password = $data['password'];
      $firstName = $data['firstName'];
      $lastName = $data['lastName'];

      // uniqueness check
      if ($this->userExists($users, $phone)) {
          return $this->conflictResponse();
      }

      // create user
      $user = new User();
      $user->setPhone($phone);
      $user->setFirstName($firstName);
      $user->setLastName($lastName);
      $user->setStatus('pending');
      $user->setIsVerified(false);

      $hashed = $hasher->hashPassword($user, $password);
      $user->setPassword($hashed);

      try {
          $em->persist($user);
          $em->flush();
      } catch (UniqueConstraintViolationException $e) {
          $logger->warning('User creation failed: phone already exists', [
              'phone' => $phone,
              'error' => $e->getMessage(),
          ]);

          return $this->conflictResponse();
      }

      // send OTP
      try {
          $result = $this->telnyxOtp->sendOtp($phone);

          if (isset($result['data'])) {
              $logger->info('OTP sent', ['phone' => $phone, 'result' => $result]);
              $message = 'User created. Verification SMS sent.';
          } elseif (isset($result['errors'])) {
              $logger->error('OTP sending failed', ['phone' => $phone, 'errors' => $result['errors']]);
              $message = 'User created, but OTP failed to send. Please try resend.';
          } else {
              $logger->warning('Unexpected OTP response', ['phone' => $phone, 'result' => $result]);
              $message = 'User created, but verification service returned unexpected response.';
          }
      } catch (\Throwable $e) {
          $logger->critical('OTP send exception', ['phone' => $phone, 'exception' => $e->getMessage()]);
          $message = 'User created, but verification service unavailable. Please try later.';
      }

      return $this->json([
          'status' => JsonResponse::HTTP_CREATED,
          'message' => $message,
          'data' => [
              'id' => $user->getId(),
              'phone' => $user->getPhone(),
          ],
      ], JsonResponse::HTTP_CREATED);
  }

  private function conflictResponse(): JsonResponse
  {
      return $this->json([
          'status' => JsonResponse::HTTP_CONFLICT,
          'message' => 'a user with this phone number already exists',
          'data' => null,
      ], JsonResponse::HTTP_CONFLICT);
  }

  private function userExists(UserRepository $users, string $phone): bool
  {
      return $users->findOneBy(['phone' => $phone]) !== null;
  }

  private function validateRegisterInput(?array $data): ?JsonResponse
  {
      if (!$data) {
          return $this->json([
              'status' => JsonResponse::HTTP_BAD_REQUEST,
              'message' => 'Invalid JSON',
              'data' => null,
          ], JsonResponse::HTTP_BAD_REQUEST);
      }

      if (empty($data['phone']) || empty($data['password']) || empty($data['firstName']) || empty($data['lastName'])) {
          return $this->json([
              'status' => JsonResponse::HTTP_BAD_REQUEST,
              'message' => 'Fields phone, password, firstName and lastName are required.',
              'data' => null,
          ], JsonResponse::HTTP_BAD_REQUEST);
      }

      return null; 
  }


  #[Route('/send-otp', name: 'send-otp', methods: ['POST'])]
  public function sendOtp(
      Request $request,
  ): JsonResponse 
  {
      // TODO: sent OTP 
      return $this->json([
        "status" => JsonResponse::HTTP_OK,
        "message" => "otp verified (stub)",
        "data" => null
      ]);
  }

  #[Route('/verify-otp', name: 'verify-otp', methods: ['POST'])]
  public function verifyOtp(
      Request $request,
      UserRepository $users,
      EntityManagerInterface $em,
      \Psr\Log\LoggerInterface $logger,
  ): JsonResponse 
  {
      $data = json_decode($request->getContent(), true);

      if (!$data || empty($data['phone']) || empty($data['code'])) {
          return $this->json([
              'status' => JsonResponse::HTTP_BAD_REQUEST,
              'message' => 'Fields phone and code are required.',
              'data' => null,
          ], JsonResponse::HTTP_BAD_REQUEST);
      }

      $phone = $data['phone'];
      $code  = $data['code'];

      $user = $users->findOneBy(['phone' => $phone]);
      if (!$user) {
          return $this->json([
              'status' => JsonResponse::HTTP_NOT_FOUND,
              'message' => 'User with this phone not found.',
              'data' => null,
          ], JsonResponse::HTTP_NOT_FOUND);
      }

      try {
          $result = $this->telnyxOtp->verifyOtp($phone, $code);
      } catch (\Throwable $e) {
          $logger->critical('OTP verify exception', ['phone' => $phone, 'exception' => $e->getMessage()]);

          return $this->json([
              'status' => JsonResponse::HTTP_SERVICE_UNAVAILABLE,
              'message' => 'Verification service unavailable. Please try later.',
              'data' => null,
          ], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
      }

      $responseCode = $result['data']['response_code'] ?? null;
      if ($responseCode === 'accepted') {
        $user->setIsVerified(true);
        $user->setStatus('active');
        $em->flush();

        $logger->info('OTP verified', ['phone' => $phone, 'result' => $result]);

        return $this->json([
          'status' => JsonResponse::HTTP_OK,
          'message' => 'OTP verified successfully.',
          'data' => null,
        ]);
      }

      if ($responseCode === 'rejected') {
          $logger->warning('OTP rejected', ['phone' => $phone, 'result' => $result]);

          return $this->json([
              'status' => JsonResponse::HTTP_BAD_REQUEST,
              'message' => 'Verification code rejected.',
              'data' => $result['data'],
          ], JsonResponse::HTTP_BAD_REQUEST);
      }

      if (isset($result['errors'])) {
          $logger->warning('OTP verification request invalid', ['phone' => $phone, 'errors' => $result['errors']]);

          return $this->json([
              'status' => JsonResponse::HTTP_BAD_REQUEST,
              'message' => 'Verification request invalid.',
              'errors' => $result['errors'],
          ], JsonResponse::HTTP_BAD_REQUEST);
      }

      $logger->warning('Unexpected OTP verification response', ['phone' => $phone, 'result' => $result]);

      return $this->json([
          'status' => JsonResponse::HTTP_BAD_GATEWAY,
          'message' => 'Verification provider returned an unexpected response.',
          'data' => $result,
      ], JsonResponse::HTTP_BAD_GATEWAY);
  }

  #[Route('/forgot-password', name: 'forgot_password', methods: ['POST'])]
  public function forgotPassword(
      Request $request,
      UserRepository $users,
      \Psr\Log\LoggerInterface $logger
  ): JsonResponse {
      $data = json_decode($request->getContent(), true);
      $phone = $data['phone'] ?? null;

      if (!$phone) {
          return $this->json([
              'status' => JsonResponse::HTTP_BAD_REQUEST,
              'message' => 'Phone number is required.',
              'data' => null,
          ], JsonResponse::HTTP_BAD_REQUEST);
      }

      $user = $users->findOneBy(['phone' => $phone]);

      if ($user) {
          try {
              $this->telnyxOtp->sendOtp($phone);
              $logger->info('Forgot password OTP sent', ['phone' => $phone]);
          } catch (\Throwable $e) {
              $logger->error('Forgot password OTP failed', [
                  'phone' => $phone,
                  'error' => $e->getMessage(),
              ]);
          }
      }

      // Завжди нейтральна відповідь
      return $this->json([
          'status' => JsonResponse::HTTP_OK,
          'message' => 'If the phone exists, a reset code was sent.',
          'data' => null,
      ], JsonResponse::HTTP_OK);
  }

  #[Route('/reset-password', name: 'reset_password', methods: ['POST'])]
  public function resetPassword(
      Request $request,
      UserRepository $users,
      EntityManagerInterface $em,
      UserPasswordHasherInterface $hasher,
      \Psr\Log\LoggerInterface $logger
  ): JsonResponse {
      $data = json_decode($request->getContent(), true);

      $phone       = $data['phone'] ?? null;
      $otp         = $data['otp'] ?? null;
      $newPassword = $data['newPassword'] ?? null;

      if (!$phone || !$otp || !$newPassword) {
          return $this->json([
              'status' => JsonResponse::HTTP_BAD_REQUEST,
              'message' => 'Fields phone, otp and newPassword are required.',
              'data' => null,
          ], JsonResponse::HTTP_BAD_REQUEST);
      }

      $user = $users->findOneBy(['phone' => $phone]);
      if (!$user) {
          // нейтральна відповідь (не видаємо існування/неіснування)
          return $this->json([
              'status' => JsonResponse::HTTP_OK,
              'message' => 'If the phone exists, the password has been reset.',
              'data' => null,
          ], JsonResponse::HTTP_OK);
      }

      try {
          $result = $this->telnyxOtp->verifyOtp($phone, $otp);
      } catch (\Throwable $e) {
          $logger->critical('OTP verify exception (reset password)', [
              'phone' => $phone,
              'exception' => $e->getMessage()
          ]);

          return $this->json([
              'status' => JsonResponse::HTTP_SERVICE_UNAVAILABLE,
              'message' => 'Verification service unavailable. Please try later.',
              'data' => null,
          ], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
      }

      $responseCode = $result['data']['response_code'] ?? null;
      if ($responseCode === 'accepted') {
          // змінюємо пароль
          $hashed = $hasher->hashPassword($user, $newPassword);
          $user->setPassword($hashed);
          $em->flush();

          $logger->info('Password reset successful', ['phone' => $phone]);

          return $this->json([
              'status' => JsonResponse::HTTP_OK,
              'message' => 'Password has been reset successfully.',
              'data' => null,
          ], JsonResponse::HTTP_OK);
      }

      if ($responseCode === 'rejected') {
          return $this->json([
              'status' => JsonResponse::HTTP_BAD_REQUEST,
              'message' => 'Invalid or expired OTP code.',
              'data' => null,
          ], JsonResponse::HTTP_BAD_REQUEST);
      }

      if (isset($result['errors'])) {
          return $this->json([
              'status' => JsonResponse::HTTP_BAD_REQUEST,
              'message' => 'Verification request invalid.',
              'errors' => $result['errors'],
          ], JsonResponse::HTTP_BAD_REQUEST);
      }

      return $this->json([
          'status' => JsonResponse::HTTP_BAD_GATEWAY,
          'message' => 'Unexpected verification provider response.',
          'data' => $result,
      ], JsonResponse::HTTP_BAD_GATEWAY);
  }

  #[Route('/login', name: 'login', methods: ['POST'])]
  public function login(): void
  {
      throw new \LogicException('This method is intercepted by the firewall (json_login).');
  }

}
