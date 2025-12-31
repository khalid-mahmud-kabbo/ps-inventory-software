$(function() {
    "use strict";

    const tableId = $('#datatable');
    const datatableForm = $("#datatableForm");

    /**
     * Server Side Datatable Records
     */
    window.loadDatatables = function() {
        // Destroy previous instance
        tableId.DataTable().destroy();

        const exportColumns = [1,2,3,4,5,6,7,8]; // Index starts from 0

        const table = tableId.DataTable({
            processing: true,
            serverSide: true,
            method: 'get',
            ajax: {
                url: baseURL + '/transaction/bank/datatable-list',
                data: {
                    party_id: $('#party_id').val(),
                    user_id: $('#user_id').val(),
                    from_date: $('input[name="from_date"]').val(),
                    to_date: $('input[name="to_date"]').val(),
                },
            },
            columns: [
                { targets: 0, data: 'id', orderable: true, visible: false },
                { data: 'transaction_type', name: 'transaction_type' },
                { data: 'transaction_date', name: 'transaction_date' },
                { data: 'party_name', name: 'party_name' },
                {
                    data: 'amount',
                    name: 'amount',
                    render: function(data, type, row) {
                        return '<span class="text-' + row.color_class + '">' + data + '</span>';
                    }
                },
                { data: 'username', name: 'username' },
                { data: 'action', name: 'action', orderable: false, searchable: false },
            ],
            dom: "<'row'<'col-sm-12'<'float-start'l><'float-end fr'><'float-end ms-2'<'card-body'B>>>>" +
                 "<'row'<'col-sm-12'tr>>" +
                 "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
            buttons: [
                {
                    className: 'btn btn-outline-danger buttons-copy buttons-html5 multi_delete',
                    text: 'Delete',
                    action: function(e, dt, node, config) {
                        requestDeleteRecords();
                    }
                },
                { extend: 'copyHtml5', exportOptions: { columns: exportColumns } },
                { extend: 'excelHtml5', exportOptions: { columns: exportColumns } },
                { extend: 'csvHtml5', exportOptions: { columns: exportColumns } },
                { extend: 'pdfHtml5', orientation: 'portrait', exportOptions: { columns: exportColumns } },
            ],
            select: { style: 'os', selector: 'td:first-child' },
            order: [[0, 'desc']]
        });

        // Update Cash in Bank total whenever table data is loaded
        table.on('xhr', function() {
            const json = table.ajax.json();
            if (json && json.cash_in_bank_total !== undefined) {
                const total = parseFloat(json.cash_in_bank_total);
                const el = $('#cashInBankTotal');
                el.text(total);
                el.removeClass('text-success text-danger')
                  .addClass(total >= 0 ? 'text-success' : 'text-danger');
            }
        });

        // Delete single record
        table.on('click', '.deleteRequest', function () {
            const deleteId = $(this).attr('data-delete-id');
            deleteRequest(deleteId);
        });

        // Styling
        $('.dataTables_length, .dataTables_filter, .dataTables_info, .dataTables_paginate')
            .wrap("<div class='card-body py-3'>");
    }

    // Header checkbox
    tableId.find('thead').on('click', '.row-select', function() {
        const isChecked = $(this).prop('checked');
        tableId.find('tbody .row-select').prop('checked', isChecked);
    });

    // Count checked checkboxes
    function countCheckedCheckbox() {
        return $('input[name="record_ids[]"]:checked').length;
    }

    // Validate checked checkboxes
    async function validateCheckedCheckbox() {
        const confirmed = await confirmAction();
        if (!confirmed) return false;
        if (countCheckedCheckbox() === 0) {
            iziToast.error({ title: 'Warning', layout: 2, message: "Please select at least one record to delete" });
            return false;
        }
        return true;
    }

    // Single delete request
    async function deleteRequest(id) {
        const confirmed = await confirmAction();
        if (confirmed) deleteRecord(id);
    }

    // Multiple delete request
    async function requestDeleteRecords() {
        const confirmed = await confirmAction();
        if (confirmed) datatableForm.trigger('submit');
    }

    // Form submit
    datatableForm.on("submit", function(e) {
        e.preventDefault();
        const form = $(this);
        const formArray = {
            formId: form.attr("id"),
            csrf: form.find('input[name="_token"]').val(),
            _method: form.find('input[name="_method"]').val(),
            url: form.closest('form').attr('action'),
            formObject: form,
            formData: new FormData(document.getElementById(form.attr("id")))
        };
        ajaxRequest(formArray);
    });

    // Delete record
    function deleteRecord(id) {
        const form = datatableForm;
        const formArray = {
            formId: form.attr("id"),
            csrf: form.find('input[name="_token"]').val(),
            _method: form.find('input[name="_method"]').val(),
            url: form.closest('form').attr('action'),
            formObject: form,
            formData: new FormData()
        };
        formArray.formData.append('record_ids[]', id);
        ajaxRequest(formArray);
    }

    // After success AJAX
    function afterSeccessOfAjaxRequest(formObject, response) {
        if (response.cash_in_bank_total !== undefined) {
            const total = parseFloat(response.cash_in_bank_total);
            const el = $('#cashInBankTotal');
            el.text(total);
            el.removeClass('text-success text-danger')
              .addClass(total >= 0 ? 'text-success' : 'text-danger');
        }
    }

    // AJAX request
    function ajaxRequest(formArray) {
        $.ajax({
            type: formArray._method,
            url: formArray.url,
            data: formArray.formData,
            dataType: 'json',
            contentType: false,
            processData: false,
            headers: { 'X-CSRF-TOKEN': formArray.csrf },
        })
        .done(function(response) {
            iziToast.success({ title: 'Success', layout: 2, message: response.message });
            afterSeccessOfAjaxRequest(formArray.formObject, response);
        })
        .fail(function(response) {
            const message = response.responseJSON?.message ?? 'Something went wrong';
            iziToast.error({ title: 'Error', layout: 2, message: message });
        })
        .always(function() {
            afterCallAjaxResponse(formArray.formObject);
        });
    }

    function afterCallAjaxResponse(formObject) {
        loadDatatables();
    }

    $(document).ready(function() {
        loadDatatables();
        initSelect2PaymentType({ dropdownParent: $('#invoicePaymentModal') });
    });

    $(document).on("change", '#party_id, #user_id, input[name="from_date"], input[name="to_date"]', function() {
        loadDatatables();
    });

});
