@extends('layouts.app')
@section('title', __('app.ladgerbook'))

@section('css')
<link href="{{ asset('assets/plugins/datatable/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet">
@endsection



        @section('content')
        <!--start page wrapper -->
        <div class="page-wrapper">
            <div class="page-content">
                <x-breadcrumb :langArray="[
                                            'app.reports',
                                            'app.ladgerbook',
                                        ]"/>
                 <div class="card">
                    <div class="card-body">
                        <div class="row g-3">
<input type="hidden" id="base_url" value="{{ url('/') }}">

                            <div class="col-md-3">
                                <x-label for="from_date" name="{{ __('app.from_date') }}" />
                                <a tabindex="0" class="text-primary" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Filter by Sale Date"><i class="fadeIn animated bx bx-info-circle"></i></a>
                                <div class="input-group mb-3">
                                    <x-input type="text" additionalClasses="datepicker-edit" name="from_date" :required="true" value=""/>
                                    <span class="input-group-text" id="input-near-focus" role="button"><i class="fadeIn animated bx bx-calendar-alt"></i></span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <x-label for="to_date" name="{{ __('app.to_date') }}" />
                                <a tabindex="0" class="text-primary" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Filter by Sale Date"><i class="fadeIn animated bx bx-info-circle"></i></a>
                                <div class="input-group mb-3">
                                    <x-input type="text" additionalClasses="datepicker-edit" name="to_date" :required="true" value=""/>
                                    <span class="input-group-text" id="input-near-focus" role="button"><i class="fadeIn animated bx bx-calendar-alt"></i></span>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <x-label for="user_id" name="{{ __('customer.user_type') }}" />
                                <select class="party-type form-select" data-placeholder="Select Party Type" id="party_type" name="party_type">
                                    <option value="">Choose One Thing</option>
                                    <option value="Customer">Customer</option>
                                    <option value="Supplier">Supplier</option>
                                </select>
                            </div>


                            <div class="col-md-3 mb-3">
                                            <x-label for="party_id" name="{{ __('customer.account_holder') }}" />
                                            <select class="form-select party-ajax" data-party-type='' data-placeholder="Select Account Holder" id="party_id" name="party_id"></select>
                            </div>


                        </div>

                            <div class="table-responsive">
                                <table class="table table-striped table-bordered border w-100" id="datatable">
                                    <thead>
                                        <tr>
                                                        <th>#</th>
                                                        <th>{{ __('app.date') }}</th>
                                                        <th>{{ __('app.flow_type') }}</th>
                                                        <th>{{ __('app.invoice_or_reference_no') }}</th>
                                                        <th>{{ __('app.name') }}</th>
                                                        <th>{{ __('app.type') }}</th>
                                                        <th>{{ __('app.grand_total') }}</th>
                                                        <th>{{ __('payment.cash_in') }}</th>
                                                        <th>{{ __('payment.cash_out') }}</th>
                                                        <th>{{ __('app.balance') }}</th>
                                        </tr>
                                    </thead>

                                    <tfoot>
    <tr>
        <th colspan="6" class="text-end">Total:</th>
        <th id="total_grand_total"></th>
        <th id="total_cash_in"></th>
        <th id="total_cash_out"></th>
        <th id="total_balance"></th>
    </tr>
</tfoot>

                                </table>
                            </div>
                    </div>
                </div>

                <!--end row-->
            </div>
        </div>
        <!-- Import Modals -->

        @endsection

@section('js')
    @include("plugin.export-table")


    <script>

        $(document).ready(function () {

            // 1. Handler for Party Type change
            $('#party_type').on('change', function () {
                let selectedType = $(this).val();
                let $partySelect = $('#party_id');

                // Set the attribute/data for the AJAX loader (assuming 'party-ajax' uses this)
                $partySelect.attr('data-party-type', selectedType);
                $partySelect.data('party-type', selectedType);

                // Clear the selected value and trigger the change event
                $partySelect.val(null).trigger('change');

                // Crucially, reload the datatables when the PART TYPE is changed
                // (before the user even selects a specific party)
                loadDatatables();
            });

            // 2. Handler for Account Holder (Party) change
            // This is necessary to immediately reload the table after a specific party is chosen.
            $('#party_id').on('change', function () {
                // The partyId is now available to the Datatable AJAX function,
                // so we reload the table to apply the filter.
                loadDatatables();
            });
        });

    </script>

    {{-- <script>

        $(document).ready(function () {
    $('#party_type').on('change', function () {
        let selectedType = $(this).val();
        let $partySelect = $('#party_id');
        $partySelect.attr('data-party-type', selectedType);
        $partySelect.data('party-type', selectedType);
        $partySelect.val(null).trigger('change');
    });
});

    </script> --}}

    <script src="{{ versionedAsset('assets/plugins/datatable/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ versionedAsset('assets/plugins/datatable/js/dataTables.bootstrap5.min.js') }}"></script>
    <script src="{{ versionedAsset('custom/js/common/common.js') }}"></script>
    <script src="{{ versionedAsset('custom/js/reports/transaction/cashflow.js') }}"></script>

@endsection
