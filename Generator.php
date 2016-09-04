<?php

namespace InvoiceAutomation;

require 'vendor/autoload.php';
require 'Lib/Connector.php';
require 'Lib/Logger.php';
require 'Lib/Calculator.php';
use InvoiceAutomation\Lib\Connector;
use InvoiceAutomation\Lib\Logger;
use InvoiceAutomation\Lib\Calculator;

class Generator
{

    /**
     *
     * @var array command options ,default is empty array
     */
    private static $options = array();

    /**
     * Wrap DB connection and export of timesheet excel sheets and invoices documents
     * @access public
     * @return boolean true if whole export process is successful or not
     */
    public static function execute()
    {
        Logger::log('Starting exporting...');
        $result = false;
        $connector = new Connector();
        // start DB connection
        $connector->startConnection();
        $connection = $connector->connection;
        // handle export exceptions
        try {
            // run migrations
            exec("php migration.php");
            // run the export process
            $exportResult = self::export($connection);
            if ($exportResult === true) {
                Logger::log('Export attempt successful!');
                $result = true;
            }
            else {
                Logger::log('Export attempt failed!');
            }
        } catch (Exception $e) {
            $exportResult = false;
            // log exception message
            Logger::log($e->getMessage());
        }
        $connector->killConnection();
        return $result;
    }

    /**
     * Export database timesheet data into excel sheets and invoices
     * @access private
     * @param PDO $connection database connection
     * @return boolean true if whole export process is successful or not
     * @throws \Exception php-zip extension is not enabled!
     */
    private static function export($connection)
    {
        // zip extension is needed to generate excel sheets
        if (!class_exists('ZipArchive')) {
            throw new \Exception("php-zip extension is not enabled!");
        }
        // handle options check
        // load passed options to command
        self::setOptions();
        $firstDayOfTheMonth = self::$options["firstDayOfTheMonth"];
        $firstDayOfNextMonth = self::$options["firstDayOfNextMonth"];
        $projects = self::$options["projects"];

        $results = Calculator::getResultsFromDatabase($connection, $firstDayOfTheMonth, $firstDayOfNextMonth, $projects);
        $resultsPerProject = array();
        // divide entries per users per projects
        foreach ($results as $result) {
            $resultsPerProject[$result["pct_name"]][$result["usr_alias"]][] = $result;
        }
        // get days array in selected month
        $days = new \DatePeriod(
                new \DateTime($firstDayOfTheMonth), new \DateInterval('P1D'), new \DateTime($firstDayOfNextMonth)
        );
        // get USD GBP exchange rate
        $exchangeRate = Calculator::getExchangeRate();
        // generate excel sheet
        self::createExcelSheets($resultsPerProject, $days, $exchangeRate, $firstDayOfTheMonth);
        // generate invoice documents
        self::createInvoiceDocuments($connection, $resultsPerProject, $days, $exchangeRate);
        // archive generated files
        self::archiveDownloads();
        return true;
    }

    /**
     * Set options after validating it
     * @access private
     * @throws \Exception date should match format mm-yyyy !
     * @throws \Exception projects should match format 'abcProject' or 'abcProject,xyz project' !
     */
    private static function setOptions()
    {
        // options starting with "--" like "--date"
        $longOptions = array(
            "date::", // Optional value
            "projects::", // Optional value
        );
        $options = getopt(/* $shortopts = */ "", $longOptions);
        if (!array_key_exists('date', $options)) {
            // set reports time range as last month 
            // starting from first day in last month till current month start
            $month = date("m") - 1;
            if ($month < 10) {
                $month = "0" . $month;
            }
            $year = date("Y");
            $firstDayOfTheMonth = date("Y-m-d", mktime(0, 0, 0, $month, /* $day = */ 1));
            $firstDayOfNextMonth = date('Y-m-01');
        }
        else {
            if (!preg_match('/^[0-1]{1}[0-9]{1}[^0-9]{1}20[0-9]{2}$/', $options['date'])) {
                throw new \Exception("date should match format mm-yyyy !");
            }
            $month = substr($options['date'], 0, 2);
            $year = substr($options['date'], 3, 7);
            $firstDayOfTheMonth = date("Y-m-d", mktime(0, 0, 0, $month, /* $day = */ 1, $year));
            $firstDayOfNextMonth = date("Y-m-d", mktime(0, 0, 0, $month + 1, /* $day = */ 1, $year));
        }
        $projects = array();
        if (array_key_exists('projects', $options)) {
            if (!preg_match('/^[0-9a-zA-Z ]+([,0-9a-zA-Z ]+)?$/', $options['projects'])) {
                throw new \Exception("projects should match format 'abcProject' or 'abcProject,xyz project' !");
            }
            $projects = explode(',', $options['projects']);
        }
        self::$options["firstDayOfTheMonth"] = $firstDayOfTheMonth;
        self::$options["firstDayOfNextMonth"] = $firstDayOfNextMonth;
        self::$options["month"] = $month;
        self::$options["year"] = $year;
        self::$options["projects"] = $projects;
    }

