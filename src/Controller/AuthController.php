<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\CompanyLookupService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\TelnyxOtpService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Validator\PasswordComplexity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/auth', name: 'auth_')]
final class AuthController extends AbstractController
{
    public function __construct(
      private TelnyxOtpService $telnyxOtp,
      private TranslatorInterface $t,
      private CompanyLookupService $companyLookup,
    ) {}

  #[Route('/register', name: 'register', methods: ['POST'])]
  public function register(
      Request $request,
      EntityManagerInterface $em,
      UserPasswordHasherInterface $hasher,
      UserRepository $users,
      \Psr\Log\LoggerInterface $logger,
      ValidatorInterface $validator,
  ): JsonResponse 
  {
      $data = json_decode($request->getContent(), true);

      // 1) Перевіряємо JSON і обов’язкові поля
      if (!$data) {
          return $this->json([
              'status' => JsonResponse::HTTP_BAD_REQUEST,
              'message' => 'Invalid JSON',
              'errors' => [
                  'json' => 'Request body must be valid JSON.',
              ],
          ], JsonResponse::HTTP_BAD_REQUEST);
      }

      $phone     = $data['phone'] ?? null;
      $password  = $data['password'] ?? null;
      $firstName = $data['firstName'] ?? null;
      $lastName  = $data['lastName'] ?? null;

      if (!$phone || !$password || !$firstName || !$lastName) {
          return $this->json([
              'status' => JsonResponse::HTTP_BAD_REQUEST,
              'message' => 'Required fields are missing.',
              'errors' => [
                  'phone' => !$phone ? 'errors.phone.required' : null,
                  'password' => !$password ? 'errors.password.required' : null,
                  'firstName' => !$firstName ? 'errors.firstName.required' : null,
                  'lastName' => !$lastName ? 'errors.lastName.required' : null,
              ],
          ], JsonResponse::HTTP_BAD_REQUEST);
      }

      // 2) Унікальність телефону
      if ($this->userExists($users, $phone)) {
          return $this->conflictResponse();
      }

      // 3) Створюємо об’єкт користувача
      $user = new User();
      $user->setPhone($phone);
      $user->setFirstName($firstName);
      $user->setLastName($lastName);
      $user->setStatus('pending');
      $user->setIsVerified(false);

      // 4) Валідація (phone, firstName, lastName)
      $violations = $validator->validate($user);
      if (\count($violations) > 0) {
          $errors = [];
          foreach ($violations as $violation) {
              $field = $violation->getPropertyPath();
              $errors[$field] = $violation->getMessage();
          }
          return $this->json([
              'status'  => 400,
              'message' => 'validation.failed',
              'errors'  => $errors,
          ], 400);
      }

      // 5) Перевірка складності пароля
      $violations = $validator->validate($password, [
          new PasswordComplexity(),
      ]);
      if (\count($violations) > 0) {
          return $this->json([
              'status'  => 400,
              'message' => 'validation.failed',
              'errors'  => [
                  'password' => $violations[0]->getMessage(),
              ],
          ], 400);
      }

      // 6) Хешування пароля
      $hashed = $hasher->hashPassword($user, $password);
      $user->setPassword($hashed);

      // 7) Зберігаємо користувача
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

      // 8) Надсилаємо OTP
      try {
          $result = $this->telnyxOtp->sendOtp($phone);

          if (isset($result['data'])) {
              $logger->info('OTP sent', ['phone' => $phone, 'result' => $result]);
              $message = 'User created. Verification SMS sent.';
          } elseif (isset($result['errors'])) {
              $errorsText = json_encode($result['errors']); 
              $logger->error('OTP sending failed', ['phone' => $phone, 'errors' => $result['errors']]);
              $message = 'User created, but OTP failed to send. Errors: ' . $errorsText;
          } else {
              $logger->warning('Unexpected OTP response', ['phone' => $phone, 'result' => $result]);
              $message = 'User created, but verification service returned unexpected response.';
          }
      } catch (\Throwable $e) {
          $logger->critical('OTP send exception', ['phone' => $phone, 'exception' => $e->getMessage()]);
          $message = 'User created, but verification service unavailable. Please try later.';
      }

      // 9) Успішна відповідь
      return $this->json([
          'status' => JsonResponse::HTTP_CREATED,
          'message' => $message,
          'data' => [
              'id' => $user->getId(),
              'phone' => $user->getPhone(),
          ],
      ], JsonResponse::HTTP_CREATED);
  }

