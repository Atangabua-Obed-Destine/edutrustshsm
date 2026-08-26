<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;

/**
 * Lean, school-focused OHADA (SYSCOHADA) chart of accounts. Seeds the class
 * headings plus the detail accounts a school actually posts to. Two-pass:
 * create all rows, then link parent_id by code prefix.
 */
class OhadaChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        // [code, EN name, FR name, class, type, category(detail/heading), normal_balance]
        $accounts = [
            // ── Class 1 — Capital & Equity ──
            ['10', 'Capital & Reserves', 'Capital et Réserves', 1, 'equity', 'heading', 'credit'],
            ['101', 'Share Capital', 'Capital social', 1, 'equity', 'detail', 'credit'],
            ['110', 'Retained Earnings', 'Report à nouveau', 1, 'equity', 'detail', 'credit'],
            ['120', 'Income Summary / Result', 'Résultat de l\'exercice', 1, 'equity', 'detail', 'credit'],

            // ── Class 2 — Fixed Assets ──
            ['21', 'Tangible Fixed Assets', 'Immobilisations corporelles', 2, 'asset', 'heading', 'debit'],
            ['213', 'Buildings', 'Bâtiments', 2, 'asset', 'detail', 'debit'],
            ['215', 'Equipment', 'Matériel et outillage', 2, 'asset', 'detail', 'debit'],
            ['218', 'Furniture & Vehicles', 'Mobilier et matériel de transport', 2, 'asset', 'detail', 'debit'],
            ['28', 'Accumulated Depreciation', 'Amortissements', 2, 'asset', 'heading', 'credit'],
            ['281', 'Accumulated Depreciation', 'Amortissements cumulés', 2, 'asset', 'detail', 'credit'],

            // ── Class 3 — Inventory ──
            ['31', 'Inventory', 'Stocks', 3, 'asset', 'heading', 'debit'],
            ['311', 'Supplies Inventory', 'Stock de fournitures', 3, 'asset', 'detail', 'debit'],

            // ── Class 4 — Third Parties (AR/AP) ──
            ['40', 'Suppliers', 'Fournisseurs', 4, 'liability', 'heading', 'credit'],
            ['401', 'Accounts Payable', 'Fournisseurs', 4, 'liability', 'detail', 'credit'],
            ['41', 'Students / Customers', 'Clients', 4, 'asset', 'heading', 'debit'],
            ['411', 'Student Fees Receivable', 'Clients - frais scolaires', 4, 'asset', 'detail', 'debit'],
            ['42', 'Personnel', 'Personnel', 4, 'liability', 'heading', 'credit'],
            ['421', 'Staff Salaries Payable', 'Personnel - salaires dus', 4, 'liability', 'detail', 'credit'],
            ['44', 'State & Taxes', 'État et collectivités', 4, 'liability', 'heading', 'credit'],
            ['441', 'Taxes Payable', 'État - impôts et taxes', 4, 'liability', 'detail', 'credit'],
            ['47', 'Other Third Parties', 'Débiteurs et créditeurs divers', 4, 'liability', 'heading', 'credit'],
            ['470', 'Other Payables / Receivables', 'Autres tiers', 4, 'liability', 'detail', 'credit'],

            // ── Class 5 — Treasury (cash/bank) ──
            ['52', 'Banks', 'Banques', 5, 'asset', 'heading', 'debit'],
            ['521', 'Bank Account', 'Banque', 5, 'asset', 'detail', 'debit'],
            ['53', 'Mobile Money', 'Instruments de monnaie électronique', 5, 'asset', 'heading', 'debit'],
            ['531', 'Mobile Money (MTN/Orange)', 'Mobile Money', 5, 'asset', 'detail', 'debit'],
            ['57', 'Cash', 'Caisse', 5, 'asset', 'heading', 'debit'],
            ['571', 'Cash Box', 'Caisse', 5, 'asset', 'detail', 'debit'],

            // ── Class 6 — Expenses ──
            ['60', 'Purchases & Supplies', 'Achats', 6, 'expense', 'heading', 'debit'],
            ['601', 'Supplies & Stationery', 'Achats de fournitures', 6, 'expense', 'detail', 'debit'],
            ['61', 'Transport', 'Transports', 6, 'expense', 'heading', 'debit'],
            ['611', 'Transport & Fuel', 'Transports et carburant', 6, 'expense', 'detail', 'debit'],
            ['62', 'External Services', 'Services extérieurs', 6, 'expense', 'heading', 'debit'],
            ['621', 'Communication', 'Communication', 6, 'expense', 'detail', 'debit'],
            ['622', 'Utilities (Water/Electricity)', 'Eau et électricité', 6, 'expense', 'detail', 'debit'],
            ['627', 'Other External Services', 'Autres services extérieurs', 6, 'expense', 'detail', 'debit'],
            ['64', 'Taxes & Duties', 'Impôts et taxes', 6, 'expense', 'heading', 'debit'],
            ['641', 'Taxes & Social Insurance', 'Impôts et charges sociales', 6, 'expense', 'detail', 'debit'],
            ['66', 'Personnel Costs', 'Charges de personnel', 6, 'expense', 'heading', 'debit'],
            ['661', 'Salaries Expense', 'Salaires', 6, 'expense', 'detail', 'debit'],
            ['663', 'Allowances', 'Indemnités', 6, 'expense', 'detail', 'debit'],
            ['68', 'Depreciation Expense', 'Dotations aux amortissements', 6, 'expense', 'heading', 'debit'],
            ['681', 'Depreciation Expense', 'Dotations aux amortissements', 6, 'expense', 'detail', 'debit'],
            ['65', 'Other Expenses', 'Autres charges', 6, 'expense', 'heading', 'debit'],
            ['658', 'Miscellaneous Expenses', 'Charges diverses', 6, 'expense', 'detail', 'debit'],

            // ── Class 7 — Revenue ──
            ['70', 'Services Revenue', 'Ventes de services', 7, 'revenue', 'heading', 'credit'],
            ['701', 'Tuition Fees Revenue', 'Frais de scolarité', 7, 'revenue', 'detail', 'credit'],
            ['702', 'Registration Revenue', 'Frais d\'inscription', 7, 'revenue', 'detail', 'credit'],
            ['706', 'Other School Fees', 'Autres frais scolaires', 7, 'revenue', 'detail', 'credit'],
            ['75', 'Other Income', 'Autres produits', 7, 'revenue', 'heading', 'credit'],
            ['758', 'Miscellaneous Income', 'Produits divers', 7, 'revenue', 'detail', 'credit'],
        ];

        // Pass 1 — create
        foreach ($accounts as $i => [$code, $en, $fr, $class, $type, $cat, $normal]) {
            ChartOfAccount::firstOrCreate(
                ['account_code' => $code],
                [
                    'account_name' => $en,
                    'account_name_fr' => $fr,
                    'class_number' => $class,
                    'account_type' => $type,
                    'account_category' => $cat,
                    'normal_balance' => $normal,
                    'is_system' => true,
                    'is_active' => true,
                    'display_order' => $i,
                ]
            );
        }

        // Pass 2 — link parents by longest matching code prefix
        $all = ChartOfAccount::orderByRaw('LENGTH(account_code) DESC')->get();
        foreach (ChartOfAccount::all() as $acc) {
            if (strlen($acc->account_code) <= 2) {
                continue; // top-level headings
            }
            $parent = $all->first(fn ($p) => $p->id !== $acc->id
                && strlen($p->account_code) < strlen($acc->account_code)
                && str_starts_with($acc->account_code, $p->account_code));
            if ($parent && $acc->parent_id !== $parent->id) {
                $acc->update(['parent_id' => $parent->id]);
            }
        }
    }
}
