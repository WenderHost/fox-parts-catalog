/* Datatables */
(function($){
  function format(d){
    var rows = [];
    var productFamilies = d.product_family;
    for( let step = 0; step < productFamilies.length; step++ ){
      let productsData = productFamilies[step].product;
      let products = [];
      if( 0 < productsData.length ){
        for( let step2 = 0; step2 < productsData.length; step2++ ){
          products.push( productsData[step2].name );
        }
      }
      rows.push('<tr><td><a href="/foxpart/F' + productFamilies[step].name + '" target="_blank">' + productFamilies[step].name + '</a></td><td>' + productFamilies[step].description + '</td><td>' + products.join(', ') + '</td></tr>');
    }

    return '<table><thead><tr><th style="width: 15%">Product Family</th><th style="width: 25%">Description</th><th style="width: 60%">Parts</th></tr></thead><tbody>' + rows.join('') + '</tbody></table>'
  }

  console.log('wpvars.partSeriesSearchUrl = ',wpvars.partSeriesSearchUrl);
  const partSeriesTable = $('table#partseries').DataTable({
    'ajaxSource': wpvars.partSeriesSearchUrl,
    'columns': [
      {
        'className':      'details-control',
        'orderable':      false,
        'data':           null,
        'defaultContent': ''
      },
      {
        'data': function(d){
          let image = ( d.product_photo_part_image )? `<img src="${d.product_photo_part_image}" style="max-height: 40px; width: auto;" /><br>` : '' ;
          return image + d.name;
        },
        'className': 'details-control'
      },
      { 'data': 'part_type' },
      { 'data': 'package_name' },
      {
        'data': function(d){
          return ( d.data_sheet_url )? `<a href="${d.data_sheet_url}">Download ${d.name} Datasheet</a>` : '' ;
        }
      }
    ]
  });

  $('table#partseries').on('click', 'td.details-control', function(){
    var tr = $(this).closest('tr');
    var row = partSeriesTable.row(tr);
    console.log('clicked a .details-control');
    console.log('row.data() = ', row.data() );

    if( row.child.isShown() ){
      row.child.hide();
      tr.removeClass('shown');
    } else {
      row.child( format( row.data() ), 'child-row' ).show();
      tr.addClass('shown');
    }
  });
/*
'columnDefs': [
  {
    targets: 0,
    data: function(row,type,val,meta){
      console.log('row=', row, 'type=', type, 'val=', val, 'meta=', meta );
    }
  }
]
*/

  const partTable = $('#partsearchresults').DataTable({
    "language": {
      "search": "Filter parts:"
    },
    "lengthMenu": [10,20,50,75,100],
    "pageLength": 20
  });

  partTable.columns().flatten().each( function( colIdx ){
    var select = $('<select><option value="">...</option></select>')
      .appendTo(
        partTable.column(colIdx).header()
      )
      .on('change', function(){
        partTable
          .column( colIdx )
          .search( $(this).val() )
          .draw();
      });

    partTable
      .column(colIdx)
      .cache('search')
      .sort()
      .unique()
      .each(function(d){
        select.append( $('<option value="' + d + '">' + d + '</option>' ) );
      });
  });
})(jQuery);