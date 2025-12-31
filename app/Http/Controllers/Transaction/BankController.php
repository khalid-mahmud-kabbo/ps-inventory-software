<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\View\View;
use Yajra\DataTables\Facades\DataTables;
use App\Models\PaymentTransaction;
use App\Services\PaymentTypeService;
use App\Models\BankAdjustment;
use App\Models\Party\PartyPayment;
use App\Enums\PaymentTypesUniqueCode;
use App\Services\PaymentTransactionService;
use App\Traits\FormatNumber;
use App\Traits\FormatsDateInputs;
use Illuminate\Support\Facades\Log;

class BankController extends Controller
{
    use FormatNumber;
    use FormatsDateInputs;

    private $paymentTypeService;

    private $paymentTransactionService;

    public function __construct(PaymentTypeService $paymentTypeService, PaymentTransactionService $paymentTransactionService)
    {
        $this->paymentTypeService = $paymentTypeService;
        $this->paymentTransactionService = $paymentTransactionService;
    }


    /**
     * List the cash transactions
     *
     * @return \Illuminate\View\View
     */

     public function list() : View {
        $cashInBank = $this->formatWithPrecision($this->returnCashInBankValue());
        return view('transaction.bank-list', compact('cashInBank'));
    }







    public function getBankAdjustmentDetails($id) : JsonResponse{
        $model = BankAdjustment::find($id);

        $data = [
            'adjustment_type'  => $model->adjustment_type,
            'adjustment_date'  => $this->toUserDateFormat($model->adjustment_date),
            'amount'  => $this->formatWithPrecision($model->amount, comma:false),
            'note'  => $model->note,
            'adjustment_id'  => $model->id,
            'operation'  => 'update',

        ];

        return response()->json([
            'status' => true,
            'message' => '',
            'data'  => $data,
        ]);

    }

    public function storeBankTransaction(Request $request) : JsonResponse{
        try {

                DB::beginTransaction();
                // Validation rules
                $rules = [
                    'adjustment_type'  => 'required|string',
                    'adjustment_date'  => ['required', 'date_format:'.implode(',', $this->getDateFormats())],
                    'amount'            => 'required|numeric|gt:0',
                    'note'              => 'nullable|string|max:250',
                ];

                //validation message
                $messages = [
                    'transaction_date.required' => 'Adjustment date is required.',
                    'adjustment_type.required'  => 'Adjustment type is required.',
                    'amount.required'          => 'Adjustment amount is required.',
                    'amount.gt'                => 'Adjustment amount must be greater than zero.',
                ];

                $validator = Validator::make($request->all(), $rules, $messages);

                //Show validation message
                if ($validator->fails()) {
                    throw new \Exception($validator->errors()->first());
                }


                $validatedData = $validator->validated();
                /**
                 * Default Payment Type
                 * Cash
                 * */
                $validatedData['payment_type_id'] = $bankId = $this->paymentTypeService->returnPaymentTypeId(PaymentTypesUniqueCode::BANK->value);
                $validatedData['adjustment_date'] = $this->toSystemDateFormat($validatedData['adjustment_date']);

                $bankAdjustmentId = request('bank_adjustment_id');

                if(!empty($bankAdjustmentId)){
                    //update records
                    $adjustmentEntry = BankAdjustment::find($bankAdjustmentId);

                    //Delete Payment Transaction
                    $paymentTransactions = $adjustmentEntry->paymentTransaction;
                    if ($paymentTransactions->count() > 0) {
                        foreach ($paymentTransactions as $paymentTransaction) {
                            $paymentTransaction->delete();
                        }
                    }

                    $adjustmentEntry->update($validatedData);

                }else{
                    //Save records
                    $adjustmentEntry = BankAdjustment::create($validatedData);
                }


                /**
                 * Record it in Payment Transactins table
                 * */
                $paymentsArray = [
                    'transaction_date'          => $validatedData['adjustment_date'],
                    'amount'                    => $validatedData['amount'],
                    'payment_type_id'           => $validatedData['payment_type_id'],
                    'note'                      => $validatedData['note'],
                ];
                if(!$transaction = $this->paymentTransactionService->recordPayment($adjustmentEntry, $paymentsArray)){
                    throw new \Exception(__('payment.failed_to_record_payment_transactions'));
                }

                DB::commit();

                return response()->json([
                    'status'    => true,
                    'message' => __('app.record_saved_successfully'),
                    'cashInBank'    => $this->returnCashInBankValue(),

                ]);

        } catch (\Exception $e) {
                DB::rollback();

                return response()->json([
                    'status' => false,
                    'message' => $e->getMessage(),
                ], 409);

        }
    }

