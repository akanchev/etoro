<?php

require_once 'bootstrap.php';

Extractor::extractInstrumentsMetadata();
Extractor::extractInstrumentsInfo();
Extractor::extractInstrumentsDataTickerData();
Extractor::updateInstrumentsClosingPrices();
Extractor::updateInstrumentsRates();
Extractor::exportToJSON();
