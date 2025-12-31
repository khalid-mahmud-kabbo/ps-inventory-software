<?php

namespace App\Http\Controllers;

use App\Models\CashAdjustment;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Models\Order;
use App\Models\Sale\SaleOrder;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Customer;
use App\Models\OrderPayment;
use App\Models\OrderedProduct;
use App\Traits\FormatNumber;

use Illuminate\Support\Number;
use App\Services\PaymentTypeService;
use App\Services\PaymentTransactionService;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleReturn;
use App\Models\Purchase\Purchase;
use App\Models\Purchase\PurchaseReturn;
use App\Models\Party\Party;
use App\Models\Party\PartyTransaction;
use App\Enums\PaymentTypesUniqueCode;
use App\Models\BankAdjustment;
use App\Models\Party\PartyPayment;
use App\Models\Expenses\Expense;
use App\Models\Items\Item;
use App\Models\Items\ItemTransaction;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\Auth;
use App\Services\PartyService;

class DashboardController extends Controller
{
    use formatNumber;

    private $paymentTypeService;

    private $paymentTransactionService;

    public function __construct(public PartyService $partyService, PaymentTypeService $paymentTypeService, PaymentTransactionService $paymentTransactionService)
    {
        $this->paymentTypeService = $paymentTypeService;
        $this->paymentTransactionService = $paymentTransactionService;
    }





public function index(Request $request)
{
    $normalize = function ($date) {
    if (!$date || trim($date) === '') {
        return null; // allow fallback
    }

    // Trim and replace for consistency
    $date = trim(str_replace('-', '/', $date));

    // If exact Y-m-d or Y/m/d
    if (preg_match('/^\d{4}[\/\-]\d{2}[\/\-]\d{2}$/', $date)) {
        return Carbon::parse($date);
    }

    $parts = explode('/', $date);

    if (count($parts) === 3) {
        [$a, $b, $c] = $parts;

        // dd/mm/yyyy
        if ((int)$a > 12 && (int)$b <= 12) {
            return Carbon::createFromFormat('d/m/Y', "$a/$b/$c");
        }

        // mm/dd/yyyy
        if ((int)$b > 12 && (int)$a <= 12) {
            return Carbon::createFromFormat('m/d/Y', "$a/$b/$c");
        }

        // If both <=12 → ambiguous → assume dd/mm/yyyy
        if ((int)$a <= 12 && (int)$b <= 12) {
            return Carbon::createFromFormat('d/m/Y', "$a/$b/$c");
        }
    }

    // Last fallback
    try {
        return Carbon::parse($date);
    } catch (\Exception $e) {
        return null;
    }
};

// FINAL from/to values (perfect fallback)
$from = $normalize($request->from_date) ?? today()->startOfDay();
$to   = $normalize($request->to_date)   ?? today()->endOfDay();

$from = $from->startOfDay();
$to   = $to->endOfDay();



    // Payment Receivables & Payables
    $todaypartyBalance = $this->todaypaymentReceivables($from, $to); // modify this method to accept date range
    // $todayPaymentReceivables = $this->formatWithPrecision($todaypartyBalance['todayreceivable']);
    // $todayPaymentPaybles = $this->formatWithPrecision($todaypartyBalance['todaypayable']);


    // Paid Purchase Amount
    $todayPaidPurchaseAmount = Purchase::query()
        ->whereBetween('purchase_date', [$from, $to])
        ->sum('grand_total');
    $todayPaidPurchaseAmount = $this->formatWithPrecision($todayPaidPurchaseAmount);

    // Paid Purchase Amount
    $todayPaidPurchaseAmountCash = Purchase::query()
        ->whereBetween('purchase_date', [$from, $to])
        ->sum('paid_amount');
    $todayPaidPurchaseAmountCash = $this->formatWithPrecision($todayPaidPurchaseAmountCash);


    // Paid Purchase Due Amount
    $todayPaidPurchaseDue = Purchase::query()
    ->whereBetween('purchase_date', [$from, $to])
    ->sum(DB::raw('grand_total - paid_amount'));

$todayPaidPurchaseDue = $this->formatWithPrecision($todayPaidPurchaseDue);






    // Purchase Returns
    $todayReturnPurchaseAmount = PurchaseReturn::query()
        ->whereBetween('created_at', [$from, $to])
        ->sum('paid_amount');
    $todayReturnPurchaseAmount = $this->formatWithPrecision($todayReturnPurchaseAmount);

    // Paid Sales
    $todayPaidSaleAmount = Sale::query()
        ->whereBetween('created_at', [$from, $to])
        ->sum('grand_total');
    $todayPaidSaleAmount = $this->formatWithPrecision($todayPaidSaleAmount);


    // Paid Sales
    $todayPaidSaleAmountCash = Sale::query()
        ->whereBetween('created_at', [$from, $to])
        ->sum('paid_amount');
    $todayPaidSaleAmountCash = $this->formatWithPrecision($todayPaidSaleAmountCash);





    $todaySaleDue = Sale::query()
    ->whereBetween('created_at', [$from, $to])
    ->sum(DB::raw('grand_total - paid_amount'));

$todaySaleDue = $this->formatWithPrecision($todaySaleDue);




    // Sale Returns
    $todayReturnSaleAmount = SaleReturn::query()
        ->whereBetween('created_at', [$from, $to])
        ->sum('paid_amount');
    $todayReturnSaleAmount = $this->formatWithPrecision($todayReturnSaleAmount);

    // Expenses
    $todayExpense = Expense::query()
        ->whereBetween('created_at', [$from, $to])
        ->sum('grand_total');
    $todayExpense = $this->formatWithPrecision($todayExpense);

    // Recent Invoices
    $recentInvoices = Sale::query()
        ->whereBetween('created_at', [$from, $to])
        ->orderByDesc('id')
        ->limit(10)
        ->get();

    // Trending, Low Stock, Sale vs Purchase
    $saleVsPurchase = $this->saleVsPurchase($from, $to); // modify this method to accept dates
    $trendingItems = $this->trendingItems($from, $to);   // modify if needed
    $lowStockItems = $this->getLowStockItemRecords();     // probably no date filter needed








    $cashBankId = $this->paymentTypeService->returnPaymentTypeId(PaymentTypesUniqueCode::CASH->value);
$chequeId   = $this->paymentTypeService->returnPaymentTypeId(PaymentTypesUniqueCode::CHEQUE->value);

// Non-cash/cheque transactions
$nonCashTransactions = PaymentTransaction::with('user', 'paymentType')
    ->where(function ($query) use ($cashBankId, $chequeId) {
        $query->whereNotIn('payment_type_id', [$cashBankId, $chequeId])
              ->orWhereNotIn('transfer_to_payment_type_id', [$cashBankId, $chequeId]);
    })
    ->whereBetween('transaction_date', [$from, $to]) // use normalized $from/$to
    ->get();





// Only cash transactions
$cashTransactions = PaymentTransaction::with('user', 'paymentType')
    ->where(function ($query) use ($cashBankId) {
        $query->where('payment_type_id', $cashBankId)
              ->orWhere('transfer_to_payment_type_id', $cashBankId);
    })
    ->whereBetween('transaction_date', [$from, $to]) // use normalized $from/$to
    ->get();









$duepaidCond = ['Purchase', 'Party Payment'];
$invoiceIdentifierDue = ['INVOICE_LIST', 'PARTY_BALANCE_AFTER_ADJUSTMENT'];
$pay = ['pay'];

$todayPaymentPaybles = PaymentTransaction::query()
    ->whereBetween('created_at', [$from, $to])
    ->whereIn('transaction_type', $duepaidCond)
    ->whereIn('payment_from_unique_code', $invoiceIdentifierDue)
    ->when(!empty($pay), function ($q) {
        $q->where(function ($sub) {
            $sub->whereRaw('LOWER(TRIM(type_of_payment)) = "pay"')
                ->orWhereNull('type_of_payment')
                ->orWhere('type_of_payment', '=', '');
        });
    })
    ->sum('amount');

$todayPaymentPaybles = $this->formatWithPrecision($todayPaymentPaybles);



$paidCond = ['Sale', 'Party Payment'];
$received = ['receive'];

$todayPaymentReceivables = PaymentTransaction::query()
    ->whereBetween('created_at', [$from, $to])
    ->whereIn('transaction_type', $paidCond)
    ->whereIn('payment_from_unique_code', $invoiceIdentifierDue)
    ->when(!empty($received), function ($q) {
        $q->where(function ($sub) {
            $sub->whereRaw('LOWER(TRIM(type_of_payment)) = "receive"')
                ->orWhereNull('type_of_payment')
                ->orWhere('type_of_payment', '=', '');
        });
    })

    ->sum('amount');

$todayPaymentReceivables = $this->formatWithPrecision($todayPaymentReceivables);














$cashinbankRaw = $this->returnCashInBankValue($from, $to);
$cashinhandRaw = $this->returnCashInHandValue($from, $to);

$cashandbankRaw = $cashinbankRaw + $cashinhandRaw;



$cashinbank   = $this->formatWithPrecision($cashinbankRaw);
$cashinhand   = $this->formatWithPrecision($cashinhandRaw);
$cashandbank  = $this->formatWithPrecision($cashandbankRaw);




$todayPaidPurchaseCash = $todayPaidPurchaseAmountCash;
$todaySaleCash = $todayPaidSaleAmountCash;








    $from_date = $from->format('d-m-y');
    $to_date = $to->format('d-m-y');

    return view('dashboard', compact(
        'saleVsPurchase',
        'trendingItems',
        'lowStockItems',
        'recentInvoices',
        'todayExpense',
        'todayPaidPurchaseAmount',
        'todayPaidSaleAmount',
        'todayReturnPurchaseAmount',
        'todayReturnSaleAmount',
        'todayPaymentPaybles',
        'todayPaymentReceivables',
        'todaySaleCash',
        'todaySaleDue',
        'todayPaidPurchaseCash',
        'todayPaidPurchaseDue',
        'cashandbank',
        'cashinhand',
        'cashinbank',
        'from_date',
        'to_date'
    ));
}