    /**
     * Bank Transaction list
     * */
    public function datatableList(Request $request){
        // Ensure morph map keys are defined
        $this->paymentTransactionService->usedTransactionTypeValue();

        $dangerTypes = ['Expense', 'Purchase', 'Purchase Return', 'Purchase Order', 'Bank Reduce'];

        $bankAdjustmentKey = 'Bank Adjustment';

        $cashId = $this->paymentTypeService->returnPaymentTypeId(PaymentTypesUniqueCode::CASH->value);
        $chequeId = $this->paymentTypeService->returnPaymentTypeId(PaymentTypesUniqueCode::CHEQUE->value);

        $data = PaymentTransaction::with('user', 'paymentType')
                                    ->where(function ($query) use ($cashId, $chequeId) {
                                        $query->whereNotIn('payment_type_id', [$cashId, $chequeId])
                                              ->orWhereNotIn('transfer_to_payment_type_id', [$cashId, $chequeId]);
                                    })
                                    ->when($request->from_date, function ($query) use ($request) {
                                        return $query->where('transaction_date', '>=', $this->toSystemDateFormat($request->from_date));
                                    })
                                    ->when($request->to_date, function ($query) use ($request) {
                                        return $query->where('transaction_date', '<=', $this->toSystemDateFormat($request->to_date));
                                    });

        $cashInBankTotal = $this->returnCashInBankValue($request->from_date ?? null, $request->to_date ?? null);
        return DataTables::of($data)
                    ->addIndexColumn()
                    ->addColumn('created_at', function ($row) {
                        return $row->created_at->format(app('company')['date_format']);
                    })
                    ->addColumn('username', function ($row) {
                        return $row->user->username??'';
                    })
                    ->addColumn('amount', function ($row) {
                        return $this->formatWithPrecision($row->amount);
                    })
                    ->addColumn('color_class', function ($row) use ($dangerTypes, $bankAdjustmentKey) {
                        if($row->transaction_type == $bankAdjustmentKey){
                            return in_array($row->transaction->adjustment_type, $dangerTypes) ? "danger" : "success";
                        }
                        else if($row->transaction->payment_direction){
                            return $row->transaction->payment_direction=="pay" ? "danger" : "success";
                        }else{
                            return in_array($row->transaction_type, $dangerTypes) ? "danger" : "success";
                        }

                    })




                    ->addColumn('transaction_type', function ($row) use ($bankAdjustmentKey) {
                        if($row->transaction_type == $bankAdjustmentKey){
                            return $row->transaction->adjustment_type;
                        }else{
                            return $row->transaction_type;
                        }
                    })
                    ->addColumn('party_name', function ($row) {
    if ($row->transaction?->party) {
        $type = ucfirst($row->transaction->party->party_type); // capitalize first letter
        $fullName = $row->transaction->party->getFullName();
        return "{$type} : {$fullName}";
    } else {
        return $row->transaction?->category?->name ?? 'Unknown';
    }
})
                    ->addColumn('action', function($row) use ($bankAdjustmentKey){
                            $id = $row->id;

                            $actionBtn = 'NA';

                            if($row->transaction_type == $bankAdjustmentKey){
                            $actionBtn = '<div class="dropdown ms-auto">
                                            <a class="dropdown-toggle dropdown-toggle-nocaret" href="#" data-bs-toggle="dropdown"><i class="bx bx-dots-vertical-rounded font-22 text-option"></i>
                                            </a>
                                            <ul class="dropdown-menu">';

                                                    $actionBtn .= '<li>
                                                        <a class="dropdown-item edit-bank-adjustment" data-bank-adjustment-id="' . $row->transaction->id . '" role="button"></i><i class="bx bx-edit"></i> '.__('app.edit').'</a>
                                                    </li>';

                                                    $actionBtn .= '<li>
                                                    <button type="button" class="dropdown-item text-danger deleteRequest " data-delete-id='.$row->transaction->id.'><i class="bx bx-trash"></i> '.__('app.delete').'</button>
                                                </li>';

                                            $actionBtn .= '</ul>
                                        </div>';
                          }
                        return $actionBtn;
                    })
                    ->rawColumns(['action'])
                    ->with(['cash_in_bank_total' => $cashInBankTotal])
                    ->make(true);
    }












