;(function( $, window, document, undefined ) {

    // Define variables
    var currentStep = 0;
    var $dataExists = false;
    var $checked = false;

    /**
     * Gets the current installer step, zero-based index
     * @returns {number}
     */
    function getCurrentStep() {
        return currentStep;
    }

    /**
     * Sets the current installer step, zero-based index
     * @returns {number}
     */
    function setCurrentStep(step) {
        currentStep = step;
    }

    // Helper function for updating the status UI
    function updateSettingsStatus(success, message) {
        if (success) {
            $('#install-success').show();
            $('#install-failed').hide();
        } else {
            $('#install-failed').show();
            $('#install-success').hide();
            $('#fail-message').html(message);
        }
    }

    function submitSettings(wizard) {
        // Is same database checked?
        if ($('#sameDatabase').is(':checked')) {
            $('#cfg__web_db_host').val($('#cfg__stats_db_host').val());
            $('#cfg__web_db_port').val($('#cfg__stats_db_port').val());
            $('#cfg__web_db_user').val($('#cfg__stats_db_user').val());
            $('#cfg__web_db_pass').val($('#cfg__stats_db_pass').val());
            $('#cfg__web_db_type').val($('#cfg__stats_db_type').val());
            $('#cfg__web_db_name').val($('#cfg__stats_db_name').val());
        }

        // Submit initial config
        let location = window.location;
        $.ajax({
            url: location.protocol + "//" + location.host + "/" + location.pathname,
            type: "POST",
            cache: false,
            data: {
                action: "settings",
                csrf_token: $("#csrf_token").val(),
                site_title: $('#cfg__site_title').val(),
                description: $('#cfg__description').val(),
                keywords: $('#cfg__keywords').val(),
                default_timezone: $('#cfg__default_timezone').val(),
                security_seed: $('#cfg__security_seed').val(),
                stats_db_type: $('#cfg__stats_db_type').val(),
                stats_db_host: $('#cfg__stats_db_host').val(),
                stats_db_port: $('#cfg__stats_db_port').val(),
                stats_db_user: $('#cfg__stats_db_user').val(),
                stats_db_pass: $('#cfg__stats_db_pass').val(),
                stats_db_name: $('#cfg__stats_db_name').val(),
                web_db_type: $('#cfg__web_db_type').val(),
                web_db_host: $('#cfg__web_db_host').val(),
                web_db_port: $('#cfg__web_db_port').val(),
                web_db_user: $('#cfg__web_db_user').val(),
                web_db_pass: $('#cfg__web_db_pass').val(),
                web_db_name: $('#cfg__web_db_name').val(),
            },
            dataType: "json"
        })
        .done(function( result ) {
            // Show my fancy loading form just a second longer...
            $.wait(1000).then( function () {
                // Parse the JSON response
                if (result.success === true) {
                    //noinspection JSUnresolvedVariable
                    if (result.dataExists) {
                        // Go to first step
                        wizard.skipNextPages(1);
                        $dataExists = true;
                    }
                    else {
                        wizard.next();
                    }

                    // Don't allow the user to backtrack now...
                    wizard.forwardOnly(true);
                }
                else {
                    wizard.prev();

                    // Display error
                    $('#ajax-message').html(result.message).show();
                }
            });
        })
        .fail(function( jqXHR ) {
            $.wait(500).then( function () {
                let result = null;
                try {
                    result = JSON.parse(jqXHR.responseText);
                } catch (e) {
                    result = null;
                }

                if (result) {
                    // Display error
                    $('#ajax-message').html("An Error Occurred: " + result.message).show();
                } else {
                    // Display error
                    $('#ajax-message').html("An Error Occurred. Please check the Site error log for details.").show();
                }
            });
        });
    }

    function submitDatabaseTables(wizard) {
        // Submit initial config
        let location = window.location;
        $.ajax({
            url: location.protocol + "//" + location.host + "/" + location.pathname +"/tables",
            type: "POST",
            cache: false,
            data: {
                action: "install",
                csrf_token: $("#csrf_token").val(),
            },
            dataType: "json"
        }).done(function( result ) {

            $.wait(1000).then( function () {
                wizard.next();
                updateSettingsStatus(result.success, result.message);
            });
        })
        .fail(function( jqXHR ) {
            $.wait(1000).then( function () {
                let result = null;
                try {
                    result = JSON.parse(jqXHR.responseText);
                } catch (e) {
                    result = null;
                }

                if (result) {
                    // Display error
                    $('#ajax-message').html("An Error Occurred: " + result.message).show();
                } else {
                    // Display error
                    $('#ajax-message').html("An Error Occurred. Please check the Site error log for details.").show();
                }
            });
        });
    }

    $(document).ready(function() {
        // Get the CSRF token from the hidden input field after DOM is fully loaded
        const csrfToken = $("#csrf_token").val();

        // Hide all tab contents except for step one
        $("fieldset#web").hide();
        $("div#newAccount-container").hide();

        // Register for the "Use Same Database" radio switch events
        $("#sameDatabase").change(function() {
            if ($(this).is(':checked')) {
                $("fieldset#web").fold("slow");
            }
            else {
                $("fieldset#web").expand("slow");
            }
        });

        // Register for the "new Battelfield 2 account" radio switch events
        $("#newAccount").change(function() {
            if ($(this).is(':checked')) {
                $("#newAccount-container").expand("slow");
                $checked = true;
                console.log('Slider state updated:', $checked);
            }
            else {
                $("#newAccount-container").fold("slow");
                $checked = false;
                console.log('Slider state updated:', $checked);
            }
        });

        // Hide and show password click events
        $('.toggle-password').click(function(event)
        {
            event.preventDefault(); // Prevent the default action of the link
            let input = $(this).parent().find('input');
            if (input.attr('type') === 'password') {
                input.attr('type', 'text');
            } else {
                input.attr('type', 'password');
            }
        });

        // Select2 doesn't mesh well with Jquery validation, so we must take
        // extra steps to add the check tick mark
        $("select#super_admin_iso").select2().on('change', function (e) {
            $( "#install-form" ).find("span[id=super_admin_iso-tick]")
                .removeClass()
                .addClass("check-ok").offsetHeight; // offsetHeight forces refresh of element
        });
		
		// Stop the Select 2 from submitting the form. The inline onclick is not allowed in the CSP
		$("a.select2-choice").click(function(event)
		{
			event.preventDefault(); // Prevent the default action of the link
			return false;
		});

        // Wait function
        $.wait = function(ms) {
            var defer = $.Deferred();
            setTimeout(function() { defer.resolve(); }, ms);
            return defer;
        };

        // Create our validate rules for form #1
        let wzd_form = $("#install-form").validate({
            errorElement: "span",
            validElement: "span",
            errorPlacement: function (error, element) {
                $(element).attr("title", error.text());
            },
            success: function (label, element) {
                $(element).attr("title", "");
            },
            highlight: function (element, errorClass, validClass) {
                // Show tick on the input box, and update border color
                $(element).addClass(errorClass).removeClass(validClass);
                $(element.form).find("span[id=" + element.id + "-tick]")
                    .removeClass().addClass("check-error").offsetHeight; // offsetHeight forces refresh of element
            },
            unhighlight: function (element, errorClass, validClass) {
                // Show tick on the input box, and update border color
                $(element).removeClass(errorClass).addClass(validClass);
                $(element.form).find("span[id=" + element.id + "-tick]")
                    .removeClass().addClass("check-ok").offsetHeight; // offsetHeight forces refresh of element
            },
            rules: {
                cfg__site_title: {
                    required: function () {
                        return getCurrentStep() === 0;
                    },
                },
                cfg__description: {
                    required: function () {
                        return getCurrentStep() === 0;
                    },
                },
                cfg__keywords: {
                    required: function () {
                        return getCurrentStep() === 0;
                    },
                },
                cfg__timezone: {
                    required: function () {
                        return getCurrentStep() === 0;
                    },
                },
                cfg__stats_db_type: {
                    required: true
                },
                cfg__stats_db_host: {
                    required: function () {
                        return getCurrentStep() === 1;
                    },
                    minlength: 3
                },
                cfg__stats_db_port: {
                    required: function () {
                        return getCurrentStep() === 1;
                    },
                    min: 1,
                    max: 65535
                },
                cfg__stats_db_user: {
                    required: function () {
                        return getCurrentStep() === 1;
                    },
                    minlength: 3
                },
                cfg__stats_db_pass: {
                    required: function () {
                        return getCurrentStep() === 1;
                    },
                    minlength: 3
                },
                cfg__web_db_host: {
                    required: function () {
                        return $("#sameDatabase").is(':checked') === false && getCurrentStep() === 1;
                    },
                    minlength: 3
                },
                cfg__web_db_type: {
                    required: true,
                },
                cfg__web_db_port: {
                    required: function () {
                        return $("#sameDatabase").is(':checked') === false && getCurrentStep() === 1;
                    },
                    min: 1,
                    max: 65535
                },
                cfg__web_db_user: {
                    required: function () {
                        return $("#sameDatabase").is(':checked') === false && getCurrentStep() === 1;
                    },
                    minlength: 3
                },
                cfg__web_db_pass: {
                    required: function () {
                        return $("#sameDatabase").is(':checked') === false && getCurrentStep() === 1;
                    },
                    minlength: 3
                },
                super_admin_username: {
                    required: true,
                    minlength: 3
                },
                super_admin_password: {
                    required: true,
                    minlength: 3,
                    maxlength: 30
                },
                super_admin_email: {
                    required: function () {
                        return $("#newAccount").is(':checked') === true && getCurrentStep() === 4;
                    },
                    email: true
                }
            },
            onsubmit: false
        });

        $('#install-form').wizard({
            canNavigate: false,
            onBeforeSubmit: function () {
                return wzd_form.form();
            },
            onStepLeave: function (wizard, currentStep, targetStep) {

                // Going forward? Validate form
                if (targetStep > currentStep) {
                    let result = wzd_form.form();
                    if (result === false) {
                        // Signal back
                        return false;
                    }
                }

                // Set step
                setCurrentStep(targetStep);

                return true;
            },
            onStepShown: function (wizard, stepObj, currentStep) {
                const stepsToHideButtons = [2, 3];

                // Hide buttons?
                if (stepsToHideButtons.indexOf(currentStep) !== -1) {
                    wizard.hideButtonRow(true);
                }
                else {
                    wizard.hideButtonRow(false);
                }

                // Is this the page we submit data?
                if (currentStep === 2) {
                    submitSettings(wizard);
                }

                if (currentStep === 3 && $dataExists === false) {
                    submitDatabaseTables(wizard);
                }
            },
            ajaxSubmit: true,
            ajaxOptions: {
                url: window.location.protocol + "//" + window.location.host + "/" + window.location.pathname +"/finalize",
                type: "POST",
                cache: false,
                data: {
                    action: "finalize",
                    csrf_token: $('#csrf_token').val(),
                },
                dataType: "json",
                beforeSubmit: function (formData) {
                    formData.length = 0; // Clear form data
                    formData.push(
                        { name: "action", value: "finalize" },
                        { name: "csrf_token", value: $("#csrf_token").val() },
                        { name: "newAccount", value: $("#newAccount").is(":checked") ? 1 : 0 },
                        { name: "super_admin_user", value: $("#super_admin_user").val() },
                        { name: "super_admin_pass", value: $("#super_admin_pass").val() },
                        { name: "super_admin_email", value: $("#super_admin_email").val() },
                        { name: "super_admin_iso", value: $("#super_admin_iso").val() }
                    );
                    let result = wzd_form.form();
                    return result !== false;
                },
                success: function (response, status, xhr, form) {
                    // Show my fancy loading form just a second longer...
                    $.wait(500).then( function () {
                        // Parse the JSON response
                        if (response.success === true) {
                            $('#install-modal').modal({
                                title: 'Installation Complete',
                                maxWidth: 800,
                                draggable: false,
                                resizable: false,
                                closeButton: false,
                                buttons: {
                                    'Finish': function(win) {
                                        let baseurl = - $('base').attr('href');
                                        window.location.href = baseurl + "/admin/settings";
                                    }
                                }
                            });
                        }
                        else {
                            // Display error
                            $('#ajax-message').html(response.message).show();
                        }
                    });
                },
                error: function(request, status, error) {
                    $('#ajax-message').html('<pre>' + request.responseText + '</pre>').show();
                }
            }
        });
    });
}) (jQuery, window, document);