<?php

namespace InvoiceAutomation\Lib;

require_once 'Lib/Invoice.php';
use InvoiceAutomation\Lib\Invoice;

class Calculator
{

    /**
     * Get results from database using passed criteria
     * @access public
     * @param PDO $connection database connection
     * @param string $firstDayOfTheMonth
     * @param string $firstDayOfNextMonth
     * @param array $projects
     * @return array entries in database
     */
    public static function getResultsFromDatabase($connection, $firstDayOfTheMonth, $firstDayOfNextMonth, $projects)
    {
        $projectsWhereClause = '';
        if (!empty($projects)) {
            $projectsWhereClause = 'and ( p.pct_name = \'' . implode('\' or p.pct_name = \'', $projects) . '\' )';
        }
        $query = $connection->query(""
                . "SELECT u.usr_name, u.usr_alias, k.knd_name, r.rate, p.pct_ID, p.pct_name, ROUND(sum(z.zef_time)/3600, 2) time_formatted, DATE_FORMAT(FROM_UNIXTIME(z.zef_in), '%d/%m/%Y') date_formatted "
                . "FROM `ki_zef` z inner join `ki_pct` p on z.zef_pctID = p.pct_ID inner join `ki_knd` k on k.knd_ID = p.pct_kndID left join `ki_rates` r on r.project_id = p.pct_ID and r.user_id is null inner join `ki_usr` u on u.usr_ID = z.zef_usrID "
                . "where z.zef_in between unix_timestamp('" . $firstDayOfTheMonth . " 00:00:00') and unix_timestamp('" . $firstDayOfNextMonth . " 00:00:00') $projectsWhereClause"
                . "group by u.usr_name, p.pct_ID, r.rate, z.zef_in order by p.pct_name, u.usr_name, z.zef_in");
        return $query->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get invoice number and insert it in database
     * @access public
     * @param PDO $connection database connection
     * @param int $projectId
     * @param string $month
     * @param string $year
     * @return int invoice number
     */
    public static function prepareInvoiceNumber($connection, $projectId, $month, $year)
    {
        $month = (int) $month;
        $year = (int) $year;
        // check invoice entry already exists
        $query = $connection->query("SELECT * FROM `invoice` i WHERE i.project_id = '$projectId' and i.month = '" . $month . "' and i.year = '" . $year . "' ORDER BY id DESC LIMIT 1");
        $existingEntry = $query->fetch(\PDO::FETCH_ASSOC);
        // get last invoice entry if not existing
        $existingFlag = false;
        if (empty($existingEntry)) {
            $query = $connection->query("SELECT i.invoice_no FROM `invoice` i ORDER BY id DESC LIMIT 1");
            $lastEntry = $query->fetch(\PDO::FETCH_ASSOC);
            if (empty($lastEntry)) {
                $invoiceNumber = 1;
            }
            else {
                $invoiceNumber = (int)$lastEntry["invoice_no"] + 1;
            }
        }
        else {
            $existingFlag = true;
            $invoiceNumber = (int)$existingEntry["invoice_no"];
        }
        if($existingFlag === false){
            // insert invoice number in database
            Invoice::add($connection, $invoiceNumber, $projectId, $month, $year);
        }
        return $invoiceNumber;
    }

    /**
     * Get current exchange rate
     * @access public
     * @return float current rate
     */
    public static function getExchangeRate()
    {
        $exchangeRateResponse = file_get_contents("https://www.google.com/finance/converter?a=1&from=USD&to=GBP");
        $exchangeRateResponseContents = explode("<span class=bld>", $exchangeRateResponse);
        $exchangeRateResponseContents = explode("</span>", $exchangeRateResponseContents[1]);
        return preg_replace("/[^0-9\.]/", null, $exchangeRateResponseContents[0]);
    }

    /**
     * Get project rate
     * @access public
     * @return float project rate
     */
    public static function getProjectRate($result)
    {
        // rate is by default 1 unless set in DB
        $projectRate = 1;
        $firstUserEntries = reset($result);
        $firstUserFirstEntry = reset($firstUserEntries);
        $projectDefaultRate = $firstUserFirstEntry["rate"];
        if (!is_null($projectDefaultRate)) {
            $projectRate = $projectDefaultRate;
        }
        return $projectRate;
    }

    /**
     * Calculate times and rates per each user and for all users
     * @access public
     * @param array $projectEntries
     * @param array $days
     * @param float $projectRate
     * @param float $exchangeRate
     * @return array calculated rates per each user and for all users
     */
    public static function getUserRates($projectEntries, $days, $projectRate, $exchangeRate)
    {
        $rateUSD = 0.0;
        $rateGBP = 0.0;
        foreach ($projectEntries as $userName => $userEntries) {
            $timePerUser = 0.0;
            $timesPerUser = array();
            // loop on days per user
            foreach ($days as $day) {
                $formattedDay = $day->format("d/m/Y");
                $timePerDay = 0.0;
                // calculate time per day per user 
                foreach ($userEntries as $singleEntry) {
                    if ($formattedDay == $singleEntry["date_formatted"]) {
                        $timePerDay += $singleEntry["time_formatted"];
                    }
                }
                $timesPerUser[$formattedDay] = $timePerDay;
                $timePerUser += $timePerDay;
            }
            $daysPerUser = ceil($timePerUser * 2 / 8) / 2; // ceil to nearest 0.5
            $rateUSDPerUser = number_format(($daysPerUser * $projectRate) ,2 , /*$dec_point =*/ "." , /*$thousands_sep =*/ "");
            $rateGBPPerUser = number_format(($rateUSDPerUser * $exchangeRate) ,2 , /*$dec_point =*/ "." , /*$thousands_sep =*/ "");
            // set number of days, rates in USD and GBP
            $rateUSD += $rateUSDPerUser;
            $rateGBP += $rateGBPPerUser;
            $userRates["users"][$userName] = array(
                'times' => $timesPerUser,
                'days' => $daysPerUser,
                'rateUSD' => $rateUSDPerUser,
                'rateGBP' => $rateGBPPerUser,
            );
        }
        $userRates = array_merge($userRates, array(
            'rateUSD' => number_format($rateUSD ,2 , /*$dec_point =*/ "." , /*$thousands_sep =*/ ""),
            'rateGBP' => number_format($rateGBP ,2 , /*$dec_point =*/ "." , /*$thousands_sep =*/ ""),
        ));
        return $userRates;
    }

}