 public function returnCashInBankValue($from_date = null, $to_date = null)
{
    // Ensure morph map keys are defined
    $this->paymentTransactionService->usedTransactionTypeValue();

    $cashId = $this->paymentTypeService->returnPaymentTypeId(PaymentTypesUniqueCode::BANK->value);

    // convert incoming dates to system format (or null)
    $from = $from_date;
    $to   = $to_date;

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

    // return $this->formatWithPrecision($cashInBank);
    return $cashInBank;


}






 public function returnCashInHandValue($from_date = null, $to_date = null)
{
    // Ensure morph map keys are defined
    $this->paymentTransactionService->usedTransactionTypeValue();

    $cashId = $this->paymentTypeService->returnPaymentTypeId(PaymentTypesUniqueCode::CASH->value);

    // convert incoming dates to system format (or null)
    $from = $from_date;
    $to   = $to_date;

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
        ->when($from, fn($q) => $q->where('adjustment_date','>=',$from))
        ->when($to,   fn($q) => $q->where('adjustment_date','<=',$to))
        ->pluck('id');

    $reduceCashIds = CashAdjustment::where('adjustment_type', 'Cash Reduce')
        ->when($from, fn($q) => $q->where('adjustment_date','>=',$from))
        ->when($to,   fn($q) => $q->where('adjustment_date','<=',$to))
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

    // return $this->formatWithPrecision($cashInHand, comma: false);
    return $cashInHand;
}










