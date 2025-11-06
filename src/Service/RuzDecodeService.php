<?php

namespace App\Service;

use App\Entity\RuzDictionary;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class RuzDecodeService
{
    private const CACHE_TTL = 21600; // 6 годин у секундах

    public function __construct(
        private EntityManagerInterface $em,
        private CacheInterface $cache,           // Symfony Cache (автовайриться, якщо налаштований)
        private ?LoggerInterface $logger = null, // логер не обов’язковий, але якщо є — логуємо все
    ) {}

    /**
     * Декодує один елемент словника.
     * Повертає null, якщо не знайдено або помилка.
     */
    public function decode(string $type, ?string $code): ?array
    {
        $type = trim(strtolower($type));
        $code = $code !== null ? trim((string)$code) : null;

        if ($code === null || $code === '') {
            $this->logger?->warning('RuzDecodeService: empty code', ['type' => $type]);
            return null;
        }

        $cacheKey = sprintf('ruz.decode.%s.%s', $type, $code);

        try {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($type, $code) {
                $item->expiresAfter(self::CACHE_TTL);

                $repo = $this->em->getRepository(RuzDictionary::class);
                /** @var RuzDictionary|null $row */
                $row = $repo->findOneBy(['type' => $type, 'code' => $code]);

                if (!$row) {
                    $this->logger?->notice('RuzDecodeService: not found', [
                        'type' => $type,
                        'code' => $code,
                    ]);
                    return null;
                }

                $this->logger?->info('RuzDecodeService: decoded', [
                    'type' => $type,
                    'code' => $code,
                    'name_sk' => $row->getNameSk(),
                    'name_en' => $row->getNameEn(),
                ]);

                return [
                    'code'  => $row->getCode(),
                    'label' => [
                        'sk' => $row->getNameSk(),
                        'en' => $row->getNameEn(),
                    ],
                ];
            });
        } catch (\Throwable $e) {
            $this->logger?->error('RuzDecodeService: exception', [
                'type' => $type,
                'code' => $code,
                'exception' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Декодує масив елементів.
     * Вхід: [['type'=>'kraj','code'=>'SK010'], ...]
     * Вихід: у тій самій послідовності масив з об'єктами або null.
     */
    public function decodeBatch(array $items): array
    {
        $this->logger?->info('RuzDecodeService: batch decode start', ['count' => count($items)]);

        $out = [];
        foreach ($items as $it) {
            $type = $it['type'] ?? '';
            $code = $it['code'] ?? null;

            try {
                $result = $this->decode((string)$type, $code);
                $out[] = $result;
            } catch (\Throwable $e) {
                $this->logger?->error('RuzDecodeService: batch item failed', [
                    'type' => $type,
                    'code' => $code,
                    'exception' => $e->getMessage(),
                ]);
                $out[] = null;
            }
        }

        $this->logger?->info('RuzDecodeService: batch decode complete', ['decoded' => count($out)]);
        return $out;
    }
}
