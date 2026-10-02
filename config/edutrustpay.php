<?php

return [

    /*
    |--------------------------------------------------------------------------
    | EdutrustPay reporting client
    |--------------------------------------------------------------------------
    |
    | This school PUSHES a signed summary of each calendar month to its body's
    | EdutrustPay console. Nothing reaches in here: outbound HTTPS only, no
    | inbound endpoint, no database access from that side.
    |
    */

    'enabled' => env('EDUTRUSTPAY_ENABLED', false),

    'endpoint' => env('EDUTRUSTPAY_ENDPOINT'),

    /*
    |--------------------------------------------------------------------------
    | Branches are separate institutions
    |--------------------------------------------------------------------------
    |
    | This system is multi-branch, and a branch is a school: ST JEROME is not the
    | same institution as a second campus enrolled later. Each therefore needs
    | its own credentials and its own reference on the platform.
    |
    | The mapping is REQUIRED rather than optional, and there is deliberately no
    | "report everything together" fallback. Merging two campuses into one report
    | would silently combine separate schools' money into a figure that describes
    | neither, and it would look completely normal on the console.
    |
    | Keyed by branch id:
    |
    |   1 => ['ref' => '...', 'key_id' => '...', 'secret' => '...'],
    |
    */

    'branches' => [
        (int) env('EDUTRUSTPAY_BRANCH_ID', 1) => [
            'ref' => env('EDUTRUSTPAY_INSTITUTION_REF'),
            'key_id' => env('EDUTRUSTPAY_KEY_ID'),
            'secret' => env('EDUTRUSTPAY_SECRET'),
        ],
    ],

    'timeout' => (int) env('EDUTRUSTPAY_TIMEOUT', 30),

    'max_attempts' => (int) env('EDUTRUSTPAY_MAX_ATTEMPTS', 12),

    'backoff_minutes' => [1, 5, 15, 60, 240, 720],

    /*
    |--------------------------------------------------------------------------
    | What this school can currently report
    |--------------------------------------------------------------------------
    |
    | THIS LIST IS SHORTER THAN IT LOOKS, AND THAT IS THE POINT.
    |
    | This deployment has student enrolments, fee structures and payments, but
    | its general ledger has not been used: journal_entries is empty. So it
    | cannot report a ledger, cash position, control totals or anything derived
    | from them — and it must not pretend to. On the console those show as
    | "not yet reporting", which is true, rather than as zeroes, which would be
    | a lie about a school that simply has not started posting yet.
    |
    | Note this is close to the OPPOSITE of what the university on paxhitestbed
    | can report: that system has a full ledger but no student fee data wired to
    | the contract, so it reports `ledger` and not `enrolment`. Neither
    | institution's capabilities are a subset of the other's, which is exactly
    | why the platform declares capabilities per institution rather than
    | assuming a common floor.
    |
    | Add to this list as modules come online, and only then.
    |
    */

    'capabilities' => [
        'enrolment',    // student_enrollments + student_fees + payments
        'receivables',  // AgingReportService::studentFees()

        /*
         * NOT DECLARED, with reasons:
         *
         *   ledger, integrity — journal_entries is empty. The OHADA chart is
         *     seeded and the observers are wired, but nothing has been posted,
         *     so there is no ledger to summarise or hash.
         *   payables — no supplier ledger in use.
         *   payroll — the payrolls table is empty.
         *   budget — no budget module.
         *   bank_recon — no bank statement import.
         */
    ],

];
