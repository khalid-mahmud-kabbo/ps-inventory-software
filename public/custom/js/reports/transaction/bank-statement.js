$(function() {
	"use strict";

    const tableId = $('#datatable');

    const datatableForm = $("#datatableForm");

    /**
     *Server Side Datatable Records
    */
    window.loadDatatables = function() {
        //Delete previous data
        tableId.DataTable().destroy();

        var exportColumns = [1,2,3,4,5];//Index Starts from 0


        var table = tableId.DataTable({
            processing: true,
            serverSide: true,
            method:'get',
            ajax: {
                    url: baseURL+'/transaction/bank/datatable-list-transaction',
                    data:{
                            from_date : $('input[name="from_date"]').val(),
                            to_date : $('input[name="to_date"]').val(),
                        },
                },
            columns: [
                { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                {data: 'transaction_date', name: 'transaction_date'},
                {data: 'invoice_or_bill_code', name: 'invoice_or_bill_code'},
                {data: 'party_name', name: 'party_name'},
                {data: 'transaction_details', name: 'transaction_details'},
                {data: 'cash_out', name: 'cash_out'},
                {data: 'cash_in', name: 'cash_in'},
                {
                    data: 'balance',
                    name: 'balance',
                      render: function(data, type, row) {
                          // Return HTML with the appropriate class
                          return '<span class="text-' + row.color_class + '">' + data + '</span>';
                      }
                },
            ],

            dom: "<'row' "+
                    "<'col-sm-12' "+
                        "<'float-start' l"+
                            /* card-body class - auto created here */
                        ">"+
                        "<'float-end' fr"+
                            /* card-body class - auto created here */
                        ">"+
                        "<'float-end ms-2'"+
                            "<'card-body ' B >"+
                        ">"+
                    ">"+
                  ">"+
            "<'row'<'col-sm-12'tr>>" +
            "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",

            buttons: [
                // Apply exportOptions only to Copy button
                {
                    extend: 'copyHtml5',
                    exportOptions: {
                        columns: exportColumns
                    }
                },
                // Apply exportOptions only to Excel button
                {
                    extend: 'excelHtml5',
                    exportOptions: {
                        columns: exportColumns
                    }
                },
                // Apply exportOptions only to CSV button
                {
                    extend: 'csvHtml5',
                    exportOptions: {
                        columns: exportColumns
                    }
                },
                // Apply exportOptions only to PDF button
                {
                    extend: 'pdfHtml5',
                    orientation: 'portrait',//or "landscape"
                    exportOptions: {
                        columns: exportColumns,
                    },
                },

            ],

            select: {
                style: 'os',
                selector: 'td:first-child'
            },
            order: [[0, 'desc']]


        });



        //Adding Space on top & bottom of the table attributes
        $('.dataTables_length, .dataTables_filter, .dataTables_info, .dataTables_paginate').wrap("<div class='card-body py-3'>");
    }


    /**
     * @return count
     * How many checkbox are checked
    */

    /**
     * Create Ajax Request:
     * Multiple Data Delete
    */

    datatableForm.on("submit", function(e) {
        e.preventDefault();

            //Form posting Functionality
            const form = $(this);
            const formArray = {
                formId: form.attr("id"),
                csrf: form.find('input[name="_token"]').val(),
                _method: form.find('input[name="_method"]').val(),
                url: form.closest('form').attr('action'),
                formObject : form,
                formData : new FormData(document.getElementById(form.attr("id"))),
            };
            ajaxRequest(formArray); //Defined in ./common/common.js

    });

    /**
     * Create AjaxRequest:
     * Single Data Delete
    */


    /**
    * Ajax Request
    */
    function ajaxRequest(formArray){
        var jqxhr = $.ajax({
            type: formArray._method,
            url: formArray.url,
            data: formArray.formData,
            dataType: 'json',
            contentType: false,
            processData: false,
            headers: {
                'X-CSRF-TOKEN': formArray.csrf
            },
            beforeSend: function() {
                // Actions to be performed before sending the AJAX request
                if (typeof beforeCallAjaxRequest === 'function') {
                    // Action Before Proceeding request
                }
            },
        });
        jqxhr.done(function(response) {
            iziToast.success({title: 'Success', layout: 2, message: response.message});
            if (typeof afterSeccessOfAjaxRequest === 'function') {
                afterSeccessOfAjaxRequest(formArray.formObject, response);
            }
        });
        jqxhr.fail(function(response) {
                var message = response.responseJSON.message;
                iziToast.error({title: 'Error', layout: 2, message: message});
        });
        jqxhr.always(function() {
            // Actions to be performed after the AJAX request is completed, regardless of success or failure
            if (typeof afterCallAjaxResponse === 'function') {
                afterCallAjaxResponse(formArray.formObject);
            }
        });
    }

    function afterCallAjaxResponse(formObject){
        loadDatatables();


    }

    $(document).ready(function() {
        //Load Datatable
        loadDatatables();

	} );

    $(document).on("change", '#party_id, #user_id, input[name="from_date"], input[name="to_date"]', function function_name(e) {
        loadDatatables();
    });

});
