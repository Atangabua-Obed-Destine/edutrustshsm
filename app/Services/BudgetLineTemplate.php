<?php

namespace App\Services;

use App\Models\BudgetLine;
use Illuminate\Support\Facades\DB;

/**
 * The standard Income & Expenditure sheet for a nursery and primary school.
 *
 * Numbered the way the printed sheet is: income in the 600s, expenditure in
 * the 400s and 500s, capital in the 540s, headings on round numbers with
 * their lines just above. The gaps are reserved space, not mistakes to tidy
 * up: a school adds its own lines into them.
 *
 * Create-only. A line that already exists is left exactly as it is, sort
 * order included, because the order is the finance office's own work.
 */
class BudgetLineTemplate
{
    /** @return array{created: int, present: int} */
    public function load(): array
    {
        $created = 0;
        $present = 0;

        DB::transaction(function () use (&$created, &$present) {
            $order = 0;
            $parentId = null;

            foreach ($this->lines() as $row) {
                [$section, $code, $name, $nameFr, $isHeader] = $row;
                $extra = $row[5] ?? [];
                $order += 10;

                $existing = BudgetLine::withoutGlobalScopes()->where('code', $code)->first();

                if ($existing) {
                    $present++;
                    if ($isHeader) {
                        $parentId = $existing->id;
                    }

                    continue;
                }

                $line = BudgetLine::create([
                    'code' => $code,
                    'name' => $name,
                    'name_fr' => $nameFr,
                    'section' => $section,
                    'is_header' => $isHeader,
                    'parent_id' => ($isHeader || ! empty($extra['top'])) ? null : $parentId,
                    'school_level' => $extra['level'] ?? null,
                    'profit_centre' => $extra['centre'] ?? null,
                    'is_payroll' => ! empty($extra['payroll']),
                    'is_standard' => true,
                    'sort_order' => $order,
                    'status' => true,
                ]);
                $created++;

                if ($isHeader) {
                    $parentId = $line->id;
                }
            }
        });

        return ['created' => $created, 'present' => $present];
    }

