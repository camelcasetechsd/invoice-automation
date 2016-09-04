A script to generate Time sheet excel sheet and invoice documents from database

Generate sheets and invoices for previous month using script `php Generator.php`

Main script can have some optional options:

* date for generated sheets and invoices as `--date="06-2016"`
* projects to generate sheets and invoices for as `--projects="abc project,xyz project"`

Note:

* invoice number is incremented unless invoice for same project and same month was generated before, then old invoice number will be reused
* for any reason, invoices number can be reset by deleting entries that correspond to range of invoice numbers using script `php InvoicesRemover.php --from="2" --to="5"`
* for any reason, invoices number can be incremented by adding entry with specific invoice number for specific project using script `php InvoicesAdder.php --number="2" --project="abcProject"`