    /**
     * Build and save excel sheets
     * @access private
     * @param array $resultsPerProject
     * @param array $days
     * @param float $exchangeRate
     * @param string $firstDayOfTheMonth
     */
    private static function createExcelSheets($resultsPerProject, $days, $exchangeRate, $firstDayOfTheMonth)
    {
        $today = date("d/m/Y");
        foreach ($resultsPerProject as $project => $result) {
            $excel = new \PHPExcel();
            // set file main properties
            $excel->getProperties()
                    ->setCreator('Camelcasetech')
                    ->setTitle('Timesheet')
                    ->setLastModifiedBy('Camelcasetech')
                    ->setDescription('Timesheet excel sheet per project listing all project team members\' contributions')
                    ->setSubject('Timesheet per project ' . $project)
                    ->setKeywords('timesheet team')
                    ->setCategory('management');
            // create first sheet with project rate
            $firstExcelSheet = $excel->getSheet(0);
            $firstExcelSheet->setTitle('Rates Per Project');
            $firstExcelSheet->setCellValue('a1', $project);
            $firstExcelSheet->getColumnDimension('a')->setAutoSize(true);
            $projectRate = Calculator::getProjectRate($result);
            $firstExcelSheet->setCellValue('b1', $projectRate);
            // create second sheet
            $secondExcelSheet = new \PHPExcel_Worksheet($excel, $project);
            $excel->addSheet($secondExcelSheet, 1);
            $secondExcelSheet->setTitle($project);
            // build sheet header
            $secondExcelSheet->setCellValue('a1', "Month");
            $secondExcelSheet->setCellValue('b1', "Date");
            $usersLetters = range('c', 'z');
            $usersRates = Calculator::getUserRates($result, $days, $projectRate, $exchangeRate);
            $userLetterIndex = 0;
            foreach ($usersRates["users"] as $userName => $usersRate) {
                // add user name in header
                $userLetter = $usersLetters[$userLetterIndex];
                $secondExcelSheet->setCellValue($userLetter . '1', $userName);
                $insertedDaysInDateColumn = array();
                // loop on days per user
                $dayIndex = 0;
                foreach ($usersRate["times"] as $formattedDay => $timePerDay) {
                    $entryIndex = 2 + (int) $dayIndex;
                    if (!in_array($formattedDay, $insertedDaysInDateColumn)) {
                        $secondExcelSheet->setCellValue('b' . $entryIndex, $formattedDay);
                    }
                    $insertedDaysInDateColumn[] = $formattedDay;
                    $secondExcelSheet->setCellValue($userLetter . $entryIndex, $timePerDay);
                    $dayIndex++;
                }
                // set number of days, rates in USD and GBP
                $secondExcelSheet->setCellValue($userLetter . ($entryIndex + 1), $usersRate["days"]);
                $secondExcelSheet->setCellValue($userLetter . ($entryIndex + 2), $usersRate["rateUSD"]);
                $secondExcelSheet->setCellValue($userLetter . ($entryIndex + 3), $usersRate["rateGBP"]);
                $userLetterIndex++;
            }
            // add days and rates titles beside exchange rate
            $secondExcelSheet->setCellValue('a' . ($entryIndex + 1), "Total Days");
            $secondExcelSheet->setCellValue('a' . ($entryIndex + 2), "Total Rate ($)");
            $secondExcelSheet->setCellValue('a' . ($entryIndex + 3), "Total Rate (£)");
            $secondExcelSheet->setCellValue('a' . ($entryIndex + 4), "\$→£ (" . $today . ")");
            $secondExcelSheet->setCellValue('b' . ($entryIndex + 4), $exchangeRate);
            // set all columns to autosize
            for ($col = ord('a'); $col <= ord($usersLetters[$userLetterIndex]); $col++) {
                $secondExcelSheet->getColumnDimension(chr($col))->setAutoSize(true);
            }
            // merge month column cells
            $secondExcelSheet->mergeCells('a2:a' . $entryIndex);
            // set header style
            $header = 'a1:' . $usersLetters[$userLetterIndex - 1] . '1';
            $headerStyle = array(
                'fill' => array(
                    'type' => \PHPExcel_Style_Fill::FILL_SOLID,
                    'color' => array('rgb' => 'EFEFEF')
                ),
                'font' => array('bold' => true,),
                'alignment' => array('horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_CENTER,),
            );
            $secondExcelSheet->getStyle($header)->applyFromArray($headerStyle);
            // set body style
            $bodyStyle = array(
                'fill' => array(
                    'type' => \PHPExcel_Style_Fill::FILL_SOLID,
                    'color' => array('rgb' => 'B0E1AC')
                ),
                'borders' => array(
                    'allborders' => array(
                        'style' => \PHPExcel_Style_Border::BORDER_THIN
                    )
                )
            );
            $body = 'a2:' . $usersLetters[$userLetterIndex - 1] . ($entryIndex + 3);
            $secondExcelSheet->getStyle($body)->applyFromArray($bodyStyle);
            // set footer style
            $footerStyle = array(
                'font' => array('bold' => true,),
            );
            $footer = 'a' . ($entryIndex + 1) . ':' . $usersLetters[$userLetterIndex - 1] . ($entryIndex + 3);
            $secondExcelSheet->getStyle($footer)->applyFromArray($footerStyle);
            // save excel file
            $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
            $writer->save('downloads/timesheets/camelcase_timesheet_' . $project . '_' . self::$options["year"] . self::$options["month"] . '.xlsx');
        }
    }

    /**
     * Build and save invoice documents
     * @access private
     * @param PDO $connection database connection
     * @param array $resultsPerProject
     * @param array $days
     * @param float $exchangeRate
     * @param string $firstDayOfTheMonth
     */
    private static function createInvoiceDocuments($connection, $resultsPerProject, $days, $exchangeRate)
    {
        $today = date("d/m/Y");
        foreach ($resultsPerProject as $project => $result) {
            $projectRate = Calculator::getProjectRate($result);
            $firstUserEntries = reset($result);
            $firstUserFirstEntry = reset($firstUserEntries);
            $projectClient = $firstUserFirstEntry["knd_name"];
            $projectId = $firstUserFirstEntry["pct_ID"];
            $userRates = Calculator::getUserRates($result, $days, $projectRate, $exchangeRate);
            $phpWord = new \PhpOffice\PhpWord\PhpWord();
            // set document properties
            $phpWord->getDocInfo()
                    ->setCreator('Camelcasetech')
                    ->setCompany('Camelcasetech')
                    ->setTitle('Invoice')
                    ->setLastModifiedBy('Camelcasetech')
                    ->setDescription('Invoice document per project listing all project team members\' contributions')
                    ->setSubject('Invoice per project ' . $project)
                    ->setKeywords('invoice team')
                    ->setCategory('management');
            $document = $phpWord->loadTemplate('template/invoiceTemplate.docx');
            // clone rows for all users, so that each user has a dedicated row
            $numberOfUsers = count($result);
            $document->cloneRow('teamMemberName', $numberOfUsers);
            $userPosition = 1;
            foreach ($userRates["users"] as $userName => $userRate) {
                $document->setValue("teamMemberName#" . $userPosition, $userName);
                $document->setValue("teamMemberDays#" . $userPosition, $userRate["days"]);
                $userPosition++;
            }
            $firstUserRate = reset($userRates["users"]);
            $daysDates = array_keys($firstUserRate["times"]);
            // prepare invoice number
            $invoiceNumber = Calculator::prepareInvoiceNumber($connection, $projectId, self::$options["month"], self::$options["year"]);
            $invoiceNumberPadded = str_pad($invoiceNumber, 6, '0', STR_PAD_LEFT);
            // set invoice data
            $document->setValue(
                    array(
                'invoiceDate',
                'invoiceNumber',
                'invoiceMonth',
                'project',
                'invoiceStartDate',
                'invoiceEndDate',
                'rateUSD',
                'rateGBP',
                'totalRateGBP',
                'exchangeRate',
                    ), array(
                $today,
                $invoiceNumber,
                date('F', mktime(0, 0, 0, self::$options["month"])) . " " . self::$options["year"],
                $projectClient . " " . $project . " Project",
                reset($daysDates),
                end($daysDates),
                $userRates["rateUSD"],
                $userRates["rateGBP"],
                $userRates["rateGBP"],
                $exchangeRate
                    )
            );

            // save document file
            $document->saveAs('downloads/invoices/camelcase_invoice_' . $invoiceNumberPadded . '_' . $project . '_' . self::$options["year"] . self::$options["month"] . '.docx');
        }
    }

    /**
     * Archive and then delete generated files
     * @access private
     */
    private static function archiveDownloads()
    {
        // Get real path for our downloads folder
        $rootPath = realpath('downloads');

        // Initialize archive object
        $zip = new \ZipArchive();
        $zip->open('downloads.zip', \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        // Initialize empty "delete list"
        $filesToDelete = array();

        // Create recursive directory iterator
        /** @var SplFileInfo[] $files */
        $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($rootPath), \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            // Skip directories (they would be added automatically)
            if (!$file->isDir() && $file->getFilename() != ".gitignore") {
                // Get real and relative path for current file
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($rootPath) + 1);

                // Add current file to archive
                $zip->addFile($filePath, $relativePath);

                // Add current file to "delete list"
                // delete it later cause ZipArchive create archive only after calling close function and ZipArchive lock files until archive created)
                $filesToDelete[] = $filePath;
            }
        }

        // Zip archive will be created only after closing object
        $zip->close();

        // Delete all files from "delete list"
        foreach ($filesToDelete as $file) {
            unlink($file);
        }
    }

}
if( ! ini_get('date.timezone') )
{
    date_default_timezone_set('Africa/Cairo');
}
// execute export command
$result = Generator::execute();
// Based on the export result, we need to set the exit code
if ($result === true) {
    exit(0); // exitcode 0 = success
}
else {
    exit(1); // exitcode 1 = error
}   
