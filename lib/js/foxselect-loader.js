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

  $('.elementor-nav-menu').on('click','li.foxselect a',function(e){
    e.preventDefault();
    window.setTimeout(function(){
      if( isVisible('#rootfoxselect') ){
        window.FoxSelect.init( document.getElementById('rootfoxselect') );
        window.addEventListener('beforeunload', confirmUnload);
      }
    },300);
  });

})(jQuery);