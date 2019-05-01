(function($){
  //console.log('productListVars =', productListVars);
  var template = Handlebars.compile( $('#product-list-template').html() );

  Object.keys(productListVars.lists).forEach(function(list){
    $.getJSON(productListVars.lists[list].dataurl,function(data){
      var content = {};
      content.modalurl = productListVars.modalurl;

      data.forEach(function(el,index){
        // Replace `https://foxonline.com` with the path to our WP Media Uploads folder
        data[index].image = ('' != el.image && null != el.image)? el.image.replace('https://foxonline.com',productListVars.imageurl) : 'https://via.placeholder.com/160x134.png?text=No+Image';

        var data_sheet = ('' != el.data_sheet && null != el.data_sheet)? el.data_sheet.replace( 'www.', '' ) : null ;
        data[index].data_sheet = ( null != data_sheet && -1 < data_sheet.indexOf( 'https://foxonline.com', productListVars.imageurl ) )? data_sheet.replace('https://foxonline.com',productListVars.imageurl) : data_sheet ;

        data[index].package_type = ( null != data[index].package_type && -1 < data[index].package_type.toLowerCase().indexOf('pin-thru') )? 'Pin-Thru' : 'SMD' ;
        data[index].frequency_unit = ( null != data[index].frequency_range && -1 <  data[index].frequency_range.toLowerCase().indexOf('khz') )? 'kHz' : 'MHz' ;
      });
      content.products = data;

      $('#product-list-' + productListVars.lists[list].id).html( template(content) );
    });
  });
})(jQuery);