    /**
     * [section, code, name, name_fr, is_header, extra{top, level, centre, payroll}]
     *
     * @return array<int, array>
     */
    public function lines(): array
    {
        $i = BudgetLine::SECTION_INCOME;
        $e = BudgetLine::SECTION_EXPENDITURE;
        $c = BudgetLine::SECTION_CAPITAL;

        return [
            // ---- INCOME ---------------------------------------------------
            [$i, '600', 'Registration Fees', 'Frais d\'inscription', false],
            [$i, '610', 'Tuition Fees — Nursery', 'Scolarité — Maternelle', false, ['level' => 'nursery']],
            [$i, '611', 'Tuition Fees — Primary', 'Scolarité — Primaire', false, ['level' => 'primary']],
            [$i, '612', 'Tuition Fees — Secondary', 'Scolarité — Secondaire', false, ['level' => 'first_cycle']],
            [$i, '613', 'Tuition Fees — High School', 'Scolarité — Lycée', false, ['level' => 'second_cycle']],
            [$i, '614', 'Examination Fees', 'Frais d\'examen', false],
            [$i, '615', 'PTA Levy', 'Cotisation APE', false],
            [$i, '616', 'Boarding Fees', 'Frais d\'internat', false],
            [$i, '617', 'Other Pupil Fees', 'Autres frais élèves', false],
            [$i, '620', 'OTHER INCOME', 'AUTRES RECETTES', true],
            [$i, '621', 'Donations / Grants', 'Dons / Subventions', false],
            [$i, '622', 'Benefactors', 'Bienfaiteurs', false],
            [$i, '623', 'Subvention / Capital contribution', 'Subvention / Apport en capital', false],
            [$i, '624', 'Sundry income', 'Recettes diverses', false],
            [$i, '625', 'Canteen sales', 'Ventes de la cantine', false, ['centre' => 'Canteen']],
            [$i, '626', 'Uniform and book sales', 'Ventes d\'uniformes et de livres', false, ['centre' => 'Uniforms and books']],

            // ---- EXPENDITURE ----------------------------------------------
            [$e, '400', 'ADMINISTRATION', 'ADMINISTRATION', true],
            [$e, '401', 'Travelling', 'Déplacements', false],
            [$e, '402', 'Meetings and Seminars', 'Réunions et séminaires', false],
            [$e, '403', 'Telephone / Postage', 'Téléphone / Courrier', false],
            [$e, '404', 'Stationery', 'Fournitures de bureau', false],
            [$e, '405', 'Printing and photocopies', 'Impression et photocopies', false],
            [$e, '409', 'Staff Meetings', 'Réunions du personnel', false],
            [$e, '410', 'PEDAGOGY', 'PÉDAGOGIE', true],
            [$e, '411', 'Didactic material', 'Matériel didactique', false],
            [$e, '412', 'Examinations', 'Examens', false],
            [$e, '413', 'Textbooks and library', 'Manuels et bibliothèque', false],
            [$e, '414', 'Sports and games', 'Sport et jeux', false],
            [$e, '415', 'Seminars / Teacher training', 'Séminaires / Formation des enseignants', false],
            [$e, '420', 'ELECTRICITY AND WATER', 'ÉLECTRICITÉ ET EAU', true],
            [$e, '421', 'Electricity Bills', 'Factures d\'électricité', false],
            [$e, '422', 'Electricity repairs', 'Réparations électriques', false],
            [$e, '423', 'Generator Fuel', 'Carburant du groupe électrogène', false],
            [$e, '424', 'Water Bills', 'Factures d\'eau', false],
            [$e, '426', 'Plumbing repairs', 'Réparations de plomberie', false],
            [$e, '430', 'STAFF COSTS', 'CHARGES DE PERSONNEL', true],
            [$e, '441', 'Internet', 'Internet', false],
            [$e, '442', 'Salaries', 'Salaires', false, ['payroll' => true]],
            [$e, '445', 'CNPS / NSIF', 'CNPS', false],
            [$e, '446', 'Duty Post Allowances', 'Primes de poste', false],
            [$e, '447', 'Recruitment / Interviews', 'Recrutement / Entretiens', false],
            [$e, '448', 'Casual labour', 'Main-d\'œuvre occasionnelle', false],
            [$e, '450', 'SANITATION', 'HYGIÈNE ET ASSAINISSEMENT', true],
            [$e, '451', 'Compound care (outdoor)', 'Entretien de la cour', false],
            [$e, '452', 'Cleaning (Buildings)', 'Nettoyage (bâtiments)', false],
            [$e, '453', 'Hygiene and first aid', 'Hygiène et premiers secours', false],
            [$e, '460', 'TRANSPORT', 'TRANSPORT', true],
            [$e, '461', 'Fuel', 'Carburant', false],
            [$e, '462', 'Vehicle Running expenses', 'Entretien des véhicules', false],
            [$e, '463', 'School bus', 'Bus scolaire', false],
            [$e, '470', 'OTHER EXPENSES', 'AUTRES DÉPENSES', true],
            [$e, '471', 'Ceremonies / Graduation', 'Cérémonies / Remise des diplômes', false],
            [$e, '472', 'Pupils\' feeding', 'Alimentation des élèves', false, ['centre' => 'Canteen']],
            [$e, '473', 'Uniforms and books purchased', 'Achat d\'uniformes et de livres', false, ['centre' => 'Uniforms and books']],
            [$e, '475', 'Visitations / Receptions', 'Visites / Réceptions', false],
            [$e, '476', 'Other Celebrations', 'Autres fêtes', false],
            [$e, '477', 'Staff welfare', 'Œuvres sociales du personnel', false],
            [$e, '478', 'Pupils\' welfare', 'Bien-être des élèves', false],
            [$e, '479', 'Adverts / Enrolment campaign', 'Publicité / Campagne d\'inscription', false],
            [$e, '480', 'INCOME-GENERATING PROJECTS', 'PROJETS GÉNÉRATEURS DE REVENUS', true],
            [$e, '481', 'Poultry', 'Aviculture', false],
            [$e, '482', 'School Farm / Garden', 'Ferme / Jardin scolaire', false],
            [$e, '489', 'Other projects', 'Autres projets', false],
            [$e, '500', 'DEPRECIATION', 'AMORTISSEMENTS', true],
            [$e, '501', 'Furniture / Equipment', 'Mobilier / Équipement', false],
            [$e, '502', 'Building', 'Bâtiments', false],
            [$e, '503', 'Vehicle depreciation', 'Amortissement des véhicules', false],
            [$e, '510', 'REPAIRS AND MAINTENANCE', 'RÉPARATIONS ET ENTRETIEN', true],
            [$e, '511', 'Buildings', 'Bâtiments', false],
            [$e, '512', 'Furniture / Equipment', 'Mobilier / Équipement', false],
            [$e, '590', 'Financial expenses / Bank charges', 'Frais financiers / Frais bancaires', false, ['top' => true]],
            [$e, '591', 'Security and guarding', 'Sécurité et gardiennage', false, ['top' => true]],
            [$e, '592', 'Unclassified expenses', 'Dépenses non classées', false, ['top' => true]],

            // ---- CAPITAL --------------------------------------------------
            [$c, '540', 'CAPITAL EXPENSES / PURCHASES', 'DÉPENSES D\'INVESTISSEMENT', true],
            [$c, '541', 'Sports equipment', 'Équipement sportif', false],
            [$c, '542', 'Furniture / Equipment', 'Mobilier / Équipement', false],
            [$c, '543', 'Buildings and construction', 'Bâtiments et construction', false],
            [$c, '544', 'Vehicles', 'Véhicules', false],
            [$c, '545', 'Computers / ICT', 'Informatique / TIC', false],
        ];
    }
}
