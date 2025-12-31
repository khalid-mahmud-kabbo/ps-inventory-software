$(function() {
	"use strict";

    let originalButtonText;



    let openModal = $('#bankAdjustmentModal');

    const makePaymentForm = $("#bankAdjustmentForm");

    const paymentFor = $("#payment_for").val();//purchase, purchase_return, sale, sale return


    makePaymentForm.on("submit", function(e) {
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
    }
    function afterCallAjaxResponse(formObject){
        enableSubmitButton(formObject);
    }
    function afterSeccessOfAjaxRequest(formObject, response){
        formAdjustIfSaveOperation(formObject);
        closeModalAndAddOption(response);
        setCashInBankValue(response.cashInBank);
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
            iziToast.success({title: 'Success', layout: 2, message: response.message});
            // Actions to be performed after response from the AJAX request
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

    function formAdjustIfSaveOperation(formObject){
        loadDatatables();
    }
    function closeModalAndAddOption(response){
        /*Close the Model*/
        openModal.modal('hide');
    }

    $(document).on('click', '.make-bank-adjustment', function() {

        console.log('make-bank-adjustment');
        handleBankAdjustment();
    });

    $(document).on('click', '.edit-bank-adjustment', function() {
        var transactionId = $(this).attr('data-bank-adjustment-id');
        var url = baseURL + `/transaction/bank/adjustment/get/`;
        ajaxGetRequest(url, transactionId, 'make-bank-adjustment');
    });

    function returnCashInBankValue(){
        var url = baseURL + `/transaction/get/cash-in-bank`;
        ajaxGetRequest(url, '', 'get-cash-in-bank-value');
    }

    window.setCashInBankValue = function(amount = 0) {
        $(".cash-in-bank").html(_parseFix(amount));
    }

    function ajaxGetRequest(url, id, _from) {
          $.ajax({
            url: url + id,
            type: 'GET',
            headers: {
              'X-CSRF-TOKEN': makePaymentForm.find('input[name="_token"]').val(),
            },
            beforeSend: function() {
              showSpinner();
            },
            success: function(response) {
                if(_from == 'make-bank-adjustment'){
                    handleBankAdjustment(response.data);
                }
                else if( _from == 'get-cash-in-bank-value'){
                    setCashInBankValue(response);
                }else{
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

    function handleBankAdjustment(data = getDefaultEmptyData()) {

        console.log('handleBankAdjustment');
        //Adjustment Type Selection box
        makePaymentForm.find('select[name="adjustment_type"]').val(data.adjustment_type);

        //Adjustment date
        makePaymentForm.find('input[name="adjustment_date"]').val(data.adjustment_date);
        if(data.operation == 'save'){
            //Set current date
            makePaymentForm.find('input[name="adjustment_date"]').val(data.adjustment_date).flatpickr({
                dateFormat: dateFormatOfApp,//Defined in script.js
                defaultDate: new Date(),
            });;
        }
        //amount
        makePaymentForm.find('input[name="amount"]').val(data.amount);

        //Note
        makePaymentForm.find('textarea[name="note"]').val(data.note);

        makePaymentForm.find('input[name="bank_adjustment_id"]').val(data.adjustment_id);

        openModal.modal('show');
    }

    function getDefaultEmptyData() {
        return {
            'adjustment_type' : 'Bank Increase',
            'adjustment_date' : '',
            'amount' : _parseFix(0),
            'note' : '',
            'adjustment_id' : '',
            'operation' : 'save',
        }
    }




});//main function
