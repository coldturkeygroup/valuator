// Application specific scripts
jQuery('document').ready(function ($) {
    // Simple AJAX listeners
    $(document).bind("ajaxSend", function () {
        $('.btn-primary').attr('disabled', 'disabled');
    }).bind("ajaxComplete", function () {
        $('.btn-primary').removeAttr('disabled');
    });

    // Email Validation
    if (Valuator.mailgun !== undefined && Valuator.mailgun !== '') {
        $('#email').mailgun_validator({
            api_key: Valuator.mailgun,
            in_progress: function () {
                $('#email').parent().removeClass('has-warning has-error');
                $(".mailcheck-suggestion").remove();
                $("[type=submit]").addClass("disabled").attr("disabled", "disabled");
            },
            success: function (data) {
                $('#email').after(get_suggestion_str(data['is_valid'], data['did_you_mean']));
            },
            error: function () {
                $("[type=submit]").removeClass("disabled").removeAttr("disabled");
            }
        });
    }
    // Parse Mailgun Responses
    function get_suggestion_str(is_valid, alternate) {
        if (is_valid) {
            if (alternate) {
                $('#email').parent().addClass('has-warning');
                return '<div class="mailcheck-suggestion help-block">Did you mean <a href="#">' + alternate + '</a>?</div>';
            }
            $("[type=submit]").removeClass("disabled").removeAttr("disabled");
            return;
        }
        $('#email').parent().addClass('has-error');
        if (alternate) {
            return '<div class="mailcheck-suggestion help-block">This email is invalid. Did you mean <a href="#">' + alternate + '</a>?</div>';
        }
        return '<div class="mailcheck-suggestion help-block">This email is invalid.</div>';
    }

    $(".form-group").on("click", ".mailcheck-suggestion a", function (e) {
        e.preventDefault();
        $("#email").val($(this).text());
        $("[type=submit]").removeClass("disabled").removeAttr("disabled");
        $(".mailcheck-suggestion").remove();
    });

    // Google Places address autocomplete
    $("#address").geocomplete({
        map: "#map_canvas",
        mapOptions: {
            mapTypeId: 'hybrid',
            disableDefaultUI: true
        }
    }).bind("geocode:result", function (event, result) {
        $('#step-one .btn-primary').removeClass('disabled').removeAttr('disabled');
    });

    // Show offer modal
    $('#get-offer').click(function () {
        $('#valuator-offer').modal('show');

        return false;
    });

    // Simple PubSub
    var o = $({});
    $.subscribe = function () {
        o.on.apply(o, arguments)
    };
    $.publish = function () {
        o.trigger.apply(o, arguments)
    };

    // Submit form via AJAX
    var submitAjax = function (e) {
        var form = $(this);
        var method = form.find('input[name="_method"]').val() || 'POST';

        if (stepVerify(form.attr('id')) == 0) {
            $.ajax({
                type: method,
                url: Valuator.ajaxurl,
                data: form.serialize(),
                dataType: 'json',
                async: true,
                success: function (response) {
                    $.publish('ajax.request.success', [form, response]);
                }
            });
        }

        e.preventDefault();
    };

    // Handle AJAX request callbacks
    $.subscribe('ajax.request.success', function (e, form, response) {
        triggerRequestCallback.apply(form, [e, $(form).data('remote-on-success'), response]);
    });

    // Trigger the registered callback for a click or form submission.
    var triggerRequestCallback = function (e, method, response) {
        var that = $(this);

        if (!(model = that.closest('*[data-model]').data('model'))) {
            return;
        }

        if (typeof window[model] == 'object' && typeof window[model][method] == 'function') {
            window[model][method](that, response);
        } else {
            console.error('Could not call method ' + method + ' on object ' + model);
        }

        e.preventDefault();
    };

    // Dom bindings.
    $('form[data-remote]').on('submit', submitAjax);
    $('*[data-click]').on('click', function (e) {
        triggerRequestCallback.apply(this, [e, $(this).data('click')]);
    });

    // Step one form submission
    window.stepOne = {};
    stepOne.process = function (form, response) {
        $('#property_id').val(response.property_id);
        $('#property_id_complete').val(response.property_id);
        $('#step-one-well').addClass('animated fadeOutLeftBig');
        setTimeout(function () {
            $('#step-one-well').hide();
            $('#step-two-well').show().addClass('animated fadeInRightBig');
            $('.valuation-page').css('padding-top', '0px');
        }, 200);

        setTimeout(function () {
            google.maps.event.trigger($("#map_canvas")[0], 'resize');
            $("#address").geocomplete("find", $('#address').val());

            setTimeout(function () {
                var map = $("#address").geocomplete("map");
                map.setZoom(19);
            }, 500);
        }, 500);
    };

    // Step two form submission
    window.stepTwo = {};
    stepTwo.process = function (form, response) {
        $('#step-two-well').removeClass('fadeInRightBig').addClass('fadeOutLeftBig');
        setTimeout(function () {
            $('#step-two-well').hide();
            $('#step-three-well').show().addClass('animated fadeInRightBig');
            $('.valuation-page').css('padding-top', '0px');
            $('.single-pf_valuator #page').css('min-height', '100%');
            $('.single-pf_valuator #page').css('height', 'auto');
        }, 200);

        // Verify that we received a result
        if (typeof response.error != 'undefined') {
            $('.valuation-value, .step-three-subtitle').remove();
            $('.valuation-result').append('<h4 style="text-align: center;" class="landing-title">Your Home Value Report Will Be Sent Within 48 Hours!</h4>');
        }
        else {
            // Fill in the valuation data
            $('.low').text(response.low);
            $('.estimated-value').text(response.amount);
            $('.high').text(response.high);
            $('.valuation-address').text(response.address);
            $('.page-media').html(response.media);
            if (typeof response.text != 'undefined') {
                $('.page-text').html(response.text);
            } else {
                $('.page-text').remove();
            }
            $('#zip_code_copy').val(response.zip_code);
        }

        // Populate the step three form
        $('#first_name_copy').val($('#first_name').val());
        $('#last_name_copy').val($('#last_name').val());
        $('#email_copy').val($('#email').val());
        $('#address_copy').val(response.street);
        $('#address2_copy').val($('#address_2').val());
        $('#city_copy').val(response.city);
        $('#state_copy').val(response.state);

        // Populate the offer form
        $('#first_name_3').val($('#first_name').val());
        $('#last_name_3').val($('#last_name').val());
        $('#email_3').val($('#email').val());

        // Facebook events
        var retargeting = $('#retargeting').val(),
            conversion = $('#conversion').val();
        if (conversion != '') {
            if (conversion !== retargeting) {
                !function (f, b, e, v, n, t, s) {
                    if (f.fbq)return;
                    n = f.fbq = function () {
                        n.callMethod ?
                            n.callMethod.apply(n, arguments) : n.queue.push(arguments)
                    };
                    if (!f._fbq)f._fbq = n;
                    n.push = n;
                    n.loaded = !0;
                    n.version = '2.0';
                    n.queue = [];
                    t = b.createElement(e);
                    t.async = !0;
                    t.src = v;
                    s = b.getElementsByTagName(e)[0];
                    s.parentNode.insertBefore(t, s)
                }(window,
                    document, 'script', '//connect.facebook.net/en_US/fbevents.js');

                fbq('init', conversion);
            }

            fbq('track', "Lead");
        }
    };

    // Step three form submission
    window.stepThree = {};
    stepThree.process = function (form, response) {
        $('#step-three-well').removeClass('fadeInRightBig').addClass('fadeOutLeftBig');
        $('#valuator-offer').modal('hide');
        setTimeout(function () {
            $('.page-media').remove();
            $('#step-three-well').hide();
            $('#step-four-well').show().addClass('animated fadeInRightBig');
            $('.valuation-page').css('padding-top', '10%');
            $('.single-pf_valuator #page').css('height', '100%');
            $('.single-pf_valuator #page').css('min-height', 'auto');
        }, 200);
        setTimeout(function () {
            $('.thank-you').addClass('animated pulse');
        }, 1500);
    };

    function stepVerify(step) {
        $('.help-block').remove();
        $('.form-group').removeClass('has-error');
        var count = 0;

        if (step === 'step-two') {
            var inputs = ["first_name", "last_name", "email"];
        }

        if (inputs !== undefined) {
            jQuery.each(inputs, function (i, id) {
                if ($("#" + id).val() === '') {
                    stepError(id, 'You must enter a value.');
                    count++;
                }
            });
        }

        // Advanced Section Specific Validation
        var nameregex = /^[a-z ,.'-]+$/i;
        if (step === 'step-two' && count === 0) {
            if (!nameregex.test($('#first_name').val())) {
                stepError('first_name', 'Your first name can only contain letters.');
                count++;
            }
        }

        if (step === 'step-two' && count === 0) {
            if (!nameregex.test($('#last_name').val())) {
                stepError('last_name', 'Your last name can only contain letters.');
                count++;
            }
        }

        if (step === 'step-two' && count === 0) {
            var emailregex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;

            if (!emailregex.test($('#email').val())) {
                stepError('email', 'Email address is not valid.');
                count++;
            }
        }

        function stepError(id, msg) {
            $("#" + id).parent().addClass('has-error');
            $("#" + id).after('<p class="help-block">Whoops! ' + msg + '</p>');
        }

        return count;
    }
});
