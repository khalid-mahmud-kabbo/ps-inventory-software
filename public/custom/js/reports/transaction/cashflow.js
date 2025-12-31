$(function() {
    "use strict";

    const tableId = $('#datatable');
    const datatableForm = $("#datatableForm");

    /**
     * Server Side Datatable Records
     */
    window.loadDatatables = function() {

        // Destroy previous DataTable instance
        if ($.fn.DataTable.isDataTable(tableId)) {
            tableId.DataTable().destroy();
        }

        var exportColumns = [1,2,3,4,5,6,7,8,9]; // Index Starts from 0

        var table = tableId.DataTable({
            processing: true,
            serverSide: true,
            method:'get',

            ajax: {
                url: baseURL + '/transaction/cash/datatable-list-transaction',

                // 💥 FIX IS HERE: Change the 'data' property to a FUNCTION
                data: function (d) {
                    d.from_date = $('input[name="from_date"]').val();
                    d.to_date   = $('input[name="to_date"]').val();
                    d.party_id  = $('#party_id').val();
                    d.party_type = $('#party_type').val();
                }
            },

            columns: [
                { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'transaction_date', name: 'transaction_date' },
                { data: 'flow_type', name: 'flow_type' },
                { data: 'invoice_or_bill_code', name: 'invoice_or_bill_code' },
                { data: 'party_name', name: 'party_name' },
                { data: 'transaction_details', name: 'transaction_details' },
                { data: 'grand_total', name: 'grand_total' },
                { data: 'cash_in', name: 'cash_in' },
                { data: 'cash_out', name: 'cash_out' },
                {
                    data: 'balance',
                    name: 'balance',
                    render: function(data, type, row) {
                        return '<span class="text-' + row.color_class + '">' + data + '</span>';
                    }
                },
            ],

            /**
             * FOOTER TOTALS
             */
            footerCallback: function(row, data, start, end, display) {
                let api = this.api();
                let json = api.ajax.json();

                // If server returned totals, update the footer
                if (json) {
                    $("#total_grand_total").html(json.total_grand_total);
                    $("#total_cash_in").html(json.total_cash_in);
                    $("#total_cash_out").html(json.total_cash_out);
                    $("#total_balance").html(json.total_balance);
                }
            },

            dom:
                "<'row' " +
                    "<'col-sm-12' " +
                        "<'float-start' l>" +
                        "<'float-end' fr>" +
                        "<'float-end ms-2' <'card-body' B>>" +
                    ">" +
                ">" +
                "<'row'<'col-sm-12'tr>>" +
                "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",

            buttons: [
                {
                    extend: 'copyHtml5',
                    exportOptions: { columns: exportColumns }
                },
                {
                    extend: 'excelHtml5',
                    exportOptions: { columns: exportColumns }
                },
                {
                    extend: 'csvHtml5',
                    exportOptions: { columns: exportColumns }
                },
                
                
                
                {
                    extend: 'print',
                    text: 'Print', 
                    orientation: 'landscape', 
                    exportOptions: { columns: exportColumns },
                    footer: true
            },
            ],

            select: {
                style: 'os',
                selector: 'td:first-child'
            },
            order: [[0, 'desc']]
        });

        // Add spacing for table elements
        $('.dataTables_length, .dataTables_filter, .dataTables_info, .dataTables_paginate')
            .wrap("<div class='card-body py-3'>");
    };


    // ... (The rest of your code: datatableForm.on("submit"), ajaxRequest, afterCallAjaxResponse, etc., remains the same) ...

    function ajaxRequest(formArray) {
        // ... (function body remains the same) ...
    }

    function afterCallAjaxResponse(formObject) {
        loadDatatables();
    }

    $(document).ready(function() {
        loadDatatables();
    });

    $(document).on("change", '#party_type, input[name="from_date"], input[name="to_date"]', function() {
        loadDatatables();
    });

});
