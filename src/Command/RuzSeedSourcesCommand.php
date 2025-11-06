<?php
namespace App\Command;

use App\Entity\RuzDictionary;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'ruz:seed-sources',
    description: 'Insert fixed data sources (zdroj_dat) into ruz_dictionary table',
)]
class RuzSeedSourcesCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $dataSources = [
            ['code' => 'SUSR', 'name_sk' => 'Štatistický úrad Slovenskej republiky', 'name_en' => 'Statistical Office of the Slovak Republic'],
            ['code' => 'SP', 'name_sk' => 'Systém štátnej pokladnice', 'name_en' => 'State Treasury System'],
            ['code' => 'DC', 'name_sk' => 'DataCentrum', 'name_en' => 'DataCentre'],
            ['code' => 'FRSR', 'name_sk' => 'Finančné riaditeľstvo Slovenskej republiky', 'name_en' => 'Financial Directorate of the Slovak Republic'],
            ['code' => 'JUS', 'name_sk' => 'Jednotné účtovníctvo štátu', 'name_en' => 'Unified Accounting of the State'],
            ['code' => 'OVSR', 'name_sk' => 'Obchodný vestník Slovenskej republiky', 'name_en' => 'Commercial Bulletin of the Slovak Republic'],
            ['code' => 'CKS', 'name_sk' => 'Centrálny konsolidačný systém', 'name_en' => 'Central Consolidation System'],
            ['code' => 'SAM', 'name_sk' => 'Rozpočtový informačný systém pre samosprávu', 'name_en' => 'Budget Information System for Self-Government'],
        ];

        $repo = $this->em->getRepository(RuzDictionary::class);

        foreach ($dataSources as $item) {
            $existing = $repo->findOneBy(['type' => 'zdroj_dat', 'code' => $item['code']]);
            if ($existing) {
                $io->text("Skipping existing: {$item['code']}");
                continue;
            }

            $dict = new RuzDictionary();
            $dict->setType('zdroj_dat');
            $dict->setCode($item['code']);
            $dict->setNameSk($item['name_sk']);
            $dict->setNameEn($item['name_en']);

            $this->em->persist($dict);
        }

        $this->em->flush();
        $io->success('zdroj_dat records inserted or already existed.');

        return Command::SUCCESS;
    }
}
