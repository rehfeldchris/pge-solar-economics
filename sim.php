<?php

require 'csv/CsvReader.php';



function toProcessedRow($row) {
    $format = 'Y-m-d H:i';
    $row['dtStart'] = DateTime::createFromFormat($format, $row['DATE'] . ' ' . $row['START TIME']);
    $row['dtEnd'] = DateTime::createFromFormat($format, $row['DATE'] . ' ' . $row['END TIME']);
    $row['price'] = substr($row['COST'], 1);
    $row['priceInfo'] = computePriceInfo($row['dtStart'], $row['USAGE']);
    return $row;
}


function computePriceInfo(DateTimeInterface $time, $kwh) {
    global $kwhSumPerMonth;
    $month = $time->format('M');
    if (empty($kwhSumPerMonth[$month])) {
        $kwhSumPerMonth[$month] = 0;
    }

    if ($kwhSumPerMonth[$month] > 290) {
        $t1Units = 0;
        $t2Units = $kwh;
    } else {
        $t1Units = $kwh;
        $t2Units = 0;
    }

    $t1Units = adjustForSolar($time, $t1Units);
    $t2Units = adjustForSolar($time, $t2Units);
    $t1PriceInfo = computePriceInfoT1($time, $t1Units);
    $t2PriceInfo = computePriceInfoT2($time, $t2Units);
    $totalCost = $t1PriceInfo['cost'] + $t2PriceInfo['cost'];
    $solarAdjustedKwh = $t1Units + $t2Units;

    $kwhSumPerMonth[$month] += $solarAdjustedKwh;

    return compact('t1PriceInfo', 't2PriceInfo', 'totalCost', 'kwh', 'solarAdjustedKwh');
}

function computePriceInfoT1(DateTimeInterface $time, $kwh) {
    $hour = $time->format('H');
    $dayOfWeek = $time->format('w');
    if ($dayOfWeek == 0 || $dayOfWeek == 6) {
        $cost = $kwh * 0.20;
        $type = 'off-peak';
    } else if ($hour >= 13 && $hour <= 19) {
        $cost = $kwh * 0.54;
        $type = 'peak';
    } else if ($hour >= 10 && $hour <= 21) {
        $cost = $kwh * 0.32;
        $type = 'part-peak';
    } else {
        $cost = $kwh * 0.20;
        $type = 'off-peak';
    }

    $tier = 1;

    return compact('type', 'cost', 'tier', 'kwh');
}

function computePriceInfoT2(DateTimeInterface $time, $kwh) {
    $hour = $time->format('H');
    $dayOfWeek = $time->format('w');
    if ($dayOfWeek == 0 || $dayOfWeek == 6) {
        $cost = $kwh * 0.28;
        $type = 'off-peak';
    } else if ($hour >= 13 && $hour <= 19) {
        $cost = $kwh * 0.62;
        $type = 'peak';
    } else if ($hour >= 10 && $hour <= 21) {
        $cost = $kwh * 0.38;
        $type = 'part-peak';
    } else {
        $cost = $kwh * 0.28;
        $type = 'off-peak';
    }

    $tier = 2;

    return compact('type', 'cost', 'tier', 'kwh');
}

function adjustForSolar(DateTimeInterface $time, $kwh) {
//    return $kwh;

    $hour = $time->format('H');
    $info = getDaylightInfo($time);

    $rise = $info['sunrise']->format('H');
    $set = $info['sunset']->format('H');

    // The first 2 hours, we don't want to count - sun is too weak.
    // So we add/sub.
    if ($hour >= $rise + 5 && $hour <= $set - 5) {
        // Peak sun.
        $adjKwm = $kwh - 0.25;
    } else if ($hour >= $rise + 3 && $hour <= $set - 3) {
        // Medium sun.
        $adjKwm = $kwh - 0.15;
    } else {
        // No sun.
        $adjKwm = $kwh;
    }

    // Don't go negative - no net metering.
    return max(0, $adjKwm);
}

function getDaylightInfo(DateTimeInterface $time) {
    $rise = date_sunrise($time->getTimestamp(), SUNFUNCS_RET_TIMESTAMP, $GLOBALS['gpsLat'], $GLOBALS['gpsLon'], 90);
    $set = date_sunset($time->getTimestamp(), SUNFUNCS_RET_TIMESTAMP, $GLOBALS['gpsLat'], $GLOBALS['gpsLon'], 90);
    $sunrise = new DateTime('@' . $rise);
    $sunrise->setTimezone($time->getTimezone());
    $sunset = new DateTime('@' . $set);
    $sunset->setTimezone($time->getTimezone());
    return compact('sunrise', 'sunset');
}


function dateTimeFromTimestamp() {

}

function runSim($rows, $modelSolar) {
    $processedRows = array_map('toProcessedRow', $rows);
    $totals = [];
    foreach ($processedRows as $row) {
        $month = $row['dtStart']->format('M');

        if (empty($totals[$month])) {
            $totals[$month]['$t1'] = 0;
            $totals[$month]['$t2'] = 0;
            $totals[$month]['$both'] = 0;
            $totals[$month]['kwh'] = 0;
            $totals[$month]['solarAdjustedKwh'] = 0;
        }
        $totals[$month]['$t1'] += $row['priceInfo']['t1PriceInfo']['cost'];
        $totals[$month]['$t2'] += $row['priceInfo']['t2PriceInfo']['cost'];
        $totals[$month]['$both'] += $row['priceInfo']['totalCost'];
        $totals[$month]['kwh'] += $row['priceInfo']['kwh'];
        $totals[$month]['solarAdjustedKwh'] += $row['priceInfo']['solarAdjustedKwh'];
    }

    foreach ($totals as $month => $tot) {
        $totals[$month]['$generationCredit'] = $totals[$month]['kwh'] / 10;
        $totals[$month]['$adjustedTotalMontlyBill'] = $totals[$month]['$both'] - $totals[$month]['$generationCredit'];
    }

    echo "Per month data:\n";
    print_r($totals);

    echo "KWH Per month:\n";
    print_r($GLOBALS['kwhSumPerMonth']);
}

// Location info. This is San Francisco.
date_default_timezone_set('America/Los_Angeles');
$gpsLat = 37.7;
$gpsLon = -122.4;

// Name of your data file.
$pgeDataFile = 'data/pge_electric_interval_data_8071115493_2017-01-01_to_2017-11-16.csv';
$rows = iterator_to_array((new CsvReader())->createIterator($pgeDataFile));

// In the output, look at the data for each month, in particular the $adjustedTotalMontlyBill is your monthly bill.
echo "Non solar results:\n";
$kwhSumPerMonth = [];
runSim($rows, false);

echo "Solar results:\n";
$kwhSumPerMonth = [];
runSim($rows, true);
