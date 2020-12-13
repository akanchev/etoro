<?php

/**
 * Class Instrument
 */
class Instrument
{
    const INSTRUMENTS_METADATA_URL = 'https://api.etorostatic.com/sapi/instrumentsmetadata/V1.1/instruments';
    const INSTRUMENTS_INFO_URL = 'https://www.etoro.com/sapi/instrumentsinfo/instruments/1405';

    /**
     *
     */
    public function extractInstrumentsMetadata ()
    {

    }

    /**
     *
     */
    public function extractInstrumentsInfo ()
    {

    }

    /**
     * @param string $url
     * @return string|null
     */
    private function getPageContent (string $url): ?string
    {
        $ch = curl_init();

        if($ch === false) {
            die('Failed to create curl object');
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

        $data = curl_exec($ch);

        curl_close($ch);

        return $data;
    }
}