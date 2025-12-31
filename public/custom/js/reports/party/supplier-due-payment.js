$(function() {
    "use strict";

    let originalButtonText;

    const tableId = $('#duePaymentReport');
    const datatableForm = $("#reportForm");
    let partyPaymentHistoryModal = $('#partyPaymentHistoryModal');

    /**
     * Language
     * */
    const _lang = {
                total : "Total",
                noRecordsFound : "No Records Found!!",
            };

    $("#reportForm").on("submit", function(e) {
        e.preventDefault();
        const form = $(this);
        const formArray = {
            formId: form.attr("id"),
            csrf: form.find('input[name="_token"]').val(),
            url: form.closest('form').attr('action'),
            formObject : form,
        };
        ajaxRequest(formArray);
    });

    function disableSubmitButton(form) {
        originalButtonText = form.find('button[type="submit"]').text();
        form.find('button[type="submit"]')
            .prop('disabled', true)
            .html('  <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>Loading...');
    }

    function enableSubmitButton(form) {
        form.find('button[type="submit"]')
            .prop('disabled', false)
            .html(originalButtonText);
    }

    function beforeCallAjaxRequest(formObject){
        disableSubmitButton(formObject);
        showSpinner();
    }
    function afterCallAjaxResponse(formObject){
        enableSubmitButton(formObject);
        hideSpinner();
    }
    function afterSeccessOfAjaxRequest(formObject, response){
        formAdjustIfSaveOperation(response);
    }
    function afterFailOfAjaxRequest(formObject){
        showNoRecordsMessageOnTableBody();
    }

    function ajaxRequest(formArray){
        var formData = new FormData(document.getElementById(formArray.formId));
        var jqxhr = $.ajax({
            type: 'POST',
            url: formArray.url,
            data: formData,
            dataType: 'json',
            contentType: false,
            processData: false,
            headers: {
                'X-CSRF-TOKEN': formArray.csrf
            },
            beforeSend: function() {
                // Actions to be performed before sending the AJAX request
                if (typeof beforeCallAjaxRequest === 'function') {
                    beforeCallAjaxRequest(formArray.formObject);
                }
            },
        });
        jqxhr.done(function(response) {
            // Actions to be performed after response from the AJAX request
            if (typeof afterSeccessOfAjaxRequest === 'function') {
                afterSeccessOfAjaxRequest(formArray.formObject, response);
            }
        });
        jqxhr.fail(function(response) {
            var message = response.responseJSON.message;
            iziToast.error({title: 'Error', layout: 2, message: message});
            if (typeof afterFailOfAjaxRequest === 'function') {
                afterFailOfAjaxRequest(formArray.formObject);
            }
        });
        jqxhr.always(function() {
            // Actions to be performed after the AJAX request is completed, regardless of success or failure
            if (typeof afterCallAjaxResponse === 'function') {
                afterCallAjaxResponse(formArray.formObject);
            }
        });
    }


     $(document).on('click', '.party-payment-history', function() {
        var partyId = $(this).attr('data-party-id');
        var url = baseURL + `/party/payment-history/`;
        ajaxGetRequest(url ,partyId, 'party-payment-history');
    });




    function formAdjustIfSaveOperation(response){
        var tableBody = tableId.find('tbody');

        var id = 1;
        var tr = "";

        var totalReceivableAmount = parseFloat(0);
        var totalPayableAmount = parseFloat(0);

        $.each(response.data, function(index, party) {
            totalReceivableAmount += parseFloat(party.due_amount < 0 ? party.due_amount : 0);
            totalPayableAmount += parseFloat(party.due_amount >0 ? party.due_amount : 0);

            tr  +=`
                <tr>
                    <td>${id++}</td>
                    <td>${party.party_name}</td>
                    <td>${party.party_phone}</td>
                    <td>${party.party_email}</td>
                    <td>${party.party_whatsapp}</td>
                    <td>${party.party_note}</td>
                    <td class='text-end' data-tableexport-celltype="number" >${_formatNumber(party.due_amount < 0 ? -party.due_amount : 0)}</td>
                    <td class='text-end' data-tableexport-celltype="number" >${_formatNumber(party.due_amount > 0 ? party.due_amount : 0)}</td>
                    <td class='${party.due_amount <= 0 ? 'text-success' : 'text-danger'}'>${party.status }</td>

                    <td class="text-center">
                        <div class="d-flex order-actions justify-content-center">
                            <a href="javascript:;" role="button" 
                               class="text-info party-payment-history" 
                               data-party-id="${party.party_id}"
                               data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Payment History">
                                <i class="bx bx-history"></i>
                            </a>
                        </div>
                    </td>
                </tr>
            `;
        });

        tr  +=`
            <tr class='fw-bold'>
                <td colspan='0' class='text-end tfoot-first-td'>${_lang.total}</td>
                <td class='text-end' data-tableexport-celltype="number">${_formatNumber(-totalReceivableAmount)}</td>
                <td class='text-end' data-tableexport-celltype="number">${_formatNumber(totalPayableAmount)}</td>
            </tr>
        `;

        // Clear existing rows:
        tableBody.empty();
        tableBody.append(tr);

        /**
         * Set colspan of the table bottom
         * */
        $('.tfoot-first-td').attr('colspan', columnCountWithoutDNoneClass(1)-2);
    }

    function showNoRecordsMessageOnTableBody() {
        var tableBody = tableId.find('tbody');

        var tr = "<tr class='fw-bold'>";
        tr += `<td colspan='0' class='text-end tfoot-first-td text-center'>${_lang.noRecordsFound}</td>"`;
        tr += "</tr>";

        tableBody.empty();
        tableBody.append(tr);

        /**
         * Set colspan of the table bottom
         * */
        $('.tfoot-first-td').attr('colspan', columnCountWithoutDNoneClass(0));
    }
    function columnCountWithoutDNoneClass(minusCount) {
        return tableId.find('thead > tr:first > th').not('.d-none').length - minusCount;
    }









    function ajaxGetRequest(url, id, _from) {
          $.ajax({
            url: url + id,
            type: 'GET',
            headers: {
              'X-CSRF-TOKEN': datatableForm.find('input[name="_token"]').val(),
            },
            beforeSend: function() {
              showSpinner();
            },
            success: function(response) {
              if(_from == 'delete-party-payment'){
                handlePartyPaymentDeleteResponse(response);
              }
              else if (_from == 'party-payment-history') {
                handlePartyPaymentHistoryResponse(response);
              } else {
                //
              }
            },
            error: function(response) {
               var message = response.responseJSON.message;
               iziToast.error({title: 'Error', layout: 2, message: message});
            },
            complete: function() {
              hideSpinner();
            },
          });
    }

    function handlePartyPaymentHistoryResponse(response, showModel = true) {
        $("#party-name").text(response.party_name);
        $("#balance-amount").text(_parseFix(response.balance));

        let totalAmount = 0;
        
        var table = $('#payment-history-table tbody');

        table.empty(); // Clear existing rows
        
        $.each(response.partyPayments, function(index, payment) {
            totalAmount += parseFloat(payment.amount);
            var newRow = `
                <tr id="${payment.payment_id}">
                    <td>${payment.transaction_date}</td>
                    <td>${payment.payment_direction}</td>
                    <td>${payment.reference_no}</td>
                    <td>${payment.payment_type}</td>
                    <td class="text-end text-${payment.color}">${payment.amount}</td>
                    <td>
                        <div class="d-flex order-actions justify-content-center">
                            <a href="${baseURL}/party/payment-receipt/print/${payment.payment_id}" target="_blank" class="text-primary" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Print"><i class="bx bxs-printer"></i></a>
                            <a href="${baseURL}/party/payment-receipt/pdf/${payment.payment_id}" target="_blank" class="ms-1 text-success" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="PDF"><i class="bx bxs-file-pdf"></i></a>
                        </div>
                    </td>
                </tr>
            `;

            table.append(newRow);
        });

        //show only if not shown, in delete payment condition no need to show modal
        if(showModel){
            partyPaymentHistoryModal.modal('show');
        }

        setTooltip();
    }



    function handlePartyPaymentDeleteResponse(response) {
        iziToast.success({title: 'Success', layout: 2, message: response.message});
        partyPaymentHistoryModal.modal('hide');
        loadDatatables();
    }



    /**
     *
     * Table Exporter
     * PDF, SpreadSheet
     * */
    $(document).on("click", '#generate_pdf', function() {
        tableId.tableExport({type:'pdf',escape:'false', fileName: 'Supplier-Due-Payments-Report'});
    });

    $(document).on("click", '#generate_excel', function() {
        tableId.tableExport({
            formats: ["xlsx"],
            fileName: 'Supplier-Due-Payments-Report',
            xlsx: {
                onCellFormat: function (cell, e) {
                    if (typeof e.value === 'string') {
                        // Remove commas and convert to number
                        var numValue = parseFloat(e.value.replace(/,/g, ''));
                        if (!isNaN(numValue)) {
                            return numValue;
                        }
                    }
                    return e.value;
                }
            }
        });
    });

});//main function
