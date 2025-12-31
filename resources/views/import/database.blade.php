@extends('layouts.app')
@section('title', __('item.import_items'))

@section('content')
<!--start page wrapper -->
<div class="page-wrapper">
    <div class="page-content">
        <x-breadcrumb :langArray="[
                                    'app.utilities',
                                    'item.import_database',
                                ]"/>

        <div class="row">
            <form class="row g-3 needs-validation" id="importForm"
                  action="{{ route('import.database.upload') }}"
                  method="POST"
                  enctype="multipart/form-data">
                @csrf

                <div class="col-12 col-lg-12">
                    @include('layouts.session')

                    <div class="card">
                        <div class="card-header px-4 py-3">
                            <h5 class="mb-0">{{ __('item.import_database') }}</h5>
                        </div>

                        <div class="card-body p-4 row g-3">
                            <!-- SQL File Upload -->
                            <div class="col-md-6">
                                <x-label for="sql_file" name="{{ __('app.browse_file') }}" />
                                <input class="form-control" type="file" id="sql_file" name="sql_file" required>
                            </div>


                        </div>

                        <div class="card-body p-4 row g-3">
                            <div class="col-md-12">
                                <div class="d-md-flex d-grid align-items-center gap-3">
                                    <x-button type="submit" class="primary px-4" text="{{ __('app.import') }}" />
                                    <x-anchor-tag href="{{ route('dashboard') }}" text="{{ __('app.close') }}" class="btn btn-light px-4" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </form>



            <form action="{{ route('import.database.export') }}" method="POST">
    @csrf
    <button type="submit" class="btn btn-outline-primary px-5 radius-0">
        {{ __('item.export_database') }}
    </button>
</form>



        </div>
        <!--end row-->
    </div>
</div>
@endsection

@section('js')
    @include("plugin.export-table")
    <script src="{{ versionedAsset('custom/js/import/items.js') }}"></script>
@endsection
