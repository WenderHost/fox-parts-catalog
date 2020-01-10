(function($){
  $('div.elementor-popup-modal').on('click','div.dialog-close-button.dialog-lightbox-close-button',function(e){
    alert('Close button clicked.');
    console.log('Close button clicked.');
  });

  /**
   * Click handler for opening FoxSelect
   */
  $(document).on('click','li.foxselect a, .elementor-element.foxselect a.elementor-button, a.foxselect, div.foxselect a',function(e){
    e.preventDefault();
    var button = $(this);
    var partNumber = button.attr('data-partnumber');

    /**
     * Elementor Popup Modals don't work in IE. So we open FoxSelect in a
     * new tab if we're in IE.
     */
    if( isIE() ){
      var url = fsloaderVars.ieurl;
      if( typeof partNumber != 'undefined' )
        url+= '?partnum=' + encodeURIComponent(partNumber);
      // Open FoxSelect in a new tab
      window.open(url)
      // Give the modal time to appear, then close it.
      window.setTimeout(function(){
        $('.elementor-popup-modal').hide();
        window.location.reload();
      }, 600);
    } else {
      console.log('testing. window = ', window);
      if( typeof partNumber != 'undefined' && -1 < partNumber.indexOf('/') ){
        loadPreConfiguredFoxSelect( partNumber );
      } else {
        window.setTimeout(function(){
          if( isVisible('#rootfoxselect') ){
            if( typeof window.FoxSelect !== 'undefined' ){
              window.FoxSelect.init( document.getElementById('rootfoxselect') );
            } else {
              console.warn("\n---- 🚨🚨🚨🚨 ----\n" + 'window.FoxSelect is `undefined`!' + "\n---- 🚨🚨🚨🚨 ----");
            }
            if( ! isIE() )
              window.addEventListener('beforeunload', confirmUnload);
          }
        },300);
      }
    }
  });


})(jQuery);

function isIE(){
  return false; // Return `false` in `yarn start` env b/c `jQuery` isn't defined here for some reason:
  if( (jQuery.browser.mozilla || jQuery.browser.msie) && 12 > parseFloat(jQuery.browser.version) ){
    return true;
  } else {
    return false;
  }
}

function isVisible( selector ){
  var displayStatus = jQuery( selector ).css('display');
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

/**
 * Loads FoxSelect configured with a part.
 *
 * @param      {string}  partNumber  The part number
 */
function loadPreConfiguredFoxSelect( partNumber ){
  var partNumberArray = partNumber.split('/');

  jQuery.get( fsloaderVars.resturl + partNumberArray[0] + '/' + partNumberArray[1] + '/' + partNumberArray[2] )
  .done( function(data){
    if( data.configuredPart ){
      var configuredPart = data.configuredPart;
      window.configuredPart = null;

      /**
       * Format our configuredPart object to only contain:
       * - options we want to be able to set
       * - non-null values
       */
      for( var property in configuredPart ){
        if(
          'part_type' == property
          ){
          console.log('Deleting configuredPart[`'+property+'`] = ',configuredPart[property] );
          delete configuredPart[property];
        } else if( '' == configuredPart[property].value || -1 < configuredPart[property].value.indexOf('_') || '0.0' == configuredPart[property].value ){
          delete configuredPart[property];
        }
      }
      window.configuredPart = configuredPart;
    }
    window.FoxSelect.init( document.getElementById('rootfoxselect') );
    if( ! isIE() )
      window.addEventListener('beforeunload', confirmUnload);
  });
}