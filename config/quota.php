<?php

return [
    /*
     |--------------------------------------------------------------------------
     | SAP purchase order source table
     |--------------------------------------------------------------------------
     |
     | Fully-qualified table name (optionally with schema) that contains the SAP
     | purchase order feed. Defaults to "purchase_orders" which works for
     | SQL Server databases where the schema/table share that name.
     */
    'sap_po_table' => env('SAP_PO_TABLE', 'purchase_orders'),
];