    public function saleVsPurchase()
    {
        $labels = [];
        $sales = [];
        $purchases = [];

        $now = now();
        for ($i = 0; $i < 6; $i++) {
            $month = $now->copy()->subMonths($i)->format('M Y');
            $labels[] = $month;

            // Get value for this month, e.g. from database
            $sales[] = Sale::whereMonth('sale_date', $now->copy()->subMonths($i)->month)
                ->whereYear('sale_date', $now->copy()->subMonths($i)->year)
                ->when(auth()->user()->can('dashboard.can.view.self.dashboard.details.only'), function ($query) {
                    return $query->where('created_by', auth()->user()->id);
                })
                ->count();

            $purchases[] = Purchase::whereMonth('purchase_date', $now->copy()->subMonths($i)->month)
                ->whereYear('purchase_date', $now->copy()->subMonths($i)->year)
                ->when(auth()->user()->can('dashboard.can.view.self.dashboard.details.only'), function ($query) {
                    return $query->where('created_by', auth()->user()->id);
                })
                ->count();
        }

        $labels = array_reverse($labels);
        $sales = array_reverse($sales);
        $purchases = array_reverse($purchases);

        $saleVsPurchase = [];

        for ($i = 0; $i < count($labels); $i++) {
            $saleVsPurchase[] = [
                'label'     => $labels[$i],
                'sales'     => $sales[$i],
                'purchases' => $purchases[$i],
            ];
        }

        return $saleVsPurchase;
    }

