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
use App\Models\CashAdjustment;
use App\Enums\PaymentTypesUniqueCode;
use App\Models\Expenses\Expense;
use App\Models\Party\PartyPayment;
use App\Services\PartyService;
use App\Models\Purchase\Purchase;
use App\Models\Sale\Sale;
use App\Services\PaymentTransactionService;
use App\Traits\FormatNumber;
use App\Traits\FormatsDateInputs;
use Illuminate\Support\Facades\Log;
use App\Models\Party\PartyTransaction;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class CashController extends Controller
{
    use FormatNumber;
    use FormatsDateInputs;

    private $paymentTypeService;

    public $partyService;


    private $paymentTransactionService;

    public function __construct(PaymentTypeService $paymentTypeService, PaymentTransactionService $paymentTransactionService, PartyService $partyService)
    {
        $this->paymentTypeService = $paymentTypeService;
        $this->paymentTransactionService = $paymentTransactionService;
        $this->partyService = $partyService;
    }


    /**
     * List the cash transactions
     *
     * @return \Illuminate\View\View
     */
    public function list(): View
    {
        $cashInHand = $this->formatWithPrecision($this->returnCashInHandValue());
        return view('transaction.cash-list', compact('cashInHand'));
    }

    public function getCashAdjustmentDetails($id): JsonResponse
    {
        $model = CashAdjustment::find($id);

        $data = [
            'adjustment_type'  => $model->adjustment_type,
            'adjustment_date'  => $this->toUserDateFormat($model->adjustment_date),
            'amount'  => $this->formatWithPrecision($model->amount, comma: false),
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

    public function storeCashTransaction(Request $request): JsonResponse
    {
        try {

            DB::beginTransaction();
            // Validation rules
            $rules = [
                'adjustment_type'  => 'required|string',
                'adjustment_date'  => ['required', 'date_format:' . implode(',', $this->getDateFormats())],
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
            $validatedData['payment_type_id'] = $cashId = $this->paymentTypeService->returnPaymentTypeId(PaymentTypesUniqueCode::CASH->value);
            $validatedData['adjustment_date'] = $this->toSystemDateFormat($validatedData['adjustment_date']);

            $cashAdjustmentId = request('cash_adjustment_id');

            if (!empty($cashAdjustmentId)) {
                //update records
                $adjustmentEntry = CashAdjustment::find($cashAdjustmentId);

                //Delete Payment Transaction
                $paymentTransactions = $adjustmentEntry->paymentTransaction;
                if ($paymentTransactions->count() > 0) {
                    foreach ($paymentTransactions as $paymentTransaction) {
                        $paymentTransaction->delete();
                    }
                }

                $adjustmentEntry->update($validatedData);
            } else {
                //Save records
                $adjustmentEntry = CashAdjustment::create($validatedData);
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
            if (!$transaction = $this->paymentTransactionService->recordPayment($adjustmentEntry, $paymentsArray)) {
                throw new \Exception(__('payment.failed_to_record_payment_transactions'));
            }

            DB::commit();

            return response()->json([
                'status'    => true,
                'message' => __('app.record_saved_successfully'),
                'cashInHand'    => $this->returnCashInHandValue(),

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
     * Cash Transaction list
     * */



    public function datatableList(Request $request)
    {

        // Ensure morph map keys are defined
        $this->paymentTransactionService->usedTransactionTypeValue();

        $dangerTypes = ['Expense', 'Purchase', 'Sale Return', 'Purchase Order', 'Cash Reduce'];

        $cashAdjustmentKey = 'Cash Adjustment';

        $cashId = $this->paymentTypeService->returnPaymentTypeId(PaymentTypesUniqueCode::CASH->value);
        $data = PaymentTransaction::with('user', 'paymentType')
            ->where(function ($query) use ($cashId) {
                $query->where('payment_type_id', $cashId)
                    ->orWhere('transfer_to_payment_type_id', $cashId);
            })
            ->when($request->from_date, function ($query) use ($request) {
                return $query->where('transaction_date', '>=', $this->toSystemDateFormat($request->from_date));
            })
            ->when($request->to_date, function ($query) use ($request) {
                return $query->where('transaction_date', '<=', $this->toSystemDateFormat($request->to_date));
            });
        $cashInHandTotal = $this->returnCashInHandValue($request->from_date ?? null, $request->to_date ?? null);
        return DataTables::of($data)
            ->addIndexColumn()
            ->addColumn('created_at', function ($row) {
                return $row->created_at->format(app('company')['date_format']);
            })
            ->addColumn('transaction_date', function ($row) {
                return $row->formatted_transaction_date;
            })
            ->addColumn('username', function ($row) {
                return $row->user->username ?? '';
            })
            ->addColumn('amount', function ($row) {
                return $this->formatWithPrecision($row->amount);
            })
            ->addColumn('transaction_type', function ($row) use ($cashAdjustmentKey) {
                if ($row->transaction_type == $cashAdjustmentKey) {
                    return $row->transaction->adjustment_type;
                } else if ($row->transaction->payment_direction) {
                    //For Party Direct Payments which may have remaining balance after adjustment in PaymentTransaction table
                    return $row->transaction_type . '(' . (ucfirst($row->transaction->payment_direction)) . ')';
                } else {
                    return $row->transaction_type;
                }
            })
            ->addColumn('color_class', function ($row) use ($dangerTypes, $cashAdjustmentKey) {
                if ($row->transaction_type == $cashAdjustmentKey) {
                    return in_array($row->transaction->adjustment_type, $dangerTypes) ? "danger" : "success";
                } else if ($row->transaction->payment_direction) {
                    //For Party Direct Payments which may have remaining balance after adjustment in PaymentTransaction table
                    return $row->transaction->payment_direction == "pay" ? "danger" : "success";
                } else {
                    return in_array($row->transaction_type, $dangerTypes) ? "danger" : "success";
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
            ->addColumn('action', function ($row) use ($cashAdjustmentKey) {
                $id = $row->id;

                $actionBtn = 'NA';

                if ($row->transaction_type == $cashAdjustmentKey) {
                    $actionBtn = '<div class="dropdown ms-auto">
                                            <a class="dropdown-toggle dropdown-toggle-nocaret" href="#" data-bs-toggle="dropdown"><i class="bx bx-dots-vertical-rounded font-22 text-option"></i>
                                            </a>
                                            <ul class="dropdown-menu">';

                    $actionBtn .= '<li>
                                                        <a class="dropdown-item edit-cash-adjustment" data-cash-adjustment-id="' . $row->transaction->id . '" role="button"></i><i class="bx bx-edit"></i> ' . __('app.edit') . '</a>
                                                    </li>';

                    $actionBtn .= '<li>
                                                    <button type="button" class="dropdown-item text-danger deleteRequest " data-delete-id=' . $row->transaction->id . '><i class="bx bx-trash"></i> ' . __('app.delete') . '</button>
                                                </li>';

                    $actionBtn .= '</ul>
                                        </div>';
                }
                return $actionBtn;
            })
            ->rawColumns(['action'])
            ->with(['cash_in_hand_total' => $cashInHandTotal])
            ->make(true);
    }

















    public function delete(Request $request): JsonResponse
    {

        $selectedRecordIds = $request->input('record_ids');

        // Perform validation for each selected record ID
        foreach ($selectedRecordIds as $recordId) {
            $record = CashAdjustment::find($recordId);
            if (!$record) {
                // Invalid record ID, handle the error (e.g., show a message, log, etc.)
                return response()->json([
                    'status'    => false,
                    'message' => __('app.invalid_record_id', ['record_id' => $recordId]),
                ]);
            }
        }


        try {

            CashAdjustment::whereIn('id', $selectedRecordIds)->chunk(100, function ($cashAdjustments) {
                foreach ($cashAdjustments as $adjustment) {

                    $paymentTransactions = $adjustment->paymentTransaction;
                    if ($paymentTransactions->isNotEmpty()) {
                        foreach ($paymentTransactions as $paymentTransaction) {

                            $paymentTransaction->delete();
                        }
                    }
                }
            });

            // Delete Complete Item
            $itemModel = CashAdjustment::whereIn('id', $selectedRecordIds)->delete();

            return response()->json([
                'status'    => true,
                'message' => __('app.record_deleted_successfully'),
                'cashInHand'    => $this->returnCashInHandValue(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() == 23000) {
                return response()->json([
                    'status'    => false,
                    'message' => __('app.cannot_delete_records'),
                ], 409);
            }
        }
    }














    public function returnCashInHandValue($from_date = null, $to_date = null)
    {
        // Ensure morph map keys are defined
        $this->paymentTransactionService->usedTransactionTypeValue();

        $cashId = $this->paymentTypeService->returnPaymentTypeId(PaymentTypesUniqueCode::CASH->value);

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
        $addCashIds = CashAdjustment::where('adjustment_type', 'Cash Increase')
            ->when($from, fn($q) => $q->where('adjustment_date', '>=', $from))
            ->when($to,   fn($q) => $q->where('adjustment_date', '<=', $to))
            ->pluck('id');

        $reduceCashIds = CashAdjustment::where('adjustment_type', 'Cash Reduce')
            ->when($from, fn($q) => $q->where('adjustment_date', '>=', $from))
            ->when($to,   fn($q) => $q->where('adjustment_date', '<=', $to))
            ->pluck('id');

        $netCashAdjustment = PaymentTransaction::where('transaction_type', 'Cash Adjustment')
            ->when($from || $to, $applyDate)
            ->whereIn('transaction_id', $addCashIds)
            ->sum('amount')
            - PaymentTransaction::where('transaction_type', 'Cash Adjustment')
            ->when($from || $to, $applyDate)
            ->whereIn('transaction_id', $reduceCashIds)
            ->sum('amount');
        // End

        $cashInHand = ($cashTransactionOfSale + $cashTransactionOfPurchaseReturn + $cashTransactionOfSaleOrder + $netCashAdjustment + $remainingReceiveBalance)
            - ($cashTransactionOfSaleReturn + $cashTransactionOfPurchase + $cashTransactionOfPurchaseOrder + $cashTransactionOfExpense + $remainingPayBalance);

        return $this->formatWithPrecision($cashInHand, comma: false);
    }





    /**
     * Retrieve cash transaction
     * */
    function getCashflowRecords(Request $request): JsonResponse
    {
        try {

            // Ensure morph map keys are defined
            $this->paymentTransactionService->usedTransactionTypeValue();

            $dangerTypes = ['Expense', 'Purchase', 'Sale Return', 'Purchase Order'];

            $cashAdjustmentKey = 'Cash Adjustment';

            // Validation rules
            $rules = [
                'from_date'         => ['required', 'date_format:' . implode(',', $this->getDateFormats())],
                'to_date'           => ['required', 'date_format:' . implode(',', $this->getDateFormats())],
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
            $preparedData = PaymentTransaction::with('user', 'paymentType')
                ->where(function ($query) use ($cashId) {
                    $query->where('payment_type_id', $cashId)
                        ->orWhere('transfer_to_payment_type_id', $cashId);
                })
                ->when($request->from_date, function ($query) use ($request) {
                    return $query->where('transaction_date', '>=', $this->toSystemDateFormat($request->from_date));
                })
                ->when($request->to_date, function ($query) use ($request) {
                    return $query->where('transaction_date', '<=', $this->toSystemDateFormat($request->to_date));
                })->get();

            if ($preparedData->count() == 0) {
                throw new \Exception('No Records Found!!');
            }
            $recordsArray = [];

            foreach ($preparedData as $data) {
                $transactionDetails = '';
                $classColor = '';
                $isCashIn = true;

                //If Party Related Cash transaction
                $partyName = $data->transaction->party ? $data->transaction->party->getFullName() : '';

                if (!empty($data->transfer_to_payment_type_id)) {
                    if ($this->paymentTransactionService->getChequeTransactionType($data->transaction_type) == 'Withdraw') {
                        $transactionDetails = 'Cheque Withdraw';
                        $classColor = 'danger';
                        $isCashIn = false;
                    } else {
                        $transactionDetails = 'Cheque Deposit';
                        $classColor = 'success';
                        $isCashIn = true;
                    }
                } else {
                    if ($data->transaction_type == 'Cash Adjustment') {
                        $transactionDetails = $data->transaction_type;
                        $classColor = ($data->transaction->adjustment_type == 'Cash Increase') ? 'success' : 'danger';
                        $isCashIn = ($data->transaction->adjustment_type == 'Cash Increase');
                        $partyName = $data->transaction->adjustment_type;
                    } else {
                        $transactionDetails = $data->transaction_type;

                        // Determine if transaction is cash in or cash out based on type
                        if (in_array($data->transaction_type, $dangerTypes)) {
                            $classColor = 'danger';
                            $isCashIn = false;
                        } else {
                            $classColor = 'success';
                            $isCashIn = true;
                        }
                    }
                }

                $recordsArray[] = [
                    'transaction_date'      => $this->toUserDateFormat($data->transaction_date),
                    'invoice_or_bill_code'  => method_exists($data->transaction, 'getTableCode') ? $data->transaction->getTableCode() : '',
                    'party_name'            => $partyName,
                    'category_name'         => $data->transaction->category ? $data->transaction->category->name : '',
                    'transaction_details'   => $transactionDetails,
                    'class_color'           => $classColor,
                    'cash_in'               => ($isCashIn) ? $this->formatWithPrecision($data->amount, comma: false) : 0,
                    'cash_out'              => (!$isCashIn) ? $this->formatWithPrecision($data->amount, comma: false) : 0,
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














public function datatableListLedger(Request $request)
{
    $cashId      = $this->paymentTypeService->returnPaymentTypeId(PaymentTypesUniqueCode::CASH->value);
    $chequeId    = $this->paymentTypeService->returnPaymentTypeId(PaymentTypesUniqueCode::CHEQUE->value);
    $dangerTypes = ['Expense', 'Purchase', 'Sale Return', 'Purchase Order'];
    $this->paymentTransactionService->usedTransactionTypeValue();
    $fromDate = $request->from_date ? $this->toSystemDateFormat($request->from_date) : null;
    $toDate   = $request->to_date ? $this->toSystemDateFormat($request->to_date) : null;
    $partyId = $request->party_id;
    $partyType = $request->party_type;

    $certainPartyRelatedMorphKeys = ['Party Payment', 'Purchase', 'Sale', 'Sale Return', 'Purchase Return'];
    $allowedTypes = ['Sale', 'Purchase', 'Party Payment', 'Sale Return', 'Purchase Return'];

    $partyFilter = function (EloquentBuilder $q) use ($partyId, $partyType, $certainPartyRelatedMorphKeys) {
        if ($partyId || $partyType) {
            $q->whereHasMorph('transaction', $certainPartyRelatedMorphKeys, function ($r) use ($partyId, $partyType) {
                if ($partyId) {
                    $r->where('party_id', $partyId);
                } elseif ($partyType) {
                    $r->whereHas('party', fn($p) => $p->where('party_type', $partyType));
                }
            });
        }
    };

    $baseQuery = PaymentTransaction::with('transaction.party', 'paymentType')
        ->whereIn('transaction_type', $allowedTypes)
        ->when($fromDate, fn($q) => $q->where('transaction_date', '>=', $fromDate))
        ->when($toDate, fn($q) => $q->where('transaction_date', '<=', $toDate));

    $cashFlow = (clone $baseQuery)
        ->where(function ($q) use ($cashId) {
            $q->where('payment_type_id', $cashId)->orWhere('transfer_to_payment_type_id', $cashId);
        })
        ->when($partyId || $partyType, $partyFilter)
        ->get()
        ->map(fn($row) => tap($row, fn(&$r) => $r->flow_type = 'cash'));

    $bankFlow = (clone $baseQuery)
        ->where(function ($q) use ($cashId, $chequeId) {
            $q->whereNotIn('payment_type_id', [$cashId, $chequeId])
                ->orWhereNotIn('transfer_to_payment_type_id', [$cashId, $chequeId]);
        })
        ->when($partyId || $partyType, $partyFilter)
        ->get()
        ->map(fn($row) => tap($row, fn(&$r) => $r->flow_type = 'bank'));

    $partyTransactions = PartyTransaction::with('party')
        ->when($partyId, fn($q) => $q->where('party_id', $partyId))
        ->when($fromDate, fn($q) => $q->where('transaction_date', '>=', $fromDate))
        ->when($toDate, fn($q) => $q->where('transaction_date', '<=', $toDate))
        ->get()
        ->map(function($row) {
            $row->flow_type = 'Opening';
            $row->amount = ($row->to_pay > 0) ? $row->to_pay : $row->to_receive;
            // The amount is captured in the description string here
            $row->transaction_type = "Opening Balance ($row->amount)";
            $row->type_of_payment = ($row->to_pay > 0) ? 'pay' : 'receive';
            return $row;
        });

    $transactions = $cashFlow->merge($bankFlow)->merge($partyTransactions)
        ->sortBy(fn($row) => $row->transaction_date . ' ' . $row->created_at)
        ->values();

    $balance = 0;
    $finalPartyLedgerBalance = null;
    $finalBalanceStatus = null;
    $finalBalanceClass = '';

    $transactions = $transactions->map(function ($row) use (&$balance, &$finalPartyLedgerBalance, &$finalBalanceStatus, &$finalBalanceClass, $dangerTypes, $partyId) {

        // --- UPDATED LOGIC FOR OPENING BALANCE ---
        if ($row->flow_type === 'Opening') {
            $row->cash_in = 0;
            $row->cash_out = 0;
            // Balance logic for opening: Receive adds to balance, Pay subtracts
            $row->balance = ($row->type_of_payment === 'receive') ? $row->amount : -$row->amount;
        } else {
            $isCashIn = true;
            $type = strtolower(trim($row->type_of_payment ?? ''));
            if ($type === 'receive') $isCashIn = true;
            elseif ($type === 'pay') $isCashIn = false;
            else $isCashIn = !in_array($row->transaction_type, $dangerTypes);

            $row->cash_in = $isCashIn ? $row->amount : 0;
            $row->cash_out = !$isCashIn ? $row->amount : 0;

            // Resolve the actual transaction object (Morph vs Direct)
            $actualTx = ($row instanceof \App\Models\Party\PartyTransaction) ? $row : $row->transaction;
            
            if ($row->transaction_type === 'Sale') {
                $row->balance = ($actualTx->grand_total ?? 0) - $row->cash_in;
            } elseif ($row->transaction_type === 'Purchase') {
                $row->balance = ($actualTx->grand_total ?? 0) - $row->cash_out;
            } else {
                $row->balance = $row->cash_in - $row->cash_out;
            }
        }

        $actualTx = ($row instanceof \App\Models\Party\PartyTransaction) ? $row : $row->transaction;
        $row->grandTotal = $actualTx->grand_total ?? 0;

        if ($actualTx?->party) {
            $row->balanceData = $this->partyService->getPartyBalance([$actualTx->party->id]);
            $rawTotalDue = $row->balanceData['balance'] ?? 0;
            $row->totalDueofCustomer = $this->formatWithPrecision($rawTotalDue);
            
            if ($partyId && $actualTx->party->id == $partyId) {
                $finalPartyLedgerBalance = $rawTotalDue;
                $status = $row->balanceData['status'] ?? '';
                if($status == 'you_collect'){
                    $finalBalanceStatus = 'You Collect'; $finalBalanceClass = 'text-success';
                }elseif($status == 'you_pay'){
                    $finalBalanceStatus = 'You Pay'; $finalBalanceClass = 'text-danger';
                }else{
                    $finalBalanceStatus = 'No Balance'; $finalBalanceClass = '';
                }
            }
        } else {
             $row->totalDueofCustomer = 'N/A';
        }

        // Party Name Assignment
        if ($actualTx?->party) {
            $pType = ucfirst($actualTx->party->party_type);
            $row->party_name = "{$pType} : {$actualTx->party->getFullName()}";
        } else {
            $row->party_name = $actualTx?->category?->name ?? '';
        }

        $row->category_name = $actualTx?->category?->name ?? '';
        $row->products = $actualTx?->itemTransaction?->map(
            fn($itemTx) => $itemTx->item?->name . ' (' . $itemTx->quantity . ')'
        )->implode('<br>') ?? '';

        $invoice = method_exists($actualTx, 'getTableCode') ? $actualTx->getTableCode() : '';
        $row->invoice_or_bill_code = $invoice . ($row->products ? '<br>' . $row->products : '');
        
        // Transaction Details column gets the label we set earlier
        $row->transaction_details = $row->transaction_type;

        return $row;
    });

    $searchValue = $request->input('search')['value'] ?? null;
    if ($searchValue) {
        $lowerSearch = strtolower($searchValue);
        $transactions = $transactions->filter(function ($row) use ($lowerSearch) {
            return stripos($row->party_name ?? '', $lowerSearch) !== false ||
                   stripos($row->transaction_details ?? '', $lowerSearch) !== false ||
                   stripos($row->invoice_or_bill_code ?? '', $lowerSearch) !== false;
        })->values();
    }

    $transactions = $transactions->reverse()->values();
    $sumGrandTotal = $transactions->sum('grandTotal');
    $sumCashIn = $transactions->sum('cash_in');
    $sumCashOut = $transactions->sum('cash_out');
    
    $runningBalance = 0;
    $transactions = $transactions->map(function ($row) use (&$runningBalance) {
        $runningBalance += $row->balance;
        $row->running_balance = $runningBalance;
        return $row;
    });

    return DataTables::of($transactions)
        ->addIndexColumn()
        ->addColumn('transaction_date', fn($row) => $row->transaction_date)
        ->addColumn('flow_type', fn($row) => ucfirst($row->flow_type))
        ->addColumn('invoice_or_bill_code', fn($row) => $row->invoice_or_bill_code ?: 'Adjustment')
        ->addColumn('party_name', fn($row) => $row->party_name)
        ->addColumn('transaction_details', fn($row) => $row->transaction_details)
        ->addColumn('grand_total', fn($row) => $this->formatWithPrecision($row->grandTotal, comma: false))
        ->addColumn('cash_in', fn($row) => $row->flow_type === 'Opening' ? '0.00' : $this->formatWithPrecision($row->cash_in, comma: false))
        ->addColumn('cash_out', fn($row) => $row->flow_type === 'Opening' ? '0.00' : $this->formatWithPrecision($row->cash_out, comma: false))
        ->addColumn('balance', fn($row) => '----')
        ->addColumn('running_balance', fn($row) => $this->formatWithPrecision($row->running_balance, comma: false))
        ->with([
            'total_grand_total' => $this->formatWithPrecision($sumGrandTotal, comma: false),
            'total_cash_in' => $this->formatWithPrecision($sumCashIn, comma: false),
            'total_cash_out' => $this->formatWithPrecision($sumCashOut, comma: false),
            'total_balance' => $partyId && is_numeric($finalPartyLedgerBalance) 
                ? '<span class="' . $finalBalanceClass . '">' . $this->formatWithPrecision($finalPartyLedgerBalance, comma: false) . ' (' . $finalBalanceStatus . ')</span>'
                : '----',
        ])
        ->rawColumns(['invoice_or_bill_code', 'total_balance'])
        ->make(true);
}







}
