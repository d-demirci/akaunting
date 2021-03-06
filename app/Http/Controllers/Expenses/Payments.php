<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expense\Payment as Request;
use App\Models\Banking\Account;
use App\Models\Expense\Payment;
use App\Models\Expense\Vendor;
use App\Models\Setting\Category;
use App\Models\Setting\Currency;
use App\Traits\Uploads;
use App\Utilities\ImportFile;
use App\Utilities\Modules;

class Payments extends Controller
{
    use Uploads;

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $payments = Payment::with(['vendor', 'account', 'category'])->collect(['paid_at'=> 'desc']);

        $vendors = collect(Vendor::enabled()->pluck('name', 'id'))
            ->prepend(trans('general.all_type', ['type' => trans_choice('general.vendors', 2)]), '');

        $categories = collect(Category::enabled()->type('expense')->pluck('name', 'id'))
            ->prepend(trans('general.all_type', ['type' => trans_choice('general.categories', 2)]), '');

        $accounts = collect(Account::enabled()->pluck('name', 'id'))
            ->prepend(trans('general.all_type', ['type' => trans_choice('general.accounts', 2)]), '');

        $transfer_cat_id = Category::transfer();

        return view('expenses.payments.index', compact('payments', 'vendors', 'categories', 'accounts', 'transfer_cat_id'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        $accounts = Account::enabled()->pluck('name', 'id');

        $currencies = Currency::enabled()->pluck('name', 'code')->toArray();

        $account_currency_code = Account::where('id', setting('general.default_account'))->pluck('currency_code')->first();

        $vendors = Vendor::enabled()->pluck('name', 'id');

        $categories = Category::enabled()->type('expense')->pluck('name', 'id');

        $payment_methods = Modules::getPaymentMethods();

        return view('expenses.payments.create', compact('accounts', 'currencies', 'account_currency_code', 'vendors', 'categories', 'payment_methods'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     *
     * @return Response
     */
    public function store(Request $request)
    {
        // Get currency object
        $currency = Currency::where('code', $request['currency_code'])->first();

        $request['currency_code'] = $currency->code;
        $request['currency_rate'] = $currency->rate;

        $payment = Payment::create($request->input());

        // Upload attachment
        $media = $this->getMedia($request->file('attachment'), 'payments');

        if ($media) {
            $payment->attachMedia($media, 'attachment');
        }

        $message = trans('messages.success.added', ['type' => trans_choice('general.payments', 1)]);

        flash($message)->success();

        return redirect('expenses/payments');
    }

    /**
     * Duplicate the specified resource.
     *
     * @param  Payment  $payment
     *
     * @return Response
     */
    public function duplicate(Payment $payment)
    {
        $clone = $payment->duplicate();

        $message = trans('messages.success.duplicated', ['type' => trans_choice('general.payments', 1)]);

        flash($message)->success();

        return redirect('expenses/payments/' . $clone->id . '/edit');
    }

    /**
     * Import the specified resource.
     *
     * @param  ImportFile  $import
     *
     * @return Response
     */
    public function import(ImportFile $import)
    {
        $rows = $import->all();

        foreach ($rows as $row) {
            $data = $row->toArray();
            $data['company_id'] = session('company_id');

            Payment::create($data);
        }

        $message = trans('messages.success.imported', ['type' => trans_choice('general.payments', 2)]);

        flash($message)->success();

        return redirect('expenses/payments');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  Payment  $payment
     *
     * @return Response
     */
    public function edit(Payment $payment)
    {
        $accounts = Account::enabled()->pluck('name', 'id');

        $currencies = Currency::enabled()->pluck('name', 'code')->toArray();

        $account_currency_code = Account::where('id', $payment->account_id)->pluck('currency_code')->first();

        $vendors = Vendor::enabled()->pluck('name', 'id');

        $categories = Category::enabled()->type('expense')->pluck('name', 'id');

        $payment_methods = Modules::getPaymentMethods();

        return view('expenses.payments.edit', compact('payment', 'accounts', 'currencies', 'account_currency_code', 'vendors', 'categories', 'payment_methods'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Payment  $payment
     * @param  Request  $request
     *
     * @return Response
     */
    public function update(Payment $payment, Request $request)
    {
        // Get currency object
        $currency = Currency::where('code', $request['currency_code'])->first();

        $request['currency_code'] = $currency->code;
        $request['currency_rate'] = $currency->rate;

        $payment->update($request->input());

        // Upload attachment
        if ($request->file('attachment')) {
            $media = $this->getMedia($request->file('attachment'), 'payments');

            $payment->attachMedia($media, 'attachment');
        }

        $message = trans('messages.success.updated', ['type' => trans_choice('general.payments', 1)]);

        flash($message)->success();

        return redirect('expenses/payments');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  Payment  $payment
     *
     * @return Response
     */
    public function destroy(Payment $payment)
    {
        // Can't delete transfer payment
        if ($payment->category->id == Category::transfer()) {
            return redirect('expenses/payments');
        }

        $payment->delete();

        $message = trans('messages.success.deleted', ['type' => trans_choice('general.payments', 1)]);

        flash($message)->success();

        return redirect('expenses/payments');
    }
}
