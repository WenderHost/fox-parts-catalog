(function($){
  //console.log('productListVars =', productListVars);
  var template = Handlebars.compile( $('#product-list-template').html() );

  Object.keys(productListVars.lists).forEach(function(list){
    $.getJSON(productListVars.lists[list].dataurl,function(data){
      var content = {};
      content.modalurl = productListVars.modalurl;

      var products = data[Object.keys(data)[0]];
      products.forEach(function(el,index){
        // Replace `https://foxonline.com` with the path to our WP Media Uploads folder
        products[index].image = ('' != el.image && null != el.image)? el.image.replace('https://foxonline.com',productListVars.imageurl) : 'https://via.placeholder.com/160x134.png?text=No+Image';

        var dataSheet = ('' != el.dataSheet && null != el.dataSheet)? el.dataSheet.replace( 'www.', '' ) : null ;
        products[index].dataSheet = ( null != dataSheet && -1 < dataSheet.indexOf( 'https://foxonline.com', productListVars.imageurl ) )? dataSheet.replace('https://foxonline.com',productListVars.imageurl) : dataSheet ;

        products[index].packageType = ( null != products[index].packageType && -1 < products[index].packageType.toLowerCase().indexOf('pin-thru') )? 'Pin-Thru' : 'SMD' ;
        products[index].frequencyUnit = ( null != products[index].frequencyRange && -1 <  products[index].frequencyRange.toLowerCase().indexOf('khz') )? 'kHz' : 'MHz' ;
        products[index].landingPage = ( typeof el.landingPage != 'undefined' && null != el.landingPage )? el.landingPage : false ;
      });
      content.products = products;

      $('#product-list-' + productListVars.lists[list].id).html( template(content) );
    });
  });
})(jQuery);