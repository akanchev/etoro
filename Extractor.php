<?php
require_once 'bootstrap.php';

use Dotenv\Exception\ExceptionInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Class Instrument
 */
class Extractor
{
    const INSTRUMENTS_METADATA_URL = 'https://api.etorostatic.com/sapi/instrumentsmetadata/V1.1/instruments';
    const INSTRUMENTS_INFO_URL = 'https://www.etoro.com/sapi/instrumentsinfo/instruments/';
    const COUNTRIES_URL = 'https://api.etorostatic.com/api/users/v1/countries';
    const INSTRUMENTS_GROUPS_URL = 'https://api.etorostatic.com/sapi/app-data/web-client/app-data/instruments-groups.json';

    /**
     * @return array|null
     * @throws GuzzleException
     */
    public static function extractInstrumentsMetadata()
    {
        $currentTimestamp = time();
        $client = new Client();

        try {
            $response = $client->get(self::INSTRUMENTS_METADATA_URL);
        } catch (GuzzleException $ex) {
            throw $ex;
        }

        if ($response->getStatusCode() === 200) {
            $body = $response->getBody();

            if (!empty($body)) {
                $instrumentsMetadata = json_decode($body, true);
            }
        }

        if (isset($instrumentsMetadata) && !empty($instrumentsMetadata)) {
            $connectionParams = [
                'host' => DB_HOST,
                'dbname' => DB_DATABASE,
                'user' => DB_USERNAME,
                'password' => DB_PASSWORD,
                'driver' => 'pdo_mysql'
            ];

            $conn = \Doctrine\DBAL\DriverManager::getConnection($connectionParams);
            $queryBuilder = $conn->createQueryBuilder();

            foreach ($instrumentsMetadata['InstrumentDisplayDatas'] as $instrumentMetadata) {
                $metadata = json_encode($instrumentMetadata);

                $queryBuilder
                    ->insert('instrument')
                    ->values(
                        [
                            'instrument_id' => '?',
                            'instrument_type_id' => '?',
                            'instrument_type_sub_category_id' => '?',
                            'instrument_display_name' => '?',
                            'exchange_id' => '?',
                            'symbol_full' => '?',
                            'stocks_industry_id' => '?',
                            'time_created' => '?',
                            'time_updated' => '?',
                            'metadata' => '?'
                        ]
                    )
                    ->setParameter(0, $instrumentMetadata['InstrumentID'])
                    ->setParameter(1, $instrumentMetadata['InstrumentTypeID'] ?? 0)
                    ->setParameter(2, $instrumentMetadata['InstrumentTypeSubCategoryID'] ?? 0)
                    ->setParameter(3, $instrumentMetadata['InstrumentDisplayName'])
                    ->setParameter(4, $instrumentMetadata['ExchangeID'] ?? 0)
                    ->setParameter(5, $instrumentMetadata['SymbolFull'])
                    ->setParameter(6, $instrumentMetadata['StocksIndustryID'] ?? 0)
                    ->setParameter(7, $currentTimestamp)
                    ->setParameter(8, $currentTimestamp)
                    ->setParameter(9, $metadata);

                try {
                    $queryBuilder->execute();
                } catch (Exception $ex) {
                    if ($ex->getCode() == MYSQL_ERROR_CODE_DUPLICATE_ENTRY) {
                        $queryBuilder = $conn->createQueryBuilder();
                        $queryBuilder->update('instrument')
                            ->set('metadata', '?')
                            ->set('time_updated', '?')
                            ->setParameter(0, $metadata)
                            ->setParameter(1, $currentTimestamp)
                            ->where('instrument_id = ' .  $instrumentMetadata['InstrumentID']);
                        $queryBuilder->execute();
                    } else {
                        throw $ex;
                    }
                }
            }
        }
    }

    /**
     *
     */
    public static function extractInstrumentsInfo ()
    {
        $client = new Client();

        $connectionParams = [
            'host' => DB_HOST,
            'dbname' => DB_DATABASE,
            'user' => DB_USERNAME,
            'password' => DB_PASSWORD,
            'driver' => 'pdo_mysql'
        ];

        $conn = \Doctrine\DBAL\DriverManager::getConnection($connectionParams);
        $queryBuilder = $conn->createQueryBuilder();

        $queryBuilder->select('*')
            ->from('instrument')->where('instrument_type_id = 5')->where('info = 0');

        $instruments = $queryBuilder->execute()->fetchAllAssociative();

        foreach ($instruments as $instrument) {
            try {
                $response = $client->get(self::INSTRUMENTS_INFO_URL . $instrument['instrument_id']);
            } catch (GuzzleException $ex) {
                throw $ex;
            }

            if ($response->getStatusCode() === 200) {
                $body = $response->getBody();

                if (!empty($body)) {
                    $queryBuilder = $conn->createQueryBuilder();
                    $queryBuilder->update('instrument')
                        ->set('has_info', '?')
                        ->set('info', '?')
                        ->setParameter(0, 1)
                        ->setParameter(1, $body)
                        ->where('instrument_id = ' .  $instrument['instrument_id']);
                    $queryBuilder->execute();
                }
            }
        }
    }
}

Extractor::extractInstrumentsMetadata();
Extractor::extractInstrumentsInfo();