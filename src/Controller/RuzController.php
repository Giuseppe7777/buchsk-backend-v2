<?php

namespace App\Controller;

use App\Entity\Company;
use App\Service\RuzDecodeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/ruz', name: 'ruz_')]
class RuzController extends AbstractController
{
    public function __construct(
        private RuzDecodeService $decodeService,
        private EntityManagerInterface $em,
        private \Psr\Log\LoggerInterface $logger,
    ) {}

    #[Route('/decode', name: 'decode', methods: ['GET'])]
    public function decode(Request $request): JsonResponse
    {
        $type = $request->query->get('type');
        $code = $request->query->get('code');

        // === 1. Валідація параметрів ===
        if (!$type || !$code) {
            $this->logger->warning('Missing type or code in /ruz/decode', [
                'query' => $request->query->all(),
            ]);

            return $this->json([
                'status' => 400,
                'message' => 'Missing type or code',
                'errors' => [
                    'type' => !$type ? 'errors.type.required' : null,
                    'code' => !$code ? 'errors.code.required' : null,
                ],
            ], 400);
        }

        // === 2. Виклик сервісу ===
        try {
            $decoded = $this->decodeService->decode($type, $code);
            $this->logger->info('RuzController: decoded item', [
                'type' => $type,
                'code' => $code,
                'found' => $decoded !== null,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('RuzController: decode failed', [
                'type' => $type,
                'code' => $code,
                'exception' => $e->getMessage(),
            ]);

            return $this->json([
                'status' => 500,
                'message' => 'Internal decode error',
                'errors' => ['exception' => $e->getMessage()],
            ], 500);
        }

        // === 3. Результат ===
        return $this->json($decoded ?? ['code' => $code, 'label' => null]);
    }

    #[Route('/decode-batch', name: 'decode_batch', methods: ['POST'])]
    public function decodeBatch(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $items = $payload['items'] ?? [];

        // === 1. Перевірка формату ===
        if (!is_array($items)) {
            $this->logger->warning('Invalid payload in /ruz/decode-batch', [
                'payload' => $payload,
            ]);

            return $this->json([
                'status' => 400,
                'message' => 'Invalid payload format',
                'errors' => ['items' => 'Must be an array'],
            ], 400);
        }

        // === 2. Декодування ===
        try {
            $this->logger->info('RuzController: decode-batch start', [
                'count' => count($items),
            ]);

            $decoded = $this->decodeService->decodeBatch($items);

            $this->logger->info('RuzController: decode-batch complete', [
                'decoded_count' => count($decoded),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('RuzController: decode-batch failed', [
                'exception' => $e->getMessage(),
            ]);

            return $this->json([
                'status' => 500,
                'message' => 'Internal error during batch decode',
                'errors' => ['exception' => $e->getMessage()],
            ], 500);
        }

        return $this->json($decoded);
    }

    #[Route('/company/me/decoded', name: 'company_me_decoded', methods: ['GET'])]
    public function companyMeDecoded(): JsonResponse
    {
        $user = $this->getUser();

        // звужуємо тип до вашої сутності
        if (!$user instanceof \App\Entity\User) {
            $this->logger->warning('Unauthorized access attempt to company/me/decoded');
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $repo = $this->em->getRepository(\App\Entity\Company::class);
        $company = $repo->findOneBy(['user' => $user]);

        if (!$company) {
            $this->logger->warning('Company not found for user', ['userId' => $user->getId()]);
            return $this->json(['error' => 'Company not found'], 404);
        }

        try {
            $decoded = [
                'ico' => $company->getIco(),
                'nazovUJ' => $company->getNazovUj(),
                'pravnaForma' => $this->decodeService->decode('pravna_forma', $company->getPravnaForma()),
                'skNace' => $this->decodeService->decode('sk_nace', $company->getSkNace()),
                'velkostOrganizacie' => $this->decodeService->decode('velkost_organizacie', $company->getVelkostOrganizacie()),
                'druhVlastnictva' => $this->decodeService->decode('druh_vlastnictva', $company->getDruhVlastnictva()),
                'kraj' => $this->decodeService->decode('kraj', $company->getKraj()),
                'okres' => $this->decodeService->decode('okres', $company->getOkres()),
                'sidlo' => $this->decodeService->decode('sidlo', $company->getSidlo()),
                'zdrojDat' => $this->decodeService->decode('zdroj_dat', $company->getZdrojDat()),
                'datumZalozenia' => $company->getDatumZalozenia()?->format('Y-m-d'),
                'datumPoslednejUpravy' => $company->getDatumPoslednejUpravy()?->format('Y-m-d'),
            ];

            $this->logger->info('Company decoded successfully', ['userId' => $user->getId()]);
            return $this->json($decoded);
        } catch (\Throwable $e) {
            $this->logger->error('Error decoding company', [
                'userId' => $user->getId(),
                'exception' => $e->getMessage(),
            ]);
            return $this->json(['error' => 'Internal server error'], 500);
        }
    }

    #[Route('/company/{id}/decoded', name: 'company_decoded', methods: ['GET'])]
    public function companyDecoded(int $id): JsonResponse
    {
        $repo = $this->em->getRepository(Company::class);
        $company = $repo->find($id);

        // === 1. Перевірка наявності ===
        if (!$company) {
            $this->logger->warning('Company not found in /ruz/company/decoded', ['id' => $id]);
            return $this->json([
                'status' => 404,
                'message' => 'Company not found',
            ], 404);
        }

        // === 2. Декодування полів ===
        try {
            $decoded = [
                'ico' => $company->getIco(),
                'nazovUJ' => $company->getNazovUj(),
                'pravnaForma' => $this->decodeService->decode('pravna_forma', $company->getPravnaForma()),
                'skNace' => $this->decodeService->decode('sk_nace', $company->getSkNace()),
                'velkostOrganizacie' => $this->decodeService->decode('velkost_organizacie', $company->getVelkostOrganizacie()),
                'druhVlastnictva' => $this->decodeService->decode('druh_vlastnictva', $company->getDruhVlastnictva()),
                'kraj' => $this->decodeService->decode('kraj', $company->getKraj()),
                'okres' => $this->decodeService->decode('okres', $company->getOkres()),
                'sidlo' => $this->decodeService->decode('sidlo', $company->getSidlo()),
                'zdrojDat' => $this->decodeService->decode('zdroj_dat', $company->getZdrojDat()),
                'datumZalozenia' => $company->getDatumZalozenia()?->format('Y-m-d'),
                'datumPoslednejUpravy' => $company->getDatumPoslednejUpravy()?->format('Y-m-d'),
            ];

            $this->logger->info('Company decoded successfully', [
                'company_id' => $id,
                'ico' => $company->getIco(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Company decode failed', [
                'company_id' => $id,
                'exception' => $e->getMessage(),
            ]);

            return $this->json([
                'status' => 500,
                'message' => 'Error decoding company data',
                'errors' => ['exception' => $e->getMessage()],
            ], 500);
        }

        return $this->json($decoded);
    }
}
