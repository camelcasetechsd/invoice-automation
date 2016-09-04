A script to generate Time sheet excel sheet and invoice documents from database

Installation:

* Run `composer install` to install dependencies
* Edit config.php inside config dir. to hold actual DB credentials

Generate sheets and invoices for previous month using script `php Generator.php`

Main script can have some optional options:

* date for generated sheets and invoices as `--date="06-2016"`
* projects to generate sheets and invoices for as `--projects="abc project,xyz project"`

Note:

* invoice number is incremented unless invoice for same project and same month was generated before, then old invoice number will be reused
* for any reason, invoices number can be reset by deleting entries that correspond to range of invoice numbers using script `php InvoicesRemover.php --from="2" --to="5"`
* for any reason, invoices number can be incremented by adding entry with specific invoice number for specific project using script `php InvoicesAdder.php --number="2" --project="abcProject"`

Kimai Customizations:

To Add link in invoice tab inside timesheet project
* replace main.tpl file in extensions/ki_invoice/templates inside kimai installation with the one here inside kimaiCustomizations dir.