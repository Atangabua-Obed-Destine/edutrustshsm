<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\PaymentAccount;
use App\Models\PaymentAccountTransaction;
use App\Models\PaymentAccountTransfer;
use App\Services\PaymentAccountService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PaymentAccountTransferController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'fund-transfer';

    public function __construct(private PaymentAccountService $accounts)
    {
    }

    public function index(Request $request)
    {
        $query = PaymentAccountTransfer::with(['fromAccount', 'toAccount', 'createdBy']);
        $accountTitle = fn (string $column) => PaymentAccount::select('title')
            ->whereColumn('payment_accounts.id', 'payment_account_transfers.'.$column)->limit(1);

        $list = new \App\Support\Listing($request, 'transfers', __('Fund Transfers'), [
            'date' => ['label' => __('Date'), 'sort' => 'payment_account_transfers.transfer_date', 'value' => fn ($t) => $t->transfer_date],
            'from' => ['label' => __('From Account'), 'sort' => fn ($q, $dir) => $q->orderBy($accountTitle('from_account_id'), $dir),
                'value' => fn ($t) => $t->fromAccount?->title],
            'to' => ['label' => __('To Account'), 'sort' => fn ($q, $dir) => $q->orderBy($accountTitle('to_account_id'), $dir),
                'value' => fn ($t) => $t->toAccount?->title],
            'amount' => ['label' => __('Amount'), 'sort' => 'payment_account_transfers.amount', 'money' => true, 'value' => fn ($t) => $t->amount],
            'note' => ['label' => __('Note'), 'value' => fn ($t) => $t->note],
            'created_by' => ['label' => __('Created By'), 'value' => fn ($t) => $t->createdBy?->full_name],
        ], default: 'date', defaultDir: 'desc');

        if ($download = $list->export($query)) {
            return $download;
        }

        $transfers = $list->paginate($query);

        return view('admin.payment-account.transfers.index', compact('transfers', 'list'));
    }

    public function create()
    {
        $accounts = PaymentAccount::active()->orderBy('title')->get();
        return view('admin.payment-account.transfers.create', compact('accounts'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'from_account_id' => ['required', 'exists:payment_accounts,id'],
            'to_account_id'   => ['required', 'exists:payment_accounts,id', 'different:from_account_id'],
            'amount'          => ['required', 'numeric', 'min:0.01'],
            'transfer_date'   => ['required', 'date', 'before_or_equal:today'],
            'note'            => ['nullable', 'string'],
            'attach'          => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:20480'],
        ]);

        try {
            DB::transaction(function () use ($request, $validated) {
                $from = PaymentAccount::findOrFail($validated['from_account_id']);
                $to = PaymentAccount::findOrFail($validated['to_account_id']);

                $attach = $request->hasFile('attach')
                    ? $request->file('attach')->store('accounts/transfers', 'local') : null;

                $transfer = PaymentAccountTransfer::create([
                    'from_account_id' => $from->id,
                    'to_account_id'   => $to->id,
                    'amount'          => $validated['amount'],
                    'transfer_date'   => $validated['transfer_date'],
                    'note'            => $validated['note'] ?? null,
                    'attach'          => $attach,
                    'created_by'      => auth()->id(),
                ]);

                $meta = [
                    'transaction_date' => $validated['transfer_date'],
                    'reference_type'   => PaymentAccountTransaction::REF_TRANSFER,
                    'reference_id'     => $transfer->id,
                ];

                // Debit source first (guards funds), then credit destination.
                $this->accounts->debit($from, $validated['amount'], $meta + [
                    'title' => 'Transfer to ' . $to->title,
                ]);
                $this->accounts->credit($to, $validated['amount'], $meta + [
                    'title' => 'Transfer from ' . $from->title,
                ]);

            });
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.payment-account-transfer.index')
            ->with('success', __('Funds transferred successfully.'));
    }

    public function destroy(PaymentAccountTransfer $payment_account_transfer)
    {
        $old = $payment_account_transfer->toArray();

        DB::transaction(function () use ($payment_account_transfer) {
            // Reverse both ledger entries (restores both balances), then remove the transfer.
            $this->accounts->reverseFor(PaymentAccountTransaction::REF_TRANSFER, $payment_account_transfer->id);
            if ($payment_account_transfer->attach) {
                Storage::disk('public')->delete($payment_account_transfer->attach);
            }
            $payment_account_transfer->delete();
        });


        return redirect()->route('admin.payment-account-transfer.index')
            ->with('success', __('Transfer reversed and deleted.'));
    }
}
