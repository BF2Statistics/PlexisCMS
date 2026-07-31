;(function($, window, document, undefined) {
    "use strict";

    // our plugin constructor
    var Wizard = function( element, options ) {
        if( arguments.length ) {
            this._init( element, options );
        }
    };

    // the plugin prototype
    Wizard.prototype = {
        defaults: {
            // Element Selectors
            element: '.wizard-step',
            navLabelElement: '.wizard-label',

            // Container Classes
            navContainerClass: 'wizard-steps no-margin',
            buttonContainerClass: '',

            // Button Classes
            defaultButtonClass: '',
            responsiveNextButtonClass: '',
            responsivePrevButtonClass: 'grey',
            submitButtonClass: '',

            // Button Attributes
            nextButtonLabel: 'Next',
            prevButtonLabel: 'Back',
            submitButtonLabel: 'Submit',
            submitButtonName: 'wizard-submit',

            // Wizard Options
            forwardOnly: false,

            // Wizard Callbacks
            /**
             * Callback function that is triggered when a step is left.
             *
             * This function is optional and is set to null by default.
             *
             * If a callback returns false, the step will not continue forward.
             *
             * @type {Function<Wizard, int, int>|null}
             */
            onStepLeave: null, // function(wizard, currentStep, targetStep);

            /**
             * Callback function that is triggered when a step is shown.
             *
             * This function is optional and is set to null by default.
             *
             * @type {Function<Wizard, Object, int>|null}
             */
            onStepShown: null, // function(wizard, step, stepId);

            /**
             * A callback function that gets executed before a form submission process.
             * This function can be used to perform actions or validations prior to the form data being submitted.
             *
             * @type {Function<Wizard, form>|null}
             *
             */
            onBeforeSubmit: null, // function(wizard, form);

            // Ajax Submit [Requires jQuery Form Plugin]
            ajaxSubmit: false,
            ajaxOptions: {}
        },

        /**
         * Initializes the component with the provided element and options.
         * This method sets up the necessary configuration, global variables,
         * and DOM elements required for the functionality of the wizard.
         *
         * @param {HTMLElement} element - The DOM element to initialize the component on.
         * @param {Object} [options] - An optional object containing configuration settings.
         *
         * The initialization process includes:
         * - Assigning the element and options to the instance.
         * - Merging default options, passed options, and data attributes from the element.
         * - Setting up global variables such as active wizard ID, navigation lock status, and activated steps.
         * - Parsing any additional options.
         * - Locating and storing the steps within the element.
         * - Hiding the steps initially.
         * - Building and storing the navigation for the steps.
         * - Building and storing navigation buttons.
         * - Binding necessary event handlers.
         * - Automatically navigating to the first step.
         */
        _init: function( element, options ) {

            // Basic Initialization
            this.element = $( element );
            this.options = $.extend( {}, this.defaults, options, this.element.data() );

            //Global Variables
            this._activeWzdId = -1;
            this._navigationLocked = false;
            this._activatedSteps = [];

            // Parse Options
            this._parseOptions();

            // Retrieve the steps
            this.steps = this.element.find( this.options.element );

            // Hide the steps
            this.steps.hide();

            // Build and retrieve Navigation
            this.nav = this._buildNavigation( this.steps );

            // Build and retrieve Buttons
            this.buttons = this._buildButtons();

            // Bind Events
            this._bindEvents();

            // Goto first step
            this._navigateTo( this.steps.eq( 0 ).data( 'wzd-id' ), true );
        },

        /**
         * Parses the options provided for the form and initializes Ajax-related settings if enabled.
         *
         * This method:
         * - Configures Ajax submission settings if the `ajaxSubmit` option is enabled and the `ajaxSubmit` plugin is available.
         * - Extends the `ajaxOptions` by wrapping existing callback functions (e.g., `success`, `complete`, `error`, `beforeSend`, `beforeSubmit`, and `beforeSerialize`)
         *   to ensure proper execution while maintaining original custom behavior.
         * - Initializes the `ajaxForm` functionality for the form using the configured `ajaxOptions`.
         * - Adds a CSS class `wizard-form` to the form element.
         */
        _parseOptions: function() {
            // Prepare Ajax Form
            if( this.options.ajaxSubmit && $.fn.ajaxSubmit ) {
                var formOptionsSuccess = this.options.ajaxOptions.success;
                var formOptionsComplete = this.options.ajaxOptions.complete;
                var formOptionsError = this.options.ajaxOptions.error;
                var formOptionsBeforeSend = this.options.ajaxOptions.beforeSend;
                var formOptionsBeforeSubmit = this.options.ajaxOptions.beforeSubmit;
                var formOptionsBeforeSerialize = this.options.ajaxOptions.beforeSerialize;

                this.options.ajaxOptions = $.extend( {}, this.options.ajaxOptions, {
                    success: function( responseText, textStatus, xhr, form ) {
                        $.isFunction( formOptionsSuccess ) && formOptionsSuccess.call( this, responseText, textStatus, xhr, form );
                    },

                    complete: function( xhr, textStatus ) {
                        $.isFunction( formOptionsComplete ) && formOptionsComplete.call( this, xhr, textStatus );
                    },

                    error: function( xhr, textStatus ) {
                        $.isFunction( formOptionsError ) && formOptionsError.call( this, xhr, textStatus );
                    },

                    beforeSubmit: function( data, form, options ) {
                        if( $.isFunction( formOptionsBeforeSubmit ) ) {
                            return formOptionsBeforeSubmit.call( this, data, form, options );
                        }
                        return true;
                    },

                    beforeSend: function( xhr ) {
                        if( $.isFunction( formOptionsBeforeSend ) ) {
                            return formOptionsBeforeSend.call( this, xhr );
                        }
                        return true;
                    },

                    beforeSerialize: function( form, options ) {
                        if( $.isFunction( formOptionsBeforeSerialize ) ) {
                            return formOptionsBeforeSerialize.call( this, form, options );
                        }
                        return true;
                    }
                });

                this.element.ajaxForm( this.options.ajaxOptions );
            }

            this.element.addClass( 'wizard-form ' );
        },

        /**
         * Invokes a provided function with a specified context and arguments if the function is valid.
         *
         * @param {Function} fn - The function to be executed. If not a valid function, the method returns true.
         * @param {Array} args - The arguments to be passed to the function when invoking it.
         * @returns {boolean|*} Returns the result of the function execution if valid; otherwise, returns true.
         */
        _callFunction: function( fn, args ) {
            return !$.isFunction( fn )? true : fn.apply( this, args );
        },

        /**
         * Generates a unique random identifier string.
         * The identifier is composed of a timestamp and additional randomized values,
         * ensuring uniqueness during its creation moment.
         *
         * @returns {string} A unique identifier string prefixed with 'wzd_'.
         */
        _generateRandomId: function() {
            var guid = new Date().getTime().toString(32), i;

            for (i = 0; i < 3; i++) {
                guid += Math.floor(Math.random() * 65535).toString(32);
            }

            return 'wzd_' + guid;
        },

        /**
         * Builds the navigation UI for a wizard component based on the provided steps.
         *
         * @param {Array} steps - An array of step elements to generate navigation items for.
         * @returns {jQuery} The generated navigation container element, which is inserted before the wizard's root element.
         *
         * - Generates a unique identifier to differentiate navigation items.
         * - Iterates over the provided step elements to create corresponding navigation list items.
         * - Hides step titles in the step elements and extracts text for navigation labels.
         * - Assigns unique identifiers to both the step elements and navigation items for reference.
         */
        _buildNavigation: function( steps ) {
            var navContainer = $( '<ol class="' + this.options.navContainerClass + '"></ol>' );
            var guid = this._generateRandomId();

            $.each( steps, $.proxy(function( i, step ) {
                let stepId = i + 1;
                let title = $( step ).find( this.options.navLabelElement ).hide();
                let text = title && title.length ? title.html() : 'Step ' + stepId;
                let item = $( '<li class="disabled" data-wzd-id="' + guid + '_' + i + '"><span class="number">' + stepId + '</span> ' + text + '</li>' );

                $( step ).attr( 'data-wzd-id', guid + '_' + i );

                // Add item
                navContainer.append( item )
            }, this));

            return navContainer.insertBefore( this.element );
        },

        /**
         * Creates and appends the wizard control buttons to the designated container element.
         *
         * This method dynamically creates three buttons (previous, next, and submit) based on the
         * configuration provided in the options. Each button is assigned specific classes and labels
         * defined in the options. The buttons are appended to the wizard button container element
         * and positioned relative to the main element.
         *
         * @returns {Object} An object containing references to the button container and the individual
         * buttons (prev, next, and submit).
         * - `buttonContainer`: A jQuery object representing the container with the buttons.
         * - `prev`: A jQuery object representing the "Previous" button.
         * - `next`: A jQuery object representing the "Next" button.
         * - `submit`: A jQuery object representing the "Submit" button.
         */
        _buildButtons: function() {
            let btnContainer = $("#wizard-button-row");
            let btn = $( '<button type="button"></button>' ).addClass( this.options.defaultButtonClass );

            let prevButton = btn.clone().addClass( this.options.prevButtonClass ).text( this.options.prevButtonLabel );
            let nextButton = btn.clone().addClass( this.options.nextButtonClass ).text( this.options.nextButtonLabel );
            let submitButton = btn.clone().addClass( this.options.submitButtonClass ).text( this.options.submitButtonLabel ).attr( 'name', this.options.submitButtonName );

            let buttons = [ prevButton, nextButton, submitButton ];
            $(btnContainer).append( buttons ).insertAfter( this.element );

            return {
                buttonContainer: btnContainer,
                prev: prevButton,
                next: nextButton,
                submit: submitButton
            };
        },

        /**
         * Updates the state of the navigation buttons in the wizard interface.
         *
         * - The "previous" button will be disabled if the current step is the first step
         *   or if the wizard is set to forward-only mode.
         * - The "next" button will be disabled if the current step is the last step.
         * - The "submit" button will only be displayed if the current step is the last step.
         *
         * Relies on helper methods to determine whether the current step is the first or last step
         * and updates button attributes and visibility accordingly.
         */
        _refreshButtons: function() {
            this.buttons.prev.attr( 'disabled', this._isFirstStep( this._activeWzdId ) || this.options.forwardOnly );
            this.buttons.next.attr( 'disabled', this._isLastStep( this._activeWzdId ) );

            this.buttons.submit.toggle( this._isLastStep( this._activeWzdId ) );
        },

        /**
         * Binds event listeners to navigation elements and buttons within the wizard component.
         * Sets up handlers for navigation clicks, next/prev button controls, and form submission.
         * This method ensures proper interaction between user actions and wizard functionality.
         *
         * @private
         */
        _bindEvents: function() {
            let that = this;

            this.buttons.prev.on( 'click.wizard', function( e ) {
                that.prev();
                e.preventDefault();
            });

            this.buttons.next.on( 'click.wizard', function( e ) {
                that.next();
                e.preventDefault();
            });

            this.buttons.submit.on( 'click.wizard', function( e ) {
                that.submitForm();
                e.preventDefault();
            });
        },

        /**
         * Determines if navigation to a specific step is allowed.
         *
         * The function checks whether navigation is locked or restricted due to the `forwardOnly` option.
         * When `forwardOnly` is enabled, it ensures that navigation is only allowed to steps with an index
         * greater than the current step's index.
         *
         * @param {string} wzdId - The identifier of the step to navigate to.
         * @returns {boolean} Returns `true` if navigation to the specified step is allowed, `false` otherwise.
         */
        _canNavigate: function( wzdId ) {
            var step = this._findStep( wzdId );
            var currentStep = this._findStep( this._activeWzdId );

            return !this._navigationLocked && !(this.options.forwardOnly && step && currentStep && step.index() <= currentStep.index());
        },

        /**
         * Checks if a given wizard step ID is valid and has been activated.
         *
         * @param {string} wzdId - The wizard step ID to check.
         * @returns {boolean} True if the step ID is valid and is in the list of activated steps; otherwise, false.
         */
        _stepActivated: function( wzdId ) {
            return this._validWzdId( wzdId ) && $.inArray( wzdId, this._activatedSteps ) > -1;
        },

        /**
         * Activates a specific step in the wizard flow identified by its ID.
         *
         * The method checks if the provided wizard ID is valid and then retrieves the index of the step.
         * It iterates through all previous steps to ensure they have been activated. If the current step
         * is not already activated, it adds it to the list of activated steps.
         *
         * @param {string} wzdId - The identifier for the wizard step to be activated.
         */
        _activateStep: function( wzdId ) {
            if ( this._validWzdId( wzdId ) ) {
                var stepIndex = this._findNav( wzdId ).index();
                for( var i = 0; i < stepIndex; ++i) {
                    if( $.inArray( this.steps.eq( i ).data( 'wzd-id' ), this._activatedSteps ) === -1 ) {
                        return;
                    }
                }
                $.inArray( wzdId, this._activatedSteps ) === -1 && this._activatedSteps.push( wzdId );
            }
        },

        /**
         * Finds and returns the first step element that matches the specified wizard ID.
         *
         * @param {string} wzdId - The wizard ID to search for.
         * @returns {Object} - The first step element matching the specified wizard ID.
         */
        _findStep: function( wzdId ) {
            return this.steps.filter( '[data-wzd-id="' + wzdId + '"]' ).first();
        },

        /**
         * Finds and returns the first navigation element in the list corresponding
         * to the specified wizard ID (wzdId).
         *
         * @param {string} wzdId - The wizard ID to search for in the navigation list.
         * @returns {jQuery} The first matching navigation element as a jQuery object.
         */
        _findNav: function( wzdId ) {
            return this.nav.find('li').filter( '[data-wzd-id="' + wzdId + '"]' ).first();
        },

        /**
         * Navigates to a specific wizard step if valid and conditions are met.
         *
         * @param {string} wzdId - The ID of the wizard step to navigate to.
         * @param {boolean} ignore - If true, bypasses validation and navigates directly.
         * @private
         */
        _navigateTo: function(wzdId, ignore ) {
            if( this._validWzdId( wzdId ) ) {

                let targetIndex = +wzdId.split('_')[2];
                let currentIndex = parseInt((!this._validWzdId(this._activeWzdId)) ? 0 : this._findStep( this._activeWzdId ).data( 'wzd-id' ).split('_')[2], 10);

                if( ignore || ( this._canNavigate( wzdId ) && this._callFunction(this.options.onStepLeave, [this, currentIndex, targetIndex] ) ) ) {
                    this._activateStep( wzdId );
                    this._showStep( wzdId );
                    return true;
                }
            }

            return false;
        },

        /**
         * Navigates to the next step in the wizard flow if the current step is not the last one.
         * If navigation to the next step is successful, the current step is marked as completed.
         *
         * @param {boolean} ignore - Determines whether to ignore certain checks or validations during the navigation process.
         * @private
         */
        _navigateForward: function(ignore) {
            let currentStep = this._activeWzdId;
            if( !this._isLastStep( currentStep ) ) {
                if (this._navigateTo( this._findStep( this._activeWzdId ).next().data( 'wzd-id' ), ignore ) ) {
                    // Tick
                    this._markStepComplete(currentStep);
                }
            }
        },

        /**
         * Navigates one step backward in a wizard-like navigation system
         * unless the current step is the first step.
         *
         * @param {boolean} ignore - Determines whether certain validations or actions should be bypassed during navigation.
         * If the current step can be navigated backward, the method moves to the previous step, updates the active wizard ID,
         * and marks both the current and previous steps as incomplete by removing their completion indicators.
         */
        _navigateBackward: function(ignore) {
            let currentStep = this._activeWzdId;
            if( !this._isFirstStep( currentStep ) ) {
                let nextId = this._findStep( this._activeWzdId ).prev().data( 'wzd-id' )
                if (this._navigateTo( nextId, ignore ) ) {
                    // remove ticks
                    this._markStepIncomplete(currentStep);
                    this._markStepIncomplete(nextId);
                }
            }
        },

        /**
         * Displays a specific step in a wizard interface based on the provided wizard step ID.
         *
         * Handles the transition between steps, updating navigation, and executing callbacks.
         * If no step is currently active, the specified step is directly displayed.
         * If a step is already active, it first checks if the new step is valid and then transitions to it using a fade-out and fade-in animation.
         * Navigation is locked during the animation to prevent simultaneous changes.
         *
         * @param {number} wzdId - The ID of the wizard step to be displayed.
         */
        _showStep: function( wzdId ) {
            if( this._validWzdId( wzdId ) ) {
                if( this._activeWzdId === -1 ) {
                    this.steps.hide();
                    this._findStep( wzdId ).show();
                    this._updateNav( wzdId );
                    this._activeWzdId = wzdId;
                    this._refreshButtons();
                }
                else if( wzdId !== this._activeWzdId && this._stepActivated( wzdId ) ) {
                    let activeStep = this._findStep( this._activeWzdId );
                    let that = this;

                    this._navigationLocked = true;
                    activeStep.fadeOut( 'fast', function() {
                        that._updateNav( wzdId );

                        let newStep = that._findStep( wzdId );
                        let index = +wzdId.split('_')[2];
                        newStep.fadeIn( 'fast', function() {
                            that._activeWzdId = wzdId;
                            that._navigationLocked = false;
                            that._refreshButtons();

                            // Call onStepShown
                            that._callFunction( that.options.onStepShown, [ that, newStep, index ] );
                        });
                    });
                }
            }
        },

        /**
         * Marks a step in a wizard process as complete by adding a tick mark to the corresponding navigation element.
         *
         * @param {string} wzdId - The ID of the wizard step to mark as complete.
         * @private
         * @throws Will throw an error if the provided wizard ID is invalid.
         */
        _markStepComplete: function(wzdId) {
            if( this._validWzdId( wzdId ) ) {
                let nav = this._findNav( wzdId );
                if ($(nav).find('span.status-ok').length > 0) {
                    return; // already exists
                }

                // Add the tick mark
                let item = $(nav).find('span.number').first();
                item.append('<span class="status-ok"></span>');
            }
        },

        /**
         * Marks a step in the wizard as incomplete by removing the "status-ok" span element
         * if a valid wizard ID is provided and the associated navigation element exists.
         *
         * @param {string} wzdId - The ID of the wizard step to be marked incomplete.
         * @private
         */
        _markStepIncomplete: function(wzdId) {
            if( this._validWzdId( wzdId ) ) {
                let nav = this._findNav( wzdId );
                let elements = $(nav).find('span.status-ok');
                if (elements.length > 0) {
                    // remove tick mark
                    elements.remove();
                }
            }
        },

        /**
         * Updates the navigation state of a wizard by modifying CSS classes of navigation elements.
         *
         * When a valid wizard ID is provided, it finds the corresponding navigation element and
         * updates its latter siblings to have a 'disabled' class, darkening the text.
         * It then updates all elements to the targeted navigation element to remove the 'disabled' class
         * and add the 'current' class to indicate the active state.
         *
         * @param {string} wzdId - The identifier of the wizard whose navigation needs to be updated.
         * @private
         */
        _updateNav: function( wzdId ) {
            if( this._validWzdId( wzdId ) ) {
                let nav = this._findNav( wzdId );

                // Remove all disabled and current class to the previous items
                $(nav).prevAll('li').each(function() {
                    $(this).removeClass( 'disabled' ).removeClass('current');
                });

                // Add disabled class to all latter elements
                $(nav).nextAll('li').each(function() {
                    $(this).addClass( 'disabled' ).removeClass('current');
                });

                // Highlight the current item
                $(nav).removeClass( 'disabled' ).addClass('current');
            }
        },

        /**
         * Determines whether the specified wizard step is the last step in the sequence.
         *
         * @param {string} wzdId - The ID of the wizard step to verify.
         * @returns {boolean} True if the given `wzdId` corresponds to the last step, otherwise false.
         * The method also checks for the validity of the `wzdId` before performing the comparison.
         */
        _isLastStep: function( wzdId ) {
            return this._validWzdId( wzdId ) && wzdId === this.steps.last().data('wzd-id');
        },

        /**
         * Checks if the provided wizard ID corresponds to the first step in a wizard flow.
         *
         * @param {string} wzdId - The wizard ID to check.
         * @returns {boolean} Returns true if the provided wizard ID is valid and matches the ID of the first step, otherwise false.
         */
        _isFirstStep: function( wzdId ) {
            return this._validWzdId( wzdId ) && wzdId === this.steps.first().data('wzd-id');
        },

        /**
         * Validates if the provided wizard ID matches the required format.
         *
         * The wizard ID is considered valid if it is a string and starts with 'wzd_'.
         *
         * @param {string} wzdId - The wizard ID to validate.
         * @returns {boolean} True if the wizard ID is valid, otherwise false.
         */
        _validWzdId: function( wzdId ) {
            return typeof( wzdId ) === 'string' && wzdId.indexOf( 'wzd_' ) === 0;
        },

        /**
         * Advances the current wizard to the next step if the current step is not the last one.
         * It determines the next step's ID and navigates to that step.
         * This function depends on the internal methods `_isLastStep`, `_findStep`, and `_navigate`.
         */
        next: function() {
            this._navigateForward(false);
        },

        /**
         * Navigates to the previous step in the wizard flow if the current step is not the first step.
         * Uses the internal methods to determine and navigate to the previous step based on the wizard's active ID.
         * Calls `_isFirstStep` to check if the current step is the first step.
         * Retrieves the ID of the previous step using `_findStep` and navigates to it using `_navigate`.
         */
        prev: function() {
            this._navigateBackward(false);
        },

        /**
         * Handles the submission of a form element.
         * Executes a user-defined callback function before the form submission.
         * If the callback function returns a truthy value, the form proceeds with submission.
         *
         * @method submitForm
         * @returns {void}
         */
        submitForm: function() {
            if( this._callFunction( this.options.onBeforeSubmit, [ this, this.element ] ) ) {
                this.element.submit();
            }
        },

        /**
         * Resets the wizard to its initial state by performing the following actions:
         * - Clears the array tracking activated steps.
         * - Resets the active wizard step ID to default.
         * - Hides all wizard steps.
         * - Clears all form fields within the element, if the `clearForm` plugin is available.
         * - Navigates to the first step in the wizard.
         * - Enables the "Next" and "Previous" navigation buttons.
         */
        reset: function() {
            // Reset Variables
            this._activatedSteps = [];
            this._activeWzdId = -1;

            // Hide Steps
            this.steps.hide();

            // Remove all status ticks
            this.nav.find('span.status-ok').remove();

            // Clear form fields
            $.fn.clearForm && this.element.clearForm();

            // Go to first step
            this._navigateTo( this.steps.eq( 0 ).data( 'wzd-id' ), true );

            // Enable buttons
            this.nextButtonDisabled(false);
            this.prevButtonDisabled(false);
        },

        /**
         * Disables or enables the "Next" button based on the specified lock status.
         *
         * @function
         * @param {boolean} lock - If true, the "Next" button will be disabled; if false, the button will be enabled.
         */
        nextButtonDisabled: function( lock ) {
            this.buttons.next.attr( 'disabled', lock );
        },

        /**
         * Disables or enables the "previous" button based on the provided lock state.
         *
         * @function
         * @param {boolean} lock - Determines the disabled state of the "previous" button.
         *                         If true, the button will be disabled; if false, it will be enabled.
         */
        prevButtonDisabled: function( lock ) {
            this.buttons.prev.attr( 'disabled', lock );
        },

        /**
         * Navigates to a specific page within a series of steps.
         * This method hides all steps and navigates to the step
         * specified by the provided page index.
         *
         * @method navigateTo
         * @param {number} page - The index of the page to navigate to.
         */
        navigateTo: function( page ) {
            // Hide Steps
            this.steps.hide();

            // Go to first step
            this._navigateTo( this.steps.eq( page ).data( 'wzd-id' ), true );
        },

        /**
         * Skips a specified number of wizard steps while activating intermediate steps and navigates to the step after the skipped ones.
         *
         * @param {number} count - The number of steps to skip. Must be greater than 0.
         */
        skipNextPages: function (count) {
            let id = this._activeWzdId;

            // Mark current step as complete
            this._markStepComplete( id );

            // Skip, but still activate the next steps excluding the desired step
            for (let i = 0; i < count; i++) {
                if (this._isLastStep(id)) {
                    break;
                }

                id = this._findStep(id).next().data('wzd-id');
                this._activateStep( id );
                this._markStepComplete( id );
            }

            // Show the next step
            if (!this._isLastStep(id)) {
                id = this._findStep(id).next().data('wzd-id');
                this._navigateTo(id, true);
            }
            else {
                this._markStepIncomplete( id );
                this._showStep( id );
            }
        },

        /**
         * Sets the "forwardOnly" option to the specified value.
         *
         * @function
         * @param {boolean} is - A boolean value to enable or disable the "forwardOnly" option.
         */
        forwardOnly: function( is ) {
            this.options.forwardOnly = is;
        },

        /**
         * Toggles the visibility of the button row based on the input parameter.
         *
         * @param {boolean} hide - A boolean flag indicating whether to hide or show the button row.
         *                          If true, the button row is hidden; if false, it is shown.
         */
        hideButtonRow: function( hide ) {
            if (hide)
                $("#wizard-button-row").fadeOut( "fast" );
            else
                $("#wizard-button-row").fadeIn( "fast" );
        }
    };

    $.fn.wizard = function(options) {

        var isMethodCall = typeof options === "string",
            args = Array.prototype.slice.call( arguments, 1 ),
            returnValue = this;

        // prevent calls to internal methods
        if ( isMethodCall && options.charAt( 0 ) === "_" ) {
            return returnValue;
        }

        if ( isMethodCall ) {
            this.each(function() {
                var instance = $.data( this, 'wizard' ),
                    methodValue = instance && $.isFunction( instance[options] ) ?
                        instance[ options ].apply( instance, args ) :
                        instance;

                if ( methodValue !== instance && methodValue !== undefined ) {
                    returnValue = methodValue;
                    return false;
                }
            });
        } else {
            this.each(function() {
                var instance = $.data( this, 'wizard' );
                if ( !instance ) {
                    $.data( this, 'wizard', new Wizard( this, options ) );
                }
            });
        }

        return returnValue;
    };

})(jQuery, window , document);
