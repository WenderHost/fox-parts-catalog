(function($){

  function isVisible( selector ){
    var displayStatus = $( selector ).css('display');
    if( typeof displayStatus == 'undefined' || displayStatus == 'none' || displayStatus == 'hidden' ){
      return false;
    } else {
      return true;
    }
  }

  $('.elementor-nav-menu').on('click','li.foxselect a',function(e){
    e.preventDefault();
    window.setTimeout(function(){
      var visible = isVisible('#rootfoxselect');
      console.log('visible for #rootfoxselect = ', visible);
      if( visible ){
        window.FoxSelect.init( document.getElementById('rootfoxselect') );
      }
    },300);
  });

})(jQuery);