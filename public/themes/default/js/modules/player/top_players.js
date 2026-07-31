;(function( $, window, document ) {

    $(document).ready(function() {

        const table = $("#top-players");
        const BASE_URL = $('head base').attr('href');

        /**
         * Create our dataTable for top players
         */
        let dataTable = table.DataTable({
            /*
             * Set DOM structure for table controls
             * @url http://www.datatables.net/examples/basic_init/dom.html
             */
            sDom: '<"block-controls"<"controls-buttons"p>>rti<"block-footer clearfix"lf>',
            //bPaginate: false,
            bFilter: false,
            //bInfo: false,
            order: [[ 2, "desc" ]],
            columnDefs: [
                { "orderable": true, "targets": 0 },
                { "orderable": true, "targets": 1 },
                { "orderable": true, "targets": 2 },
            ]
        }).on( 'draw.dt', function () {

            table.applyTemplateSetup();

            //noinspection JSUnresolvedVariable
            $.fn.tooltip && $('[rel="tooltip"]').tooltip({ "delay": { show: 500, hide: 0 } });
        });

        // Add button
        $('#top-players_wrapper .block-footer').append(
            "<div class='float-right'><img src='public/images/icons/fugue/arrow-curve-000-left.png' width='16' height='16' class='picto'>"
            + "&nbsp;<a class='button' href='" + BASE_URL + "/rankings'>View More Rankings</a></div>"
        );

        /**
         * Sorting arrow behavior on dataTables "up arrows"
         */
        table.find('thead .sort-up').click(function(event) {
            // Stop link behaviour
            event.preventDefault();

            // Find column index
            let column = $(this).closest('th');
            let columnIndex = column.parent().children().index(column.get(0));

            // Send command
            dataTable.order([columnIndex, 'asc']).draw();

            // Prevent bubbling
            return false;
        });

        /**
         * Sorting arrow behavior on dataTables "down arrows"
         */
        table.find('thead .sort-down').click(function(event) {
            // Stop link behaviour
            event.preventDefault();

            // Find column index
            let column = $(this).closest('th');
            let columnIndex = column.parent().children().index(column.get(0));

            // Send command
            dataTable.order([columnIndex, 'desc']).draw();

            // Prevent bubbling
            return false;
        });
    });
}) (jQuery, window, document);