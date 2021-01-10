<?php
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Dotenv\Exception\ExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Class Instrument
 */
class Extractor
{
    const INSTRUMENTS_METADATA_URL = 'https://api.etorostatic.com/sapi/instrumentsmetadata/V1.1/instruments';
    const INSTRUMENTS_INFO_URL = 'https://www.etoro.com/sapi/instrumentsinfo/instruments/';
    const INSTRUMENTS_DATA_TICKER_URL = 'https://widgets.tipranks.com/api/etoro/dataForTicker?ticker=';
    const COUNTRIES_URL = 'https://api.etorostatic.com/api/users/v1/countries';
    const INSTRUMENTS_GROUPS_URL = 'https://api.etorostatic.com/sapi/app-data/web-client/app-data/instruments-groups.json';
    const INSTRUMENTS_CLOSING_PRICES_URL = 'https://api.etorostatic.com/sapi/candles/closingprices.json';
    const INSTRUMENTS_DATA_FILTER_RATES_URL = 'https://www.etoro.com/sapi/trade-real/instruments?InstrumentDataFilters=Rates';

    /**
     * @var Connection
     */
    protected static $connection;

    /**
     * @return Connection
     * @throws \Doctrine\DBAL\Exception
     */
    protected static function getConnection(): Connection
    {
        if (!isset(self::$connection)) {
            $connectionParams = [
                'host'      => DB_HOST,
                'dbname'    => DB_DATABASE,
                'user'      => DB_USERNAME,
                'password'  => DB_PASSWORD,
                'driver'    => 'pdo_mysql'
            ];

            self::$connection = DriverManager::getConnection($connectionParams);
        }

        return self::$connection;
    }

    /**
     * @param string $url
     * @return array|null
     */
    protected static function extractJSON(string $url): ?array
    {
        $client = new Client();

        echo 'DOWNLOAD: ' . $url . PHP_EOL;

        try {
            $response = $client->get($url);
        } catch (GuzzleException $ex) {
            $response = null;
        }

        if ($response && $response->getStatusCode() === 200) {
            $body = $response->getBody();

            if (!empty($body)) {
                $result = json_decode($body, true);
            }
        }

        return $result ?? null;
    }

    /**
     * @param array $urls
     * @param int $maxUrlsInBatch
     * @param bool $resultJsonDecode
     * @return array|null
     */
    protected static function extractUrls(array $urls, int $maxUrlsInBatch = 10, bool $resultJsonDecode = false): ?array
    {
        $results = [];
        $urlBatches = [];
        $urlBatch = [];

        foreach ($urls as $key =>  $url) {
            if (count($urlBatch) >= $maxUrlsInBatch) {
                $urlBatches[] = $urlBatch;
                $urlBatch = [];
            }

            $urlBatch[$key] = $url;
        }

        if (!empty($urlBatch)) {
            $urlBatches[] = $urlBatch;
        }

        if (!empty($urlBatches)) {
            foreach ($urlBatches as $urlBatch) {
                $client = new Client();
                $promises = [];

                foreach ($urlBatch as $key => $url) {
                    $promises[$key] = $client->getAsync($url);

                    echo 'Download: ' . $url . PHP_EOL;
                }

                // Wait for the requests to complete, even if some of them fail
                $responses = Promise\Utils::settle($promises)->wait();

                if ($responses) {
                    foreach ($responses as $key => $response) {
                        $result = null;

                        if ($response['value']->getStatusCode() === 200) {
                            $body = $response['value']->getBody();

                            if (!empty($body)) {
                                $result = (string) $body;

                                if (!empty($result) && $resultJsonDecode) {
                                    $result = json_decode($result, true);
                                }
                            }
                        }

                        $results[$key] = $result;
                    }
                }
            }
        }

        return $results;
    }

