@extends('layouts.app')
@section('title', __('payment.bank_statement'))

@section('css')
<link href="{{ asset('assets/plugins/datatable/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet">
@endsection

        @section('content')
        <!--start page wrapper -->
        <div class="page-wrapper">
            <div class="page-content">
                <x-breadcrumb :langArray="[
                                            'app.reports',
                                            'payment.bank_statement',
                                        ]"/>

                 <div class="card">
                    <div class="card-body">
                        <div class="row g-3">
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
                        </div>

                            <div class="table-responsive">
                                <table class="table table-striped table-bordered border w-100" id="datatable">
                                    <thead>
                                        <tr>
                                                        <th>#</th>
                                                        <th>{{ __('app.date') }}</th>
                                                        <th>{{ __('app.invoice_or_reference_no') }}</th>
                                                        <th>{{ __('app.name') }}</th>
                                                        <th>{{ __('app.type') }}</th>
                                                        <th>{{ __('payment.withdrawal_amount') }}</th>
                                                        <th>{{ __('payment.deposit_amount') }}</th>
                                                        <th>{{ __('app.balance') }}</th>
                                        </tr>
                                    </thead>
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
    <script src="{{ versionedAsset('assets/plugins/datatable/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ versionedAsset('assets/plugins/datatable/js/dataTables.bootstrap5.min.js') }}"></script>
    <script src="{{ versionedAsset('custom/js/common/common.js') }}"></script>
    <script src="{{ versionedAsset('custom/js/reports/transaction/bank-statement.js') }}"></script>

@endsection
