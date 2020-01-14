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
    var partTable = '';

    if( 'Crystal' == d.part_type && 0 < d.extended_product_families.length ){
      var extendedProductFamilies = d.extended_product_families;
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
        var productFamilyString = JSON.stringify(productFamilies[step]);
        var products = productFamilies[step].product;
        if( 0 < products.length ){
          for( let step2 = 0; step2 < products.length; step2++ ){
            if( 0 < products[step2].name.indexOf(wpvars.frequency) ){
              if( 'Crystal' == d.part_type ){
                var productNameWithoutFrequency = products[step2].name.replace(wpvars.frequency, '');
                partTable = `<table style="margin-top: 20px;"><thead><tr><th style="width: 10%;">Part</th><th style="width: 90%">Description</th></tr></thead><tbody><tr><td><a href="/foxpart/${productNameWithoutFrequency}/${wpvars.frequency}" target="_blank">${products[step2].name}</a></td><td>${products[step2].description}</td></tr></tbody></table>`;
              } else {
                partTable = `<table style="margin-top: 20px;"><thead><tr><th style="width: 10%;">Part</th><th style="width: 90%">Description</th></tr></thead><tbody><tr><td><a href="/foxpart/F${productFamilies[step].name}/${wpvars.frequency}" target="_blank">${products[step2].name}</a></td><td>${products[step2].description}</td></tr></tbody></table>`;
              }

            }
          }
        }

        rows.push('<tr><td><a href="/foxpart/F' + productFamilies[step].name + '" target="_blank">' + productFamilies[step].name + '</a></td><td>' + productFamilies[step].description + partTable + '</td></tr>');
      }
    }

    return '<table><thead><tr><th style="width: 10%">Product Family</th><th style="width: 90%">Description</th></tr></thead><tbody>' + rows.join('') + '</tbody></table>';
  }

  console.log('wpvars.partSeriesSearchUrl = ',wpvars.partSeriesSearchUrl);

  const partSeriesTable = $('table#partseries')
  .DataTable({
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
          if( typeof d.length_mm !== 'undefined' ){
            details.push('<strong>Package Size:</strong> ' + d.length_mm + 'x' + d.width_mm + 'mm');
            if( d.part_type && 'oscillator' == d.part_type.toLowerCase() )
              details.push('<strong>Output:</strong> ' + d.output);
            if( typeof d.individual_part_weight_grams !== 'undefined' )
              details.push('<strong>Weight:</strong> ' + d.individual_part_weight_grams + 'g');
            if( typeof d.export_control_classification_number !== 'undefined' )
              details.push('<strong>ECCN:</strong> ' + d.export_control_classification_number);
            if( typeof d.cage_code !== 'undefined' )
              details.push('<strong>Cage Code:</strong> ' + d.cage_code);

            // Show child data if we are searching on a full part number:
            if( d.full_part_number )
              details.push( '<br>' + listProductFamilies(d) );

            return details.join('<br>');
          } else {
            return ( typeof d.details !== 'undefined' )? d.details : '';
          }
        }
      },
      {
        'data': function(d){
          return ( d.data_sheet_url )? `<a href="${d.data_sheet_url}">Download ${d.name} Datasheet</a>` : '' ;
        }
      }
    ]
  });

  /**
   * Handle click for opening/closing child rows
   */
  function onRowDetailsClick(table){
    var tr = $(this).closest('tr');
    var row = table.row(tr);

    if( row.child.isShown() ){
      row.child.hide();
      tr.removeClass('shown');
    } else {
      row.child( listProductFamilies( row.data() ), 'child-row' ).show();
      tr.addClass('shown');
    }
  }
  $('table#partseries').on('click', 'td.details-control', function(){
    onRowDetailsClick.call(this, partSeriesTable);
  });

  /*
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
  /**/
})(jQuery);