    /**
     * @param array $record
     */
    protected static function qualifyCSVColumns(array &$record): void
    {
        foreach ($record as &$value) {
            $value = '"' . preg_replace('/\"/', '""', $value) . '"';
        }

        unset ($value);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public static function exportToJSON(): void
    {
        $records = self::generateRecords();

        if (!empty($records)) {
            $fp = fopen('/home/alex/experiments/experiment-app/src/assets/data.json', 'w');

            fwrite($fp, json_encode($records));

            fclose($fp);
        }
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public static function exportToCSV(): void
    {
        $records = self::generateRecords();

        if (!empty($records)) {
            $fp = fopen('data.csv', 'w');
            // put UTF-8 BOM
            fwrite($fp, pack("CCC", 0xEF, 0xBB, 0xBF));

            foreach ($records as $key => $record) {
                // set headers
                if ($key === 0) {
                    $csvHeader = array_keys($records[$key]);

                    self::qualifyCSVColumns($csvHeader);

                    fwrite($fp, implode(',', $csvHeader) . PHP_EOL);
                }

                $values = [];

                foreach ($record as $item) {
                    $values[] = $item['value'];
                }

                self::qualifyCSVColumns($values);

                fwrite($fp, implode(',', array_values($values)) . PHP_EOL);
            }

            fclose($fp);
        }
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public static function extractInstrumentsMetadata(): void
    {
        $data = self::extractJSON(self::INSTRUMENTS_METADATA_URL);

        if (!empty($data)) {
            $queryBuilder = self::getConnection()->createQueryBuilder();

            $currentTimestamp = time();

            foreach ($data['InstrumentDisplayDatas'] as $instrumentMetadata) {
                $metadata = json_encode($instrumentMetadata);

                try {
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
                        ->setParameter(9, $metadata)
                        ->execute();
                } catch (Exception $ex) {
                    if ($ex->getCode() == MYSQL_ERROR_CODE_DUPLICATE_ENTRY) {
                        self::getConnection()
                            ->createQueryBuilder()
                            ->update('instrument')
                            ->set('metadata', '?')
                            ->set('time_updated', '?')
                            ->setParameter(0, $metadata)
                            ->setParameter(1, $currentTimestamp)
                            ->where('instrument_id = ' .  $instrumentMetadata['InstrumentID'])
                            ->execute();
                    } else {
                        throw $ex;
                    }
                }
            }
        }
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public static function extractInstrumentsDataTickerData(): void
    {
        $instruments = self::getConnection()
            ->createQueryBuilder()
            ->select('symbol_full, instrument_id')
            ->from('instrument')
            ->where('instrument_type_id = 5')
            ->execute()
            ->fetchAllAssociative();

        if ($instruments) {
            $urls = [];

            foreach ($instruments as $instrument) {
                $url = self::INSTRUMENTS_DATA_TICKER_URL . $instrument['symbol_full'];
                $urls[$instrument['instrument_id']] = $url;
            }

            $results = self::extractUrls($urls, 5, false);

            if (!empty($results)) {
                foreach ($results as $instrumentId => $result) {
                    self::getConnection()
                        ->createQueryBuilder()
                        ->update('instrument')
                        ->set('data_ticker', '?')
                        ->setParameter(0, $result)
                        ->where('instrument_id = ' .  $instrumentId)
                        ->execute();
                }
            }
        }
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public static function updateInstrumentsClosingPrices():void
    {
        $data = self::extractJSON(self::INSTRUMENTS_CLOSING_PRICES_URL);

        if (!empty($data)) {
            $instrumentsClosingPrices = [];

            foreach ($data as $instrumentsClosingPrice) {
                $instrumentsClosingPrices[$instrumentsClosingPrice['InstrumentId']] = $instrumentsClosingPrice;
            }

            $instruments = self::getConnection()
                ->createQueryBuilder()
                ->select('*')
                ->from('instrument')
                ->where('instrument_type_id = 5')
                ->execute()
                ->fetchAllAssociative();

            if ($instruments) {
                foreach ($instruments as $instrument) {
                    $closingPrice = $instrumentsClosingPrices[$instrument['instrument_id']] ?? [];

                    self::getConnection()
                        ->createQueryBuilder()
                        ->update('instrument')
                        ->set('closing_prices', '?')
                        ->setParameter(0, json_encode($closingPrice))
                        ->where('instrument_id = ' .  $instrument['instrument_id'])
                        ->execute();
                }
            }
        }
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public static function updateInstrumentsRates():void
    {
        $data = self::extractJSON(self::INSTRUMENTS_DATA_FILTER_RATES_URL);

        if (!empty($data)) {
            $instrumentsClosingPrices = [];

            foreach ($data["Rates"] as $instrumentsClosingPrice) {
                $instrumentsClosingPrices[$instrumentsClosingPrice['InstrumentID']] = $instrumentsClosingPrice;
            }

            $instruments = self::getConnection()
                ->createQueryBuilder()
                ->select('*')
                ->from('instrument')
                ->where('instrument_type_id = 5')
                ->execute()
                ->fetchAllAssociative();

            if ($instruments) {
                foreach ($instruments as $instrument) {
                    $closingPrice = $instrumentsClosingPrices[$instrument['instrument_id']] ?? [];

                    self::getConnection()
                        ->createQueryBuilder()
                        ->update('instrument')
                        ->set('rates', '?')
                        ->setParameter(0, json_encode($closingPrice))
                        ->where('instrument_id = ' .  $instrument['instrument_id'])
                        ->execute();
                }
            }
        }
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public static function extractInstrumentsInfo(): void
    {
        $instruments = self::getConnection()
            ->createQueryBuilder()
            ->select('instrument_id')
            ->from('instrument')
            ->where('instrument_type_id = 5')
            ->execute()
            ->fetchAllAssociative();

        if ($instruments) {
            $urls = [];

            foreach ($instruments as $instrument) {
                $url = self::INSTRUMENTS_INFO_URL . $instrument['instrument_id'];
                $urls[$instrument['instrument_id']] = $url;
            }

            $results = self::extractUrls($urls, 5, false);

            if (!empty($results)) {
                foreach ($results as $instrumentId => $result) {
                    self::getConnection()
                        ->createQueryBuilder()
                        ->update('instrument')
                        ->set('info', '?')
                        ->setParameter(0, $result)
                        ->where('instrument_id = ' .  $instrumentId)
                        ->execute();
                }
            }
        }
    }

    /**
     * @return array
     * @throws \Doctrine\DBAL\Exception
     */
    public static function generateRecords()
    {
        $rows = [];

        $instruments = self::getConnection()
            ->createQueryBuilder()
            ->select('*')
            ->from('instrument')
            ->where('instrument_type_id = 5')
            ->execute()
            ->fetchAllAssociative();

        if ($instruments) {
            foreach ($instruments as $instrument) {
                $instrument['curl_metadata']         = json_decode($instrument['metadata'], true) ?? [];
                $instrument['curl_info']             = json_decode($instrument['info'], true) ?? [];
                $instrument['curl_data_ticker']      = json_decode($instrument['data_ticker'], true) ?? [];
                $instrument['curl_closing_prices']   = json_decode($instrument['closing_prices'], true) ?? [];
                $instrument['curl_rates']            = json_decode($instrument['rates'], true) ?? [];

                if (isset($instrument['curl_metadata']['Images']) && !empty($instrument['curl_metadata']['Images'])) {
                    foreach ($instrument['curl_metadata']['Images'] as $image) {
                        if (strpos($image['Uri'], "http") === 0) {
                            $logo = $image['Uri'];

                            if ($image['width'] === 35) {
                                break;
                            }
                        }
                    }
                }

                $instrument['curl_metadata']['logo']                    = $logo ?? '';
                $instrument['curl_rates']['last_execution_price']       = (float) ($instrument['curl_rates']['LastExecution'] ?? 0);
                $instrument['curl_info']['last_close_price']            = (float) ($instrument['curl_info']['lastClose-TTM'] ?? 0);
                $instrument['curl_info']['high_last_52_weeks_price']    = (float) ($instrument['curl_info']['highPriceLast52Weeks-TTM'] ?? 0);
                $instrument['curl_info']['low_last_52_weeks_price']     = (float) ($instrument['curl_info']['lowPriceLast52Weeks-TTM'] ?? 0);

                $row = [
                    'id' => [
                        'name'          => 'ID',
                        'value'         => $instrument['id'],
                        'order'         => 1,
                        'status'        => 1,
                        'cellRenderer' => 'agGroupCellRenderer',
                        'filter'    => 'agNumberColumnFilter',
                        'width'          => 25,
                    ],
                    'logo' => [
                        'name'      => 'Logo',
                        'value'     => $logo ?? '',
                        'order'     => 1,
                        'status'    => 1,
                        'cellRenderer' => 'logoCellRenderer',
                        'width'          => 47,
                        'sortable'          => false,
                        'resizable'         => true,
                        'floatingFilter'    => false,
                        'filter'            => false
                    ],
                    'symbol_full' => [
                        'name'      => 'Symbol',
                        'value'     => $instrument['symbol_full'] ?? '',
                        'status'    => 0,
                        'order'     => 1,
                        'resizable'         => false,
                        'width'          => 45,
                    ],
                    'company_name' => [
                        'name'      => 'Company Name',
                        'value'     => $instrument['curl_info']['companyName-TTM'] ?? '',
                        'status'    => 1,
                        'order'     => 1,
                        'cellRenderer' => 'companyNameCellRenderer',
                    ],
                    'instrument_id' => [
                        'name'      => 'Instrument ID',
                        'value'     => $instrument['instrument_id'] ?? '',
                        'order'     => 1,
                        'status'    => 0,
                        'filter'    => 'agNumberColumnFilter'
                    ],
                    'instrument_type_id' => [
                        'name'      => 'Instrument Type ID',
                        'value'     => $instrument['instrument_type_id'] ?? '',
                        'order'     => 1,
                        'status'    => 0,
                    ],
                    'instrument_type_sub_category_id' => [
                        'name'      => 'Instrument Sub Category ID',
                        'value'     => $instrument['instrument_type_sub_category_id'] ?? '',
                        'order'     => 1,
                        'status'    => 0,
                    ],
                    'sector_name' => [
                        'name'      => 'Sector Name',
                        'value'     => $instrument['curl_info']['sectorName-TTM'] ?? $instrument['curl_info']['sector-TTM'] ?? '',
                        'order'     => 1,
                        'status'    => 1,
                        'width'    => 150,
                    ],
                    'industry_name' => [
                        'name'      => 'Industry Name',
                        'value'     => $instrument['curl_info']['industryName-TTM'] ?? $instrument['curl_info']['industry-TTM'] ?? '',
                        'order'     => 1,
                        'status'    => 1,
                        'width'    => 150,
                    ],
                    'short_bio' => [
                        'name'      => 'Short Bio',
                        'value'     => $instrument['curl_info']['shortBio-en-us'] ?? '',
                        'order'     => 1,
                        'status'    => 0,
                    ],
                    'long_bio' => [
                        'name'      => 'Long Bio',
                        'value'     => $instrument['curl_info']['longBio-en-us'] ?? '',
                        'order'     => 1,
                        'status'    => 0,
                    ],
                    'next_earning_date' => [
                        'name'      => 'Next Earning Date',
                        'value'     => isset($instrument['curl_info']['nextEarningDate']) ? date("Y-m-d", strtotime($instrument['curl_info']['nextEarningDate'])) : '',
                        'order'     => 1,
                        'status'    => 0,
                        'width'    => 100,
                    ],
                    'company_website' => [
                        'name'      => 'Company Website',
                        'value'     => $instrument['curl_info']['website-TTM'] ?? '',
                        'status'    => 0,
                        'order'     => 1,
                    ],
                    'price_source' => [
                        'name'      => 'Price Source',
                        'value'     => $instrument['curl_metadata']['PriceSource'] ?? '',
                        'status'    => 0,
                        'order'     => 1,
                    ],
                    'dividend_ex_date' => [
                        'name'      => 'Dividend Ex Date',
                        'value'     => isset($instrument['curl_info']['dividendExDate-TTM']) ? date("Y-m-d", strtotime($instrument['curl_info']['dividendExDate-TTM'])) : '',
                        'status'    => 0,
                        'order'     => 1,
                    ],
                    'companyFoundedDate-TTM' => [
                        'name'      => 'Price Source',
                        'value'     => $instrument['curl_info']['companyFoundedDate-TTM'] ?? '',
                        'status'    => 0,
                        'order'     => 1,
                    ],
                    'last_close_price' => [
                        'name'      => 'Last Close Price',
                        'value'     => $instrument['curl_info']['lastClose-TTM'] ?? '',
                        'status'    => 1,
                        'order'     => 1,
                        'filter'    => 'agNumberColumnFilter'
                    ],
                    'official_closing_price' => [
                        'name'      => 'Official Close Price',
                        'value'     => $instrument['curl_closing_prices']['OfficialClosingPrice'] ?? '',
                        'status'    => 1,
                        'order'     => 1,
                        'filter'    => 'agNumberColumnFilter'
                    ],
                    'rates_closing_price' => [
                        'name'      => 'Rates Last Execution Price',
                        'value'     => $instrument['curl_rates']['LastExecution'] ?? '',
                        'status'    => 1,
                        'order'     => 1,
                        'filter'    => 'agNumberColumnFilter'
                    ],
                    'last_low_price' => [
                        'name'      => 'last Low Price',
                        'value'     => $instrument['curl_info']['lastLow-TTM'] ?? '',
                        'status'    => 0,
                        'order'     => 1,
                        'width'    => 100,
                        'filter'    => 'agNumberColumnFilter'
                    ],
                    'last_high_price' => [
                        'name'      => 'last High Price',
                        'value'     => $instrument['curl_info']['lastHigh-TTM'] ?? '',
                        'status'    => 0,
                        'order'     => 1,
                        'filter'    => 'agNumberColumnFilter'
                    ],
                    'dividend_rate' => [
                        'name'      => 'Dividend Rate',
                        'value'     => $instrument['curl_info']['dividendRate-TTM'] ?? '',
                        'status'    => 0,
                        'order'     => 1,
                    ],
                    'daily_price_change' => [
                        'name'      => 'Daily Price Change',
                        'value'     => $instrument['curl_info']['dailyPriceChange'] ?? '',
                        'status'    => 1,
                        'order'     => 1,
                    ],
                    'low_rice_ytd' => [
                        'name'      => 'low Price YTD',
                        'value'     => $instrument['curl_info']['lowPriceYTD-TTM'] ?? '',
                        'status'    => 1,
                        'order'     => 1,
                    ],
                    'low_price_mtd' => [
                        'name'      => 'low Price MTD',
                        'value'     => $instrument['curl_info']['lowPriceMTD-TTM'] ?? '',
                        'status'    => 1,
                        'order'     => 1,
                    ],
                    'iso_country_code' => [
                        'name'      => 'ISO Country Code',
                        'value'     => $instrument['curl_info']['isoCountryCode-TTM'] ?? '',
                        'status'    => 0,
                        'order'     => 1,
                    ],
                    'date_of_52_week_low' => [
                        'name'      => 'Date 52 week low',
                        'value'     => $instrument['curl_info']['dateOf52WeekLow-TTM'] ?? '',
                        'status'    => 0,
                        'order'     => 1,
                    ],
                    'instrument_class' => [
                        'name'      => 'Instrument Class',
                        'value'     => $instrument['curl_info']['instrumentClass-TTM'] ?? '',
                        'status'    => 0,
                        'order'     => 1,
                    ],
                    'dividend_rate_percentage' => [
                        'name'      => 'Dividend Rate Percentage',
                        'value'     => $instrument['curl_info']['dividendRatePercentage'] ?? '',
                        'status'    => 0,
                        'order'     => 1,
                    ],
                    'low_price_last_13_weeks' => [
                        'name'      => 'Low Price Last 13 Weeks',
                        'value'     => $instrument['curl_info']['lowPriceLast13Weeks-TTM'] ?? '',
                        'status'    => 0,
                        'order'     => 1,
                    ],
                    'low_price_last_26_weeks' => [
                        'name'      => 'Low Price Last 26 Weeks',
                        'value'     => $instrument['curl_info']['lowPriceLast26Weeks-TTM'] ?? '',
                        'status'    => 0,
                        'order'     => 1,
                    ],
                    'low_price_last_52_weeks' => [
                        'name'      => 'Low Price Last 52 Weeks',
                        'value'     => $instrument['curl_info']['lowPriceLast52Weeks-TTM'] ?? '',
                        'status'    => 1,
                        'order'     => 1,
                    ],
                    'low_price_last_3_months' => [
                        'name'      => 'Low Price Last 3 Months',
                        'value'     => $instrument['curl_info']['lowPriceLast3Months-TTM'] ?? '',
                        'status'    => 0,
                        'order'     => 1,
                    ],
                    'low_price_last_6_months' => [
                        'name'      => 'Low Price Last 6 Months',
                        'value'     => $instrument['curl_info']['lowPriceLast6Months-TTM'] ?? '',
                        'status'    => 0,
                        'order'     => 1,
                    ],
                    'low_price_last_9_months' => [
                        'name'      => 'Low Price Last 9 Months',
                        'value'     => $instrument['curl_info']['lowPriceLast9Months-TTM'] ?? '',
                        'status'    => 0,
                        'order'     => 1,
                    ],

                    'high_price_last_13_weeks' => [
                        'name'      => 'High Price Last 13 Weeks',
                        'value'     => $instrument['curl_info']['highPriceLast13Weeks-TTM'] ?? '',
                        'status'    => 0,
                        'order'     => 1,
                    ],
                    'high_price_last_26_weeks' => [
                        'name'      => 'High Price Last 26 Weeks',
                        'value'     => $instrument['curl_info']['highPriceLast26Weeks-TTM'] ?? '',
                        'status'    => 0,
                        'order'     => 1,
                    ],
                    'high_price_last_52_weeks' => [
                        'name'      => 'High Price Last 52 Weeks',
                        'value'     => $instrument['curl_info']['highPriceLast52Weeks-TTM'] ?? '',
                        'status'    => 1,
                        'order'     => 1,
                    ],
                    'high_price_last_3_months' => [
                        'name'      => 'High Price Last 3 Months',
                        'value'     => $instrument['curl_info']['highPriceLast3Months-TTM'] ?? '',
                        'status'    => 0,
                        'order'     => 1,
                    ],
                    'high_price_last_6_months' => [
                        'name'      => 'High Price Last 6 Months',
                        'value'     => $instrument['curl_info']['highPriceLast6Months-TTM'] ?? '',
                        'status'    => 0,
                        'order'     => 1,
                    ],
                    'high_price_last_9_months' => [
                        'name'      => 'High Price Last 9 Months',
                        'value'     => $instrument['curl_info']['highPriceLast9Months-TTM'] ?? '',
                        'status'    => 0,
                        'order'     => 1,
                    ],
                    '52_weeks_percentage_difference_high' => [
                        'name'      => 'Diff 52 High',
                        'value'     => (($instrument['curl_info']['high_last_52_weeks_price'] / $instrument['curl_rates']['last_execution_price']) - 1) * 100,
                        'status'    => 1,
                        'order'     => 1,
                        'filter'    => 'agNumberColumnFilter'
                    ],
                    '52_weeks_percentage_difference_low' => [
                        'name'      => 'Diff 52 Low',
                        'value'     => (($instrument['curl_info']['low_last_52_weeks_price'] / $instrument['curl_rates']['last_execution_price']) - 1) * 100,
                        'status'    => 1,
                        'order'     => 1,
                        'filter'    => 'agNumberColumnFilter'
                    ],
                    '12_months_expected_low' => [
                        'name'      => '12M Expected Low',
                        'value'     => $instrument['curl_data_ticker']["overview"]["ptConsensus"][0]["low"] ?? 0,
                        'status'    => 1,
                        'order'     => 1,
                        'filter'    => 'agNumberColumnFilter'
                    ],
                    '12_months_expected_high' => [
                        'name'      => '12M Expected High',
                        'value'     => $instrument['curl_data_ticker']["overview"]["ptConsensus"][0]["high"] ?? 0,
                        'status'    => 1,
                        'order'     => 1,
                        'filter'    => 'agNumberColumnFilter'
                    ],

                    '12_months_expected_low_difference' => [
                        'name'      => 'Expected Low Diff',
                        'value'     => ((int) round(((int)($instrument['curl_data_ticker']["overview"]["ptConsensus"][0]["low"] ?? 0) / (int)($instrument['curl_info']['lastClose-TTM'] ?? 0) - 1) * 100)),
                        'status'    => 1,
                        'order'     => 1,
                        'filter'    => 'agNumberColumnFilter'
                    ],
                    '12_months_expected_high_difference' => [
                        'name'      => 'Expected High Diff',
                        'value'     => ((int) round(((int)($instrument['curl_data_ticker']["overview"]["ptConsensus"][0]["high"] ?? 0) / (int)($instrument['curl_info']['lastClose-TTM'] ?? 0) - 1) * 100)),
                        'status'    => 1,
                        'order'     => 1,
                        'filter'    => 'agNumberColumnFilter'
                    ],
                    'dateOf52WeekLow-Annual' => [
                        'name'      => 'Expected High Diff',
                        'value'     => ((int) round(((int)($instrument['curl_data_ticker']["overview"]["ptConsensus"][0]["high"] ?? 0) / (int)($instrument['curl_info']['lastClose-TTM'] ?? 0) - 1) * 100)),
                        'status'    => 1,
                        'order'     => 1,
                        'filter'    => 'agNumberColumnFilter'
                    ],
                    '52_week_low_date' => [
                        'name'      => '52 W.L.D',
                        'value'     => isset($instrument['curl_info']['dateOf52WeekLow-Annual']) ? date("Y-m-d", strtotime($instrument['curl_info']['dateOf52WeekLow-Annual'])) : '',
                        'status'    => 1,
                        'order'     => 1,
                    ],
                    '52_week_high_date' => [
                        'name'      => '52 W.H.D',
                        'value'     => isset($instrument['curl_info']['dateOf52WeekHigh-Annual']) ? date("Y-m-d", strtotime($instrument['curl_info']['dateOf52WeekHigh-Annual'])) : '',
                        'status'    => 1,
                        'order'     => 1,
                    ],
                ];

                $rows[] = $row;
            }
        }

        return $rows;
    }
}
