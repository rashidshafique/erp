# Foundry ERP

Laravel ERP for iron foundries: enquiry and quotation, sales orders with contract review, melting plan, heats and thermal analysis, moulding and sand lab, pouring with a thermal gate, casting lots, inspection and NCRs, packing and dispatch, purchasing and stores, plus invoicing, supplier bills, payments and a double-entry general ledger, taxation (Pakistan first), HR and payroll, maintenance, tooling, CAPA and controlled documents.

Optional parts live as modules in `app/Domains/` and can be switched on or off from the modules settings page.

Docs: `docs/STAGE2.md` (order to pour), `docs/STATUS.md` (what is done), `docs/PLAN.md` (module by module plan).

## Quick start (fresh copy)

    bash setup.sh            # uses database foundry_erp
    bash setup.sh my_db      # or pick another database name
    php artisan serve

Pattern cavity map: Patterns → a pattern → "Cavity map" links each cavity to the product it makes.

Order-to-pour features (customers, sales orders, contract review, melting plan, iron grades, thermal analysis, pouring gate): see docs/STAGE2.md.
