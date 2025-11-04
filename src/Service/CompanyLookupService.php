<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class CompanyLookupService
{
    public function __construct(private HttpClientInterface $client) {}

    public function getCompanyDataByIco(string $ico): ?array
    {
        $response = $this->client->request('GET', 'https://www.registeruz.sk/cruz-public/api/uctovne-jednotky', [
            'query' => [
                'zmenene-od' => '2000-01-01',
                'ico' => $ico,
            ],
        ]);

        $data = $response->toArray(false);

        file_put_contents(__DIR__.'/../../var/log/company_debug.log', "FIRST RESPONSE:\n".print_r($data, true)."\n", FILE_APPEND);

        if (empty($data['id'][0])) {
            file_put_contents(__DIR__.'/../../var/log/company_debug.log', "NO ID FOUND for IČO $ico\n", FILE_APPEND);
            return ['error' => 'No company found for given IČO'];
        }

        $id = $data['id'][0];

        $detailResponse = $this->client->request('GET', 'https://www.registeruz.sk/cruz-public/api/uctovna-jednotka', [
            'query' => ['id' => $id],
        ]);

        $details = $detailResponse->toArray(false);

        file_put_contents(__DIR__.'/../../var/log/company_debug.log', "DETAIL RESPONSE:\n".print_r($details, true)."\n", FILE_APPEND);

        return [
            'id' => $id,
            'detail' => $details,
        ];
    }

}
