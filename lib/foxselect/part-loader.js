(function($){

  function isVisible( selector ){
    var displayStatus = $( selector ).css('display');
    if( typeof displayStatus == 'undefined' || displayStatus == 'none' || displayStatus == 'hidden' ){
      return false;
    } else {
      return true;
    }
  }

  function confirmUnload(e){
    if( isVisible('.elementor-popup-modal') ){
      e.preventDefault();
      e.returnValue = 'You have unsaved work!';
      return e;
    } else {
      return null;
    }
  }

  $('div.elementor-popup-modal').on('click','div.dialog-close-button.dialog-lightbox-close-button',function(e){
    alert('Close button clicked.');
    console.log('Close button clicked.');
  });

  $(document).on('click','li.foxselect a, .elementor-element.foxselect a.elementor-button, a.foxselect',function(e){
    e.preventDefault();
    var button = $(this);
    var partNumber = button.attr('data-partnumber');
    if( typeof partNumber != 'undefined' && -1 < partNumber.indexOf('/') ){
      var partNumberArray = partNumber.split('/');

      $.get( fsloaderVars.resturl + partNumberArray[0] + '/' + partNumberArray[1] + '/' + partNumberArray[2] )
      .done( function(data){
        if( data.configuredPart ){
          var configuredPart = data.configuredPart;
          //console.log('[part-loader.js] this is the configurePart we are getting back: ', configuredPart);

          window.configuredPart = null;

          // Format our configuredPart object to only
          // contain:
          // - options we want to be able to set
          // - non-null values
          //*
          /*
              'frequency' == property ||
              'package_type' == property ||
              'frequency_unit' == property ||
              'number' == property ||
              'part_type' == property ||
              'package_option' == property
          */
          for( var property in configuredPart ){

            if(
              'part_type' == property
              ){
              //console.log('Deleting configuredPart[`'+property+'`] = ',configuredPart[property] );
              delete configuredPart[property];
            } else if( '' == configuredPart[property].value || -1 < configuredPart[property].value.indexOf('_') || '0.0' == configuredPart[property].value ){
              delete configuredPart[property];
            }
          }
          window.configuredPart = configuredPart;
          //console.log('[part-loader.js] window.configuredPart = ',window.configuredPart);
          /**/
        }
        window.FoxSelect.init( document.getElementById('root') );
        window.addEventListener('beforeunload', confirmUnload);
      });
    } else {
      window.setTimeout(function(){
        if( isVisible('#root') ){
          window.FoxSelect.init( document.getElementById('root') );
          window.addEventListener('beforeunload', confirmUnload);
        }
      },300);
    }
  });

})(jQuery);