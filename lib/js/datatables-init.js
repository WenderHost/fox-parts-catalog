/* Datatables */
(function($){
  /**
   * Formats Part Series child rows
   *
   * @param      {object}  d       Row data object
   * @return     {string}  HTML content for the child row
   */
  function listProductFamilies(d){
    var rows = [];
    var productFamilies = d.product_family;

    if( 'Crystal' == d.part_type ){
      //alert('This is a crystal.');
      var extendedProductFamilies = d.extended_product_families;
      //alert('extendedProductFamilies = ' + JSON.stringify(extendedProductFamilies) );
      for( let step = 0; step < extendedProductFamilies.length; step++ ){
        rows.push('<tr><td><a href="/foxpart/' + extendedProductFamilies[step].name + '" target="_blank">' + extendedProductFamilies[step].name + '</a></td><td>' + extendedProductFamilies[step].from_frequency + '-' + extendedProductFamilies[step].to_frequency + 'MHz, Tol: ' + extendedProductFamilies[step].tolerance + ', Stability: ' + extendedProductFamilies[step].stability + ', OpTemp: ' + extendedProductFamilies[step].optemp + '</td></tr>')
      }
    } else {
      // Sort Product Families by their names
      productFamilies.sort( (a,b) => {
        const nameA = a.name.toUpperCase();
        const nameB = b.name.toUpperCase();

        let comparison = 0;
        if( nameA > nameB ){
          comparison = 1;
        } else if( nameA < nameB ){
          comparison = -1;
        }

        return comparison;
      });
      for( let step = 0; step < productFamilies.length; step++ ){
        rows.push('<tr><td><a href="/foxpart/F' + productFamilies[step].name + '" target="_blank">' + productFamilies[step].name + '</a></td><td>' + productFamilies[step].description + '</td></tr>');
      }
    }

    return '<table><thead><tr><th style="width: 10%">Product Family</th><th style="width: 90%">Description</th></tr></thead><tbody>' + rows.join('') + '</tbody></table>'
  }

  console.log('wpvars.partSeriesSearchUrl = ',wpvars.partSeriesSearchUrl);
  const partSeriesTable = $('table#partseries').DataTable({
    'order': [[1,'asc']],
    'language': {
      'search': 'Filter these results:'
    },
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
      { 'data': function(d){
          let details = [];
          details.push('<strong>Package Size:</strong> ' + d.length_mm + 'x' + d.width_mm + 'mm');
          if( d.part_type && 'oscillator' == d.part_type.toLowerCase() )
            details.push('<strong>Output:</strong> ' + d.output);
          details.push('<strong>Weight:</strong> ' + d.individual_part_weight_grams + 'g');
          details.push('<strong>ECCN:</strong> ' + d.export_control_classification_number);
          details.push('<strong>Cage Code:</strong> ' + d.cage_code);
          return details.join('<br>');
        }
      },
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
      row.child( listProductFamilies( row.data() ), 'child-row' ).show();
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