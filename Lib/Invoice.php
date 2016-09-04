<?php

namespace InvoiceAutomation\Lib;

class Invoice
{
    
    /**
     * Add invoice to database using passed data
     * @access public
     * @param PDO $connection database connection
     * @param int $invoiceNumber
     * @param int $projectId
     * @param int $month ,default is false
     * @param int $year ,default is false
     */
    public static function add($connection, $invoiceNumber, $projectId, $month = false, $year = false)
    {
        if($month === false || $year === false){
            $today = new \DateTime();
            $month = $today->format("m");
            $year = $today->format("Y");
        }
        $newInvoice = $connection->prepare("INSERT INTO `invoice` (invoice_no, project_id, month, year) VALUES (:invoiceNumber, :projectId, :month, :year)");
        $newInvoice->execute(array(
            ':invoiceNumber' => $invoiceNumber,
            ':projectId' => $projectId,
            ':month' => $month,
            ':year' => $year,
        ));
    }
    
    /**
     * Delete invoices from database using passed criteria
     * @access public
     * @param PDO $connection database connection
     * @param int $from
     * @param int $to
     */
    public static function delete($connection, $from, $to)
    {
        $deletedInvoices = $connection->prepare("DELETE FROM `invoice` WHERE invoice_no >= :from and invoice_no <= :to");
        $deletedInvoices->execute(array(
            ':from' => $from,
            ':to' => $to,
        ));
    }
}

