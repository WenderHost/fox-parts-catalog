/* Datatables */
(function($){
  const table = $('#partsearchresults').DataTable({
    "language": {
      "search": "Filter parts:"
    },
    "lengthMenu": [10,20,50,75,100],
    "pageLength": 20
  });

  table.columns().flatten().each( function( colIdx ){
    var select = $('<select><option value="">...</option></select>')
      .appendTo(
        table.column(colIdx).header()
      )
      .on('change', function(){
        table
          .column( colIdx )
          .search( $(this).val() )
          .draw();
      });

    table
      .column(colIdx)
      .cache('search')
      .sort()
      .unique()
      .each(function(d){
        select.append( $('<option value="' + d + '">' + d + '</option>' ) );
      });
  });
})(jQuery);