  #[Route('/verify-otp', name: 'verify-otp', methods: ['POST'])]
  public function verifyOtp(
      Request $request,
      UserRepository $users,
      EntityManagerInterface $em,
      \Psr\Log\LoggerInterface $logger,
      ValidatorInterface $validator,
  ): JsonResponse 
  {
      $data = json_decode($request->getContent(), true);

      if (!$data) {
          return $this->json([
              'status' => 400,
              'message' => 'Invalid JSON',
              'errors' => [ 'json' => 'Request body must be valid JSON.' ],
          ], 400);
      }

      $phone = $data['phone'] ?? null;
      $code  = $data['code']  ?? null;

      if (!$phone || !$code) {
          return $this->json([
              'status'  => 400,
              'message' => 'Required fields are missing.',
              'errors'  => [
                  'phone' => !$phone ? 'errors.phone.required' : null,
                  'otp'   => !$code  ? 'errors.otp.required'   : null,
              ],
          ], 400);
      }

      // regex-валідатор для OTP (5-6 цифр)
      $codeViolations = $validator->validate($code, [
          new Assert\Regex([
              'pattern' => '/^\d{5,6}$/',
              'message' => 'OTP must be 5 or 6 digits.',
          ]),
      ]);
      if (\count($codeViolations) > 0) {
          return $this->json([
              'status'  => 400,
              'message' => 'validation.failed',
              'errors'  => [ 'otp' => $codeViolations[0]->getMessage() ],
          ], 400);
      }

      $user = $users->findOneBy(['phone' => $phone]);
      if (!$user) {
          return $this->json([
              'status'  => 404,
              'message' => 'validation.failed',
              'errors'  => [ 'phone' => 'User with this phone not found.' ],
          ], 404);
      }

      try {
          $result = $this->telnyxOtp->verifyOtp($phone, $code);
      } catch (\Throwable $e) {
          $logger->critical('OTP verify exception', ['phone' => $phone, 'exception' => $e->getMessage()]);

          return $this->json([
            'status'  => 503,
            'message' => 'Verification service unavailable. Please try later.',
            'errors'  => [ 'otp' => 'Verification temporarily unavailable.' ],
        ], 503);
      }

      $responseCode = $result['data']['response_code'] ?? null;
      if ($responseCode === 'accepted') {
        $user->setIsVerified(true);
        $user->setStatus('active');
        $em->flush();

        // === 1. Отримуємо IČO з JSON (воно вже приходить з фронтенду) ===
        $ico = $data['ico'] ?? null;

        if ($ico) {
            try {
                // === 2. Отримуємо дані з RegisterUZ API ===
                $companyData = $this->companyLookup->getCompanyDataByIco($ico);

                if (!isset($companyData['error'])) {
                    $detail = $companyData['detail'] ?? [];

                    // === 3. Створюємо Company і заповнюємо поля ===
                    $company = new \App\Entity\Company();
                    $company->setUser($user);
                    $company->setRuzId($companyData['id']);
                    $company->setIco($ico);
                    $company->setDic($detail['dic'] ?? null);
                    $company->setSid($detail['sid'] ?? null);
                    $company->setNazovUj($detail['nazovUJ'] ?? null);
                    $company->setMesto($detail['mesto'] ?? null);
                    $company->setUlica($detail['ulica'] ?? null);
                    $company->setPsc($detail['psc'] ?? null);
                    $company->setDatumZalozenia(!empty($detail['datumZalozenia']) ? new \DateTime($detail['datumZalozenia']) : null);
                    $company->setDatumZrusenia(
                        !empty($detail['datumZrusenia']) ? new \DateTime($detail['datumZrusenia']) : null
                    );
                    $company->setPravnaForma($detail['pravnaForma'] ?? null);
                    $company->setSkNace($detail['skNace'] ?? null);
                    $company->setVelkostOrganizacie($detail['velkostOrganizacie'] ?? null);
                    $company->setDruhVlastnictva($detail['druhVlastnictva'] ?? null);
                    $company->setKraj($detail['kraj'] ?? null);
                    $company->setOkres($detail['okres'] ?? null);
                    $company->setSidlo($detail['sidlo'] ?? null);
                    $company->setKonsolidovana(
                        isset($detail['konsolidovana'])
                            ? filter_var($detail['konsolidovana'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                            : null
                    );
                    $company->setIdUctovnychZavierok(
                        isset($detail['idUctovnychZavierok']) && is_array($detail['idUctovnychZavierok'])
                            ? array_map(static fn($v) => (int) $v, $detail['idUctovnychZavierok'])
                            : null
                    );
                    $company->setIdVyrocnychSprav(
                        isset($detail['idVyrocnychSprav']) && is_array($detail['idVyrocnychSprav'])
                            ? array_map(static fn($v) => (int) $v, $detail['idVyrocnychSprav'])
                            : null
                    );
                    $company->setZdrojDat($detail['zdrojDat'] ?? null);
                    $company->setDatumPoslednejUpravy(!empty($detail['datumPoslednejUpravy']) ? new \DateTime($detail['datumPoslednejUpravy']) : null);

                    $em->persist($company);
                    $em->flush();

                    $logger->info('Company data saved', [
                        'userId' => $user->getId(),
                        'ico' => $ico,
                        'companyId' => $company->getId()
                    ]);
                } else {
                    $logger->warning('Company not found in RegisterUZ', ['ico' => $ico]);
                }
            } catch (\Throwable $e) {
                $logger->error('Failed to fetch or save company data', [
                    'ico' => $ico,
                    'exception' => $e->getMessage(),
                ]);
            }
        } else {
            $logger->warning('ICO missing in OTP verification request', ['phone' => $phone]);
        }

        // === 4. Повертаємо успішну відповідь ===
        return $this->json([
            'status' => JsonResponse::HTTP_OK,
            'message' => 'OTP verified successfully and company data saved.',
            'data' => null,
        ]);
    }

      if ($responseCode === 'rejected') {
          $logger->warning('OTP rejected', ['phone' => $phone, 'result' => $result]);

          return $this->json([
            'status'  => 400,
            'message' => 'validation.failed',
            'errors'  => [ 'otp' => 'errors.otp.rejected' ],
        ], 400);
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
          'status'  => 502,
          'message' => 'Unexpected verification provider response.',
          'errors'  => [ 'otp' => 'Unexpected verification provider response.' ],
      ], 502);
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
              'status'  => 400,
              'message' => 'validation.failed',
              'errors'  => [ 'phone' => 'errors.phone.required' ],
          ], 400);
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
      \Psr\Log\LoggerInterface $logger,
      ValidatorInterface $validator
  ): JsonResponse {
      $data = json_decode($request->getContent(), true);

      $phone       = $data['phone'] ?? null;
      $otp         = $data['otp'] ?? null;
      $newPassword = $data['newPassword'] ?? null;

      // 1) Перевіряємо, що всі поля передані
      if (!$phone || !$otp || !$newPassword) {
          return $this->json([
              'status' => JsonResponse::HTTP_BAD_REQUEST,
              'message' => 'Fields phone, otp and newPassword are required.',
              'errors' => [
                  'phone' => !$phone ? 'errors.phone.required' : null,
                  'otp' => !$otp ? 'errors.otp.required' : null,
                  'password' => !$newPassword ? 'errors.password.required' : null,
              ],
          ], JsonResponse::HTTP_BAD_REQUEST);
      }

      // валідація OTP формату (6 цифр)
      $otpViolations = $validator->validate($otp, [
          new Assert\Regex([
              'pattern' => '/^\d{5,6}$/',
              'message' => 'OTP must be 5 or 6 digits.',
          ]),
      ]);
      if (\count($otpViolations) > 0) {
          return $this->json([
              'status'  => 400,
              'message' => 'validation.failed',
              'errors'  => [ 'otp' => $otpViolations[0]->getMessage() ],
          ], 400);
      }

      // 2) Тепер перевірка складності пароля
      $violations = $validator->validate($newPassword, [
          new PasswordComplexity(),
      ]);
      if (\count($violations) > 0) {
          return $this->json([
              'status'  => 400,
              'message' => 'validation.failed',
              'errors'  => [
                  'password' => $violations[0]->getMessage(),
              ],
          ], 400);
      }

      // 3) Шукаємо користувача
      $user = $users->findOneBy(['phone' => $phone]);
      if (!$user) {
          // нейтральна відповідь (не видаємо існування/неіснування)
          return $this->json([
              'status' => JsonResponse::HTTP_OK,
              'message' => 'If the phone exists, the password has been reset.',
              'data' => null,
          ], JsonResponse::HTTP_OK);
      }

      // 4) Перевірка OTP через Telnyx
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
            'status'  => 400,
            'message' => 'validation.failed',
            'errors'  => [ 'otp' => 'errors.otp.rejected' ],
        ], 400);
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

  // test get entrepreneur data =========================================
  #[Route('/test-ico/{ico}', name: 'auth_test_ico', methods: ['GET'])]
  public function testIco(string $ico, CompanyLookupService $lookup): JsonResponse
  {
      $data = $lookup->getCompanyDataByIco($ico);
      return new JsonResponse($data);
  }


  // help functions =========================================

    private function conflictResponse(): JsonResponse
  {
      return $this->json([
          'status' => JsonResponse::HTTP_CONFLICT,
          'message' => $this->t->trans('validation.failed'),
          'errors' => [
              'phone' => $this->t->trans('errors.phone.exists'),
          ],
      ], JsonResponse::HTTP_CONFLICT);
  }

  private function userExists(UserRepository $users, string $phone): bool
  {
      return $users->findOneBy(['phone' => $phone]) !== null;
  }

}
