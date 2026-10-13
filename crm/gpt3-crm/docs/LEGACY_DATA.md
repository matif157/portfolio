# Legacy data mapping
The supplied exports were inspected before integration.

Known intentional duplicates:
- 05_Invoice_Line_Items.csv and 06_Invoice_Line_Items_Duplicate.csv are identical.
- 07_Instant_Invoices_Guest_Clients.csv and 08_Instant_Invoices_Guest_Clients_Duplicate.csv are identical.

Known legacy quality issues:
- Some files contain `0000-00-00` dates, which are not valid MySQL dates and are normalized as NULL in the modern tables.
- Some historical text fields contain HTML fragments such as `<br />`/`&nbsp;`.
- Some business dates use mixed text formats.
- Historical card files contain PAN/CVV data. These should not be retained in a normal production payment workflow; use tokenized payment-provider references instead.

The raw exports remain in `database/legacy_data` for traceability. The importer logs source hashes and row counts in `import_runs`.
