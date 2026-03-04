<?php

namespace App\Services;

use Google\Client;
use Google\Service\Sheets;

class GoogleSheetService
{
    protected Sheets $service;

    public function __construct()
    {
        $client = new Client;
        $client->setAuthConfig(base_path('google/service-account.json'));
        $client->addScope(Sheets::SPREADSHEETS_READONLY);

        $this->service = new Sheets($client);
    }

    public function read(string $spreadsheetId, string $range): array
    {
        $response = $this->service
            ->spreadsheets_values
            ->get($spreadsheetId, $range);

        return $response->getValues();
    }
}
