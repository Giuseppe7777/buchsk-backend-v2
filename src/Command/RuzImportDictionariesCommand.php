<?php

namespace App\Command;

use App\Entity\RuzDictionary;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'ruz:import-dictionaries',
    description: 'Imports all RUZ classification dictionaries (číselníky) into ruz_dictionary table',
)]
class RuzImportDictionariesCommand extends Command
{
    private array $sources = [
        'pravna_forma' => 'https://www.registeruz.sk/cruz-public/api/pravne-formy',
        'sk_nace' => 'https://www.registeruz.sk/cruz-public/api/sk-nace',
        'druh_vlastnictva' => 'https://www.registeruz.sk/cruz-public/api/druhy-vlastnictva',
        'velkost_organizacie' => 'https://www.registeruz.sk/cruz-public/api/velkosti-organizacie',
        'kraj' => 'https://www.registeruz.sk/cruz-public/api/kraje',
        'okres' => 'https://www.registeruz.sk/cruz-public/api/okresy',
        'sidlo' => 'https://www.registeruz.sk/cruz-public/api/sidla',
    ];

    public function __construct(
        private HttpClientInterface $client,
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $repo = $this->em->getRepository(RuzDictionary::class);

        foreach ($this->sources as $type => $url) {
            $io->section("Fetching $type ...");

            try {
                $response = $this->client->request('GET', $url, ['timeout' => 20]);
                $data = $response->toArray();
            } catch (\Throwable $e) {
                $io->error("Failed to fetch $type: " . $e->getMessage());
                continue;
            }

            // вибірка потрібного поля
            $items = $data['klasifikacie'] ?? $data['lokacie'] ?? [];

            $count = 0;
            foreach ($items as $item) {
                $code = $item['kod'] ?? null;
                $nameSk = $item['nazov']['sk'] ?? null;
                $nameEn = $item['nazov']['en'] ?? null;

                if (!$code || !$nameSk) {
                    continue;
                }

                $existing = $repo->findOneBy(['type' => $type, 'code' => $code]);

                if ($existing) {
                    // оновлення (якщо назва змінилася)
                    if ($existing->getNameSk() !== $nameSk || $existing->getNameEn() !== $nameEn) {
                        $existing->setNameSk($nameSk);
                        $existing->setNameEn($nameEn);
                        $count++;
                    }
                } else {
                    // новий запис
                    $dict = new RuzDictionary();
                    $dict->setType($type);
                    $dict->setCode($code);
                    $dict->setNameSk($nameSk);
                    $dict->setNameEn($nameEn);
                    $this->em->persist($dict);
                    $count++;
                }
            }

            $this->em->flush();
            $io->success("$type: $count records inserted/updated.");
        }

        $io->success('✅ All dictionaries imported successfully!');
        return Command::SUCCESS;
    }
}