      public function delete(Request $request) : JsonResponse{

        $selectedRecordIds = $request->input('record_ids');

        // Perform validation for each selected record ID
        foreach ($selectedRecordIds as $recordId) {
            $record = BankAdjustment::find($recordId);
            if (!$record) {
                // Invalid record ID, handle the error (e.g., show a message, log, etc.)
                return response()->json([
                    'status'    => false,
                    'message' => __('app.invalid_record_id',['record_id' => $recordId]),
                ]);

            }
            // You can perform additional validation checks here if needed before deletion
        }

        /**
         * All selected record IDs are valid, proceed with the deletion
         * Delete all records with the selected IDs in one query
         * */


        try {

            // Attempt deletion (as in previous responses)
            BankAdjustment::whereIn('id', $selectedRecordIds)->chunk(100, function ($bankAdjustments) {
                foreach ($bankAdjustments as $adjustment) {

                    $paymentTransactions = $adjustment->paymentTransaction;
                    if ($paymentTransactions->isNotEmpty()) {
                        foreach ($paymentTransactions as $paymentTransaction) {
                            //delete Payment now
                            $paymentTransaction->delete();
                        }
                    }//isNotEmpty
                }
            });

            // Delete Complete Item
            $itemModel = BankAdjustment::whereIn('id', $selectedRecordIds)->delete();

            return response()->json([
                'status'    => true,
                'message' => __('app.record_deleted_successfully'),
                'cashInBank'    => $this->returnCashInBankValue(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() == 23000) {
                return response()->json([
                    'status'    => false,
                    'message' => __('app.cannot_delete_records'),
                ],409);
            }
        }
    }























    public function returnCashInBankValue($from_date = null, $to_date = null)
{
    // Ensure morph map keys are defined
    $this->paymentTransactionService->usedTransactionTypeValue();

    $cashId = $this->paymentTypeService->returnPaymentTypeId(PaymentTypesUniqueCode::BANK->value);

    // convert incoming dates to system format (or null)
    $from = $from_date ? $this->toSystemDateFormat($from_date) : null;
    $to   = $to_date   ? $this->toSystemDateFormat($to_date)   : null;

    // helper closure to apply date filters to a query
    $applyDate = function ($query) use ($from, $to) {
        if ($from) $query->where('transaction_date', '>=', $from);
        if ($to)   $query->where('transaction_date', '<=', $to);
    };

    // Calculate bank-related transactions (apply date window)
    $cashTransactionOfSale = PaymentTransaction::where('transaction_type', 'Sale')
        ->where(function ($q) use ($cashId) {
            $q->where('payment_type_id', $cashId)
              ->orWhere('transfer_to_payment_type_id', $cashId);
        })
        ->when($from || $to, $applyDate)
        ->sum('amount');

    $cashTransactionOfSaleReturn = PaymentTransaction::where('transaction_type', 'Sale Return')
        ->where(function ($q) use ($cashId) {
            $q->where('payment_type_id', $cashId)
              ->orWhere('transfer_to_payment_type_id', $cashId);
        })
        ->when($from || $to, $applyDate)
        ->sum('amount');

    $cashTransactionOfSaleOrder = PaymentTransaction::where('transaction_type', 'Sale Order')
        ->where(function ($q) use ($cashId) {
            $q->where('payment_type_id', $cashId)
              ->orWhere('transfer_to_payment_type_id', $cashId);
        })
        ->when($from || $to, $applyDate)
        ->sum('amount');

    $cashTransactionOfPurchase = PaymentTransaction::where('transaction_type', 'Purchase')
        ->where(function ($q) use ($cashId) {
            $q->where('payment_type_id', $cashId)
              ->orWhere('transfer_to_payment_type_id', $cashId);
        })
        ->when($from || $to, $applyDate)
        ->sum('amount');

    $cashTransactionOfPurchaseReturn = PaymentTransaction::where('transaction_type', 'Purchase Return')
        ->where(function ($q) use ($cashId) {
            $q->where('payment_type_id', $cashId)
              ->orWhere('transfer_to_payment_type_id', $cashId);
        })
        ->when($from || $to, $applyDate)
        ->sum('amount');

    $cashTransactionOfPurchaseOrder = PaymentTransaction::where('transaction_type', 'Purchase Order')
        ->where(function ($q) use ($cashId) {
            $q->where('payment_type_id', $cashId)
              ->orWhere('transfer_to_payment_type_id', $cashId);
        })
        ->when($from || $to, $applyDate)
        ->sum('amount');

    $cashTransactionOfExpense = PaymentTransaction::where('transaction_type', 'Expense')
        ->where(function ($q) use ($cashId) {
            $q->where('payment_type_id', $cashId)
              ->orWhere('transfer_to_payment_type_id', $cashId);
        })
        ->when($from || $to, $applyDate)
        ->sum('amount');

    // Party payments: pay (reduction) & receive (increase)
    $remainingPayBalance = PaymentTransaction::where('transaction_type', 'Party Payment')
        ->whereIn('transaction_id', function ($q) {
            $q->select('id')
              ->from(with(new PartyPayment())->getTable())
              ->where('payment_direction', 'pay');
        })
        ->where('payment_from_unique_code', 'PARTY_BALANCE_AFTER_ADJUSTMENT')
        ->where(function ($q) use ($cashId) {
            $q->where('payment_type_id', $cashId)
              ->orWhere('transfer_to_payment_type_id', $cashId);
        })
        ->when($from || $to, $applyDate)
        ->sum('amount');

    $remainingReceiveBalance = PaymentTransaction::where('transaction_type', 'Party Payment')
        ->whereIn('transaction_id', function ($q) {
            $q->select('id')
              ->from(with(new PartyPayment())->getTable())
              ->where('payment_direction', 'receive');
        })
        ->where('payment_from_unique_code', 'PARTY_BALANCE_AFTER_ADJUSTMENT')
        ->where(function ($q) use ($cashId) {
            $q->where('payment_type_id', $cashId)
              ->orWhere('transfer_to_payment_type_id', $cashId);
        })
        ->when($from || $to, $applyDate)
        ->sum('amount');

    /**
     * Only Bank Adjustment Records (apply adjustment_date)
     */
    $addCashIds = BankAdjustment::where('adjustment_type', 'Bank Increase')
        ->when($from, fn($q) => $q->where('adjustment_date','>=',$from))
        ->when($to,   fn($q) => $q->where('adjustment_date','<=',$to))
        ->pluck('id');

    $reduceCashIds = BankAdjustment::where('adjustment_type', 'Bank Reduce')
        ->when($from, fn($q) => $q->where('adjustment_date','>=',$from))
        ->when($to,   fn($q) => $q->where('adjustment_date','<=',$to))
        ->pluck('id');

    $netCashAdjustment = PaymentTransaction::where('transaction_type', 'Bank Adjustment')
        ->when($from || $to, $applyDate)
        ->whereIn('transaction_id', $addCashIds)
        ->sum('amount')
      - PaymentTransaction::where('transaction_type', 'Bank Adjustment')
        ->when($from || $to, $applyDate)
        ->whereIn('transaction_id', $reduceCashIds)
        ->sum('amount');
    // End

    $cashInBank = ($cashTransactionOfSale + $cashTransactionOfPurchaseReturn + $cashTransactionOfSaleOrder + $netCashAdjustment + $remainingReceiveBalance)
                    - ($cashTransactionOfSaleReturn + $cashTransactionOfPurchase + $cashTransactionOfPurchaseOrder + $cashTransactionOfExpense + $remainingPayBalance);

    return $this->formatWithPrecision($cashInBank, comma: false);
}















    /**
     * Retrieve cash transaction
     * */
    function getBankStatementRecords(Request $request): JsonResponse{
        try{
            // Ensure morph map keys are defined
            $this->paymentTransactionService->usedTransactionTypeValue();

            $dangerTypes = ['Expense', 'Purchase', 'Sale Return', 'Purchase Order'];

            $cashAdjustmentKey = 'Cash Adjustment';

            // Validation rules
            $rules = [
                'from_date'         => ['required', 'date_format:'.implode(',', $this->getDateFormats())],
                'to_date'           => ['required', 'date_format:'.implode(',', $this->getDateFormats())],
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                throw new \Exception($validator->errors()->first());
            }

            $fromDate           = $request->input('from_date');
            $fromDate           = $this->toSystemDateFormat($fromDate);
            $toDate             = $request->input('to_date');
            $toDate             = $this->toSystemDateFormat($toDate);

            $cashId = $this->paymentTypeService->returnPaymentTypeId(PaymentTypesUniqueCode::CASH->value);
            $chequeId = $this->paymentTypeService->returnPaymentTypeId(PaymentTypesUniqueCode::CHEQUE->value);

            $preparedData = PaymentTransaction::with('user', 'paymentType')
                                        ->where(function ($query) use ($cashId, $chequeId) {
                                            $query->whereNotIn('payment_type_id', [$cashId, $chequeId])
                                                  ->orWhereNotIn('transfer_to_payment_type_id', [$cashId, $chequeId]);
                                        })
                                        ->when($request->from_date, function ($query) use ($request) {
                                            return $query->where('transaction_date', '>=', $this->toSystemDateFormat($request->from_date));
                                        })
                                        ->when($request->to_date, function ($query) use ($request) {
                                            return $query->where('transaction_date', '<=', $this->toSystemDateFormat($request->to_date));
                                        })->get();

            if($preparedData->count() == 0){
                throw new \Exception('No Records Found!!');
            }
            $recordsArray = [];

            foreach ($preparedData as $data) {
                $transactionDetails = '';
                $isCashIn = true;
                if(!empty($data->transfer_to_payment_type_id)){
                    if($this->paymentTransactionService->getChequeTransactionType($data->transaction_type) == 'Withdraw'){
                        $isCashIn = false;
                    }else{
                        $isCashIn = true;
                    }
                }else{
                    $isCashIn = true;
                }

                $recordsArray[] = [
                                'transaction_date'      => $this->toUserDateFormat($data->transaction_date),
                                'invoice_or_bill_code'  => $data->transaction->getTableCode(),
                                'party_name'            => $data->transaction->party? $data->transaction->party->getFullName() : $data->transaction->category->name,
                                'transaction_details'   => $data->transaction_type,
                                'deposit_amount'        => ($isCashIn) ? $this->formatWithPrecision($data->amount, comma:false) : 0,
                                'withdrawal_amount'     => (!$isCashIn) ? $this->formatWithPrecision($data->amount, comma:false) : 0,
                            ];
            }

            return response()->json([
                        'status'    => true,
                        'message' => "Records are retrieved!!",
                        'data' => $recordsArray,
                    ]);
        } catch (\Exception $e) {
                return response()->json([
                    'status' => false,
                    'message' => $e->getMessage(),
                ], 409);

        }
    }














     public function datatableListBankflow(Request $request)
{
    $cashId = $this->paymentTypeService->returnPaymentTypeId(PaymentTypesUniqueCode::CASH->value);
    $chequeId = $this->paymentTypeService->returnPaymentTypeId(PaymentTypesUniqueCode::CHEQUE->value);

    $this->paymentTransactionService->usedTransactionTypeValue();

    $cashAdjustmentKey = 'Bank Adjustment';
    $dangerTypes = ['Expense', 'Purchase', 'Sale Return', 'Purchase Order'];

    // Fetch all relevant data first (for running balance calculation)
    $transactions = PaymentTransaction::with('user', 'paymentType')
                                        ->where(function ($query) use ($cashId, $chequeId) {
                                            $query->whereNotIn('payment_type_id', [$cashId, $chequeId])
                                                  ->orWhereNotIn('transfer_to_payment_type_id', [$cashId, $chequeId]);
                                        })
                                        ->when($request->from_date, function ($query) use ($request) {
                                            return $query->where('transaction_date', '>=', $this->toSystemDateFormat($request->from_date));
                                        })
                                        ->when($request->to_date, function ($query) use ($request) {
                                            return $query->where('transaction_date', '<=', $this->toSystemDateFormat($request->to_date));
                                        })->get();



    $runningBalance = 0;

    $transactions = $transactions->map(function ($row) use ($dangerTypes, $cashAdjustmentKey, &$runningBalance) {

        $isCashIn = true;

        if (!empty($row->transfer_to_payment_type_id)) {
            $isCashIn = $this->paymentTransactionService->getChequeTransactionType($row->transaction_type) != 'Withdraw';
        } else {
            if ($row->transaction_type == $cashAdjustmentKey) {
                $isCashIn = ($row->transaction->adjustment_type ?? '') == 'Bank Increase';
            } else {
                $isCashIn = !in_array($row->transaction_type, $dangerTypes);
            }
        }

        // Cash In / Out
        $row->cash_in = $isCashIn ? $row->amount : 0;
        $row->cash_out = !$isCashIn ? $row->amount : 0;

        // Running balance
        $runningBalance += $row->cash_in - $row->cash_out;
        $row->balance = $runningBalance;

        // Color class
        if ($row->transaction_type == $cashAdjustmentKey) {
            $row->color_class = in_array($row->transaction->adjustment_type ?? '', $dangerTypes) ? "danger" : "success";
        } elseif ($row->transaction?->payment_direction) {
            $row->color_class = $row->transaction->payment_direction == "pay" ? "danger" : "success";
        } else {
            $row->color_class = in_array($row->transaction_type, $dangerTypes) ? "danger" : "success";
        }

        // Party Name
        $row->party_name = $row->transaction->party?->getFullName()
                            ?? $row->transaction->category->name ?? '';

        // Category Name
        $row->category_name = $row->transaction->category->name ?? '';

        // Transaction Details
        $row->transaction_details = $row->transaction_type;


$row->products = '';

if ($row->transaction && method_exists($row->transaction, 'itemTransaction')) {
    $row->products = $row->transaction->itemTransaction->map(function ($itemTx) {
        return $itemTx->item?->name . ' (' . $itemTx->quantity . ')';
    })->implode('<br>'); // <br> for new line
}

$invoice = $row->transaction && method_exists($row->transaction, 'getTableCode')
            ? $row->transaction->getTableCode()
            : '';

$row->invoice_or_bill_code = $invoice . ($row->products ? '<br>' . $row->products : '');


        return $row;
    });

    return DataTables::of($transactions)
        ->addIndexColumn()
        ->addColumn('transaction_date', fn($row) => $row->transaction_date)
        ->addColumn('invoice_or_bill_code', function($row) {
        return $row->invoice_or_bill_code ? $row->invoice_or_bill_code : 'Adjustment';
        })
        ->addColumn('party_name', fn($row) => $row->party_name)
        ->addColumn('transaction_details', fn($row) => $row->transaction_details)
        ->addColumn('cash_in', fn($row) => $this->formatWithPrecision($row->cash_in, comma:false))
        ->addColumn('cash_out', fn($row) => $this->formatWithPrecision($row->cash_out, comma:false))
        ->addColumn('balance', fn($row) => $this->formatWithPrecision($row->balance, comma:false))
        ->addColumn('color_class', fn($row) => $row->color_class)
        ->rawColumns(['invoice_or_bill_code'])
        ->make(true);
}

}