    public function trendingItems(): array
    {
        // Get top 4 trending items (adjust limit as needed)
        return ItemTransaction::query()
            ->select([
                'items.name',
                DB::raw('SUM(item_transactions.quantity) as total_quantity')
            ])
            ->join('items', 'items.id', '=', 'item_transactions.item_id')
            ->where('item_transactions.transaction_type', getMorphedModelName(Sale::class))
            ->when(auth()->user()->can('dashboard.can.view.self.dashboard.details.only'), function ($query) {
                return $query->where('item_transactions.created_by', auth()->user()->id);
            })
            ->groupBy('item_transactions.item_id', 'items.name')
            ->orderByDesc('total_quantity')
            ->limit(4)
            ->get()
            ->toArray();
    }


    public function paymentReceivables()
    {
        $customerIds = Party::where('party_type', 'customer')->pluck('id');
        $supplierIds = Party::where('party_type', 'supplier')->pluck('id');

        $customerIds = $customerIds->toArray();
        $supplierIds = $supplierIds->toArray();

        $customerBalance = $this->partyService->getPartyBalance($customerIds);
        $supplierBalance = $this->partyService->getPartyBalance($supplierIds);

        return [
            'payable' => abs($supplierBalance['balance']),
            'receivable' => abs($customerBalance['balance']),
        ];
    }




    public function todaypaymentReceivables()
    {

        $customerIds = Party::where('party_type', 'customer')->pluck('id')->toArray();
        $supplierIds = Party::where('party_type', 'supplier')->pluck('id')->toArray();

        // Call the new service method to get TODAY's net activity
        $customerMovement = $this->partyService->getPartyDailyMovement($customerIds);
        $supplierMovement = $this->partyService->getPartyDailyMovement($supplierIds);

        // Note: The logic here assumes that for customers, a positive movement means a new receivable,
        // and for suppliers, a negative movement means a new payable.

        return [
            // Total NEW payables created today (suppliers)
            'todaypayable' => abs($supplierMovement['balance']),
            // Total NEW receivables created today (customers)
            'todayreceivable' => abs($customerMovement['balance']),
        ];
    }



    function getLowStockItemRecords()
    {
        return Item::with('baseUnit')
            ->whereColumn('current_stock', '<=', 'min_stock')
            ->where('min_stock', '>', 0)
            ->orderByDesc('current_stock')
            ->limit(10)->get();
    }
}
